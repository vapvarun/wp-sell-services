<?php
/**
 * Earnings REST Controller
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\API;

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
							'enum'        => array( 'day', 'week', 'month', 'year' ),
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
					'permission_callback' => array( $this, 'check_permissions' ),
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
							'enum'        => array( 'pending', 'approved', 'rejected', 'completed' ),
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
							'enum'        => array( 'approved', 'rejected', 'completed' ),
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
					'permission_callback' => array( $this, 'check_permissions' ),
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
		global $wpdb;

		$vendor_id    = get_current_user_id();
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$wallet_table = $wpdb->prefix . 'wpss_wallet_transactions';
		$wd_table     = $wpdb->prefix . 'wpss_withdrawals';

		// Total earned from completed orders.
		$total_earned = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(vendor_earnings), 0) FROM {$orders_table} WHERE vendor_id = %d AND status = 'completed'",
				$vendor_id
			)
		);

		// Total withdrawn.
		$total_withdrawn = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$wd_table} WHERE vendor_id = %d AND status IN ('approved', 'completed')",
				$vendor_id
			)
		);

		// Pending withdrawal.
		$pending_withdrawal = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$wd_table} WHERE vendor_id = %d AND status = 'pending'",
				$vendor_id
			)
		);

		$available = $total_earned - $total_withdrawn - $pending_withdrawal;

		return new WP_REST_Response(
			array(
				'total_earned'       => round( $total_earned, 2 ),
				'total_withdrawn'    => round( $total_withdrawn, 2 ),
				'pending_withdrawal' => round( $pending_withdrawal, 2 ),
				'available_balance'  => round( max( 0, $available ), 2 ),
				'currency'           => wpss_get_currency(),
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

			$items[] = array(
				'order_id'         => (int) $order['id'],
				'order_number'     => $order['order_number'],
				'service_title'    => $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ),
				'total'            => (float) $order['total'],
				'vendor_earnings'   => (float) $order['vendor_earnings'],
				'commission'       => (float) $order['platform_fee'],
				'currency'         => $order['currency'],
				'completed_at'     => $order['completed_at'],
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
		$min_amount = (float) get_option( 'wpss_min_withdrawal_amount', 10 );
		if ( $amount < $min_amount ) {
			return new WP_Error(
				'below_minimum',
				/* translators: %s: minimum withdrawal amount */
				sprintf( __( 'Minimum withdrawal amount is %s.', 'wp-sell-services' ), wpss_format_currency( $min_amount ) ),
				array( 'status' => 400 )
			);
		}

		// Check available balance using transaction to prevent race conditions.
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$wd_table     = $wpdb->prefix . 'wpss_withdrawals';

		$wpdb->query( 'START TRANSACTION' );

		$earned = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(vendor_earnings), 0) FROM {$orders_table} WHERE vendor_id = %d AND status = 'completed'",
				$vendor_id
			)
		);

		$withdrawn_and_pending = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$wd_table} WHERE vendor_id = %d AND status IN ('pending', 'approved', 'completed') FOR UPDATE",
				$vendor_id
			)
		);

		$available = $earned - $withdrawn_and_pending;

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

		$withdrawal_id = $wpdb->insert_id;

		if ( ! $withdrawal_id ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'create_failed', __( 'Failed to create withdrawal request.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		$wpdb->query( 'COMMIT' );

		return new WP_REST_Response(
			array(
				'id'         => $withdrawal_id,
				'amount'     => $amount,
				'method'     => $method,
				'status'     => 'pending',
				'created_at' => current_time( 'mysql', true ),
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
				'method'       => $item['method'],
				'details'      => json_decode( $item['details'] ?? '{}', true ),
				'status'       => $item['status'],
				'notes'        => $item['admin_note'] ?? '',
				'processed_at' => $item['processed_at'] ?? null,
				'created_at'   => $item['created_at'],
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

		$withdrawal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wd_table} WHERE id = %d", $withdrawal_id ),
			ARRAY_A
		);

		if ( ! $withdrawal ) {
			return new WP_Error( 'not_found', __( 'Withdrawal not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( 'pending' !== $withdrawal['status'] && 'approved' !== $withdrawal['status'] ) {
			return new WP_Error( 'invalid_status', __( 'This withdrawal cannot be updated.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$wpdb->update(
			$wd_table,
			array(
				'status'       => $new_status,
				'admin_note'   => $note,
				'processed_by' => get_current_user_id(),
				'processed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $withdrawal_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after a withdrawal is processed.
		 *
		 * @param int    $withdrawal_id Withdrawal ID.
		 * @param string $new_status    New status.
		 * @param array  $withdrawal    Original withdrawal data.
		 */
		do_action( 'wpss_withdrawal_processed', $withdrawal_id, $new_status, $withdrawal );

		$withdrawal['status']       = $new_status;
		$withdrawal['notes']        = $note;
		$withdrawal['processed_at'] = current_time( 'mysql', true );

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

	/**
	 * Check vendor permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_vendor_permissions( WP_REST_Request $request ) {
		$perm_check = $this->check_permissions( $request );
		if ( is_wp_error( $perm_check ) ) {
			return $perm_check;
		}

		if ( ! get_user_meta( get_current_user_id(), '_wpss_is_vendor', true ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Only vendors can access earnings.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		// Prevent pending vendors from accessing earnings.
		$vendor_status = get_user_meta( get_current_user_id(), '_wpss_vendor_status', true );
		if ( 'pending' === $vendor_status ) {
			return new WP_Error( 'rest_forbidden', __( 'Your vendor account is pending approval.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		return true;
	}
}
