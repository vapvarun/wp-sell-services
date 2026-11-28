<?php
/**
 * Digital products: the file a buyer downloads, who may download it, and the delivery made at payment.
 *
 * A digital product is a service of type `digital` (wpss_get_service_types()). Its file lives in the one
 * private file store, folder `s{service}`, read through scope `service`. Each upload is a new version;
 * buyers always get the latest. Paying an order for one delivers it at once through DeliveryService, so
 * auto-complete, reviews, disputes, refunds and commission work as for any order (Basecamp 10551449503).
 *
 * @package WPSellServices
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every version of a product's file, oldest first.
 *
 * @since 2.0.0
 *
 * @param int $service_id Service post ID.
 * @return array<int, array<string,mixed>> File records.
 */
function wpss_get_product_files( int $service_id ): array {
	return array_values( array_filter( (array) get_post_meta( $service_id, '_wpss_product_files', true ), 'is_array' ) );
}

/**
 * The file buyers get now: the latest version.
 *
 * @since 2.0.0
 *
 * @param int $service_id Service post ID.
 * @return array<string,mixed>|null File record, or null when none is uploaded.
 */
function wpss_get_product_file( int $service_id ): ?array {
	$files = wpss_get_product_files( $service_id );

	return $files ? end( $files ) : null;
}

/**
 * Store an uploaded file as the product's new version. The one writer of _wpss_product_files.
 *
 * Earlier versions stay, so a buyer's download of the version they paid for is never broken by a
 * re-upload in the middle of it; links always point at the latest.
 *
 * @since 2.0.0
 *
 * @param int                 $service_id Service post ID.
 * @param array<string,mixed> $file       One entry from $_FILES.
 * @return array<string,mixed>|null The stored record, or null when the upload was refused.
 */
function wpss_add_product_file( int $service_id, array $file ): ?array {
	if ( 'wpss_service' !== get_post_type( $service_id ) ) {
		return null;
	}

	$record = wpss_store_order_file( $file, $service_id, 'product' );

	if ( ! $record ) {
		return null;
	}

	$record['uploaded_at'] = gmdate( 'Y-m-d H:i:s' );

	$files   = wpss_get_product_files( $service_id );
	$files[] = $record;
	update_post_meta( $service_id, '_wpss_product_files', $files );

	/**
	 * Fires after a digital product gets a new version of its file.
	 *
	 * @since 2.0.0
	 *
	 * @param int                 $service_id Service post ID.
	 * @param array<string,mixed> $record     The stored file record.
	 */
	do_action( 'wpss_product_file_added', $service_id, $record );

	return $record;
}

/**
 * A product's current file as a client may see it: name, size and the gated link, never storage internals.
 *
 * @since 2.0.0
 *
 * @param int $service_id Service post ID.
 * @return array<string,mixed>|null
 */
function wpss_product_file_for_client( int $service_id ): ?array {
	$record = wpss_get_product_file( $service_id );

	if ( ! $record ) {
		return null;
	}

	return array(
		'id'          => (string) $record['id'],
		'name'        => (string) ( $record['name'] ?? '' ),
		'type'        => (string) ( $record['type'] ?? '' ),
		'size'        => (int) ( $record['size'] ?? 0 ),
		'version'     => count( wpss_get_product_files( $service_id ) ),
		'uploaded_at' => wpss_rest_date( (string) ( $record['uploaded_at'] ?? '' ) ),
		'url'         => wpss_file_link( 'service', $service_id, (string) $record['id'] ),
	);
}

/**
 * Whether an order sells a digital product: frozen on the order when it is paid, so a later change of
 * the service's type never changes what the buyer bought.
 *
 * @since 2.0.0
 *
 * @param object $order Order row or model (its `meta` as an array or JSON).
 * @return string `digital` or `service`.
 */
function wpss_get_order_kind( object $order ): string {
	$meta = $order->meta ?? array();
	$meta = is_array( $meta ) ? $meta : (array) json_decode( (string) $meta, true );

	return 'digital' === ( $meta['service_type'] ?? '' ) ? 'digital' : 'service';
}

/**
 * A buyer's digital purchases they may still download, newest first.
 *
 * Paid (or part refunded) and not cancelled or refunded: a fully refunded order loses its download.
 *
 * @since 2.0.0
 *
 * @param int $user_id    Buyer.
 * @param int $service_id Only this product; 0 for every product.
 * @param int $limit      Rows; 0 for a count.
 * @param int $offset     Rows to skip.
 * @return array<int, object>|int Order rows (id, order_number, service_id, paid_at), or the count when $limit is 0.
 */
function wpss_get_buyer_downloads( int $user_id, int $service_id = 0, int $limit = 20, int $offset = 0 ) {
	global $wpdb;

	if ( $user_id <= 0 ) {
		return $limit ? array() : 0;
	}

	$table = $wpdb->prefix . 'wpss_orders';
	// Narrowed by idx_customer first; the kind is a JSON key on those few rows.
	$where = $wpdb->prepare(
		"customer_id = %d AND payment_status IN ( 'paid', 'partially_refunded' ) AND status NOT IN ( 'cancelled', 'refunded' ) AND meta LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$user_id,
		'%' . $wpdb->esc_like( '"service_type":"digital"' ) . '%'
	);

	if ( $service_id > 0 ) {
		$where .= $wpdb->prepare( ' AND service_id = %d', $service_id );
	}

	if ( ! $limit ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, order_number, service_id, paid_at FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, max( 0, $offset ) ) );
}

/**
 * One row of a buyer's downloads, the same for the dashboard and GET /downloads.
 *
 * @since 2.0.0
 *
 * @param object $row A row from wpss_get_buyer_downloads().
 * @return array<string,mixed>
 */
