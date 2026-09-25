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
$order_id = function_exists( 'wpss_resolve_request_order_id' ) ? wpss_resolve_request_order_id() : 0;

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

		$order_action = function_exists( 'wpss_resolve_request_order_action' ) ? wpss_resolve_request_order_action() : '';

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
$sales_period = isset( $_GET['sales_period'] ) ? sanitize_key( wp_unslash( $_GET['sales_period'] ) ) : 'all';
$sales_page   = isset( $_GET['sales_page'] ) ? max( 1, absint( wp_unslash( $_GET['sales_page'] ) ) ) : 1;
$sales_group  = isset( $_GET['sales_status'] ) ? sanitize_key( wp_unslash( $_GET['sales_status'] ) ) : 'all';
$sales_search = isset( $_GET['sales_search'] ) ? sanitize_text_field( wp_unslash( $_GET['sales_search'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$valid_periods = array(
	'today'  => array(
		'label' => __( 'Today', 'wp-sell-services' ),
		'days'  => 0,
	),
	'30days' => array(
		'label' => __( 'Last 30 days', 'wp-sell-services' ),
		'days'  => 30,
	),
	'90days' => array(
		'label' => __( 'Last 90 days', 'wp-sell-services' ),
		'days'  => 90,
	),
	'1year'  => array(
		'label' => __( 'Last 12 months', 'wp-sell-services' ),
		'days'  => 365,
	),
	'all'    => array(
		'label' => __( 'All time', 'wp-sell-services' ),
		'days'  => 0,
	),
);
if ( ! isset( $valid_periods[ $sales_period ] ) ) {
	$sales_period = 'all';
}

$per_page  = 20;
$date_from = '';

/*
 * Site time, not UTC. Orders are written with current_time( 'mysql' ), so a
 * window built from gmdate()/time() is offset by the site's timezone on every
 * non-UTC install - the totals are then quietly wrong rather than obviously
 * missing. 'today' and 'all' both carry days = 0, so they are told apart by
 * the key, not by the number.
 */
$sales_today = current_time( 'Y-m-d' );

if ( 'today' === $sales_period ) {
	$date_from = $sales_today . ' 00:00:00';
} elseif ( $valid_periods[ $sales_period ]['days'] > 0 ) {
	$date_from = gmdate( 'Y-m-d', strtotime( $sales_today . ' -' . (int) $valid_periods[ $sales_period ]['days'] . ' days' ) ) . ' 00:00:00';
}

// Status chips + order-number search: the same filter the buyer list has,
// from the same status groups (F27). An unknown chip key widens to All.
$status_counts = $order_repo->count_by_vendor_grouped( $user_id );
$status_groups = wpss_get_order_filter_groups( $status_counts );
if ( ! isset( $status_groups[ $sales_group ] ) ) {
	$sales_group = 'all';
}

$filter_args = array(
	'date_from'  => $date_from,
	'status__in' => $status_groups[ $sales_group ]['statuses'],
	'search'     => $sales_search,
);

$orders          = $order_repo->get_by_vendor(
	$user_id,
	$filter_args + array(
		'limit'  => $per_page,
		'offset' => ( $sales_page - 1 ) * $per_page,
	)
);
$total_in_period = $order_repo->count_by_vendor( $user_id, $filter_args );
$total_pages     = max( 1, (int) ceil( $total_in_period / $per_page ) );
$sales_filtered  = 'all' !== $sales_period || 'all' !== $sales_group || '' !== $sales_search;

// Get order stats from vendor stats (lifetime totals — not affected by date filter).
$stats           = $order_repo->get_vendor_stats( $user_id );
$active_count    = (int) ( $stats['active_orders'] ?? 0 );
$completed_count = (int) ( $stats['completed_orders'] ?? 0 );
$total_count     = (int) ( $stats['total_orders'] ?? 0 );
$total_revenue   = (float) ( $stats['total_earnings'] ?? 0 );
?>

<div class="wpss-section wpss-section--sales wpss-card">
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
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Earnings (paid orders, after commission)', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php
	// VS10 — date-range filter (always visible if vendor has any lifetime orders).
	if ( $total_count > 0 ) :
		$filter_prefix = 'sales';
		$filter_group  = $sales_group;
		$filter_search = $sales_search;
		require WPSS_PLUGIN_DIR . 'templates/dashboard/partials/order-filters.php';
		?>
		<div class="wpss-sales-filter">
			<form method="get" class="wpss-sales-filter__form">
				<input type="hidden" name="section" value="sales">
				<?php if ( 'all' !== $sales_group ) : ?>
					<input type="hidden" name="sales_status" value="<?php echo esc_attr( $sales_group ); ?>">
				<?php endif; ?>
				<?php if ( '' !== $sales_search ) : ?>
					<input type="hidden" name="sales_search" value="<?php echo esc_attr( $sales_search ); ?>">
				<?php endif; ?>
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
				if ( $sales_filtered ) {
					esc_html_e( 'No sales match this filter', 'wp-sell-services' );
				} else {
					esc_html_e( 'No sales yet', 'wp-sell-services' );
				}
				?>
			</h3>
			<p>
				<?php
				if ( $sales_filtered ) {
					esc_html_e( 'Try a wider date range or clear the status and search filters.', 'wp-sell-services' );
				} else {
					esc_html_e( 'When someone orders your service, it will appear here.', 'wp-sell-services' );
				}
				?>
			</p>
			<?php if ( $sales_filtered ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'sales_period', 'sales_status', 'sales_search', 'sales_page' ) ) ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Show all orders', 'wp-sell-services' ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( wpss_append_dashboard_section( wpss_get_page_url( 'dashboard' ), 'services' ) ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'View My Services', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="wpss-orders-list">
			<?php wpss_get_credited_order_ids( wp_list_pluck( $orders, 'id' ) ); // One query for the page's revenue rows. ?>
			<?php
			// One query each for the page's services and people, not one per row.
			_prime_post_caches( array_filter( array_map( 'intval', wp_list_pluck( $orders, 'service_id' ) ) ), false, false );
			cache_users( array_map( 'intval', array_merge( wp_list_pluck( $orders, 'customer_id' ), wp_list_pluck( $orders, 'vendor_id' ) ) ) );
			$status_labels = wpss_get_order_status_labels();
			$wpss_side     = 'seller';
			foreach ( $orders as $order_item ) {
				require WPSS_PLUGIN_DIR . 'templates/dashboard/partials/order-row.php';
			}
			?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Sales pages', 'wp-sell-services' ); ?>">
				<?php
				// Built from the CURRENT request so the period, chip and search
				// all survive paging (same rule as the buyer list).
				$page_url = static function ( int $page ): string {
					return $page > 1 ? add_query_arg( 'sales_page', $page ) : remove_query_arg( 'sales_page' );
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
