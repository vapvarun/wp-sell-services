<?php
/**
 * Secrets, app tokens and client IP.
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
 * A settings screen that never echoes a secret submits '' to mean "keep it", so
 * pass the stored value as $keep and the result is always safe to write back.
 * A value already carrying the `enc:` prefix is not wrapped twice.
 *
 * @param string $plain Value to protect.
 * @param string $keep  Stored value returned when $plain is empty.
 * @return string Encrypted value, or the input unchanged when OpenSSL is unavailable.
 */
function wpss_encrypt_secret( string $plain, string $keep = '' ): string {
	if ( '' === $plain ) {
		return $keep;
	}

	if ( 0 === strpos( $plain, 'enc:' ) || ! function_exists( 'openssl_encrypt' ) ) {
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
	 * @since 1.9.2 Receives the entries from Settings > System > Trusted proxies.
	 *
	 * @param string[] $proxies Trusted proxy IPs / ranges.
	 */
	$trusted = (array) apply_filters( 'wpss_trusted_proxies', wpss_get_trusted_proxies() );
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

/**
 * The proxies the owner listed under Settings > System > Trusted proxies.
 *
 * @since 1.9.2
 *
 * @return string[] IPs or CIDR ranges, one per entry. Empty by default.
 */
function wpss_get_trusted_proxies(): array {
	return array_values( array_filter( array_map( 'trim', explode( "\n", (string) get_option( 'wpss_trusted_proxies', '' ) ) ) ) );
}

/**
 * Whether a string is one IP address or a CIDR range (v4 or v6).
 *
 * @since 1.9.2
 *
 * @param string $entry Candidate, e.g. 10.0.0.5 or 10.0.0.0/8.
 * @return bool
 */
function wpss_is_ip_or_cidr( string $entry ): bool {
	list( $ip, $bits ) = array_pad( explode( '/', $entry, 2 ), 2, null );

	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	if ( null === $bits ) {
		return true;
	}

	return ctype_digit( $bits ) && (int) $bits <= ( str_contains( $ip, ':' ) ? 128 : 32 );
}

/**
 * Sanitize the Trusted proxies field: one IP or CIDR range per line.
 *
 * Any invalid entry refuses the whole save and keeps the stored list, so a typo
 * never silently drops a proxy the site depends on. Empty keeps the secure
 * default (only Cloudflare is trusted).
 *
 * @since 1.9.2
 *
 * @param mixed $value Submitted value (textarea text).
 * @return string Newline-separated entries.
 */
function wpss_sanitize_trusted_proxies( $value ): string {
	$entries = array_values( array_unique( array_filter( array_map( 'trim', (array) preg_split( '/[\s,]+/', (string) $value ) ) ) ) );
	$invalid = array_filter( $entries, static fn( $entry ) => ! wpss_is_ip_or_cidr( (string) $entry ) );

	if ( ! $invalid ) {
		return implode( "\n", $entries );
	}

	// Not loaded on REST; core calls this sanitizer twice when the option is new, so report once.
	static $reported = false;

	if ( ! $reported && function_exists( 'add_settings_error' ) ) {
		$reported = true;
		add_settings_error(
			'wpss_trusted_proxies',
			'wpss_invalid_trusted_proxies',
			sprintf(
				/* translators: %s: the entries that were refused, comma separated */
				__( 'Trusted proxies were not saved: %s is not an IP address or CIDR range (for example 10.0.0.5 or 10.0.0.0/8). Fix the entry and save again.', 'wp-sell-services' ),
				implode( ', ', array_map( 'sanitize_text_field', $invalid ) )
			)
		);
	}

	return (string) get_option( 'wpss_trusted_proxies', '' );
}

/*
 * Registered on init, not only in the admin, so core's /wp/v2/settings serves it
 * to administrators (the REST entry point) as well as the System settings form.
 */
add_action(
	'init',
	static function (): void {
		register_setting(
			'wpss_advanced',
			'wpss_trusted_proxies',
			array(
				'type'              => 'string',
				'description'       => __( 'Proxies allowed to report the visitor IP: one IP address or CIDR range per line. Cloudflare is handled automatically.', 'wp-sell-services' ),
				'sanitize_callback' => 'wpss_sanitize_trusted_proxies',
				'default'           => '',
				'show_in_rest'      => true,
			)
		);
	}
);

/**
 * Secrets a site can keep in wp-config.php instead of the database.
 *
 * Define the constant and it wins over the saved value at every reader, its
 * settings field locks, and a save never writes it to wp_options. Owner decision
 * for 1.9.2: match WooCommerce, keep the saved settings as they are, add constants.
 *
 * @since 1.9.2
 *
 * @return array<string, array<string, string>> Option name => field => constant name.
 */
function wpss_secret_constants(): array {
	/**
	 * Filters the secret fields that wp-config.php constants can supply.
	 *
	 * Add entries before plugins_loaded priority 10, from the plugin's main file,
	 * so they are in place before any gateway reads its settings.
	 *
	 * @since 1.9.2
	 *
	 * @param array<string, array<string, string>> $map Option name => field => constant name.
	 */
	return (array) apply_filters(
		'wpss_secret_constants',
		array(
			'wpss_stripe_settings'   => array(
				'test_secret_key' => 'WPSS_STRIPE_TEST_SECRET_KEY',
				'live_secret_key' => 'WPSS_STRIPE_LIVE_SECRET_KEY',
				'webhook_secret'  => 'WPSS_STRIPE_WEBHOOK_SECRET',
			),
			'wpss_paypal_settings'   => array(
				'sandbox_client_secret' => 'WPSS_PAYPAL_SANDBOX_CLIENT_SECRET',
				'live_client_secret'    => 'WPSS_PAYPAL_LIVE_CLIENT_SECRET',
			),
			'wpss_realtime_settings' => array(
				'secret' => 'WPSS_REALTIME_SECRET',
			),
		)
	);
}

/**
 * The wp-config.php constant supplying a secret field, or '' when none is set.
 *
 * @since 1.9.2
 *
 * @param string $option Option name.
 * @param string $field  Field key within the option.
 * @return string Constant name, only when it is defined as a non-empty string.
 */
function wpss_secret_constant( string $option, string $field ): string {
	$name = (string) ( wpss_secret_constants()[ $option ][ $field ] ?? '' );

	return '' !== $name && defined( $name ) && is_string( constant( $name ) ) && '' !== constant( $name ) ? $name : '';
}

/*
 * Read: the constant replaces the saved value for every reader of the option,
 * also when the option was never saved (default_option_), as on a site whose
 * secrets live only in wp-config.php.
 * Write: a field a constant covers is stored empty, so the secret leaves the database.
 */
$wpss_secret_reader = static function ( $value, $option ) {
	$secrets = array();
	foreach ( array_keys( wpss_secret_constants()[ $option ] ?? array() ) as $field ) {
		$constant = wpss_secret_constant( $option, (string) $field );
		if ( '' !== $constant ) {
			$secrets[ $field ] = (string) constant( $constant );
		}
	}
	// No constant: leave the value alone, so a missing option still reads as its default.
	return array() === $secrets ? $value : array_merge( is_array( $value ) ? $value : array(), $secrets );
};
foreach ( array_keys( wpss_secret_constants() ) as $wpss_secret_option ) {
	add_filter( 'option_' . $wpss_secret_option, $wpss_secret_reader, 10, 2 );
	add_filter( 'default_option_' . $wpss_secret_option, $wpss_secret_reader, 10, 2 );
	add_filter(
		'pre_update_option_' . $wpss_secret_option,
		static function ( $value, $old, $option ) {
			if ( ! is_array( $value ) ) {
				return $value;
			}
			foreach ( array_keys( wpss_secret_constants()[ $option ] ?? array() ) as $field ) {
				if ( '' !== wpss_secret_constant( $option, (string) $field ) ) {
					$value[ $field ] = '';
				}
			}
			return $value;
		},
		10,
		3
	);
}
unset( $wpss_secret_option, $wpss_secret_reader );
