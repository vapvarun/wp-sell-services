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
 * Where a notification leads: the thing it is about.
 *
 * An explicit action_url wins; otherwise the IDs the notification already
 * carries name the target - a thread with no order goes to Messages, anything
 * with an order goes to that order (the dashboard picks the buyer or seller
 * side), then a request, a withdrawal. Most rows carry only an order_id, so
 * the list, the REST payload and the email button all read the link from here
 * instead of each rows' empty action_url column (Basecamp 10337217098).
 *
 * @since 1.8.0
 *
 * @param array       $data       The notification's data payload.
 * @param string|null $action_url The stored action_url column, if any.
 * @return string URL, or '' when the notification points at nothing.
 */
function wpss_get_notification_url( array $data, ?string $action_url = null ): string {
	foreach ( array( $action_url, $data['action_url'] ?? '', $data['link'] ?? '' ) as $url ) {
		if ( ! empty( $url ) ) {
			return (string) $url;
		}
	}

	if ( ! empty( $data['conversation_id'] ) && empty( $data['order_id'] ) ) {
		return add_query_arg(
			array(
				'section'         => 'messages',
				'conversation_id' => (int) $data['conversation_id'],
			),
			wpss_get_dashboard_url()
		);
	}

	// A dispute notification opens the dispute, not its order (Basecamp
	// 10337159668). The dashboard view admits only the two parties, so the
	// site's admin goes to the admin dispute screen.
	if ( ! empty( $data['dispute_id'] ) ) {
		return current_user_can( 'manage_options' )
			? admin_url( 'admin.php?page=wpss-disputes&action=view&dispute_id=' . (int) $data['dispute_id'] )
			: add_query_arg( 'dispute', (int) $data['dispute_id'], wpss_get_dashboard_url( 'disputes' ) );
	}

	foreach ( array( 'order_id', 'parent_order_id' ) as $key ) {
		if ( ! empty( $data[ $key ] ) ) {
			$url = wpss_get_order_url( (int) $data[ $key ] );
			if ( '' !== $url ) {
				return $url;
			}
		}
	}

	if ( ! empty( $data['request_id'] ) ) {
		return (string) get_permalink( (int) $data['request_id'] );
	}

	if ( ! empty( $data['withdrawal_id'] ) ) {
		return wpss_get_dashboard_url( 'earnings' );
	}

	return '';
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
	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Count a user's notifications.
 *
 * Needed so the notifications screen can page: it used to take the 50 most
 * recent and stop, with nothing in the UI to reach anything older.
 *
 * @since 1.7.2
 *
 * @param int                  $user_id User ID.
 * @param array<string, mixed> $args    Query arguments (unread_only).
 * @return int Total matching notifications.
 */
function wpss_count_user_notifications( int $user_id, array $args = array() ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_notifications';

	$args = wp_parse_args( $args, array( 'unread_only' => false ) );

	$sql    = "SELECT COUNT(*) FROM {$table} WHERE user_id = %d";
	$params = array( $user_id );

	if ( $args['unread_only'] ) {
		$sql .= ' AND is_read = 0';
	}

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- $sql is hardcoded fragments with %d placeholders; values come via prepare().
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
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
 * @param string               $kind     message|contact|dispute|receipt - grouping only.
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

		// Unlisted means unguessable, or it means nothing - see
		// wpss_obfuscate_public_upload_name().
		add_filter( 'wp_handle_upload_prefilter', 'wpss_obfuscate_public_upload_name' );
		$attachment_id = media_handle_upload( 'upload_file', 0, array( 'post_status' => 'private' ) );
		remove_filter( 'wp_handle_upload_prefilter', 'wpss_obfuscate_public_upload_name' );

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

/**
 * Whether an admin hook suffix or screen id refers to one of our pages.
 *
 * WordPress derives a submenu hook suffix from the PARENT MENU TITLE, not from
 * the parent slug: sanitize_title( $menu_title ) . '_page_' . $page_slug. The
 * menu title is renameable - Pro's White Label sells exactly that - so any
 * comparison against a literal like 'sell-services_page_wpss-reports' silently
 * stops matching the moment an owner renames the menu, and whatever it guarded
 * simply never happens. That has now cost two separate defects: the vendor
 * detail screen sat on "Loading" forever (fixed in 1.7.2 by capturing the
 * suffix at registration), and the Reports queue lost the script carrying its
 * suspend/close-account confirmation (Basecamp 10320551487).
 *
 * Compare the page slug instead, which no site owner can change. Handles both
 * the 'toplevel_page_<slug>' and '<parent>_page_<slug>' forms.
 *
 * Where a class registers the page itself, capturing the return value of
 * add_submenu_page() is still the most direct answer; this helper is for the
 * places that only receive the hook.
 *
 * @since 1.7.2
 *
 * @param string $hook  Hook suffix or WP_Screen id.
 * @param string ...$slugs One or more page slugs, e.g. 'wpss-reports'.
 * @return bool True when $hook addresses any of the given slugs.
 */
function wpss_is_admin_page( string $hook, string ...$slugs ): bool {
	foreach ( $slugs as $slug ) {
		if ( '' === $slug ) {
			continue;
		}

		if ( $hook === 'toplevel_page_' . $slug || str_ends_with( $hook, '_page_' . $slug ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Enqueue one of the plugin's stylesheets, with its RTL sibling registered.
 *
 * Every sheet registered from a class remembered wp_style_add_data( …, 'rtl',
 * 'replace' ); every sheet enqueued inline from a template forgot it. So
 * orders.css, vendor-dashboard.css and messaging.css shipped an -rtl.css that
 * WordPress was never told about and therefore never served - the RTL build ran
 * on every release and its output went nowhere (Basecamp 10320551778).
 *
 * Call this instead of wp_enqueue_style() for plugin CSS so the RTL pairing is
 * not something each call site has to remember.
 *
 * @since 1.7.2
 *
 * @param string   $handle   Style handle, e.g. 'wpss-orders'.
 * @param string   $relative Path under the plugin root, e.g. 'assets/css/orders.css'.
 * @param string[] $deps     Dependencies. Defaults to the design system.
 * @return void
 */
function wpss_enqueue_style( string $handle, string $relative, array $deps = array( 'wpss-design-system' ) ): void {
	wp_enqueue_style( $handle, WPSS_PLUGIN_URL . $relative, $deps, WPSS_VERSION );
	wp_style_add_data( $handle, 'rtl', 'replace' );
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
