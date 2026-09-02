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

	$provider = wpss_get_storage_provider( $active_id );

	if ( ! $provider ) {
		wpss_log(
			sprintf( 'Storage provider "%s" is configured but not registered; falling back to local disk.', $active_id ),
			'error'
		);
		return null;
	}

	if ( ! method_exists( $provider, 'is_configured' ) || ! $provider->is_configured() ) {
		wpss_log(
			sprintf( 'Storage provider "%s" is registered but not configured; falling back to local disk.', $active_id ),
			'error'
		);
		return null;
	}

	return $provider;
}

/**
 * Look up one registered storage provider by id.
 *
 * A pure registry read, deliberately separate from the ACTIVE provider: a
 * file record names the provider that holds it, and that provider must keep
 * resolving after the owner switches buckets - the file did not move.
 *
 * @since 1.7.1
 *
 * @param string $id Provider id as registered on `wpss_storage_providers` ('s3', 'gcs', 'do', ...).
 * @return object|null Provider implementing StorageProviderInterface, or null when not registered.
 */
function wpss_get_storage_provider( string $id ): ?object {
	if ( '' === $id || 'local' === $id ) {
		return null;
	}

	$providers = (array) apply_filters( 'wpss_storage_providers', array() );
	$provider  = $providers[ $id ] ?? null;

	return is_object( $provider ) ? $provider : null;
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

	// IIS honours none of the above. Without this a Windows host had no
	// directory denial at all - the .htaccess is inert there, and the guard
	// only ever covered Apache.
	if ( ! file_exists( $dir . 'web.config' ) ) {
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$dir . 'web.config',
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n"
		);
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
 * Validate an upload against the plugin's size and type settings.
 *
 * ONE reading of `wpss_max_file_size` and `wpss_allowed_file_types`. Message,
 * contact and dispute uploads each carried a private copy of these limits
 * (and dispute evidence had no size cap at all), so the owner's settings
 * governed some uploads and not others (Basecamp 10264291163).
 *
 * @since 1.7.1
 *
 * @param array<string,mixed> $file One entry from $_FILES.
 * @return WP_Error|null Error describing the refusal, or null when acceptable.
 */
function wpss_check_upload( array $file ): ?WP_Error {
	$max_mb = (int) get_option( 'wpss_max_file_size', 10 );

	if ( (int) ( $file['size'] ?? 0 ) > $max_mb * MB_IN_BYTES ) {
		return new WP_Error(
			'file_too_large',
			/* translators: %s: maximum file size */
			sprintf( __( 'File size exceeds the maximum of %s.', 'wp-sell-services' ), size_format( $max_mb * MB_IN_BYTES ) ),
			array( 'status' => 400 )
		);
	}

	// Real bytes, not the client-supplied extension or mime.
	$checked = wp_check_filetype_and_ext( (string) ( $file['tmp_name'] ?? '' ), (string) ( $file['name'] ?? '' ) );

	if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
		return new WP_Error( 'invalid_type', __( 'File type could not be verified.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	$allowed = array_map( 'trim', explode( ',', strtolower( (string) get_option( 'wpss_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx' ) ) ) );

	if ( ! in_array( strtolower( (string) $checked['ext'] ), $allowed, true ) ) {
		return new WP_Error( 'invalid_type', __( 'File type not allowed.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	return null;
}

/**
 * Store one uploaded file against an order.
 *
 * Replaces the bare wp_handle_upload() that each caller used to run. Writes
 * outside the web root, pushes to the configured cloud provider when there is
 * one, and returns a record addressed by id rather than by URL.
 *
 * The returned array keeps the `id`, `name`, `type` and `size` keys the old
 * shape had so stored rows stay readable, and adds `path`, `remote_path`,
 * `provider` (the storage provider id holding `remote_path`) and `user_id`
 * (who uploaded it - what lets a dispute reply prove a file is its own).
 * It deliberately omits `url`: see wpss_get_order_file_url().
 *
 * @since 1.7.0
 *
 * @param array<string,mixed> $file One entry from $_FILES.
 * @param int                 $order_id Order the file belongs to.
 * @param string              $kind     delivery|requirement|message|dispute|contact - used only for grouping.
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

	// Size is enforced here for every kind; the type allow-list is the
	// caller's (deliveries keep their own, wider, filterable list).
	if ( (int) ( $file['size'] ?? 0 ) > (int) get_option( 'wpss_max_file_size', 10 ) * MB_IN_BYTES ) {
		return null;
	}

	$order_id = max( 0, $order_id );
	$kind     = in_array( $kind, array( 'delivery', 'requirement', 'message', 'dispute', 'contact' ), true ) ? $kind : 'delivery';

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
		'user_id'     => get_current_user_id(),
		'remote_path' => null,
		'provider'    => null,
	);

	$provider = wpss_get_active_storage_provider();

	if ( $provider ) {
		$remote = sprintf( 'wpss/%d/%s/%s', $order_id, $kind, $filename );
		$result = $provider->upload( $target, $remote );

		if ( ! is_wp_error( $result ) && ! empty( $result['key'] ) ) {
			$record['remote_path'] = (string) $result['key'];
			$record['provider']    = (string) get_option( 'wpss_active_storage_provider', '' );

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
					is_wp_error( $result ) ? $result->get_error_message() : 'no key returned'
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

	if ( '' === $id || $order_id <= 0 ) {
		// Nothing addressable - all we can do is hand back whatever it has.
		return (string) ( $record['url'] ?? '' );
	}

	if ( ! $is_stored ) {
		/*
		 * A pre-1.7.0 row, still sitting in wp-content/uploads with no check in
		 * front of it. It is addressable (it has an id and an order), so it goes
		 * through the endpoint like everything else - and the endpoint moves it
		 * into the private store on the way past.
		 *
		 * This used to return the bare public URL, on the reasoning that
		 * breaking a delivered file to tighten history would punish the buyer.
		 * That reasoning was right about the buyer and wrong about the outcome:
		 * it left every brief and deliverable ever uploaded fetchable by anyone
		 * holding the link, permanently. Migrating as people open their own
		 * files keeps the link working AND closes the hole.
		 */
		if ( empty( $record['url'] ) ) {
			// No path, no bucket, no url - there is no file anywhere. Handing
			// back an endpoint link here would be a link that 404s, which is
			// worse than showing nothing.
			return '';
		}

		if ( ! wpss_can_read_order_files( $order_id ) ) {
			// Not this person's file. Do not hand back the public URL just
			// because the record is old.
			return '';
		}
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
	foreach ( wpss_get_order_file_records( $order_id ) as $record ) {
		if ( (string) $record['id'] === $file_id ) {
			return $record;
		}
	}

	return null;
}

/**
 * Every file record attached to an order.
 *
 * Reads every table that holds one, because the endpoint is handed an id and
 * nothing else - the link in an email does not say which kind of file it
 * points at. Conversation and dispute rows are keyed by order through their
 * parent, so message and dispute attachments answer to the same read gate as
 * deliveries (Basecamp 10264291163).
 *
 * @since 1.7.1
 *
 * @param int $order_id Order ID.
 * @return array<int,array<string,mixed>> Records as stored, each with `order_id` set.
 */
function wpss_get_order_file_records( int $order_id ): array {
	global $wpdb;

	$sets = array(
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_col( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_deliveries WHERE order_id = %d", $order_id ) ),
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_col( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_order_requirements WHERE order_id = %d", $order_id ) ),
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_col( $wpdb->prepare( "SELECT m.attachments FROM {$wpdb->prefix}wpss_messages m INNER JOIN {$wpdb->prefix}wpss_conversations c ON c.id = m.conversation_id WHERE c.order_id = %d", $order_id ) ),
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_col( $wpdb->prepare( "SELECT dm.attachments FROM {$wpdb->prefix}wpss_dispute_messages dm INNER JOIN {$wpdb->prefix}wpss_disputes d ON d.id = dm.dispute_id WHERE d.order_id = %d", $order_id ) ),
	);

	$records = array();

	foreach ( $sets as $rows ) {
		foreach ( (array) $rows as $raw ) {
			$decoded = json_decode( (string) $raw, true );

			if ( ! is_array( $decoded ) ) {
				continue;
			}

			foreach ( $decoded as $record ) {
				if ( is_array( $record ) && isset( $record['id'] ) ) {
					$record['order_id'] = $order_id;
					$records[]          = $record;
				}
			}
		}
	}

	return $records;
}

/**
 * Remove every file attached to an order, on disk and in the bucket.
 *
 * Called when the order row itself is deleted (service cascade, uninstall).
 * Before 1.7.1 nothing removed these on any path, so a deleted order left its
 * buyer's brief and the seller's deliverables readable on disk forever.
 *
 * A remote object the provider cannot delete is logged, not retried: the row
 * that named it is about to go, so this log line is the owner's only pointer.
 *
 * @since 1.7.1
 *
 * @param int $order_id Order ID.
 * @return void
 */
function wpss_delete_order_files( int $order_id ): void {
	if ( $order_id <= 0 ) {
		return;
	}

	foreach ( wpss_get_order_file_records( $order_id ) as $record ) {
		if ( empty( $record['remote_path'] ) ) {
			continue;
		}

		$provider_id = (string) ( $record['provider'] ?? '' );
		$provider    = '' !== $provider_id ? wpss_get_storage_provider( $provider_id ) : wpss_get_active_storage_provider();

		if ( $provider && method_exists( $provider, 'delete' ) && $provider->delete( (string) $record['remote_path'] ) ) {
			continue;
		}

		wpss_log(
			sprintf(
				'Could not delete remote file %s for order %d from storage provider "%s"; remove it from the bucket by hand.',
				(string) $record['remote_path'],
				$order_id,
				'' !== $provider_id ? $provider_id : (string) get_option( 'wpss_active_storage_provider', '' )
			),
			'error'
		);
	}

	wpss_rmdir_recursive( wpss_get_order_files_dir() . $order_id . '/' );
}

/**
 * Delete a directory and everything under it.
 *
 * Plain PHP rather than WP_Filesystem: the order-file store is written with
 * plain PHP too, and this also runs from uninstall.php where no credentials
 * prompt is possible.
 *
 * @since 1.7.1
 *
 * @param string $dir Absolute path.
 * @return void
 */
function wpss_rmdir_recursive( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		} else {
			wp_delete_file( $item->getPathname() );
		}
	}

	rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}

/**
 * Move a pre-1.7.0 order file into the private store, the first time it is opened.
 *
 * Files uploaded before this release live in wp-content/uploads with no check
 * in front of them: the URL is unlisted, not secret, and anyone holding the
 * link can fetch it forever. Order requirements are where buyers hand over
 * briefs, contracts and occasionally identity documents, so that is a real
 * exposure, not a tidiness problem.
 *
 * Migrating on access rather than in bulk was a deliberate choice (Basecamp
 * 10239807824). A bulk move breaks every link already sitting in somebody's
 * inbox; doing nothing leaves history public forever. Moving a file the moment
 * someone with permission opens it means old links keep working, the exposure
 * closes as people actually touch their files, and no owner has to know to run
 * anything.
 *
 * Failure is non-fatal by design: if anything goes wrong the record is left
 * exactly as it was and the caller serves the original URL. A buyer trying to
 * download their own deliverable must never be handed an error because our
 * migration had a bad day.
 *
 * @since 1.7.0
 *
 * @param array<string,mixed> $record   File record as stored, carrying `url` and no `path`.
 * @param int                 $order_id Order the file belongs to.
 * @return array<string,mixed>|null Rewritten record, or null when it could not be migrated.
 */
function wpss_migrate_legacy_order_file( array $record, int $order_id ): ?array {
	global $wpdb;

	$file_id = (string) ( $record['id'] ?? '' );
	$url     = (string) ( $record['url'] ?? '' );

	if ( '' === $file_id || '' === $url || $order_id <= 0 ) {
		return null;
	}

	// Already private - nothing to do.
	if ( ! empty( $record['path'] ) || ! empty( $record['remote_path'] ) ) {
		return null;
	}

	// Two people opening the same old file at once must not both migrate it.
	$lock = 'wpss_migrating_file_' . md5( $file_id );

	if ( get_transient( $lock ) ) {
		return null;
	}

	set_transient( $lock, 1, MINUTE_IN_SECONDS );

	$source = wpss_resolve_local_path_from_url( $url );

	if ( ! $source || ! is_readable( $source ) ) {
		// Already gone, or served from somewhere we cannot reach on disk (a CDN
		// rewrite, an offloaded bucket). Leave it alone.
		delete_transient( $lock );
		return null;
	}

	$dir = wpss_get_order_files_dir() . $order_id . '/';

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$filename = wp_unique_filename( $dir, sanitize_file_name( basename( $source ) ) );
	$target   = $dir . $filename;

	// COPY, not rename: if the record rewrite below fails we must still have a
	// readable file at the original URL.
	if ( ! @copy( $source, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_copy
		wpss_log( sprintf( 'Could not copy legacy order file %s for order %d', $file_id, $order_id ), 'error' );
		delete_transient( $lock );
		return null;
	}

	@chmod( $target, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod

	$updated             = $record;
	$updated['path']     = $filename;
	$updated['order_id'] = $order_id;
	$updated['migrated'] = gmdate( 'c' );
	// `url` is dropped: leaving it would give the file two addresses, and the
	// public one would keep winning wherever something reads it directly.
	unset( $updated['url'] );

	if ( ! wpss_rewrite_order_file_record( $order_id, $file_id, $updated ) ) {
		// Roll back the copy so we do not leave an orphan behind.
		wp_delete_file( $target );
		delete_transient( $lock );
		return null;
	}

	// Only now is the public copy safe to remove - the record points at the
	// private one and the bytes are there.
	wp_delete_file( $source );

	// If the id was an attachment post, drop it too. Leaving it behind means a
	// media-library row pointing at a file that is no longer there.
	if ( ctype_digit( $file_id ) && 'attachment' === get_post_type( (int) $file_id ) ) {
		wp_delete_attachment( (int) $file_id, true );
	}

	delete_transient( $lock );

	wpss_log( sprintf( 'Migrated legacy order file %s (order %d) into the private store', $file_id, $order_id ), 'info' );

	return $updated;
}

/**
 * Resolve an uploads URL to a path on disk, refusing anything outside uploads.
 *
 * @since 1.7.0
 *
 * @param string $url File URL.
 * @return string|null Absolute path, or null when it is not a local upload.
 */
function wpss_resolve_local_path_from_url( string $url ): ?string {
	$uploads = wp_get_upload_dir();

	if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
		return null;
	}

	// Compare without scheme so http/https and protocol-relative all match.
	$strip   = static function ( string $v ): string {
		return preg_replace( '#^https?:#', '', $v );
	};
	$baseurl = $strip( $uploads['baseurl'] );
	$target  = $strip( $url );

	if ( 0 !== strpos( $target, $baseurl ) ) {
		return null;
	}

	$relative = ltrim( substr( $target, strlen( $baseurl ) ), '/' );

	// No traversal out of the uploads tree.
	if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
		return null;
	}

	$path = trailingslashit( $uploads['basedir'] ) . $relative;
	$real = realpath( $path );
	$base = realpath( $uploads['basedir'] );

	if ( ! $real || ! $base || 0 !== strpos( $real, $base ) ) {
		return null;
	}

	return $real;
}

/**
 * Replace one file record inside whichever row currently holds it.
 *
 * @since 1.7.0
 *
 * @param int                 $order_id Order id.
 * @param string              $file_id  File id to replace.
 * @param array<string,mixed> $updated Replacement record.
 * @return bool True when a row was rewritten.
 */
function wpss_rewrite_order_file_record( int $order_id, string $file_id, array $updated ): bool {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'wpss_deliveries',
		$wpdb->prefix . 'wpss_order_requirements',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name from a fixed list.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, attachments FROM {$table} WHERE order_id = %d", $order_id ) );

		foreach ( (array) $rows as $row ) {
			$decoded = json_decode( (string) $row->attachments, true );

			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$changed = false;

			foreach ( $decoded as $i => $entry ) {
				if ( is_array( $entry ) && isset( $entry['id'] ) && (string) $entry['id'] === $file_id ) {
					$decoded[ $i ] = $updated;
					$changed       = true;
				}
			}

			if ( ! $changed ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->update(
				$table,
				array( 'attachments' => wp_json_encode( $decoded ) ),
				array( 'id' => (int) $row->id )
			);

			return false !== $ok;
		}
	}

	return false;
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

	/*
	 * A legacy record reaching this point belongs to someone who is allowed to
	 * read it - wpss_can_read_order_files() ran above. Move it into the private
	 * store now, then serve the migrated copy.
	 *
	 * If the migration cannot complete the record is untouched and we fall
	 * through to serving the original file, so the download never fails because
	 * of housekeeping.
	 */
	if ( empty( $record['path'] ) && empty( $record['remote_path'] ) ) {
		$migrated = wpss_migrate_legacy_order_file( $record, $order_id );

		if ( $migrated ) {
			$record = $migrated;
		} elseif ( ! empty( $record['url'] ) ) {
			wp_redirect( (string) $record['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the file's own historical location on this site.
			exit;
		}
	}

	// In a bucket: hand over a short-lived signed URL rather than streaming the
	// bytes through PHP. The record names the provider that holds it; only
	// rows written before 1.7.1 lack one, and for those the active provider
	// is the only candidate.
	if ( ! empty( $record['remote_path'] ) ) {
		$provider_id = (string) ( $record['provider'] ?? '' );
		$provider    = '' !== $provider_id ? wpss_get_storage_provider( $provider_id ) : wpss_get_active_storage_provider();

		if ( $provider && method_exists( $provider, 'get_signed_url' ) ) {
			$signed = $provider->get_signed_url( (string) $record['remote_path'], 5 * MINUTE_IN_SECONDS );

			if ( $signed ) {
				wp_redirect( $signed ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- deliberately offsite: the configured bucket.
				exit;
			}
		}

		wpss_log(
			sprintf(
				'Storage provider "%s" holds file %s for order %d but is not registered or cannot sign URLs; download refused with 503.',
				'' !== $provider_id ? $provider_id : (string) get_option( 'wpss_active_storage_provider', '' ),
				(string) ( $record['id'] ?? '' ),
				$order_id
			),
			'error'
		);

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
