<?php
/**
 * Order files: storing, locating, streaming and legacy migration.
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

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
 * @param string              $kind     delivery|requirement|message|dispute|contact|receipt - used only for grouping.
 * @return array<string,mixed>|null Record, or null when the upload is rejected.
 */
function wpss_store_order_file( array $file, int $order_id, string $kind = 'delivery' ): ?array {
	/*
	 * Enforce the allow-list here, not only in the callers.
	 *
	 * Every current caller runs wpss_check_upload() first, so this is belt and
	 * braces today - but a future one that forgets falls back to WordPress's
	 * default mime list, which includes html, and this function writes into the
	 * order-files store (Basecamp 10321653509). wpss_check_upload() is
	 * idempotent, so running it twice costs a mime lookup and nothing else.
	 */
	$wpss_refused = wpss_check_upload( $file );

	if ( $wpss_refused ) {
		// null, not the WP_Error: this function's contract is ?array and every
		// caller reads null as "not stored".
		wpss_log(
			sprintf( 'Refused an order-file upload for order %d: %s', $order_id, $wpss_refused->get_error_message() ),
			'warning'
		);

		return null;
	}

	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return null;
	}

	$checked = wp_check_filetype( (string) ( $file['name'] ?? '' ) );

	if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
		return null;
	}

	// Size is enforced here for every kind; the type allow-list is the
	// caller's (deliveries keep their own, wider, filterable list).
	if ( (int) ( $file['size'] ?? 0 ) > (int) wpss_get_option( 'advanced', 'max_file_size' ) * MB_IN_BYTES ) {
		return null;
	}

	$order_id = max( 0, $order_id );
	$kind     = in_array( $kind, array( 'delivery', 'requirement', 'message', 'dispute', 'contact', 'receipt' ), true ) ? $kind : 'delivery';

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
 * deliveries (Basecamp 10264291163), and so does a buyer's proof of payment
 * (Basecamp 10267994010).
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_col( $wpdb->prepare( "SELECT attachments FROM {$wpdb->prefix}wpss_payment_receipts WHERE order_id = %d", $order_id ) ),
	);

	$records     = array();
	$customer_id = null;

	foreach ( $sets as $set => $rows ) {
		foreach ( (array) $rows as $raw ) {
			$decoded = json_decode( (string) $raw, true );

			if ( ! is_array( $decoded ) ) {
				continue;
			}

			// Requirement rows may hold bare media ids (see the normaliser).
			if ( 1 === $set ) {
				$customer_id ??= (int) ( wpss_get_order( $order_id )->customer_id ?? 0 );
				$decoded       = wpss_normalize_requirement_attachments( $decoded, $customer_id );
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
 * A buyer's requirement attachments as file records, whatever shape was stored.
 *
 * Two writers stored bare media-library ids instead of records - a buyer
 * request converted to an order, and REST POST /orders/{id}/requirements -
 * while every reader indexes into each entry, so one int fatalled the order
 * page (Basecamp 10380173025, 10380395139) and the REST reads dropped the
 * file (Basecamp 10380601755). An id becomes the pre-1.7.0 record shape
 * (id, name, url, type, size), which the readers and the file endpoint
 * already serve, and migrate into the private store when opened.
 *
 * Only the buyer's own uploads are accepted: that migration deletes the
 * public copy, so an id naming someone else's media must never become a
 * record on this order.
 *
 * @since 1.8.0
 *
 * @param array<int,mixed> $entries     Stored entries: records or media ids.
 * @param int              $customer_id The order's buyer.
 * @return array<int,array<string,mixed>> Records only.
 */
function wpss_normalize_requirement_attachments( array $entries, int $customer_id ): array {
	$records = array();

	foreach ( $entries as $entry ) {
		if ( is_array( $entry ) ) {
			$records[] = $entry;
			continue;
		}

		$attachment_id = is_numeric( $entry ) ? (int) $entry : 0;

		if ( $attachment_id <= 0 || $customer_id <= 0
			|| 'attachment' !== get_post_type( $attachment_id )
			|| (int) get_post_field( 'post_author', $attachment_id ) !== $customer_id ) {
			continue;
		}

		$path = (string) get_attached_file( $attachment_id );

		$records[] = array(
			'id'   => (string) $attachment_id,
			'name' => '' !== $path ? wp_basename( $path ) : (string) get_post_field( 'post_title', $attachment_id ),
			'url'  => (string) wp_get_attachment_url( $attachment_id ),
			'type' => (string) get_post_mime_type( $attachment_id ),
			'size' => '' !== $path && is_readable( $path ) ? (int) filesize( $path ) : 0,
		);
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

	$attachment_id = ctype_digit( $file_id ) && 'attachment' === get_post_type( (int) $file_id ) ? (int) $file_id : 0;

	// The same upload can also be the brief of a buyer request a seller has
	// proposed on. That request still links the public copy, so it stays; the
	// order now reads its own private copy either way.
	if ( $attachment_id && ( new \WPSellServices\Services\BuyerRequestService() )->is_file_locked( $attachment_id ) ) {
		delete_transient( $lock );
		return $updated;
	}

	// Only now is the public copy safe to remove - the record points at the
	// private one and the bytes are there.
	wp_delete_file( $source );

	// If the id was an attachment post, drop it too. Leaving it behind means a
	// media-library row pointing at a file that is no longer there.
	if ( $attachment_id ) {
		wp_delete_attachment( $attachment_id, true );
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
		$wpdb->prefix . 'wpss_payment_receipts',
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
				// A requirement row may still hold the bare media id the
				// record was built from; replacing it is what persists the
				// migration.
				$entry_id = is_array( $entry ) ? ( $entry['id'] ?? null ) : $entry;

				if ( is_scalar( $entry_id ) && (string) $entry_id === $file_id ) {
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
 * The gated link to a receipt's proof of payment, and enough about it to render.
 *
 * ONE reading of a receipt row's file for both surfaces that show it - the
 * admin review box and GET /orders/{id}/receipts. Both used to emit
 * wp_get_attachment_url(), which is a public wp-content/uploads link: a
 * stranger holding it downloaded a stranger's bank transfer slip (Basecamp
 * 10267994010).
 *
 * @since 1.7.1
 *
 * @param object $receipt Receipt row.
 * @return array{url:string,is_image:bool,name:string} `url` is '' when there is nothing this user may open.
 */
function wpss_get_receipt_file( object $receipt ): array {
	$decoded = json_decode( (string) ( $receipt->attachments ?? '' ), true );
	$record  = ( is_array( $decoded ) && isset( $decoded[0] ) && is_array( $decoded[0] ) ) ? $decoded[0] : null;

	if ( ! $record && (int) ( $receipt->attachment_id ?? 0 ) > 0 ) {
		// A row the upgrade pass has not reached yet. Adopt it now rather than
		// hand back the public URL for one more render.
		$record = wpss_adopt_legacy_receipt_file( $receipt );
	}

	if ( ! $record ) {
		return array(
			'url'      => '',
			'is_image' => false,
			'name'     => '',
		);
	}

	$record['order_id'] = (int) ( $receipt->order_id ?? 0 );

	return array(
		'url'      => wpss_get_order_file_url( $record ),
		'is_image' => 0 === strpos( (string) ( $record['type'] ?? '' ), 'image/' ),
		'name'     => (string) ( $record['name'] ?? '' ),
	);
}

/**
 * Move a pre-1.7.1 receipt out of the media library and into the order store.
 *
 * Receipts written before this release are media-library attachments: the row
 * holds `attachment_id` and the file sits in wp-content/uploads with nothing in
 * front of it. This writes the file record the rest of the order-file seam
 * expects, then hands the move itself to wpss_migrate_legacy_order_file() -
 * the same copy/rewrite/delete that every other legacy order file goes through.
 *
 * The record is written to the row BEFORE the move, because that migrator
 * rewrites the row it finds; and it is returned even when the move fails (a
 * file already deleted, an offloaded bucket), because a record carrying only
 * `url` still routes through the permission-checked endpoint, where a bare
 * attachment id would 404.
 *
 * Called from the upgrade pass for every legacy row, and from
 * wpss_get_receipt_file() for anything that pass missed.
 *
 * @since 1.7.1
 *
 * @param object $receipt Receipt row carrying `id`, `order_id` and `attachment_id`.
 * @return array<string,mixed>|null File record, or null when there is nothing to adopt.
 */
function wpss_adopt_legacy_receipt_file( object $receipt ): ?array {
	global $wpdb;

	$receipt_id    = (int) ( $receipt->id ?? 0 );
	$order_id      = (int) ( $receipt->order_id ?? 0 );
	$attachment_id = (int) ( $receipt->attachment_id ?? 0 );
	$url           = $attachment_id > 0 ? (string) wp_get_attachment_url( $attachment_id ) : '';

	if ( $receipt_id <= 0 || $order_id <= 0 || '' === $url ) {
		return null;
	}

	$file = (string) get_attached_file( $attachment_id );

	$record = array(
		'id'       => (string) $attachment_id,
		'name'     => '' !== $file ? basename( $file ) : (string) $attachment_id,
		'type'     => (string) get_post_mime_type( $attachment_id ),
		'size'     => ( '' !== $file && is_readable( $file ) ) ? (int) filesize( $file ) : 0,
		'url'      => $url,
		'order_id' => $order_id,
		'kind'     => 'receipt',
		'user_id'  => (int) ( $receipt->uploaded_by ?? 0 ),
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->update(
		$wpdb->prefix . 'wpss_payment_receipts',
		array( 'attachments' => wp_json_encode( array( $record ) ) ),
		array( 'id' => $receipt_id )
	);

	$moved = wpss_migrate_legacy_order_file( $record, $order_id );

	return $moved ? $moved : $record;
}

/**
 * Resolve an order file into something a caller who is entitled to it can be served.
 *
 * Authorisation is NOT done here - the two callers (the admin-post link and
 * the REST route) each run wpss_can_read_order_files() first, because they
 * answer a refusal differently. Everything after that decision is shared: find
 * the record, migrate a pre-1.7.0 file into the private store on the way past,
 * hand back a short-lived signed URL when a bucket holds the bytes, or the
 * readable local path when this server does.
 *
 * @since 1.7.1
 *
 * @param int    $order_id Order ID.
 * @param string $file_id  Record id.
 * @return array{record:array<string,mixed>,path?:string,url?:string,expires_in?:int}|WP_Error
 *         `path` is a readable local file; `url` is a signed bucket URL (with
 *         `expires_in` seconds) or, for a legacy record that could not be
 *         migrated, its historical public location.
 */
function wpss_locate_order_file( int $order_id, string $file_id ) {
	$record = wpss_find_order_file( $order_id, $file_id );

	if ( ! $record ) {
		return new WP_Error( 'wpss_file_not_found', __( 'File not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
	}

	/*
	 * A legacy record reaching this point belongs to someone who is allowed to
	 * read it - the caller checked. Move it into the private store now, then
	 * serve the migrated copy.
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
			return array(
				'record' => $record,
				'url'    => (string) $record['url'],
			);
		}
	}

	// In a bucket: hand over a short-lived signed URL rather than streaming the
	// bytes through PHP. The record names the provider that holds it; only
	// rows written before 1.7.1 lack one, and for those the active provider
	// is the only candidate.
	if ( ! empty( $record['remote_path'] ) ) {
		$provider_id = (string) ( $record['provider'] ?? '' );
		$provider    = '' !== $provider_id ? wpss_get_storage_provider( $provider_id ) : wpss_get_active_storage_provider();
		$ttl         = 5 * MINUTE_IN_SECONDS;

		if ( $provider && method_exists( $provider, 'get_signed_url' ) ) {
			$signed = $provider->get_signed_url( (string) $record['remote_path'], $ttl );

			if ( $signed ) {
				return array(
					'record'     => $record,
					'url'        => (string) $signed,
					'expires_in' => $ttl,
				);
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

		return new WP_Error(
			'wpss_storage_unavailable',
			__( 'This file is stored remotely and the storage provider is unavailable. Try again shortly.', 'wp-sell-services' ),
			array( 'status' => 503 )
		);
	}

	$path = wpss_get_order_files_dir() . $order_id . '/' . basename( (string) ( $record['path'] ?? '' ) );

	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'wpss_file_not_found', __( 'File not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
	}

	return array(
		'record' => $record,
		'path'   => $path,
	);
}

/**
 * Write a local order file to the response as a download.
 *
 * Headers and bytes only; the caller has already authorised and located it,
 * and decides whether to exit afterwards.
 *
 * @since 1.7.1
 *
 * @param array<string,mixed> $record File record, for the name and MIME type.
 * @param string              $path   Readable local path from wpss_locate_order_file().
 * @return void
 */
function wpss_stream_order_file( array $record, string $path ): void {
	nocache_headers();
	$mime = ( ! empty( $record['type'] ) ) ? (string) $record['type'] : 'application/octet-stream';
	header( 'Content-Type: ' . $mime );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'Content-Disposition: attachment; filename="' . rawurlencode( (string) ( $record['name'] ?? basename( $path ) ) ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
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
 * App clients, which hold a token and no cookie, use GET
 * /wpss/v1/orders/{id}/files/{file} instead; both run the same
 * wpss_locate_order_file() underneath.
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

	$located = wpss_locate_order_file( $order_id, $file_id );

	if ( is_wp_error( $located ) ) {
		$data = (array) $located->get_error_data();
		wp_die( esc_html( $located->get_error_message() ), '', array( 'response' => (int) ( $data['status'] ?? 404 ) ) );
	}

	if ( isset( $located['url'] ) ) {
		wp_redirect( $located['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- deliberately offsite: the configured bucket, or the file's own historical location on this site.
		exit;
	}

	wpss_stream_order_file( $located['record'], $located['path'] );
	exit;
}

/**
 * Renderable file links for one dispute message.
 *
 * A dispute message that carries a file is stored with message = '' and the
 * file in the `attachments` column, so any surface that renders only
 * $message->message draws an empty bubble and the evidence is invisible. That
 * is what the admin dispute screen did (Basecamp 10320551466) - the person
 * being asked to decide the dispute could not open a single piece of evidence,
 * and a PDF appeared as the raw admin-post.php query string.
 *
 * Two record shapes exist in the wild: older rows carry a ready `url`, newer
 * private-store rows carry `id` + `order_id` + `path` and no url at all. Both
 * are resolved through wpss_get_order_file_url(), which already knows the
 * difference and applies the permission check, so neither caller has to.
 *
 * @since 1.7.2
 *
 * @param mixed $raw The message's `attachments` value: a JSON string or array.
 * @return array<int, array{name: string, url: string}> Links, possibly empty.
 */
function wpss_dispute_message_attachments( $raw ): array {
	$rows = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

	if ( ! is_array( $rows ) ) {
		return array();
	}

	$links = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		/*
		 * No fallback to the stored public URL.
		 *
		 * wpss_get_order_file_url() returns '' when the file is unreadable -
		 * which includes the PERMISSION-REFUSED case - so falling back to
		 * $row['url'] handed back exactly the address the private-file gate
		 * exists to suppress. Only an admin screen calls this today, which is
		 * why it was never seen, but the fallback undoes the gate for whoever
		 * calls it next (Basecamp 10321653509).
		 *
		 * A row with no readable file simply gets no link, which is what the
		 * branch below already does for every other unaddressable case.
		 */
		$url = wpss_get_order_file_url( $row );

		if ( '' === $url ) {
			// No addressable file. A link here would only 404.
			continue;
		}

		$links[] = array(
			'name' => wpss_format_attachment_name( (string) ( $row['name'] ?? '' ) ),
			'url'  => $url,
		);
	}

	return $links;
}

/**
 * Give a publicly-served upload an unguessable filename.
 *
 * Two upload paths cannot use the private store because they have no owning
 * record to gate on: the generic `wpss_upload_file` handler, and pre-sale
 * contact attachments, which exist before any order or conversation. Both are
 * documented as "unlisted, not secret" - post_status private hides the library
 * row, not the bytes.
 *
 * That claim only holds if the URL cannot be guessed, and WordPress keeps the
 * uploader's own filename, so `brief.pdf` in this month's upload folder is a
 * URL anyone can try. Prefixing 16 random characters makes the documented
 * property actually true (Basecamp 10321653478, finding 4).
 *
 * The original name is still what the UI shows - both callers carry it
 * separately - so this changes the stored path, not what anyone reads.
 *
 * Attach with add_filter( 'wp_handle_upload_prefilter', ... ) immediately
 * before the upload and remove it immediately after, so it never touches
 * uploads from anywhere else.
 *
 * @since 1.7.2
 *
 * @param array<string, mixed> $file Upload array.
 * @return array<string, mixed>
 */
function wpss_obfuscate_public_upload_name( array $file ): array {
	$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );

	if ( '' !== $name ) {
		$file['name'] = wp_generate_password( 16, false, false ) . '-' . $name;
	}

	return $file;
}
