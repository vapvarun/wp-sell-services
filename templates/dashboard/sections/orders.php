<?php
/**
 * Dashboard Section: My Orders (as buyer)
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

use WPSellServices\Database\Repositories\OrderRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Fires before the orders dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('orders').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'orders', $user_id );

// Check if viewing a specific order.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display, access controlled by order ownership.
$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

if ( $order_id ) {
	// Verify user has access to this order (buyer or vendor).
	$current_order = wpss_get_order( $order_id );

	if ( $current_order && ( (int) $current_order->customer_id === $user_id || (int) $current_order->vendor_id === $user_id ) ) {
		include WPSS_PLUGIN_DIR . 'templates/order/order-view.php';
		return;
	}
}

$order_repo = new OrderRepository();
$orders     = $order_repo->get_by_customer( $user_id, array( 'limit' => 20 ) );

// Get order stats.
$stats           = $order_repo->get_customer_stats( $user_id );
$active_count    = (int) ( $stats['active_orders'] ?? 0 );
$completed_count = (int) ( $stats['completed_orders'] ?? 0 );
$total_count     = (int) ( $stats['total_orders'] ?? 0 );
?>

<div class="wpss-section wpss-section--orders">
	<div class="wpss-stats-grid">
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $total_count ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Total Orders', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $active_count ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Active', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $completed_count ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php
	/**
	 * Fires in the orders filter area.
	 *
	 * Allows developers to add filtering or sorting options for orders.
	 *
	 * @since 1.1.0
	 *
	 * @param int $user_id Current user ID.
	 */
	do_action( 'wpss_orders_filters', $user_id );
	?>

	<?php if ( empty( $orders ) ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
			</div>
			<h3><?php esc_html_e( 'No orders yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'When you purchase a service, your orders will appear here.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( wpss_get_page_url( 'services_page' ) ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Browse Services', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-orders-list">
			<?php foreach ( $orders as $order_item ) : ?>
				<?php
				$service       = $order_item->service_id ? get_post( $order_item->service_id ) : null;
				$vendor        = get_userdata( $order_item->vendor_id );
				$status_class  = 'wpss-status--' . sanitize_html_class( $order_item->status );
				$status_labels = wpss_get_order_status_labels();

				// For request-based orders, use the request title.
				if ( ! $service && 'request' === $order_item->platform && $order_item->platform_order_id ) {
					$request_post = get_post( $order_item->platform_order_id );
				}
				$order_title = $service ? $service->post_title : ( ! empty( $request_post ) ? $request_post->post_title : __( 'Deleted Service', 'wp-sell-services' ) );
				?>
				<div class="wpss-order-card">
					<div class="wpss-order-card__main">
						<?php if ( $service && has_post_thumbnail( $service ) ) : ?>
							<div class="wpss-order-card__image">
								<?php echo get_the_post_thumbnail( $service, 'thumbnail' ); ?>
							</div>
						<?php endif; ?>
						<div class="wpss-order-card__info">
							<h4 class="wpss-order-card__title">
								<?php echo esc_html( $order_title ); ?>
							</h4>
							<p class="wpss-order-card__meta">
								<?php
								printf(
									/* translators: %s: vendor name */
									esc_html__( 'by %s', 'wp-sell-services' ),
									esc_html( $vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ) )
								);
								?>
								<span class="wpss-order-card__sep">&bull;</span>
								<?php echo esc_html( human_time_diff( strtotime( $order_item->created_at ), current_time( 'timestamp' ) ) ); ?>
								<?php esc_html_e( 'ago', 'wp-sell-services' ); ?>
							</p>
						</div>
					</div>
					<div class="wpss-order-card__actions">
						<span class="wpss-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_labels[ $order_item->status ] ?? $order_item->status ); ?>
						</span>
						<a href="<?php echo esc_url( wpss_get_order_url( $order_item->id ) ); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm">
							<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the orders dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('orders').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'orders', $user_id );
?>
