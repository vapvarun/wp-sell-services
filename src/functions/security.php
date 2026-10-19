<?php
/**
 * Secrets, app tokens, login lockout and client IP.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encrypt a secret for storage at rest.
 *
 * AES-256-CBC under a key derived from the site's secure-auth salt, a fresh
 * IV per value, base64, and an `enc:` prefix so wpss_decrypt_secret() can
 * tell an encrypted value from a row written before 1.7.1.
 *
 * Rotating the salt makes existing values unreadable; that is the trade-off
 * of keying on the salt instead of shipping a second secret to manage.
 *
 * @since 1.7.1
 *
 * @param string $plain Value to protect.
 * @return string Encrypted value, or the input unchanged when OpenSSL is unavailable.
 */
function wpss_encrypt_secret( string $plain ): string {
	if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
		return $plain;
	}

	$iv     = random_bytes( 16 );
	$cipher = openssl_encrypt( $plain, 'aes-256-cbc', wpss_secret_key(), OPENSSL_RAW_DATA, $iv );

	return false === $cipher ? $plain : 'enc:' . base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe transport, not obfuscation.
}

/**
 * Decrypt a value written by wpss_encrypt_secret().
 *
 * Anything without the `enc:` prefix is returned as-is, so legacy plaintext
 * rows keep reading until the upgrade routine rewrites them.
 *
 * @since 1.7.1
 *
 * @param string $stored Stored value.
 * @return string Plaintext, or '' when the value cannot be decrypted.
 */
function wpss_decrypt_secret( string $stored ): string {
	if ( 0 !== strpos( $stored, 'enc:' ) ) {
		return $stored;
	}

	$raw = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- see wpss_encrypt_secret().

	if ( false === $raw || strlen( $raw ) <= 16 || ! function_exists( 'openssl_decrypt' ) ) {
		return '';
	}

	$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', wpss_secret_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );

	return false === $plain ? '' : $plain;
}

/**
 * 32-byte key for wpss_encrypt_secret(), derived from the secure-auth salt.
 *
 * @since 1.7.1
 *
 * @return string Raw key bytes.
 */
function wpss_secret_key(): string {
	return hash( 'sha256', wp_salt( 'secure_auth' ), true );
}

/**
 * Encrypt every withdrawal `details` row still stored in plaintext.
 *
 * Runs once from the upgrade routine. Idempotent: rows already carrying the
 * `enc:` prefix are not selected.
 *
 * @since 1.7.1
 *
 * @return int Rows rewritten.
 */
function wpss_encrypt_legacy_withdrawal_details(): int {
	global $wpdb;

	$table = $wpdb->prefix . 'wpss_withdrawals';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results( "SELECT id, details FROM {$table} WHERE details IS NOT NULL AND details <> '' AND details NOT LIKE 'enc:%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.

	$done = 0;

	foreach ( (array) $rows as $row ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->update( $table, array( 'details' => wpss_encrypt_secret( (string) $row->details ) ), array( 'id' => (int) $row->id ) );

		$done += false === $ok ? 0 : 1;
	}

	return $done;
}

/**
 * Revoke every mobile session this member holds.
 *
 * Deletes the WPSS-prefixed Application Passwords the app mints at login, and
 * forgets the push devices registered against them. After this call every
 * `Authorization: Basic` token the app has ever been handed is dead.
 *
 * ONE FLOW, ONE IMPLEMENTATION. This is the body that used to sit inline in
 * `AuthController::logout()`. Account deletion needs exactly the same
 * operation, and two copies of "how do we revoke a session?" is the kind of
 * pair that drifts until one of them forgets a credential store.
 *
 * The `WPSS` name prefix is the contract with `create_app_password()`: it is
 * how an app-minted credential is told apart from one the member created by
 * hand in wp-admin for some other tool. Those are deliberately left alone.
 *
 * @since 1.5.2
 *
 * @param int $user_id User ID.
 * @return int Number of application passwords revoked.
 */
function wpss_revoke_app_sessions( int $user_id ): int {
	$revoked = 0;

	if ( class_exists( 'WP_Application_Passwords' ) ) {
		$passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );

		foreach ( (array) $passwords as $password ) {
			if ( ! isset( $password['name'], $password['uuid'] ) ) {
				continue;
			}

			if ( ! str_starts_with( (string) $password['name'], 'WPSS' ) ) {
				continue;
			}

			WP_Application_Passwords::delete_application_password( $user_id, $password['uuid'] );
			++$revoked;
		}
	}

	delete_user_meta( $user_id, '_wpss_push_devices' );

	/**
	 * Fires after a member's app sessions have been revoked.
	 *
	 * @since 1.5.2
	 *
	 * @param int $user_id User ID.
	 * @param int $revoked Number of application passwords deleted.
	 */
	do_action( 'wpss_app_sessions_revoked', $user_id, $revoked );

	return $revoked;
}

