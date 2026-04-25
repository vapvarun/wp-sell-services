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
		// Tip orders render a dedicated receipt view — vendors should see
		// "Tip received from X" rather than the full service-order UI.
		if ( \WPSellServices\Services\TippingService::ORDER_TYPE === ( $current_order->platform ?? '' ) ) {
			include WPSS_PLUGIN_DIR . 'templates/order/tip-view.php';
			return;
		}

		// Extension sub-orders: dedicated receipt/awaiting-payment view so
		// vendors see "Extension approved" (or awaiting payment) and buyers
		// see the accept/decline UI — not the service-delivery workflow.
		if ( \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE === ( $current_order->platform ?? '' ) ) {
			include WPSS_PLUGIN_DIR . 'templates/order/extension-view.php';
			return;
		}

		// Milestone sub-orders get the phase receipt view — shows
		// title/deliverables and the lifecycle-appropriate action for the
		// viewer (buyer accepts & pays / vendor submits / buyer approves).
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

// VS10 (plans/ORDER-FLOW-AUDIT.md): paginated sales list with date filter.
// Vendors couldn't see beyond their 20 most recent orders — now they page
// through history + scope to a date range.
$order_repo = new OrderRepository();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display filters.
$sales_period  = isset( $_GET['sales_period'] ) ? sanitize_key( wp_unslash( $_GET['sales_period'] ) ) : 'all';
$sales_page    = isset( $_GET['sales_page'] ) ? max( 1, absint( wp_unslash( $_GET['sales_page'] ) ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$valid_periods = array(
	'30days' => array(
		'label' => __( 'Last 30 days', 'wp-sell-services' ),
		'days'  => 30,
	),
	'90days' => array(
		'label' => __( 'Last 90 days', 'wp-sell-services' ),
		'days'  => 90,
	),
	'1year' => array(
		'label' => __( 'Last 12 months', 'wp-sell-services' ),
		'days'  => 365,
	),
	'all'   => array(
		'label' => __( 'All time', 'wp-sell-services' ),
		'days'  => 0,
	),
);
if ( ! isset( $valid_periods[ $sales_period ] ) ) {
	$sales_period = 'all';
}

$per_page  = 20;
$date_from = '';
if ( $valid_periods[ $sales_period ]['days'] > 0 ) {
	$date_from = gmdate( 'Y-m-d H:i:s', time() - ( $valid_periods[ $sales_period ]['days'] * DAY_IN_SECONDS ) );
}

$query_args = array(
	'limit'     => $per_page,
	'offset'    => ( $sales_page - 1 ) * $per_page,
	'date_from' => $date_from,
);

$orders            = $order_repo->get_by_vendor( $user_id, $query_args );
$total_in_period   = $order_repo->count_by_vendor(
	$user_id,
	array( 'date_from' => $date_from )
);
$total_pages       = max( 1, (int) ceil( $total_in_period / $per_page ) );

// Get order stats from vendor stats (lifetime totals — not affected by date filter).
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

	<?php
	// VS10 — date-range filter (always visible if vendor has any lifetime orders).
	if ( $total_count > 0 ) :
		$base_url = add_query_arg(
			array(
				'section' => 'sales',
			),
			get_permalink()
		);
		?>
		<div class="wpss-sales-filter">
			<form method="get" class="wpss-sales-filter__form">
				<input type="hidden" name="section" value="sales">
				<label for="wpss-sales-period" class="wpss-sales-filter__label">
					<?php esc_html_e( 'Show:', 'wp-sell-services' ); ?>
				</label>
				<select id="wpss-sales-period" name="sales_period" class="wpss-form-select" onchange="this.form.submit()">
					<?php foreach ( $valid_periods as $period_key => $period ) : ?>
						<option value="<?php echo esc_attr( $period_key ); ?>" <?php selected( $sales_period, $period_key ); ?>>
							<?php echo esc_html( $period['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<noscript>
					<button type="submit" class="wpss-btn wpss-btn--outline wpss-btn--sm">
						<?php esc_html_e( 'Apply', 'wp-sell-services' ); ?>
					</button>
				</noscript>
			</form>
			<p class="wpss-sales-filter__count">
				<?php
				printf(
					/* translators: 1: count, 2: period label */
					esc_html__( '%1$d orders in %2$s', 'wp-sell-services' ),
					(int) $total_in_period,
					esc_html( strtolower( $valid_periods[ $sales_period ]['label'] ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $orders ) ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<i data-lucide="banknote" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			</div>
			<h3>
				<?php
				if ( 'all' === $sales_period ) {
					esc_html_e( 'No sales yet', 'wp-sell-services' );
				} else {
					esc_html_e( 'No sales in this period', 'wp-sell-services' );
				}
				?>
			</h3>
			<p>
				<?php
				if ( 'all' === $sales_period ) {
					esc_html_e( 'When someone orders your service, it will appear here.', 'wp-sell-services' );
				} else {
					esc_html_e( 'Try a wider date range to see more orders.', 'wp-sell-services' );
				}
				?>
			</p>
			<?php if ( 'all' === $sales_period ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'section', 'services', get_permalink() ) ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'View My Services', 'wp-sell-services' ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'section' => 'sales', 'sales_period' => 'all' ), get_permalink() ) ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Show all orders', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
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
				$customer       = get_userdata( $order_item->customer_id );
				$status_class   = 'wpss-status--' . sanitize_html_class( $order_item->status );
				$status_labels  = wpss_get_order_status_labels();

				// For request-based orders, use the request title.
				if ( ! $service && 'request' === $order_platform && $order_item->platform_order_id ) {
					$request_post = get_post( $order_item->platform_order_id );
				}

				if ( $is_sub_order ) {
					// Tip / extension / milestone rows reference the parent
					// service order via platform_order_id; fall back gracefully
					// when the parent has been deleted.
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
						// Milestone: prefer the phase title from the meta JSON,
						// fall back to the parent title if the meta was dropped.
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
								<i data-lucide="heart" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
							</div>
						<?php elseif ( $is_extension ) : ?>
							<div class="wpss-order-card__tip-icon wpss-order-card__extension-icon" aria-hidden="true">
								<i data-lucide="clock" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
							</div>
						<?php elseif ( $is_milestone ) : ?>
							<div class="wpss-order-card__tip-icon wpss-order-card__milestone-icon" aria-hidden="true">
								<i data-lucide="flag" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
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
									/* translators: %s: customer name */
									esc_html__( 'Buyer: %s', 'wp-sell-services' ),
									esc_html( $customer ? $customer->display_name : __( 'Unknown', 'wp-sell-services' ) )
								);
								?>
								<span class="wpss-order-card__sep">&bull;</span>
								<?php
								// Vendor sees the NET take-home (post-commission) so the sum of
								// rows matches the Revenue stat above and the wallet balance.
								// Falls back to $total for legacy rows where vendor_earnings is
								// NULL (orders created before CommissionService populated it).
								$row_net_amount = isset( $order_item->vendor_earnings ) && null !== $order_item->vendor_earnings
									? (float) $order_item->vendor_earnings
									: (float) $order_item->total;
								$row_gross      = (float) $order_item->total;
								?>
								<span class="wpss-order-card__amount" title="<?php echo esc_attr( sprintf( /* translators: %s: gross amount the buyer paid */ __( 'Buyer paid %s (gross). You earn the net amount after platform fee.', 'wp-sell-services' ), wpss_format_price( $row_gross ) ) ); ?>">
									<?php echo esc_html( wpss_format_price( $row_net_amount ) ); ?>
									<?php if ( abs( $row_gross - $row_net_amount ) > 0.005 ) : ?>
										<small class="wpss-order-card__gross"><?php
										/* translators: %s: buyer-paid amount before platform fee */
										printf( esc_html__( '(buyer paid %s)', 'wp-sell-services' ), esc_html( wpss_format_price( $row_gross ) ) );
										?></small>
									<?php endif; ?>
								</span>
							</p>
						</div>
					</div>
					<div class="wpss-order-card__actions">
						<span class="wpss-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_labels[ $order_item->status ] ?? $order_item->status ); ?>
						</span>
						<a href="<?php echo esc_url( wpss_get_order_url( $order_item->id, 'sales' ) ); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm">
							<?php echo esc_html( $is_sub_order ? __( 'View', 'wp-sell-services' ) : __( 'Manage', 'wp-sell-services' ) ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Sales pages', 'wp-sell-services' ); ?>">
				<?php
				$page_base_args = array( 'section' => 'sales' );
				if ( 'all' !== $sales_period ) {
					$page_base_args['sales_period'] = $sales_period;
				}
				$page_url = static function ( int $page ) use ( $page_base_args ): string {
					$args = $page_base_args;
					if ( $page > 1 ) {
						$args['sales_page'] = $page;
					}
					return add_query_arg( $args, get_permalink() );
				};
				?>
				<?php if ( $sales_page > 1 ) : ?>
					<a href="<?php echo esc_url( $page_url( $sales_page - 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--prev">
						<i data-lucide="chevron-left" class="wpss-icon" aria-hidden="true"></i>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<span class="wpss-pagination__current">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'wp-sell-services' ),
						(int) $sales_page,
						(int) $total_pages
					);
					?>
				</span>
				<?php if ( $sales_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( $page_url( $sales_page + 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--next">
						<?php esc_html_e( 'Next', 'wp-sell-services' ); ?>
						<i data-lucide="chevron-right" class="wpss-icon" aria-hidden="true"></i>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
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
