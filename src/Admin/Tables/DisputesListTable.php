<?php
/**
 * Disputes List Table
 *
 * @package WPSellServices\Admin\Tables
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Admin\Tables;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\Dispute;

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
	 * What an owner triages a dispute by: the money, the two parties, how long
	 * it has been open and who has to act next (Basecamp 10337171525).
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'      => '<input type="checkbox" />',
			'id'      => __( 'Dispute', 'wp-sell-services' ),
			'amount'  => __( 'Amount', 'wp-sell-services' ),
			'parties' => __( 'Buyer / Vendor', 'wp-sell-services' ),
			'reason'  => __( 'Reason', 'wp-sell-services' ),
			'status'  => __( 'Status', 'wp-sell-services' ),
			'age'     => __( 'Open for', 'wp-sell-services' ),
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
			'amount' => array( 'amount', false ),
			'status' => array( 'status', false ),
			// Longest open first on the first click.
			'age'    => array( 'age', true ),
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

		// On a phone core shows only this column, so the status and amount
		// ride along (hidden above 782px by admin.css). The order sits under
		// the number at every width.
		$mobile = sprintf(
			'<span class="wpss-order-row-mobile">%s %s</span>',
			$this->column_status( $item ),
			$this->column_amount( $item )
		);

		return sprintf(
			'<strong><a href="%s">#%d</a></strong><small class="wpss-order-row-sub">%s</small>%s%s',
			esc_url( $view_url ),
			$item->id,
			/* translators: %s: order number link. */
			sprintf( esc_html__( 'Order %s', 'wp-sell-services' ), $this->column_order( $item ) ),
			$mobile,
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
		if ( empty( $item->order_number ) ) {
			return '<em>' . esc_html__( 'Deleted', 'wp-sell-services' ) . '</em>';
		}

		return sprintf(
			'<a href="%s">#%s</a>',
			esc_url( admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . $item->order_id ) ),
			esc_html( $item->order_number )
		);
	}

	/**
	 * Amount column: what the order is worth, and what went back if ruled.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_amount( $item ): string {
		if ( null === $item->order_total ) {
			return '&mdash;';
		}

		return esc_html( wpss_format_price( (float) $item->order_total, (string) $item->currency ) );
	}

	/**
	 * Buyer and vendor, with who opened it.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_parties( $item ): string {
		$line = static function ( int $user_id, bool $opened ): string {
			return sprintf(
				'<a href="%s">%s</a>%s',
				esc_url( get_edit_user_link( $user_id ) ),
				esc_html( wpss_get_member_display_name( $user_id ) ),
				$opened ? ' <small class="wpss-order-row-sub" style="display:inline">(' . esc_html__( 'opened it', 'wp-sell-services' ) . ')</small>' : ''
			);
		};

		if ( empty( $item->customer_id ) ) {
			return '&mdash;';
		}

		return $line( (int) $item->customer_id, (int) $item->initiated_by === (int) $item->customer_id )
			. '<br>'
			. $line( (int) $item->vendor_id, (int) $item->initiated_by === (int) $item->vendor_id );
	}

	/**
	 * Reason column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_reason( $item ): string {
		$reasons = wpss_get_dispute_reasons();

		return esc_html( $reasons[ $item->reason ] ?? ucwords( str_replace( '_', ' ', $item->reason ) ) );
	}

	/**
	 * Status column, with who has to act next.
	 *
	 * The next move is read from the conversation, not last_response_by: a
	 * message posted from the dispute thread never wrote that column, so it
	 * named the wrong party.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_status( $item ): string {
		$statuses = Dispute::get_statuses();
		$label    = $statuses[ $item->status ] ?? ucwords( str_replace( '_', ' ', $item->status ) );

		$status_classes = array(
			'open'           => 'wpss-status-on-hold',
			'pending_review' => 'wpss-status-processing',
			'resolved'       => 'wpss-status-completed',
			'escalated'      => 'wpss-status-on-hold',
			'closed'         => 'wpss-status-cancelled',
		);

		$html = sprintf(
			'<span class="wpss-status-badge %s">%s</span>',
			esc_attr( $status_classes[ $item->status ] ?? 'wpss-status-pending' ),
			esc_html( $label )
		);

		$waiting = $this->waiting_on( $item );
		if ( '' !== $waiting ) {
			$html .= '<small class="wpss-order-row-sub">' . esc_html( $waiting ) . '</small>';
		}

		return $html;
	}

	/**
	 * Who the dispute is waiting on, in words; '' once it is settled.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	private function waiting_on( object $item ): string {
		if ( in_array( $item->status, array( 'resolved', 'closed' ), true ) ) {
			return '';
		}

		if ( 'escalated' === $item->status ) {
			return __( 'Waiting on you', 'wp-sell-services' );
		}

		$last = (int) ( $item->last_sender ?: $item->initiated_by );

		if ( $last === (int) $item->customer_id ) {
			return __( 'Waiting on the vendor', 'wp-sell-services' );
		}

		if ( $last === (int) $item->vendor_id ) {
			return __( 'Waiting on the buyer', 'wp-sell-services' );
		}

		// The admin spoke last.
		return __( 'Waiting on both parties', 'wp-sell-services' );
	}

	/**
	 * How long the dispute has been (or was) open.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_age( $item ): string {
		$opened = strtotime( (string) $item->created_at );
		$until  = ! empty( $item->resolved_at ) ? strtotime( (string) $item->resolved_at ) : strtotime( current_time( 'mysql' ) );
		$days   = max( 0, (int) floor( ( $until - $opened ) / DAY_IN_SECONDS ) );

		return sprintf(
			'<time datetime="%s" title="%s">%s</time>',
			esc_attr( gmdate( 'c', $opened ) ),
			/* translators: %s: date the dispute was opened. */
			esc_attr( sprintf( __( 'Opened %s', 'wp-sell-services' ), wp_date( get_option( 'date_format' ), $opened ) ) ),
			/* translators: %s: number of days. */
			esc_html( sprintf( _n( '%s day', '%s days', $days, 'wp-sell-services' ), number_format_i18n( $days ) ) )
		);
	}

	/**
	 * Reason filter.
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$selected = isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : '';
		?>
		<div class="alignleft actions">
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
			if ( ! empty( $_GET['status'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				printf( '<input type="hidden" name="status" value="%s">', esc_attr( sanitize_key( wp_unslash( $_GET['status'] ) ) ) );
			}
			?>
			<label for="wpss-disputes-reason-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by reason', 'wp-sell-services' ); ?></label>
			<select name="reason" id="wpss-disputes-reason-filter">
				<option value=""><?php esc_html_e( 'All reasons', 'wp-sell-services' ); ?></option>
				<?php foreach ( wpss_get_dispute_reasons() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'wp-sell-services' ), '', 'filter_action', false ); ?>
		</div>
		<?php
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

		foreach ( Dispute::get_statuses() as $status => $label ) {
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

		// Build query. One JOIN brings the order's number, value and parties
		// (the Order column used to run a query per row), and the latest
		// message's sender tells who has to act next.
		$where  = '1=1';
		$params = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['status'] ) ) {
			$where .= ' AND d.status = %s';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params[] = sanitize_key( $_GET['status'] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['reason'] ) ) {
			$where .= ' AND d.reason = %s';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params[] = sanitize_key( wp_unslash( $_GET['reason'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['s'] ) ) {
			$where .= ' AND (d.id = %d OR d.reason LIKE %s OR o.order_number LIKE %s)';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params[] = absint( $_GET['s'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%';
			$params[] = $search;
			$params[] = $search;
		}

		$orders   = $wpdb->prefix . 'wpss_orders';
		$messages = $wpdb->prefix . 'wpss_dispute_messages';
		$from     = "{$table} d LEFT JOIN {$orders} o ON o.id = d.order_id";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $from/$where are built from fixed fragments with placeholders.
		$total_items = $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$from} WHERE {$where}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$from}" );

		// Sorting, whitelisted.
		$orderby_map = array(
			'id'         => 'd.id',
			'status'     => 'd.status',
			'amount'     => 'o.total',
			'age'        => 'd.created_at',
			'created_at' => 'd.created_at',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby_key = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$orderby     = $orderby_map[ $orderby_key ] ?? 'd.created_at';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';
		if ( 'age' === $orderby_key ) {
			// Oldest first is "longest open": age ascending is created_at descending.
			$order = 'ASC' === $order ? 'DESC' : 'ASC';
		}

		$offset = ( $current_page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed fragments, values bound.
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, o.order_number, o.total AS order_total, o.currency, o.customer_id, o.vendor_id,
					( SELECT m.sender_id FROM {$messages} m WHERE m.dispute_id = d.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1 ) AS last_sender
				FROM {$from} WHERE {$where} ORDER BY {$orderby} {$order}, d.id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $params, array( $per_page, $offset ) )
			)
		);

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		if ( array_filter( array_intersect_key( $_GET, array_flip( array( 'status', 'reason', 's' ) ) ) ) ) {
			printf(
				'%s <a href="%s">%s</a>',
				esc_html__( 'No disputes match these filters.', 'wp-sell-services' ),
				esc_url( admin_url( 'admin.php?page=wpss-disputes' ) ),
				esc_html__( 'Show all disputes', 'wp-sell-services' )
			);
			return;
		}
		?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<?php echo \WPSellServices\Services\Icon::render( 'shield-alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<h2 class="wpss-empty-state__title"><?php esc_html_e( 'No disputes', 'wp-sell-services' ); ?></h2>
			<p class="wpss-empty-state__body"><?php esc_html_e( "If a buyer and vendor can't resolve an issue, a dispute opens here for admin mediation.", 'wp-sell-services' ); ?></p>
			<p class="wpss-empty-state__actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings#orders' ) ); ?>" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Dispute settings', 'wp-sell-services' ); ?></a>
				<a href="https://wbcomdesigns.com/docs/wp-sell-services/dispute-admin-mediation-wpss" class="wpss-empty-state__learn" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'wp-sell-services' ); ?></a>
			</p>
		</div>
		<?php
	}
}