/**
 * How long an app token stays valid.
 *
 * Two limits, because either alone leaves the hole open (Basecamp
 * 10154918753):
 *
 * - IDLE alone would let a token that is actively being used live forever,
 *   which is exactly the stolen-token case.
 * - ABSOLUTE alone would log a daily user out on a fixed schedule for no
 *   security gain, and people respond to that by staying logged in elsewhere.
 *
 * So a token dies 30 days after it was last used, OR 90 days after it was
 * issued, whichever comes first. A daily user is never interrupted inside the
 * quarter; a token nobody is using is gone in a month.
 *
 * @since 1.6.0
 *
 * @return array{idle: int, absolute: int} Seconds.
 */
function wpss_app_token_lifetime(): array {
	$lifetime = array(
		'idle'     => 30 * DAY_IN_SECONDS,
		'absolute' => 90 * DAY_IN_SECONDS,
	);

	/**
	 * Filter how long a mobile app token stays valid.
	 *
	 * Returning 0 for either key disables that limit. Disabling BOTH restores
	 * the pre-1.6.0 behaviour of tokens that never expire - which is what this
	 * card was filed about, so do it only with a reason.
	 *
	 * @since 1.6.0
	 *
	 * @param array{idle: int, absolute: int} $lifetime Seconds.
	 */
	$lifetime = (array) apply_filters( 'wpss_app_token_lifetime', $lifetime );

	return array(
		'idle'     => max( 0, (int) ( $lifetime['idle'] ?? 0 ) ),
		'absolute' => max( 0, (int) ( $lifetime['absolute'] ?? 0 ) ),
	);
}

/**
 * When an app token expires, as a unix timestamp.
 *
 * @since 1.6.0
 *
 * @param array<string, mixed> $item Application password item from core.
 * @return int|null Timestamp, or null if it never expires.
 */
function wpss_app_token_expires_at( array $item ): ?int {
	$lifetime = wpss_app_token_lifetime();
	$created  = (int) ( $item['created'] ?? 0 );

	// last_used is null until the token is actually used; fall back to issue
	// time so a token minted and abandoned still ages out.
	$last_used = (int) ( $item['last_used'] ?? 0 );
	$last_used = $last_used > 0 ? $last_used : $created;

	$deadlines = array();

	if ( $lifetime['absolute'] > 0 && $created > 0 ) {
		$deadlines[] = $created + $lifetime['absolute'];
	}

	if ( $lifetime['idle'] > 0 && $last_used > 0 ) {
		$deadlines[] = $last_used + $lifetime['idle'];
	}

	return $deadlines ? min( $deadlines ) : null;
}

/**
 * Whether an app token has expired.
 *
 * @since 1.6.0
 *
 * @param array<string, mixed> $item Application password item from core.
 * @return bool
 */
function wpss_app_token_is_expired( array $item ): bool {
	$expires = wpss_app_token_expires_at( $item );

	return null !== $expires && $expires <= time();
}

/**
 * Whether a submitted password is one of this member's app tokens.
 *
 * Used to stop a token minting more tokens. WordPress authenticates
 * application passwords through the same wp_authenticate() chain as the real
 * account password, so POST /auth/login accepted a token where it meant to ask
 * for the password - and happily issued another one (Basecamp 10154918753).
 *
 * @since 1.6.0
 *
 * @param \WP_User $user     User being authenticated.
 * @param string   $password The submitted password.
 * @return bool
 */
