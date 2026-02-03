<?php
/**
 * Disputes List Table
 *
 * @package WPSellServices\Admin\Tables
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Tables;

// Load WP_List_Table if not loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Disputes list table for admin.
 *
 * @since 1.0.0
 */
class DisputesListTable extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'dispute',
				'plural'   => 'disputes',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'        => '<input type="checkbox" />',
			'id'        => __( 'ID', 'wp-sell-services' ),
			'order'     => __( 'Order', 'wp-sell-services' ),
			'opened_by' => __( 'Opened By', 'wp-sell-services' ),
			'reason'    => __( 'Reason', 'wp-sell-services' ),
			'status'    => __( 'Status', 'wp-sell-services' ),
			'date'      => __( 'Date', 'wp-sell-services' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array(
			'id'     => array( 'id', false ),
			'status' => array( 'status', false ),
			'date'   => array( 'created_at', true ),
		);
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
			'<input type="checkbox" name="dispute_ids[]" value="%d" />',
			$item->id
		);
	}

	/**
	 * ID column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_id( $item ): string {
		$view_url = add_query_arg(
			array(
				'page'       => 'wpss-disputes',
				'action'     => 'view',
				'dispute_id' => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$actions = array(
			'view' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $view_url ),
				__( 'View', 'wp-sell-services' )
			),
		);

		return sprintf(
			'<strong><a href="%s">#%d</a></strong>%s',
			esc_url( $view_url ),
			$item->id,
			$this->row_actions( $actions )
		);
	}

	/**
	 * Order column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_order( $item ): string {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT order_number FROM {$orders_table} WHERE id = %d",
				$item->order_id
			)
		);

		if ( ! $order ) {
			return '<em>' . esc_html__( 'Deleted', 'wp-sell-services' ) . '</em>';
		}

		return sprintf(
			'<a href="%s">#%s</a>',
			esc_url( admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . $item->order_id ) ),
			esc_html( $order->order_number )
		);
	}

	/**
	 * Opened by column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_opened_by( $item ): string {
		$user = get_userdata( $item->initiated_by );

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
	 * Reason column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_reason( $item ): string {
		$reasons = array(
			'quality'       => __( 'Quality Issues', 'wp-sell-services' ),
			'delivery'      => __( 'Late Delivery', 'wp-sell-services' ),
			'communication' => __( 'Communication Issues', 'wp-sell-services' ),
			'not_delivered' => __( 'Not Delivered', 'wp-sell-services' ),
			'other'         => __( 'Other', 'wp-sell-services' ),
		);

		return esc_html( $reasons[ $item->reason ] ?? $item->reason );
	}

	/**
	 * Status column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_status( $item ): string {
		// Use statuses from DisputeService.
		$statuses = array(
			'open'           => __( 'Open', 'wp-sell-services' ),
			'pending_review' => __( 'Pending Review', 'wp-sell-services' ),
			'resolved'       => __( 'Resolved', 'wp-sell-services' ),
			'escalated'      => __( 'Escalated', 'wp-sell-services' ),
			'closed'         => __( 'Closed', 'wp-sell-services' ),
		);

		$label = $statuses[ $item->status ] ?? ucwords( str_replace( '_', ' ', $item->status ) );

		$status_classes = array(
			'open'           => 'wpss-status-on-hold',
			'pending_review' => 'wpss-status-processing',
			'resolved'       => 'wpss-status-completed',
			'escalated'      => 'wpss-status-on-hold',
			'closed'         => 'wpss-status-cancelled',
		);

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
		$table = $wpdb->prefix . 'wpss_disputes';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( ! $table_exists ) {
			return array( 'all' => sprintf( '<a href="%s" class="current">%s <span class="count">(0)</span></a>', esc_url( admin_url( 'admin.php?page=wpss-disputes' ) ), __( 'All', 'wp-sell-services' ) ) );
		}

		// Get status counts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
			OBJECT_K
		);

		$total = array_sum( array_column( (array) $counts, 'count' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		$views = array(
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( admin_url( 'admin.php?page=wpss-disputes' ) ),
				empty( $current_status ) ? 'current' : '',
				__( 'All', 'wp-sell-services' ),
				$total
			),
		);

		// Use statuses from DisputeService.
		$statuses = array(
			'open'           => __( 'Open', 'wp-sell-services' ),
			'pending_review' => __( 'Pending Review', 'wp-sell-services' ),
			'resolved'       => __( 'Resolved', 'wp-sell-services' ),
			'escalated'      => __( 'Escalated', 'wp-sell-services' ),
			'closed'         => __( 'Closed', 'wp-sell-services' ),
		);

		foreach ( $statuses as $status => $label ) {
			$count = isset( $counts[ $status ] ) ? (int) $counts[ $status ]->count : 0;

			if ( $count > 0 ) {
				$views[ $status ] = sprintf(
					'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
					esc_url( add_query_arg( 'status', $status, admin_url( 'admin.php?page=wpss-disputes' ) ) ),
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
		return array(
			'mark_pending_review' => __( 'Mark Pending Review', 'wp-sell-services' ),
			'mark_escalated'      => __( 'Escalate', 'wp-sell-services' ),
			'mark_closed'         => __( 'Close', 'wp-sell-services' ),
		);
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_disputes';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( ! $table_exists ) {
			$this->items = array();
			$this->set_pagination_args(
				array(
					'total_items' => 0,
					'per_page'    => 20,
					'total_pages' => 0,
				)
			);
			$this->_column_headers = array(
				$this->get_columns(),
				array(),
				$this->get_sortable_columns(),
			);
			return;
		}

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Build query.
		$where  = '1=1';
		$params = array();

		// Status filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['status'] ) ) {
			$where .= ' AND status = %s';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params[] = sanitize_key( $_GET['status'] );
		}

		// Search.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['s'] ) ) {
			$where .= ' AND (id = %d OR reason LIKE %s)';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params[] = absint( $_GET['s'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $_GET['s'] ) ) . '%';
			$params[] = $search;
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

		$allowed_orderby = array( 'id', 'status', 'created_at' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}

		// Get items.
		$offset       = ( $current_page - 1 ) * $per_page;
		$query_params = array_merge( $params, array( $per_page, $offset ) );

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
			array(
				'total_items' => (int) $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( (int) $total_items / $per_page ),
			)
		);

		// Set column headers.
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Display when no items.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No disputes found.', 'wp-sell-services' );
	}
}
