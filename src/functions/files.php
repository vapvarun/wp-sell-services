<?php
/**
 * Order file storage and access.
 *
 * ONE seam for every file a buyer or vendor attaches to an order. Before this,
 * five call sites each ran wp_handle_upload() and wrote the resulting PUBLIC
 * filesystem URL into the database, which produced two defects at once:
 *
 * - Cloud storage never received anything, because nothing routed to a provider
 *   (Basecamp 10239805812).
 * - "Private" hid the row, not the file: anyone holding the URL could read
 *   another buyer's brief forever (Basecamp 10239807824).
 *
 * A stored URL cannot fix both - a public cloud URL is not private, and a
 * signed URL expires and rots in the row. So nothing stores a URL any more.
 * Files are written outside the web root, optionally pushed to the configured
 * cloud provider, and addressed by a stable id that
 * wpss_get_order_file_url() turns into a permission-checked endpoint link at
 * render time.
 *
 * @package WPSellServices
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the configured cloud storage provider.
 *
 * Deliberately does NOT substitute a different provider when the configured one
 * is missing, and deliberately returns null rather than silently falling back to
 * local. Its wallet twin does exactly that and swaps in a different LEDGER
 * (Basecamp 10239808430); the same reasoning applies to a bucket - quietly
 * writing somewhere other than where the owner configured is not a graceful
 * degradation, it is a bug that hides itself.
 *
 * @since 1.7.0
 *
 * @return object|null Provider implementing StorageProviderInterface, or null.
 */
function wpss_get_active_storage_provider(): ?object {
	$active_id = (string) get_option( 'wpss_active_storage_provider', '' );

	if ( '' === $active_id || 'local' === $active_id ) {
		return null;
	}

	$providers = (array) apply_filters( 'wpss_storage_providers', array() );

	if ( ! isset( $providers[ $active_id ] ) ) {
		wpss_log(
			sprintf( 'Storage provider "%s" is configured but not registered; falling back to local disk.', $active_id ),
			'error'
		);
		return null;
	}

	$provider = $providers[ $active_id ];

	if ( ! is_object( $provider ) || ! method_exists( $provider, 'is_configured' ) || ! $provider->is_configured() ) {
		wpss_log(
			sprintf( 'Storage provider "%s" is registered but not configured; falling back to local disk.', $active_id ),
			'error'
		);
		return null;
	}

	return $provider;
}

/**
 * Directory that order files are written to.
 *
 * Prefers a location OUTSIDE the document root, because the guards that make an
 * uploads subdirectory private are not reliable: `.htaccess` is read by Apache
 * and ignored entirely by nginx. On the first host this was tested against
 * (nginx), a file written under uploads with a correct deny file was served
 * happily at HTTP 200 with its contents - which is the very bug this is meant
 * to fix (Basecamp 10239807824).
 *
 * Falls back to uploads when the parent of ABSPATH is not writable, which is
 * common on shared hosting. In that case the guards go down AND
 * wpss_order_files_are_public() is consulted so the owner is told rather than
 * left believing the files are private.
 *
 * @since 1.7.0
 *
 * @return string Absolute path with a trailing slash.
 */
function wpss_get_order_files_dir(): string {
	$outside = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'wpss-order-files/';

	// Only worth using if it is genuinely not under the WordPress root. On a
	// host where ABSPATH's parent IS the document root this is false and we go
	// to the fallback, which at least knows it needs checking.
	$is_outside_root = 0 !== strpos( $outside, trailingslashit( ABSPATH ) );

	if ( $is_outside_root && ( is_dir( $outside ) || wp_mkdir_p( $outside ) ) && wp_is_writable( $outside ) ) {
		// No guard file here on purpose: nothing serves this directory, so a
		// deny rule would be decoration. The guards below exist because the
		// fallback IS served.
		return $outside;
	}

	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'wpss-order-files/';

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	wpss_guard_files_dir( $dir );

	return $dir;
}

/**
 * Belt-and-braces guards on a file directory.
 *
 * Neither is sufficient on its own and one of them does nothing on nginx, which
 * is why the directory choice above matters more than either.
 *
 * @since 1.7.0
 *
 * @param string $dir Absolute path with a trailing slash.
 * @return void
 */
