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
		// Tip orders have no delivery workflow — render a dedicated receipt
		// view instead of the full service-order UI.
		if ( \WPSellServices\Services\TippingService::ORDER_TYPE === ( $current_order->platform ?? '' ) ) {
			include WPSS_PLUGIN_DIR . 'templates/order/tip-view.php';
			return;
		}

		// Extension sub-orders: same pattern — they are payment records on a
		// parent order, not a separate delivery, so buyers see the accept /
		// decline UI, not the requirements/delivery workflow.
		if ( \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE === ( $current_order->platform ?? '' ) ) {
			include WPSS_PLUGIN_DIR . 'templates/order/extension-view.php';
			return;
		}

		// Milestone sub-orders route to the phase receipt view; buyers see
		// Accept & Pay / Approve, vendors see Submit Delivery, depending on
		// the phase's current state.
		if ( \WPSellServices\Services\MilestoneService::ORDER_TYPE === ( $current_order->platform ?? '' ) ) {
			include WPSS_PLUGIN_DIR . 'templates/order/milestone-view.php';
			return;
		}

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
			<p><?php esc_html_e( 'Browse our marketplace to find the perfect service for your needs.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( wpss_get_page_url( 'services_page' ) ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Browse Services', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-orders-list">
			<?php foreach ( $orders as $order_item ) : ?>
				<?php
				$order_platform = $order_item->platform ?? '';
				$is_tip         = \WPSellServices\Services\TippingService::ORDER_TYPE === $order_platform;
				$is_extension   = \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE === $order_platform;
				$is_milestone   = \WPSellServices\Services\MilestoneService::ORDER_TYPE === $order_platform;
				$is_sub_order   = $is_tip || $is_extension || $is_milestone;
				$service        = $order_item->service_id ? get_post( $order_item->service_id ) : null;
				$vendor         = get_userdata( $order_item->vendor_id );
				$status_class   = 'wpss-status--' . sanitize_html_class( $order_item->status );
				$status_labels  = wpss_get_order_status_labels();

				// For request-based orders, use the request title.
				if ( ! $service && 'request' === $order_platform && $order_item->platform_order_id ) {
					$request_post = get_post( $order_item->platform_order_id );
				}

				if ( $is_sub_order ) {
					// Sub-orders label relative to the parent service the buyer
					// actually ordered.
					$parent_order = $order_item->platform_order_id ? wpss_get_order( (int) $order_item->platform_order_id ) : null;
					$parent_title = '';
					if ( $parent_order ) {
						$parent_service = $parent_order->service_id ? get_post( $parent_order->service_id ) : null;
						$parent_title   = $parent_service ? $parent_service->post_title : $parent_order->order_number;
					}
					if ( $is_tip ) {
						$order_title = $parent_title
							? sprintf( /* translators: %s: original service / order title */ __( 'Tip for %s', 'wp-sell-services' ), $parent_title )
							: __( 'Tip', 'wp-sell-services' );
					} elseif ( $is_extension ) {
						$order_title = $parent_title
							? sprintf( /* translators: %s: original service / order title */ __( 'Extension for %s', 'wp-sell-services' ), $parent_title )
							: __( 'Extension', 'wp-sell-services' );
					} else {
						$ms_meta        = is_string( $order_item->meta ?? '' ) && '' !== $order_item->meta ? json_decode( $order_item->meta, true ) : array();
						$ms_phase_title = is_array( $ms_meta ) && ! empty( $ms_meta['title'] ) ? (string) $ms_meta['title'] : '';
						if ( '' !== $ms_phase_title && '' !== $parent_title ) {
							$order_title = sprintf( /* translators: 1: milestone phase title, 2: parent service title */ __( 'Milestone: %1$s (for %2$s)', 'wp-sell-services' ), $ms_phase_title, $parent_title );
						} elseif ( '' !== $ms_phase_title ) {
							$order_title = sprintf( /* translators: %s: milestone phase title */ __( 'Milestone: %s', 'wp-sell-services' ), $ms_phase_title );
						} elseif ( '' !== $parent_title ) {
							$order_title = sprintf( /* translators: %s: parent service title */ __( 'Milestone for %s', 'wp-sell-services' ), $parent_title );
						} else {
							$order_title = __( 'Milestone', 'wp-sell-services' );
						}
					}
				} else {
					$order_title = $service ? $service->post_title : ( ! empty( $request_post ) ? $request_post->post_title : __( 'Deleted Service', 'wp-sell-services' ) );
				}
				?>
				<div class="wpss-order-card<?php echo $is_tip ? ' wpss-order-card--tip' : ''; ?><?php echo $is_extension ? ' wpss-order-card--extension' : ''; ?><?php echo $is_milestone ? ' wpss-order-card--milestone' : ''; ?>">
					<div class="wpss-order-card__main">
						<?php if ( ! $is_sub_order && $service && has_post_thumbnail( $service ) ) : ?>
							<div class="wpss-order-card__image">
								<?php echo get_the_post_thumbnail( $service, 'thumbnail' ); ?>
							</div>
						<?php elseif ( $is_tip ) : ?>
							<div class="wpss-order-card__tip-icon" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
								</svg>
							</div>
						<?php elseif ( $is_extension ) : ?>
							<div class="wpss-order-card__tip-icon wpss-order-card__extension-icon" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<circle cx="12" cy="12" r="10"/>
									<polyline points="12 6 12 12 16 14"/>
								</svg>
							</div>
						<?php elseif ( $is_milestone ) : ?>
							<div class="wpss-order-card__tip-icon wpss-order-card__milestone-icon" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M4 4v16"/>
									<path d="M4 4h12l-2 4 2 4H4"/>
								</svg>
							</div>
						<?php endif; ?>
						<div class="wpss-order-card__info">
							<h4 class="wpss-order-card__title">
								<?php if ( $is_tip ) : ?>
									<span class="wpss-badge wpss-badge--tip"><?php esc_html_e( 'Tip', 'wp-sell-services' ); ?></span>
									<?php echo esc_html( $order_title ); ?>
								<?php elseif ( $is_extension ) : ?>
									<span class="wpss-badge wpss-badge--extension"><?php esc_html_e( 'Extension', 'wp-sell-services' ); ?></span>
									<?php echo esc_html( $order_title ); ?>
								<?php elseif ( $is_milestone ) : ?>
									<span class="wpss-badge wpss-badge--milestone"><?php esc_html_e( 'Milestone', 'wp-sell-services' ); ?></span>
									<?php echo esc_html( $order_title ); ?>
								<?php elseif ( $service ) : ?>
									<a href="<?php echo esc_url( get_permalink( $service ) ); ?>">
										<?php echo esc_html( $order_title ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $order_title ); ?>
								<?php endif; ?>
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
