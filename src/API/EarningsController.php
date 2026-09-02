<?php
/**
 * Earnings REST Controller
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for vendor earnings and withdrawals.
 *
 * @since 1.0.0
 */
class EarningsController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'earnings';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /earnings/summary - Get vendor earnings summary.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
				),
			)
		);

		// GET /earnings/history - Get earnings transaction history.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/history',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_history' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'maximum' => 100,
						),
						'period'   => array(
							'description' => __( 'Group by period.', 'wp-sell-services' ),
							'type'        => 'string',
							'enum'        => array_keys( \WPSellServices\Services\EarningsService::get_periods() ),
						),
					),
				),
			)
		);

		// GET /wallet/transactions - Get own wallet transaction ledger.
		register_rest_route(
			$this->namespace,
			'/wallet/transactions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wallet_transactions' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'type'     => array(
							'description'       => __( 'Filter by transaction type.', 'wp-sell-services' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		// POST /withdrawals - Request withdrawal.
		register_rest_route(
			$this->namespace,
			'/withdrawals',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'request_withdrawal' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'amount'  => array(
							'description' => __( 'Withdrawal amount.', 'wp-sell-services' ),
							'type'        => 'number',
							'required'    => true,
						),
						'method'  => array(
							'description' => __( 'Withdrawal method.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'details' => array(
							'description'       => __( 'Payment details.', 'wp-sell-services' ),
							'type'              => 'object',
							'sanitize_callback' => function ( $details ) {
								return map_deep( (array) $details, 'sanitize_text_field' );
							},
						),
					),
				),
			)
		);

		// GET /withdrawals - Get withdrawal history.
		register_rest_route(
			$this->namespace,
			'/withdrawals',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_withdrawals' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'maximum' => 100,
						),
						'status'   => array(
							'description' => __( 'Filter by status.', 'wp-sell-services' ),
							'type'        => 'string',
							'enum'        => array_keys( \WPSellServices\Services\EarningsService::get_withdrawal_statuses() ),
						),
					),
				),
			)
		);

		// PUT /withdrawals/{id} - Process withdrawal (admin).
		register_rest_route(
			$this->namespace,
			'/withdrawals/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'process_withdrawal' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'status' => array(
							'description' => __( 'New status.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
							'enum'        => \WPSellServices\Services\EarningsService::get_processable_withdrawal_statuses(),
						),
						'note'   => array(
							'description' => __( 'Admin note.', 'wp-sell-services' ),
							'type'        => 'string',
						),
					),
				),
			)
		);

		// GET /withdrawals/methods - Get withdrawal methods.
		register_rest_route(
			$this->namespace,
			'/withdrawals/methods',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_withdrawal_methods' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
				),
			)
		);
	}

	/**
	 * Get earnings summary.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_summary( WP_REST_Request $request ): WP_REST_Response {
		// Delegate to EarningsService — it is the ONE place the balance is
		// computed. This endpoint used to run its own three queries against
		// wpss_orders and wpss_withdrawals, and disagreed with the dashboard on
		// two counts: it ignored the clearance window entirely (reporting money
		// as withdrawable days before it was), and it counted 'approved'
		// withdrawals as already withdrawn rather than as reserved.
		$summary = ( new \WPSellServices\Services\EarningsService() )->get_summary( get_current_user_id() );

		$currency = wpss_get_currency();

		// Floats stay exactly as they were - changing them would break every
		// existing consumer to fix a problem only some clients have. The
		// *_minor integers are added alongside for clients that do arithmetic
		// rather than just print: a withdrawal form has to answer "is this
		// amount within the balance", and in float 63 - 37.8 is
		// 25.200000000000003, which fails a >= check against 25.2.
		return new WP_REST_Response(
			array(
				'total_earned'             => round( $summary['total_earned'], 2 ),
				'total_withdrawn'          => round( $summary['withdrawn'], 2 ),
				'pending_withdrawal'       => round( $summary['pending_withdrawal'], 2 ),
				'available_balance'        => round( $summary['available_balance'], 2 ),
				'in_clearance'             => round( $summary['in_clearance'], 2 ),
				'total_earned_minor'       => wpss_amount_to_minor_units( (float) $summary['total_earned'], $currency ),
				'total_withdrawn_minor'    => wpss_amount_to_minor_units( (float) $summary['withdrawn'], $currency ),
				'pending_withdrawal_minor' => wpss_amount_to_minor_units( (float) $summary['pending_withdrawal'], $currency ),
				'available_balance_minor'  => wpss_amount_to_minor_units( (float) $summary['available_balance'], $currency ),
				'in_clearance_minor'       => wpss_amount_to_minor_units( (float) $summary['in_clearance'], $currency ),
				'currency'                 => $currency,
			)
		);
	}

	/**
	 * Get earnings history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_history( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$pagination   = $this->get_pagination_args( $request );
		$vendor_id    = get_current_user_id();
		$orders_table = $wpdb->prefix . 'wpss_orders';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} WHERE vendor_id = %d AND status = 'completed'",
				$vendor_id
			)
		);

		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_number, service_id, total, vendor_earnings, platform_fee, currency, completed_at, created_at
				FROM {$orders_table}
				WHERE vendor_id = %d AND status = 'completed'
				ORDER BY completed_at DESC
				LIMIT %d OFFSET %d",
				$vendor_id,
				$pagination['per_page'],
				$pagination['offset']
			),
			ARRAY_A
		);

		$items = array();
		foreach ( $orders ?: array() as $order ) {
			$service = get_post( $order['service_id'] );

			$row_currency = (string) $order['currency'];

			$items[] = array(
				'order_id'              => (int) $order['id'],
				'order_number'          => $order['order_number'],
				'service_title'         => $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ),
				'total'                 => (float) $order['total'],
				'vendor_earnings'       => (float) $order['vendor_earnings'],
				'commission'            => (float) $order['platform_fee'],
				// Minor units alongside the floats, same contract as /orders
				// and /earnings/summary. Per-row currency, not the site
				// default, because a historic row keeps the currency it was
				// sold in and zero-decimal currencies scale differently.
				'total_minor'           => wpss_amount_to_minor_units( (float) $order['total'], $row_currency ),
				'vendor_earnings_minor' => wpss_amount_to_minor_units( (float) $order['vendor_earnings'], $row_currency ),
				'commission_minor'      => wpss_amount_to_minor_units( (float) $order['platform_fee'], $row_currency ),
				'currency'              => $row_currency,
				'completed_at'          => $this->format_datetime( $order['completed_at'] ),
			);
		}

		return $this->paginated_response( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get the current user's wallet transaction ledger.
	 *
	 * Additive, read-only endpoint exposing the rows in
	 * `wpss_wallet_transactions` for the authenticated vendor only. Returns the
	 * standard list envelope so app/web clients can paginate the ledger that
	 * already drives the admin wallet view and the earnings dashboard balance.
	 *
	 * @since 1.2.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_wallet_transactions( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$pagination = $this->get_pagination_args( $request );
		$user_id    = get_current_user_id();
		$txn_table  = $wpdb->prefix . 'wpss_wallet_transactions';

		$type  = $request->get_param( 'type' );
		$where = $wpdb->prepare( 'user_id = %d', $user_id );

		if ( ! empty( $type ) ) {
			$where .= $wpdb->prepare( ' AND type = %s', sanitize_key( $type ) );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $txn_table from $wpdb->prefix; $where built from $wpdb->prepare fragments only; LIMIT/OFFSET prepared below.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$txn_table} WHERE {$where}" );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, type, amount, currency, description, reference_type, reference_id, status, created_at
				FROM {$txn_table}
				WHERE {$where}
				ORDER BY created_at DESC, id DESC
				LIMIT %d OFFSET %d",
				$pagination['per_page'],
				$pagination['offset']
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Reference types that resolve to an order (incl. order sub-types — tips,
		// extensions and milestones are all stored as sub-orders), mapped to the
		// vendor-facing link label.
		$reference_labels = array(
			'order'     => __( 'View Order', 'wp-sell-services' ),
			'tip'       => __( 'View Tip', 'wp-sell-services' ),
			'extension' => __( 'View Extension', 'wp-sell-services' ),
			'milestone' => __( 'View Milestone', 'wp-sell-services' ),
		);

		$items = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$reference_id   = null !== $row['reference_id'] ? (int) $row['reference_id'] : null;
			$reference_type = (string) $row['reference_type'];

			// Build a clickable reference (label + order detail URL) instead of an
			// opaque internal ID. Empty url => the JS renders plain text.
			$reference_label = '';
			$reference_url   = '';
			if ( $reference_id && isset( $reference_labels[ $reference_type ] ) ) {
				$url = wpss_get_order_url( $reference_id );
				if ( '' !== $url ) {
					$reference_label = $reference_labels[ $reference_type ];
					$reference_url   = $url;
				}
			}

			$items[] = array(
				'id'              => (int) $row['id'],
				'type'            => $row['type'],
				'amount'          => (float) $row['amount'],
				// Minor units alongside the float, as everywhere else money is
				// returned. balance_after is not exposed: it is a stored running
				// number that drifts from the ledger SUM; the balance comes from
				// /earnings/summary.
				'amount_minor'    => wpss_amount_to_minor_units( (float) $row['amount'], (string) $row['currency'] ),
				// Whether this row REDUCES the balance. The client cannot infer
				// it from the sign: debits are stored POSITIVE and the sign is
				// applied on read from wpss_get_ledger_debit_types(), so a
				// withdrawal rendered as "+90.00" — a payout looking like a
				// credit. The server owns the debit-type list, so it answers
				// here rather than the JS duplicating the rule.
				'is_debit'        => in_array( $row['type'], wpss_get_ledger_debit_types(), true )
					|| (float) $row['amount'] < 0,
				'currency'        => $row['currency'],
				'description'     => $row['description'],
				'reference_type'  => $reference_type,
				'reference_id'    => $reference_id,
				'reference_label' => $reference_label,
				'reference_url'   => $reference_url,
				'status'          => $row['status'],
				'created_at'      => $this->format_datetime( $row['created_at'] ),
			);
		}

		return $this->paginated_response( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Request withdrawal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function request_withdrawal( WP_REST_Request $request ) {
		global $wpdb;

		$vendor_id = get_current_user_id();
		$amount    = (float) $request->get_param( 'amount' );
		$method    = sanitize_text_field( $request->get_param( 'method' ) );
		$details   = $request->get_param( 'details' ) ?: array();

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Amount must be greater than zero.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Check minimum withdrawal.
		$min_amount = \WPSellServices\Services\EarningsService::get_min_withdrawal_amount();
		if ( $amount < $min_amount ) {
			return new WP_Error(
				'below_minimum',
				/* translators: %s: minimum withdrawal amount */
				sprintf( __( 'Minimum withdrawal amount is %s.', 'wp-sell-services' ), wpss_format_currency( $min_amount ) ),
				array( 'status' => 400 )
			);
		}

		// Check available balance using transaction to prevent race conditions.
		$wd_table = $wpdb->prefix . 'wpss_withdrawals';

		$wpdb->query( 'START TRANSACTION' );

		// Take the row lock on this vendor's open withdrawals first, so two
		// concurrent requests cannot both pass the gate below.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$wd_table} WHERE vendor_id = %d AND status IN ('pending', 'approved', 'completed') FOR UPDATE",
				$vendor_id
			)
		);

		// Ask the ONE balance authority rather than re-deriving it. This block
		// used to re-implement get_summary() inline — its own comment said so —
		// and had already drifted: it counted completed withdrawals from the
		// withdrawals table, which double-debits them now that they are in the
		// ledger, and it scoped clearance to order rows so tips and milestone
		// credits were withdrawable immediately.
		$available = ( new \WPSellServices\Services\EarningsService() )
			->get_summary( $vendor_id )['available_balance'];

		if ( $amount > $available ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'insufficient_balance', __( 'Insufficient available balance.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// Check for existing pending withdrawal.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wd_table} WHERE vendor_id = %d AND status = 'pending'",
				$vendor_id
			)
		);

		if ( $existing > 0 ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'pending_exists', __( 'You already have a pending withdrawal request.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$wpdb->insert(
			$wd_table,
			array(
				'vendor_id'  => $vendor_id,
				'amount'     => $amount,
				'method'     => $method,
				'details'    => wp_json_encode( $details ),
				'status'     => 'pending',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s' )
		);

		$withdrawal_id = (int) $wpdb->insert_id;

		if ( ! $withdrawal_id ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'create_failed', __( 'Failed to create withdrawal request.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		$wpdb->query( 'COMMIT' );

		// Persist payout profile so cron can find eligible vendors.
		update_user_meta( $vendor_id, 'wpss_payout_method', $method );
		update_user_meta( $vendor_id, 'wpss_payout_details', $details );

		return new WP_REST_Response(
			array(
				'id'           => $withdrawal_id,
				'amount'       => $amount,
				'amount_minor' => wpss_amount_to_minor_units( (float) $amount, wpss_get_currency() ),
				'currency'     => wpss_get_currency(),
				'method'       => $method,
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql', true ),
			),
			201
		);
	}

	/**
	 * Get withdrawals.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_withdrawals( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$pagination = $this->get_pagination_args( $request );
		$wd_table   = $wpdb->prefix . 'wpss_withdrawals';
		$is_admin   = current_user_can( 'manage_options' );
		$user_id    = get_current_user_id();

		$where = $is_admin ? '1=1' : $wpdb->prepare( 'vendor_id = %d', $user_id );

		$status = $request->get_param( 'status' );
		if ( $status ) {
			$where .= $wpdb->prepare( ' AND status = %s', sanitize_text_field( $status ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wd_table} WHERE {$where}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wd_table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$pagination['per_page'],
				$pagination['offset']
			),
			ARRAY_A
		);

		$withdrawals = array();
		foreach ( $items ?: array() as $item ) {
			$wd = array(
				'id'           => (int) $item['id'],
				'vendor_id'    => (int) $item['vendor_id'],
				'amount'       => (float) $item['amount'],
				// See get_summary(): float kept, integer added. This is the
				// value a client compares against available_balance_minor.
				'amount_minor' => wpss_amount_to_minor_units( (float) $item['amount'], wpss_get_currency() ),
				'currency'     => wpss_get_currency(),
				'method'       => $item['method'],
				'details'      => json_decode( $item['details'] ?? '{}', true ),
				'status'       => $item['status'],
				'notes'        => $item['admin_note'] ?? '',
				'processed_at' => $this->format_datetime( $item['processed_at'] ?? null ),
				'created_at'   => $this->format_datetime( $item['created_at'] ),
			);

			if ( $is_admin ) {
				$user              = get_user_by( 'id', $item['vendor_id'] );
				$wd['vendor_name'] = $user ? $user->display_name : __( 'Unknown', 'wp-sell-services' );
			}

			$withdrawals[] = $wd;
		}

		return $this->paginated_response( $withdrawals, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Process withdrawal (admin).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function process_withdrawal( WP_REST_Request $request ) {
		global $wpdb;

		$withdrawal_id = (int) $request->get_param( 'id' );
		$new_status    = sanitize_text_field( $request->get_param( 'status' ) );
		$note          = sanitize_textarea_field( $request->get_param( 'note' ) ?: '' );
		$wd_table      = $wpdb->prefix . 'wpss_withdrawals';

		// Delegate to the service — the ONE transition path. It row-locks the
		// withdrawal, enforces the terminal-state guard, and for 'completed'
		// routes through mark_paid(), which writes the wallet-ledger debit in
		// the same transaction. This controller used to run its own UPDATE and
		// fire the hook itself: a second, driftable copy of the money path with
		// no vendor notification and no ledger guarantee.
		$result = ( new \WPSellServices\Services\EarningsService() )->process_withdrawal( $withdrawal_id, $new_status, $note );

		if ( empty( $result['success'] ) ) {
			$code        = $result['code'] ?? 'update_failed';
			$http_status = 'not_found' === $code ? 404 : 400;
			return new WP_Error( 'wpss_' . $code, $result['message'], array( 'status' => $http_status ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wd_table} WHERE id = %d", $withdrawal_id ),
			ARRAY_A
		);

		if ( $withdrawal ) {
			$withdrawal['notes'] = $withdrawal['admin_note'] ?? '';
		}

		return new WP_REST_Response( $withdrawal );
	}

	/**
	 * Get withdrawal methods.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_withdrawal_methods( WP_REST_Request $request ): WP_REST_Response {
		$methods = array(
			'bank_transfer' => __( 'Bank Transfer', 'wp-sell-services' ),
			'paypal'        => __( 'PayPal', 'wp-sell-services' ),
		);

		/**
		 * Filter available withdrawal methods.
		 *
		 * @param array $methods Withdrawal methods.
		 */
		return new WP_REST_Response( apply_filters( 'wpss_withdrawal_methods', $methods ) );
	}
}
