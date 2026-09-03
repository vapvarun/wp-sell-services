<?php
/**
 * Assorted helpers that do not belong to one domain.
 *
 * Split out of src/functions.php, which had grown to 6,187 lines and 148
 * global functions in a single file. This is a positional move only - no
 * function was renamed, resignatured or changed, so every call site is
 * untouched. src/functions.php now just requires these files.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the platform name.
 *
 * @since 1.1.0
 *
 * @return string Platform name or site name as fallback.
 */
function wpss_get_platform_name(): string {
	$platform_name = (string) wpss_get_option( 'general', 'platform_name' );

	// Fall back to site name if empty.
	if ( empty( $platform_name ) ) {
		$platform_name = get_bloginfo( 'name' );
	}

	/**
	 * Filter the platform name.
	 *
	 * @since 1.1.0
	 * @param string $platform_name Platform name.
	 */
	return apply_filters( 'wpss_platform_name', $platform_name );
}

/**
 * Get the plugin instance.
 *
 * @return \WPSellServices\Core\Plugin
 */
function wpss(): \WPSellServices\Core\Plugin {
	return \WPSellServices\Core\Plugin::get_instance();
}

/**
 * Sanitize HTML content.
 *
 * @param string $content HTML content.
 * @return string
 */
function wpss_sanitize_html( string $content ): string {
	return wp_kses(
		$content,
		array(
			'a'          => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'br'         => array(),
			'em'         => array(),
			'strong'     => array(),
			'p'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'h1'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'h6'         => array(),
			'blockquote' => array(),
			'code'       => array(),
			'pre'        => array(),
		)
	);
}

/**
 * Log message for debugging.
 *
 * @param mixed  $message Message to log.
 * @param string $level   Log level (info, warning, error).
 * @return void
 */
function wpss_log( $message, string $level = 'info' ): void {
	$plugin_debug = (bool) wpss_get_option( 'advanced', 'enable_debug_mode' );

	if ( ! $plugin_debug && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
		return;
	}

	if ( ! is_string( $message ) ) {
		$message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

	$log_message = sprintf(
		'[%s] [WPSS %s] %s',
		wp_date( 'Y-m-d H:i:s' ),
		strtoupper( $level ),
		$message
	);

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $log_message );
}

/**
 * Get max upload size in bytes.
 *
 * @return int
 */
function wpss_get_max_upload_size(): int {
	$upload_max = wp_max_upload_size();

	/**
	 * Filter the max upload size for requirements files.
	 *
	 * @param int $max_size Max size in bytes.
	 */
	return (int) apply_filters( 'wpss_max_upload_size', $upload_max );
}

/**
 * Add a notification for a user.
 *
 * Helper function to simplify adding notifications via NotificationService.
 *
 * @since 1.0.0
 *
 * @param int    $user_id User ID to notify.
 * @param string $type    Notification type.
 * @param string $message Notification message.
 * @param array  $data    Additional data.
 * @return int|false Notification ID or false on failure.
 */
function wpss_add_notification( int $user_id, string $type, string $message, array $data = array() ) {
	$notification_service = new \WPSellServices\Services\NotificationService();

	// Generate title from type.
	$type_titles = array(
		'order_created'       => __( 'New Order', 'wp-sell-services' ),
		'order_status'        => __( 'Order Update', 'wp-sell-services' ),
		'new_message'         => __( 'New Message', 'wp-sell-services' ),
		'delivery_submitted'  => __( 'Delivery Submitted', 'wp-sell-services' ),
		'delivery_accepted'   => __( 'Delivery Accepted', 'wp-sell-services' ),
		'revision_requested'  => __( 'Revision Requested', 'wp-sell-services' ),
		'review_received'     => __( 'New Review', 'wp-sell-services' ),
		'dispute_opened'      => __( 'Dispute Opened', 'wp-sell-services' ),
		'dispute_resolved'    => __( 'Dispute Resolved', 'wp-sell-services' ),
		'deadline_warning'    => __( 'Deadline Warning', 'wp-sell-services' ),
		'service_approved'    => __( 'Service Approved', 'wp-sell-services' ),
		'service_rejected'    => __( 'Service Requires Changes', 'wp-sell-services' ),
		'withdrawal_pending'  => __( 'Withdrawal Request', 'wp-sell-services' ),
		'withdrawal_approved' => __( 'Withdrawal Approved', 'wp-sell-services' ),
		'withdrawal_rejected' => __( 'Withdrawal Rejected', 'wp-sell-services' ),
	);

	$title = $type_titles[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );

	return $notification_service->create( $user_id, $type, $title, $message, $data );
}

/**
 * Get notifications for a user.
 *
 * @since 1.2.0
 *
 * @param int   $user_id User ID.
 * @param array $args    Query arguments (limit, offset, unread_only).
 * @return array Array of notification objects.
 */
