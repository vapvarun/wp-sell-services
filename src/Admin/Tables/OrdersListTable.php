<?php
/**
 * Orders List Table
 *
 * @package WPSellServices\Admin\Tables
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Tables;

use WPSellServices\Models\ServiceOrder;

// Load WP_List_Table if not loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Orders list table for admin.
 *
 * @since 1.0.0
 */
class OrdersListTable extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'order',
				'plural'   => 'orders',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return [
			'cb'           => '<input type="checkbox" />',
			'order_number' => __( 'Order', 'wp-sell-services' ),
			'service'      => __( 'Service', 'wp-sell-services' ),
			'customer'     => __( 'Customer', 'wp-sell-services' ),
			'vendor'       => __( 'Vendor', 'wp-sell-services' ),
			'total'        => __( 'Total', 'wp-sell-services' ),
			'status'       => __( 'Status', 'wp-sell-services' ),
			'date'         => __( 'Date', 'wp-sell-services' ),
		];
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return [
			'order_number' => [ 'order_number', false ],
			'total'        => [ 'total', false ],
			'status'       => [ 'status', false ],
			'date'         => [ 'created_at', true ],
		];
	}

	/**
	 * Column default.
	 *
	 * @param object $item        Item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="order_ids[]" value="%d" />',
			$item->id
		);
	}

	/**
	 * Order number column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_order_number( $item ): string {
		$view_url = add_query_arg(
			[
				'page'     => 'wpss-orders',
				'action'   => 'view',
				'order_id' => $item->id,
			],
			admin_url( 'admin.php' )
		);

		$actions = [
			'view' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $view_url ),
				__( 'View', 'wp-sell-services' )
			),
		];

		return sprintf(
			'<strong><a href="%s">#%s</a></strong>%s',
			esc_url( $view_url ),
			esc_html( $item->order_number ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Service column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_service( $item ): string {
		$service = get_post( $item->service_id );

		if ( ! $service ) {
			return '<em>' . esc_html__( 'Deleted', 'wp-sell-services' ) . '</em>';
		}

		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( get_edit_post_link( $service->ID ) ),
			esc_html( $service->post_title )
		);
	}

	/**
	 * Customer column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_customer( $item ): string {
		$user = get_userdata( $item->customer_id );

		if ( ! $user ) {
			return '<em>' . esc_html__( 'Unknown', 'wp-sell-services' ) . '</em>';
		}

		return sprintf(
			'<a href="%s">%s</a><br><small>%s</small>',
			esc_url( get_edit_user_link( $user->ID ) ),
			esc_html( $user->display_name ),
			esc_html( $user->user_email )
		);
	}

	/**
	 * Vendor column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_vendor( $item ): string {
		$user = get_userdata( $item->vendor_id );

		if ( ! $user ) {
			return '<em>' . esc_html__( 'Unknown', 'wp-sell-services' ) . '</em>';
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_user_link( $user->ID ) ),
			esc_html( $user->display_name )
		);
	}

	/**
	 * Total column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_total( $item ): string {
		return esc_html( wpss_format_price( (float) $item->total, $item->currency ) );
	}

	/**
	 * Status column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_status( $item ): string {
		$statuses = ServiceOrder::get_statuses();
		$label    = $statuses[ $item->status ] ?? $item->status;

		$status_classes = [
			'pending_payment'      => 'wpss-status-pending',
			'pending_requirements' => 'wpss-status-pending',
			'in_progress'          => 'wpss-status-processing',
			'pending_approval'     => 'wpss-status-processing',
			'revision_requested'   => 'wpss-status-on-hold',
			'completed'            => 'wpss-status-completed',
			'cancelled'            => 'wpss-status-cancelled',
			'disputed'             => 'wpss-status-failed',
			'on_hold'              => 'wpss-status-on-hold',
			'late'                 => 'wpss-status-failed',
		];

		$class = $status_classes[ $item->status ] ?? 'wpss-status-pending';

		return sprintf(
			'<span class="wpss-status-badge %s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Date column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_date( $item ): string {
		$date = strtotime( $item->created_at );

		return sprintf(
			'<time datetime="%s" title="%s">%s</time>',
			esc_attr( gmdate( 'c', $date ) ),
			esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) ),
			esc_html( wp_date( 'M j, Y', $date ) )
		);
	}

	/**
	 * Get views (status filters).
	 *
	 * @return array
	 */
	protected function get_views(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Vendor filter for non-admin vendors.
		$vendor_where = '';
		if ( ! current_user_can( 'manage_options' ) && wpss_is_vendor() ) {
			$vendor_where = $wpdb->prepare( ' WHERE vendor_id = %d', get_current_user_id() );
		}

		// Get status counts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table}{$vendor_where} GROUP BY status",
			OBJECT_K
		);

		$total = array_sum( array_column( (array) $counts, 'count' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		$views = [
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( admin_url( 'admin.php?page=wpss-orders' ) ),
				empty( $current_status ) ? 'current' : '',
				__( 'All', 'wp-sell-services' ),
				$total
			),
		];

		$statuses = ServiceOrder::get_statuses();

		foreach ( $statuses as $status => $label ) {
			$count = isset( $counts[ $status ] ) ? (int) $counts[ $status ]->count : 0;

			if ( $count > 0 ) {
				$views[ $status ] = sprintf(
					'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
					esc_url( add_query_arg( 'status', $status, admin_url( 'admin.php?page=wpss-orders' ) ) ),
					$current_status === $status ? 'current' : '',
					$label,
					$count
				);
			}
		}

		return $views;
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions(): array {
		return [
			'mark_completed' => __( 'Mark as Completed', 'wp-sell-services' ),
			'mark_cancelled' => __( 'Mark as Cancelled', 'wp-sell-services' ),
		];
	}

	/**
	 * Extra table nav (filters).
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php
			// Date filter.
			$months = $this->get_order_months();

			if ( ! empty( $months ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$selected_month = isset( $_GET['m'] ) ? sanitize_text_field( $_GET['m'] ) : '';
				?>
				<select name="m">
					<option value=""><?php esc_html_e( 'All dates', 'wp-sell-services' ); ?></option>
					<?php foreach ( $months as $month ) : ?>
						<option value="<?php echo esc_attr( $month->month ); ?>" <?php selected( $selected_month, $month->month ); ?>>
							<?php echo esc_html( wp_date( 'F Y', strtotime( $month->month . '-01' ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
			}

			submit_button( __( 'Filter', 'wp-sell-services' ), '', 'filter_action', false );
			?>
		</div>
		<?php
	}

	/**
	 * Get order months for filter.
	 *
	 * @return array
	 */
	private function get_order_months(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Vendor filter for non-admin vendors.
		$vendor_where = '';
		if ( ! current_user_can( 'manage_options' ) && wpss_is_vendor() ) {
			$vendor_where = $wpdb->prepare( ' WHERE vendor_id = %d', get_current_user_id() );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			"SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month
			FROM {$table}{$vendor_where}
			ORDER BY month DESC
			LIMIT 24"
		);
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$per_page = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = $this->get_pagenum();

		// Build query.
		$where  = '1=1';
		$params = [];

		// Vendor filter — non-admin vendors only see their own orders.
		if ( ! current_user_can( 'manage_options' ) && wpss_is_vendor() ) {
			$where   .= ' AND vendor_id = %d';
			$params[] = get_current_user_id();
		}

		// Status filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['status'] ) ) {
			$where .= ' AND status = %s';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params[] = sanitize_key( $_GET['status'] );
		}

		// Month filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['m'] ) ) {
			$where .= ' AND DATE_FORMAT(created_at, "%%Y-%%m") = %s';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params[] = sanitize_text_field( $_GET['m'] );
		}

		// Search.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['s'] ) ) {
			$where .= ' AND (order_number LIKE %s OR id = %d)';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search = '%' . $wpdb->esc_like( sanitize_text_field( $_GET['s'] ) ) . '%';
			$params[] = $search;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params[] = absint( $_GET['s'] );
		}

		// Count total.
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_items = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		// Sorting.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( $_GET['orderby'] . ' ASC' ) : 'created_at';
		$orderby = $orderby ? explode( ' ', $orderby )[0] : 'created_at';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		$allowed_orderby = [ 'id', 'order_number', 'total', 'status', 'created_at' ];
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}

		// Get items.
		$offset = ( $current_page - 1 ) * $per_page;
		$query_params = array_merge( $params, [ $per_page, $offset ] );

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
					$query_params
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
					$per_page,
					$offset
				)
			);
		}

		// Set pagination.
		$this->set_pagination_args(
			[
				'total_items' => (int) $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( (int) $total_items / $per_page ),
			]
		);

		// Set column headers.
		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Display when no items.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No orders found.', 'wp-sell-services' );
	}
}