function wpss_password_is_app_token( \WP_User $user, string $password ): bool {
	if ( ! class_exists( 'WP_Application_Passwords' ) || '' === $password ) {
		return false;
	}

	// Application passwords are shown to the member with spaces in, and stored
	// without them.
	$candidate = str_replace( ' ', '', $password );

	foreach ( (array) \WP_Application_Passwords::get_user_application_passwords( $user->ID ) as $item ) {
		if ( empty( $item['password'] ) ) {
			continue;
		}

		/*
		 * Verify the way CORE verifies, not with wp_check_password().
		 *
		 * WordPress 6.8 moved application passwords onto fast hashes with their
		 * own checker; wp_check_password() does not recognise them and returns
		 * false for a perfectly valid token. That is a silent failure in
		 * exactly the wrong direction - the guard reports "not a token" and
		 * waves the request through. It was caught only because the fix was
		 * re-run against the live HTTP endpoint rather than assumed.
		 */

		/*
		 * The method_exists() guard is NOT redundant, whatever the analyser
		 * says: it is stubbed against current WordPress, where the method
		 * exists, but this plugin supports 6.4 and up and the method arrived in
		 * 6.8. Removing the guard fatals on a supported version.
		 *
		 * @phpstan-ignore function.alreadyNarrowedType
		 */
		$matches = method_exists( '\WP_Application_Passwords', 'check_password' )
			? \WP_Application_Passwords::check_password( $candidate, (string) $item['password'] )
			: wp_check_password( $candidate, (string) $item['password'], $user->ID );

		if ( $matches ) {
			return true;
		}
	}

	return false;
}

/**
 * The transient key a sign-in attempt counts against.
 *
 * Resolved to the account, not to what was typed, so "sofia" and
 * "sofia@example.com" share one budget. Hashed, so the failed logins people
 * try - which are often somebody's real password typed in the wrong box - are
 * never written to the options table in the clear.
 *
 * An unknown login is hashed and counted exactly like a known one. Skipping
 * unknown logins would be cheaper, but then the sixth attempt answers
 * "locked" for a real account and "invalid" for one that does not exist,
 * which turns the lockout into an account-enumeration oracle. Counting is the
 * safer of the two; the cost is bounded by the 15-minute expiry.
 *
 * @since 1.7.1
 *
 * @param string $login Login or email address as submitted.
 * @return string
 */
function wpss_login_lock_key( string $login ): string {
	$login = trim( $login );
	$user  = get_user_by( 'login', $login ) ?: get_user_by( 'email', $login );

	return md5( strtolower( $user instanceof \WP_User ? $user->user_login : $login ) );
}

/**
 * Whether an account is currently locked out of signing in.
 *
 * Impure by nature: it reads a transient that a failed sign-in between two
 * calls will have changed, which is exactly how the REST route asks again
 * after wp_authenticate().
 *
 * @since 1.7.1
 *
 * @phpstan-impure
 *
 * @param string $login Login or email address as submitted.
 * @return bool
 */
function wpss_login_is_locked( string $login ): bool {
	if ( '' === trim( $login ) ) {
		return false;
	}

	return (bool) get_transient( 'wpss_login_lock_' . wpss_login_lock_key( $login ) );
}

/**
 * Count one wrong password against an account.
 *
 * @since 1.7.1
 *
 * @param string $login Login or email address as submitted.
 * @return bool Whether the account is locked as of this failure.
 */
function wpss_login_record_failure( string $login ): bool {
	if ( '' === trim( $login ) ) {
		return false;
	}

	$key = wpss_login_lock_key( $login );

	/*
	 * An already-locked account is not counted again.
	 *
	 * Refusing a locked sign-in fires wp_login_failed a second time, and
	 * counting that would push the expiry out on every attempt - an attacker
	 * could hold an administrator out of their own site indefinitely by
	 * knocking on the door. The 15 minutes always runs down.
	 */
	if ( get_transient( 'wpss_login_lock_' . $key ) ) {
		return true;
	}

	$fails = (int) get_transient( 'wpss_login_fails_' . $key ) + 1;

	if ( $fails >= 5 ) {
		set_transient( 'wpss_login_lock_' . $key, time(), 15 * MINUTE_IN_SECONDS );
		delete_transient( 'wpss_login_fails_' . $key );

		return true;
	}

	set_transient( 'wpss_login_fails_' . $key, $fails, 15 * MINUTE_IN_SECONDS );

	return false;
}

/**
 * Forget an account's failures and any lock on it.
 *
 * @since 1.7.1
 *
 * @param string $login Login or email address as submitted.
 * @return void
 */
function wpss_login_clear_failures( string $login ): void {
	if ( '' === trim( $login ) ) {
		return;
	}

	$key = wpss_login_lock_key( $login );

	delete_transient( 'wpss_login_lock_' . $key );
	delete_transient( 'wpss_login_fails_' . $key );
}