function wpss_get_user_notifications( int $user_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_notifications';

	$defaults = array(
		'limit'       => 20,
		'offset'      => 0,
		'unread_only' => false,
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE user_id = %d";
	$params = array( $user_id );

	if ( $args['unread_only'] ) {
		$sql .= ' AND is_read = 0';
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Validate + store conversation message file attachments.
 *
 * Single source of truth for message, contact and dispute-reply attachments,
 * used by REST and admin-ajax alike so the limits and the storage are the
 * same on every transport.
 *
 * With an order id the file goes through wpss_store_order_file() - outside
 * the web root, behind the order read gate, no URL stored - because a
 * message on an order carries the same briefs and proofs a requirement does
 * (Basecamp 10264291163). Without one (a pre-sale contact) there is no
 * order to gate on, so it stays a media-library attachment marked private.
 *
 * @since 1.2.0
 * @since 1.7.1 Order-scoped files use the private order store; limits come from settings.
 *
 * @param array<string, mixed> $files    A single $_FILES['attachments'] entry (PHP's grouped
 *                                       multi-file shape: name[], type[], tmp_name[], etc.).
 * @param int                  $order_id Order the conversation belongs to, 0 for none.
 * @param string               $kind     message|contact|dispute - grouping only.
 * @return array{attachments: array<int, array<string,mixed>>, skipped: array<int,string>}
 */
function wpss_handle_message_attachments( array $files, int $order_id = 0, string $kind = 'message' ): array {
	$attachments = array();
	$skipped     = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return array(
			'attachments' => $attachments,
			'skipped'     => $skipped,
		);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$file_count = count( $files['name'] );
	for ( $i = 0; $i < $file_count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}

		$file = array(
			'name'     => $files['name'][ $i ],
			'type'     => $files['type'][ $i ],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);

		$file_name = sanitize_file_name( $file['name'] );
		$refused   = wpss_check_upload( $file );

		if ( $refused ) {
			$skipped[] = $file_name . ': ' . $refused->get_error_message();
			continue;
		}

		if ( $order_id > 0 ) {
			$record = wpss_store_order_file( $file, $order_id, $kind );

			if ( $record ) {
				$attachments[] = $record;
			} else {
				$skipped[] = $file_name . ': ' . __( 'upload failed', 'wp-sell-services' );
			}
			continue;
		}

		// ponytail: pre-sale contact files stay in the media library; the URL is
		// unlisted, not gated. Move them behind a conversation read gate if
		// pre-sale inquiries start carrying sensitive documents.
		$_FILES['upload_file'] = $file;
		$attachment_id         = media_handle_upload( 'upload_file', 0, array( 'post_status' => 'private' ) );

		if ( ! is_wp_error( $attachment_id ) ) {
			$attachments[] = array(
				'id'   => $attachment_id,
				'url'  => wp_get_attachment_url( $attachment_id ),
				'name' => $files['name'][ $i ],
				'type' => (string) get_post_mime_type( $attachment_id ), // Server-verified MIME, not client-provided.
			);
		} else {
			$skipped[] = $file_name . ': ' . $attachment_id->get_error_message();
		}
	}

	return array(
		'attachments' => $attachments,
		'skipped'     => $skipped,
	);
}

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
 * Normalize PHP's grouped $_FILES entry into a list of per-file specs.
 *
 * Turns the `name[]/type[]/tmp_name[]/error[]/size[]` shape PHP produces for a
 * multi-file field into a flat array of single-file specs (name/type/tmp_name/
 * error/size), skipping empty slots and sanitizing the client-supplied name +
 * mime. tmp_name/error/size are not user-controlled. Shared by the REST
 * deliverables endpoint and the legacy admin-ajax delivery handler so both
 * feed DeliveryService::submit() the same shape.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $files A single grouped $_FILES['field'] entry.
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function wpss_normalize_uploaded_files( array $files ): array {
	$out = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return $out;
	}

	$count = count( $files['name'] );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}
		$out[] = array(
			'name'     => sanitize_file_name( $files['name'][ $i ] ),
			'type'     => sanitize_mime_type( $files['type'][ $i ] ),
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => (int) $files['error'][ $i ],
			'size'     => (int) $files['size'][ $i ],
		);
	}

	return $out;
}

/**
 * Sanitize a date string to a strict Y-m-d value or null.
 *
 * Accepts only a calendar-valid Y-m-d date that round-trips exactly; anything
 * else (empty string, partial, or impossible date like 2026-13-40) returns
 * null so the DATE column stores SQL NULL.
 *
 * @since 1.2.0
 *
 * @param string $value Raw date string.
 * @return string|null Valid Y-m-d date, or null.
 */
function wpss_sanitize_date( string $value ): ?string {
	$value = trim( sanitize_text_field( $value ) );

	if ( '' === $value ) {
		return null;
	}

	$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

	if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $value ) {
		return null;
	}

	return $value;
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
 * Render a submit button that works outside wp-admin.
 *
 * `submit_button()` lives in wp-admin/includes/template.php, which is only
 * loaded on admin requests. Settings sections render through the
 * `wpss_settings_sections_*` actions, and anything may fire those - so a
 * section calling submit_button() directly fatals with "undefined function"
 * the moment it is drawn anywhere else. Free's menu-visibility section and
 * Pro's display-currency section both did.
 *
 * Admin screens keep using core's function; only the fallback markup is ours.
 *
 * @since 1.6.0
 *
 * @param string $text Button label.
 * @param string $type Button type passed through to submit_button().
 * @param string $name Button name attribute.
 * @return void
 */
function wpss_submit_button( string $text, string $type = 'primary', string $name = 'submit' ): void {
	if ( function_exists( 'submit_button' ) ) {
		submit_button( $text, $type, $name, false );
		return;
	}

	printf(
		'<button type="submit" name="%1$s" id="%1$s" class="button button-%2$s">%3$s</button>',
		esc_attr( $name ),
		esc_attr( $type ),
		esc_html( $text )
	);
}