function wpss_buyer_download_for_client( object $row ): array {
	$service_id = (int) $row->service_id;

	return array(
		'order_id'     => (int) $row->id,
		'order_number' => (string) $row->order_number,
		'order_url'    => wpss_get_order_url( (int) $row->id ),
		'service'      => array(
			'id'    => $service_id,
			'title' => 'wpss_service' === get_post_type( $service_id ) ? get_the_title( $service_id ) : __( 'Deleted product', 'wp-sell-services' ),
			'link'  => 'publish' === get_post_status( $service_id ) ? (string) get_permalink( $service_id ) : '',
		),
		'purchased_at' => wpss_rest_date( (string) $row->paid_at ),
		'file'         => wpss_product_file_for_client( $service_id ),
	);
}

/**
 * Whether a user may download a product's file: its seller, an administrator, or a buyer holding a paid
 * order for it that was not cancelled or refunded.
 *
 * @since 2.0.0
 *
 * @param int $service_id Service post ID.
 * @param int $user_id    User ID.
 * @return bool
 */
function wpss_can_download_product( int $service_id, int $user_id ): bool {
	if ( $user_id <= 0 || 'wpss_service' !== get_post_type( $service_id ) ) {
		return false;
	}

	$allowed = user_can( $user_id, 'manage_options' )
		|| (int) get_post_field( 'post_author', $service_id ) === $user_id
		|| wpss_get_buyer_downloads( $user_id, $service_id, 0 ) > 0;

	/**
	 * Filter whether a user may download a digital product's file.
	 *
	 * The place for a download limit or an expiry: none is applied by default.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $allowed    Whether the download is allowed.
	 * @param int  $service_id Service post ID.
	 * @param int  $user_id    User ID.
	 */
	return (bool) apply_filters( 'wpss_can_download_product', $allowed, $service_id, $user_id );
}

/**
 * Whether a paid order is a digital product whose file is handed over the moment it is paid.
 *
 * The new-order emails and notices word the purchase as a download when this is true. A digital product
 * with no file uploaded is false: it waits in progress for the vendor to deliver by hand, as any order does.
 *
 * @since 2.0.0
 *
 * @param object $order Order row or model.
 * @return bool
 */
function wpss_order_delivers_instantly( object $order ): bool {
	return 'digital' === wpss_get_order_kind( $order ) && (bool) wpss_get_product_file( (int) ( $order->service_id ?? 0 ) );
}

/**
 * Whether the automatic hand-over of a digital order is running now. The receipt already says the
 * download is ready, so the delivery emails and notices stand down for it.
 *
 * @since 2.0.0
 *
 * @param int       $order_id Order ID.
 * @param bool|null $running  Set by wpss_deliver_digital_order() around the delivery; omit to read.
 * @return bool
 */
function wpss_is_instant_delivery( int $order_id, ?bool $running = null ): bool {
	static $orders = array();

	if ( true === $running ) {
		$orders[ $order_id ] = true;
	} elseif ( false === $running ) {
		unset( $orders[ $order_id ] );
	}

	return isset( $orders[ $order_id ] );
}

/**
 * Deliver a paid digital order at once through DeliveryService.
 *
 * Runs on wpss_order_paid, which every rail fires from mark_as_paid(), after mark_as_paid() has frozen
 * the order's kind. A digital order skips the brief (wpss_order_needs_requirements()), so it arrives
 * here in progress. With no file uploaded it stays in progress and the vendor delivers by hand.
 *
 * @since 2.0.0
 *
 * @param int $order_id Order ID.
 * @return void
 */
function wpss_deliver_digital_order( int $order_id ): void {
	$order = wpss_get_order( $order_id );

	if ( ! $order || ! wpss_order_delivers_instantly( $order ) || \WPSellServices\Models\ServiceOrder::STATUS_IN_PROGRESS !== $order->status ) {
		return;
	}

	$file = (array) wpss_get_product_file( (int) $order->service_id );

	wpss_is_instant_delivery( $order_id, true );
	$delivered = ( new \WPSellServices\Services\DeliveryService() )->submit(
		$order_id,
		sprintf(
			/* translators: %s: file name. */
			__( 'Your download is ready: %s. Download it from this order or from Downloads in your dashboard, any time. You always get the latest version.', 'wp-sell-services' ),
			(string) ( $file['name'] ?? '' )
		)
	);
	wpss_is_instant_delivery( $order_id, false );

	if ( ! $delivered ) {
		wpss_log( sprintf( 'Digital order %d was paid but could not be delivered automatically.', $order_id ), 'error' );
		return;
	}

	/**
	 * Fires after a paid digital order is delivered automatically.
	 *
	 * @since 2.0.0
	 *
	 * @param int                 $order_id   Order ID.
	 * @param int                 $service_id Service post ID.
	 * @param array<string,mixed> $file       The file record delivered.
	 */
	do_action( 'wpss_instant_delivery_created', $order_id, (int) $order->service_id, $file );
}
add_action( 'wpss_order_paid', 'wpss_deliver_digital_order', 20 );

/**
 * Remove a product's stored files when its service is deleted for good.
 *
 * @since 2.0.0
 *
 * @param int $post_id Post ID.
 * @return void
 */
function wpss_discard_product_files( int $post_id ): void {
	if ( 'wpss_service' !== get_post_type( $post_id ) ) {
		return;
	}

	foreach ( wpss_get_product_files( $post_id ) as $record ) {
		wpss_discard_order_file( $record, 0 );
	}

	foreach ( wpss_order_files_dirs() as $dir ) {
		@rmdir( $dir . 's' . $post_id . '/' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removed only when empty.
	}
}
add_action( 'before_delete_post', 'wpss_discard_product_files' );