/**
 * The error a locked account answers with, on the website and over REST.
 *
 * 423 Locked rather than 429: the client did nothing too fast, the account is
 * refusing sign-ins for a while, and the client should say so rather than back
 * off and retry. wp-login.php ignores the status and prints the message.
 *
 * @since 1.7.1
 *
 * @return \WP_Error
 */
function wpss_login_lock_error(): \WP_Error {
	return new \WP_Error(
		'wpss_account_locked',
		__( 'Too many failed sign-ins. This account is locked for 15 minutes.', 'wp-sell-services' ),
		array( 'status' => 423 )
	);
}

/**
 * The visitor's IP address - THE one reader of the client address.
 *
 * REMOTE_ADDR, unless the connection comes from a hop allowed to speak for
 * the visitor: Cloudflare's published ranges for CF-Connecting-IP, or a proxy
 * declared through the wpss_trusted_proxies filter for X-Forwarded-For (read
 * right to left, stopping at the first hop that is not a declared proxy).
 * Taking the first header value from anyone let a script be a new visitor on
 * every request and walk past every guest rate limit (Basecamp 10336398096).
 *
 * @since 1.8.0
 *
 * @return string Validated IP, or '' when there is none (CLI, cron).
 */
function wpss_client_ip(): string {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- validated by filter_var below.
	$remote = trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );

	if ( ! filter_var( $remote, FILTER_VALIDATE_IP ) ) {
		return '';
	}

	$cf = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '' ) ) );

	/**
	 * Filters Cloudflare's address ranges. Update when Cloudflare publishes new ones.
	 *
	 * @since 1.8.0
	 *
	 * @param string[] $ranges CIDR ranges from https://www.cloudflare.com/ips/.
	 */
	$cloudflare = (array) apply_filters(
		'wpss_cloudflare_ip_ranges',
		array(
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		)
	);

	if ( filter_var( $cf, FILTER_VALIDATE_IP ) && wpss_ip_in_ranges( $remote, $cloudflare ) ) {
		return $cf;
	}

	/**
	 * Filters the proxies allowed to set X-Forwarded-For (IPs or CIDR ranges).
	 * Declare a load balancer or proxy here, or every guest behind it shares
	 * one rate-limit bucket. Cloudflare needs no entry.
	 *
	 * @since 1.8.0
	 *
	 * @param string[] $proxies Trusted proxy IPs / ranges.
	 */
	$trusted = (array) apply_filters( 'wpss_trusted_proxies', array() );
	$xff     = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' ) );

	if ( $trusted && '' !== $xff && wpss_ip_in_ranges( $remote, $trusted ) ) {
		foreach ( array_reverse( array_map( 'trim', explode( ',', $xff ) ) ) as $hop ) {
			if ( ! filter_var( $hop, FILTER_VALIDATE_IP ) ) {
				break;
			}
			if ( ! wpss_ip_in_ranges( $hop, $trusted ) ) {
				return $hop;
			}
		}
	}

	return $remote;
}

/**
 * Whether an IP falls inside any of the given IPs / CIDR ranges (v4 or v6).
 *
 * @since 1.8.0
 *
 * @param string   $ip     IP address.
 * @param string[] $ranges IPs or CIDR ranges.
 * @return bool
 */
function wpss_ip_in_ranges( string $ip, array $ranges ): bool {
	$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input returns false.

	if ( false === $packed ) {
		return false;
	}

	foreach ( $ranges as $range ) {
		list( $subnet, $bits ) = array_pad( explode( '/', trim( (string) $range ), 2 ), 2, null );
		$net                   = @inet_pton( (string) $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $net || strlen( $net ) !== strlen( $packed ) ) {
			continue;
		}

		$bits  = null === $bits ? strlen( $net ) * 8 : (int) $bits;
		$bytes = intdiv( $bits, 8 );
		$rest  = $bits % 8;

		if ( substr( $packed, 0, $bytes ) !== substr( $net, 0, $bytes ) ) {
			continue;
		}

		if ( 0 === $rest || ( ( ord( $packed[ $bytes ] ) ^ ord( $net[ $bytes ] ) ) & ( 0xFF << ( 8 - $rest ) ) & 0xFF ) === 0 ) {
			return true;
		}
	}

	return false;
}