function wpss_guard_files_dir( string $dir ): void {
	if ( ! file_exists( $dir . '.htaccess' ) ) {
		file_put_contents( $dir . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	if ( ! file_exists( $dir . 'index.php' ) ) {
		file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
}

/**
 * Are order files reachable straight from the browser?
 *
 * Writes a canary and asks the web server for it. This is the only way to know:
 * whether a deny rule is honoured depends on server software and configuration
 * that PHP cannot read. Cached, because it is an HTTP round trip and the answer
 * only changes when the server does.
 *
 * @since 1.7.0
 *
 * @param bool $force Re-probe instead of using the cached answer.
 * @return bool|null True if publicly readable, false if not, null if unknown.
 */
function wpss_order_files_are_public( bool $force = false ): ?bool {
	$cached = get_transient( 'wpss_order_files_public' );

	if ( ! $force && false !== $cached ) {
		return 'unknown' === $cached ? null : ( 'yes' === $cached );
	}

	$dir = wpss_get_order_files_dir();

	// Outside the document root there is no URL to probe.
	$uploads = wp_upload_dir();
	$base    = trailingslashit( $uploads['basedir'] ) . 'wpss-order-files/';

	if ( trailingslashit( $dir ) !== trailingslashit( $base ) ) {
		set_transient( 'wpss_order_files_public', 'no', WEEK_IN_SECONDS );
		return false;
	}

	$name = 'canary-' . wp_generate_password( 12, false, false ) . '.txt';

	if ( false === file_put_contents( $dir . $name, 'canary' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		set_transient( 'wpss_order_files_public', 'unknown', HOUR_IN_SECONDS );
		return null;
	}

	$response = wp_remote_get(
		trailingslashit( $uploads['baseurl'] ) . 'wpss-order-files/' . $name,
		array(
			'timeout'   => 5,
			'sslverify' => false,
		)
	);

	wp_delete_file( $dir . $name );

	if ( is_wp_error( $response ) ) {
		set_transient( 'wpss_order_files_public', 'unknown', HOUR_IN_SECONDS );
		return null;
	}

	$public = 200 === wp_remote_retrieve_response_code( $response )
		&& false !== strpos( (string) wp_remote_retrieve_body( $response ), 'canary' );

	set_transient( 'wpss_order_files_public', $public ? 'yes' : 'no', WEEK_IN_SECONDS );

	return $public;
}

/**
 * Store one uploaded file against an order.
 *
 * Replaces the bare wp_handle_upload() that each caller used to run. Writes
 * outside the web root, pushes to the configured cloud provider when there is
 * one, and returns a record addressed by id rather than by URL.
 *
 * The returned array keeps the `id`, `name`, `type` and `size` keys the old
 * shape had so stored rows stay readable, and adds `path` and `remote_path`.
 * It deliberately omits `url`: see wpss_get_order_file_url().
 *
 * @since 1.7.0
 *
 * @param array  $file     One entry from $_FILES.
 * @param int    $order_id Order the file belongs to.
 * @param string $kind     'delivery' or 'requirement' - used only for grouping.
 * @return array<string,mixed>|null Record, or null when the upload is rejected.
 */
function wpss_store_order_file( array $file, int $order_id, string $kind = 'delivery' ): ?array {
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return null;
	}

	$checked = wp_check_filetype( (string) ( $file['name'] ?? '' ) );

	if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
		return null;
	}

	$order_id = max( 0, $order_id );
	$kind     = in_array( $kind, array( 'delivery', 'requirement' ), true ) ? $kind : 'delivery';

	// wp_unique_filename() against the target directory, so two buyers uploading
	// brief.pdf to the same order cannot overwrite each other.
	$dir = wpss_get_order_files_dir() . $order_id . '/';

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$filename = wp_unique_filename( $dir, sanitize_file_name( (string) $file['name'] ) );
	$target   = $dir . $filename;

	if ( ! @move_uploaded_file( $file['tmp_name'], $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file
		wpss_log( sprintf( 'Could not move upload for order %d into %s', $order_id, $dir ), 'error' );
		return null;
	}

	// 0640: readable by the web user, not by other accounts on shared hosting.
	@chmod( $target, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod

	$record = array(
		'id'          => wp_generate_uuid4(),
		'name'        => (string) $file['name'],
		'type'        => (string) $checked['type'],
		'size'        => (int) ( $file['size'] ?? filesize( $target ) ),
		'path'        => $filename,
		'order_id'    => $order_id,
		'kind'        => $kind,
		'remote_path' => null,
	);

	$provider = wpss_get_active_storage_provider();

	if ( $provider ) {
		$remote = sprintf( 'wpss/%d/%s/%s', $order_id, $kind, $filename );
		$result = $provider->upload( $target, $remote );

		if ( ! empty( $result['success'] ) ) {
			$record['remote_path'] = $remote;

			// The local copy has served its purpose. Keeping it would mean the
			// owner pays for the bucket and the disk both, which is the whole
			// reason they configured cloud storage.
			wp_delete_file( $target );
		} else {
			// Local copy stays and the endpoint serves from it, so a bucket
			// outage costs the owner disk space, never a delivery.
			wpss_log(
				sprintf(
					'Cloud upload failed for order %d (%s); keeping the local copy. %s',
					$order_id,
					$filename,
					isset( $result['error'] ) ? (string) $result['error'] : ''
				),
				'error'
			);
		}
	}

	return $record;
}

/**
 * Is this user entitled to read an order's files?
 *
 * Party to the order, or an administrator. Deliberately narrow: a file attached
 * to an order is a private brief or a paid deliverable, and nobody else has a
 * reason to open it.
 *
 * @since 1.7.0
 *
 * @param int $order_id Order ID.
 * @param int $user_id  User ID. Defaults to the current user.
 * @return bool
 */
function wpss_can_read_order_files( int $order_id, int $user_id = 0 ): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return false;
	}

	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return false;
	}

	return (int) $order->customer_id === $user_id || (int) $order->vendor_id === $user_id;
}

/**
 * Permission-checked link to an order file.
 *
 * Generated at render time, never stored. That is the point: a stored link is
 * either public forever or a signature that expires in the row.
 *
 * @since 1.7.0
 *
 * @param array<string,mixed> $record Record from wpss_store_order_file().
 * @return string URL, or '' when the record is not addressable.
 */
function wpss_get_order_file_url( array $record ): string {
	$id       = (string) ( $record['id'] ?? '' );
	$order_id = (int) ( $record['order_id'] ?? 0 );

	// The discriminator is `path`/`remote_path`, NOT the presence of an id:
	// pre-1.7.0 rows carry an ATTACHMENT id in the same key, and routing those
	// to the endpoint would 404 every file delivered before this release.
	$is_stored = ! empty( $record['path'] ) || ! empty( $record['remote_path'] );

	if ( ! $is_stored || '' === $id || $order_id <= 0 ) {
		// Pre-1.7.0 rows stored a bare URL. Serve what they have - those files
		// are already public, and breaking a delivered file to tighten history
		// would punish the buyer for our bug.
		return (string) ( $record['url'] ?? '' );
	}

	return add_query_arg(
		array(
			'action' => 'wpss_order_file',
			'order'  => $order_id,
			'file'   => rawurlencode( $id ),
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * Find one stored file record on an order.
 *
 * Looks in the order's deliveries and its requirements, because the same id
 * space serves both and the caller should not have to know which.
 *
 * @since 1.7.0
 *
 * @param int    $order_id Order ID.
 * @param string $file_id  Record id.
 * @return array<string,mixed>|null
 */
function wpss_find_order_file( int $order_id, string $file_id ): ?array {
	global $wpdb;

	// Both tables, because the endpoint is handed an id and nothing else - the
	// link in an email does not say which kind of file it points at.
	$sets = array(
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_col( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_deliveries WHERE order_id = %d", $order_id ) ),
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_col( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_order_requirements WHERE order_id = %d", $order_id ) ),
	);

	foreach ( $sets as $rows ) {
		foreach ( (array) $rows as $raw ) {
			$decoded = json_decode( (string) $raw, true );

			if ( ! is_array( $decoded ) ) {
				continue;
			}

			foreach ( $decoded as $record ) {
				if ( is_array( $record ) && isset( $record['id'] ) && (string) $record['id'] === $file_id ) {
					$record['order_id'] = $order_id;
					return $record;
				}
			}
		}
	}

	return null;
}

/**
 * Serve an order file to someone entitled to read it.
 *
 * Hooked on admin_post_ rather than REST because this is followed by a plain
 * <a download>: REST cookie auth needs a nonce on the URL, and a nonce in a
 * link the buyer might bookmark expires in a day. admin-post.php authenticates
 * from the session cookie alone, so the link keeps working for exactly as long
 * as the person is entitled to it - which is the rule we actually want.
 *
 * @since 1.7.0
 *
 * @return void
 */
function wpss_serve_order_file(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only; authorisation is the capability check below, not a nonce.
	$order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0;
	$file_id  = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $order_id <= 0 || '' === $file_id || ! wpss_can_read_order_files( $order_id ) ) {
		// One answer for "no such file" and "not yours", so this cannot be used
		// to discover which order ids exist.
		wp_die( esc_html__( 'File not found.', 'wp-sell-services' ), '', array( 'response' => 404 ) );
	}

	$record = wpss_find_order_file( $order_id, $file_id );

	if ( ! $record ) {
		wp_die( esc_html__( 'File not found.', 'wp-sell-services' ), '', array( 'response' => 404 ) );
	}

	// In a bucket: hand over a short-lived signed URL rather than streaming the
	// bytes through PHP.
	if ( ! empty( $record['remote_path'] ) ) {
		$provider = wpss_get_active_storage_provider();

		if ( $provider && method_exists( $provider, 'get_signed_url' ) ) {
			$signed = $provider->get_signed_url( (string) $record['remote_path'], 5 * MINUTE_IN_SECONDS );

			if ( $signed ) {
				wp_redirect( $signed ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- deliberately offsite: the configured bucket.
				exit;
			}
		}

		wp_die( esc_html__( 'This file is stored remotely and the storage provider is unavailable. Try again shortly.', 'wp-sell-services' ), '', array( 'response' => 503 ) );
	}

	$path = wpss_get_order_files_dir() . $order_id . '/' . basename( (string) ( $record['path'] ?? '' ) );

	if ( ! is_readable( $path ) ) {
		wp_die( esc_html__( 'File not found.', 'wp-sell-services' ), '', array( 'response' => 404 ) );
	}

	nocache_headers();
	$mime = ( ! empty( $record['type'] ) ) ? (string) $record['type'] : 'application/octet-stream';
	header( 'Content-Type: ' . $mime );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'Content-Disposition: attachment; filename="' . rawurlencode( (string) ( $record['name'] ?? basename( $path ) ) ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}
