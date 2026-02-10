<?php
/**
 * Dashboard Section: Sales Orders (vendor only)
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
 * Fires before the sales dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('sales').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'sales', $user_id );

// Check if viewing a specific order.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display, access controlled by order ownership.
$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

if ( $order_id ) {
	// Verify user has access to this order (buyer or vendor).
	$current_order = wpss_get_order( $order_id );

	if ( $current_order && ( (int) $current_order->customer_id === $user_id || (int) $current_order->vendor_id === $user_id ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing, no data processing.
		$order_action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		switch ( $order_action ) {
			case 'requirements':
				include WPSS_PLUGIN_DIR . 'templates/order/order-requirements.php';
				break;
			default:
				include WPSS_PLUGIN_DIR . 'templates/order/order-view.php';
				break;
		}
		return;
	}
}

$order_repo = new OrderRepository();
$orders     = $order_repo->get_by_vendor( $user_id, array( 'limit' => 20 ) );

// Get order stats from vendor stats.
$stats           = $order_repo->get_vendor_stats( $user_id );
$active_count    = (int) ( $stats['active_orders'] ?? 0 );
$completed_count = (int) ( $stats['completed_orders'] ?? 0 );
$total_count     = (int) ( $stats['total_orders'] ?? 0 );
$total_revenue   = (float) ( $stats['total_earnings'] ?? 0 );
?>

<div class="wpss-section wpss-section--sales">
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
		<div class="wpss-stat-card wpss-stat-card--highlight">
			<span class="wpss-stat-card__value"><?php echo esc_html( wpss_format_price( $total_revenue ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Revenue', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php if ( empty( $orders ) ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/></svg>
			</div>
			<h3><?php esc_html_e( 'No sales yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'When someone orders your service, it will appear here.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( add_query_arg( 'section', 'services', get_permalink() ) ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'View My Services', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-orders-list">
			<?php foreach ( $orders as $order_item ) : ?>
				<?php
				$service       = $order_item->service_id ? get_post( $order_item->service_id ) : null;
				$customer      = get_userdata( $order_item->customer_id );
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
									/* translators: %s: customer name */
									esc_html__( 'Buyer: %s', 'wp-sell-services' ),
									esc_html( $customer ? $customer->display_name : __( 'Unknown', 'wp-sell-services' ) )
								);
								?>
								<span class="wpss-order-card__sep">&bull;</span>
								<?php echo esc_html( wpss_format_price( $order_item->total ) ); ?>
							</p>
						</div>
					</div>
					<div class="wpss-order-card__actions">
						<span class="wpss-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_labels[ $order_item->status ] ?? $order_item->status ); ?>
						</span>
						<a href="<?php echo esc_url( wpss_get_order_url( $order_item->id ) ); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm">
							<?php esc_html_e( 'Manage', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the sales dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('sales').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'sales', $user_id );
?>
