<?php
/**
 * Template: Dashboard Orders Tab
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$user_id   = get_current_user_id();
$is_vendor = wpss_is_vendor( $user_id );

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
$role     = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable

// If viewing single order.
if ( $order_id ) {
	if ( ! wpss_user_can_view_order( $order_id, $user_id ) ) {
		echo '<div class="wpss-notice wpss-notice-error">' . esc_html__( 'You do not have permission to view this order.', 'wp-sell-services' ) . '</div>';
		return;
	}

	wpss_get_template( 'order/order-view.php', [ 'order_id' => $order_id ] );
	return;
}

global $wpdb;
$orders_table = $wpdb->prefix . 'wpss_orders';
$per_page = 10;

// Build query.
$where  = [];
$values = [];

if ( $is_vendor && 'vendor' === $role ) {
	$where[]  = 'vendor_id = %d';
	$values[] = $user_id;
} elseif ( 'customer' === $role ) {
	$where[]  = 'customer_id = %d';
	$values[] = $user_id;
} else {
	$where[]  = '(customer_id = %d OR vendor_id = %d)';
	$values[] = $user_id;
	$values[] = $user_id;
}

if ( $status ) {
	$where[]  = 'status = %s';
	$values[] = $status;
}

$where_sql = implode( ' AND ', $where );

// Count total.
$count_query = "SELECT COUNT(*) FROM {$orders_table} WHERE {$where_sql}";
$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$total_pages = ceil( $total / $per_page );
$offset = ( $page - 1 ) * $per_page;

// Get orders.
$query = "SELECT * FROM {$orders_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
$orders = $wpdb->get_results( $wpdb->prepare( $query, ...array_merge( $values, [ $per_page, $offset ] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Status counts.
$status_counts = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT status, COUNT(*) as count FROM {$orders_table}
		WHERE customer_id = %d OR vendor_id = %d
		GROUP BY status",
		$user_id,
		$user_id
	),
	OBJECT_K
);
?>

<div class="wpss-dashboard-orders">
	<div class="wpss-page-header">
		<h2><?php esc_html_e( 'Orders', 'wp-sell-services' ); ?></h2>

		<?php if ( $is_vendor ) : ?>
			<div class="wpss-role-toggle">
				<a href="<?php echo esc_url( add_query_arg( 'role', '', wpss_get_dashboard_url( 'orders' ) ) ); ?>"
				   class="<?php echo empty( $role ) ? 'active' : ''; ?>">
					<?php esc_html_e( 'All', 'wp-sell-services' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'role', 'vendor', wpss_get_dashboard_url( 'orders' ) ) ); ?>"
				   class="<?php echo 'vendor' === $role ? 'active' : ''; ?>">
					<?php esc_html_e( 'As Seller', 'wp-sell-services' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'role', 'customer', wpss_get_dashboard_url( 'orders' ) ) ); ?>"
				   class="<?php echo 'customer' === $role ? 'active' : ''; ?>">
					<?php esc_html_e( 'As Buyer', 'wp-sell-services' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<div class="wpss-status-filters">
		<a href="<?php echo esc_url( remove_query_arg( 'status', wpss_get_dashboard_url( 'orders' ) ) ); ?>"
		   class="<?php echo empty( $status ) ? 'active' : ''; ?>">
			<?php esc_html_e( 'All', 'wp-sell-services' ); ?>
			<span class="count"><?php echo esc_html( $total ); ?></span>
		</a>
		<?php
		$filter_statuses = [ 'pending', 'in_progress', 'delivered', 'completed', 'cancelled' ];
		foreach ( $filter_statuses as $filter_status ) :
			$count = isset( $status_counts[ $filter_status ] ) ? $status_counts[ $filter_status ]->count : 0;
			if ( $count > 0 ) :
				?>
				<a href="<?php echo esc_url( add_query_arg( 'status', $filter_status, wpss_get_dashboard_url( 'orders' ) ) ); ?>"
				   class="<?php echo $status === $filter_status ? 'active' : ''; ?>">
					<?php echo esc_html( wpss_get_order_status_label( $filter_status ) ); ?>
					<span class="count"><?php echo esc_html( $count ); ?></span>
				</a>
				<?php
			endif;
		endforeach;
		?>
	</div>

	<?php if ( ! empty( $orders ) ) : ?>
		<div class="wpss-orders-list">
			<?php foreach ( $orders as $order ) : ?>
				<?php
				$service    = get_post( $order->service_id );
				$vendor     = get_userdata( $order->vendor_id );
				$customer   = get_userdata( $order->customer_id );
				$is_selling = (int) $order->vendor_id === $user_id;
				$other_party = $is_selling ? $customer : $vendor;
				?>
				<div class="wpss-order-card">
					<div class="wpss-order-header">
						<div class="wpss-order-info">
							<span class="wpss-order-number"><?php echo esc_html( $order->order_number ); ?></span>
							<span class="wpss-order-date"><?php echo esc_html( wpss_time_ago( $order->created_at ) ); ?></span>
						</div>
						<span class="wpss-status wpss-status-<?php echo esc_attr( $order->status ); ?>">
							<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
						</span>
					</div>

					<div class="wpss-order-body">
						<div class="wpss-order-service">
							<?php if ( $service && has_post_thumbnail( $service->ID ) ) : ?>
								<img src="<?php echo esc_url( get_the_post_thumbnail_url( $service->ID, 'thumbnail' ) ); ?>"
									 alt="<?php echo esc_attr( $service->post_title ); ?>"
									 class="wpss-order-thumb">
							<?php endif; ?>
							<div class="wpss-order-details">
								<h4><?php echo esc_html( $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ) ); ?></h4>
								<p>
									<?php if ( $is_selling ) : ?>
										<?php esc_html_e( 'Buyer:', 'wp-sell-services' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Seller:', 'wp-sell-services' ); ?>
									<?php endif; ?>
									<strong><?php echo esc_html( $other_party ? $other_party->display_name : '' ); ?></strong>
								</p>
							</div>
						</div>

						<div class="wpss-order-meta">
							<span class="wpss-order-amount">
								<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
							</span>
							<?php if ( $order->due_date ) : ?>
								<span class="wpss-order-due">
									<?php
									$due_timestamp = strtotime( $order->due_date );
									$is_overdue = $due_timestamp < current_time( 'timestamp' ) && ! in_array( $order->status, [ 'completed', 'cancelled', 'refunded' ], true );
									?>
									<span class="<?php echo $is_overdue ? 'overdue' : ''; ?>">
										<?php
										printf(
											/* translators: %s: due date */
											esc_html__( 'Due: %s', 'wp-sell-services' ),
											esc_html( wp_date( get_option( 'date_format' ), $due_timestamp ) )
										);
										?>
									</span>
								</span>
							<?php endif; ?>
						</div>
					</div>

					<div class="wpss-order-actions">
						<a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) . '&order=' . $order->id ); ?>"
						   class="wpss-btn wpss-btn-primary">
							<?php esc_html_e( 'View Order', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="wpss-pagination">
				<?php
				echo paginate_links(
					[
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $page,
						'total'     => $total_pages,
						'prev_text' => '&laquo; ' . __( 'Previous', 'wp-sell-services' ),
						'next_text' => __( 'Next', 'wp-sell-services' ) . ' &raquo;',
					]
				);
				?>
			</nav>
		<?php endif; ?>

	<?php else : ?>
		<div class="wpss-empty-state">
			<span class="wpss-empty-icon"></span>
			<h3><?php esc_html_e( 'No orders found', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'When you place or receive orders, they will appear here.', 'wp-sell-services' ); ?></p>
			<?php if ( ! $is_vendor ) : ?>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'wpss_service' ) ); ?>" class="wpss-btn wpss-btn-primary">
					<?php esc_html_e( 'Browse Services', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
