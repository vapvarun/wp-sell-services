<?php
/**
 * WP-CLI data-flow tests for WP Sell Services.
 *
 * Exercises the end-to-end data flows (service purchase, buyer request → proposal
 * → order, order cancel / earnings reversal) by calling service classes directly
 * and asserting DB state. Each flow seeds isolated test data prefixed with
 * `test_flow_` and cleans up afterwards.
 *
 * @package WPSellServices\CLI
 * @since   1.0.1
 */

declare(strict_types=1);


namespace WPSellServices\CLI;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use WP_CLI_Command;
use WPSellServices\Services\AuditLogService;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\CommissionService;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\OrderWorkflowManager;
use WPSellServices\Services\ProposalService;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Integrations\Standalone\StandaloneOrderProvider;

/**
 * Run end-to-end data flow tests.
 *
 * These tests are intended for developer verification and CI — they write real
 * rows to the DB and clean them up at the end. Run against a dev site only.
 *
 * ## EXAMPLES
 *
 *     # Run all flows
 *     $ wp wpss test:flow all
 *
 *     # Run a single flow
 *     $ wp wpss test:flow buyer-request
 *     $ wp wpss test:flow service-purchase
 *     $ wp wpss test:flow order-cancel-rollback
 *
 *     # Skip cleanup (leaves test data in DB for inspection)
 *     $ wp wpss test:flow buyer-request --no-cleanup
 *
 * @since 1.0.1
 */
class TestFlowCommand extends WP_CLI_Command {

	private const USER_PREFIX    = 'test_flow_';
	private const SERVICE_PREFIX = 'test_flow_service_';

	/**
	 * IDs collected during a run so we can clean up.
	 *
	 * @var array{users: int[], posts: int[], proposals: int[], orders: int[], wallet_txns: int[]}
	 */
	private array $created = array(
		'users'       => array(),
		'posts'       => array(),
		'proposals'   => array(),
		'orders'      => array(),
		'wallet_txns' => array(),
		'audit_rows'  => array(),
	);

	/**
	 * Failures recorded during the run.
	 *
	 * @var array<int, string>
	 */
	private array $failures = array();

	/**
	 * Run one or more end-to-end data flows.
	 *
	 * ## OPTIONS
	 *
	 * <flow>
	 * : Flow to run. One of: all, buyer-request, service-purchase, order-cancel-rollback, order-audit-trail.
	 *
	 * [--no-cleanup]
	 * : Leave seeded data in the DB after the run (for manual inspection).
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$flow    = $args[0] ?? 'all';
		$cleanup = empty( $assoc_args['no-cleanup'] );

		$flows = array(
			'buyer-request'         => 'flow_buyer_request',
			'service-purchase'      => 'flow_service_purchase',
			'order-cancel-rollback' => 'flow_order_cancel_rollback',
			'order-audit-trail'     => 'flow_order_audit_trail',
		);

		$to_run = 'all' === $flow ? array_keys( $flows ) : array( $flow );

		foreach ( $to_run as $name ) {
			if ( ! isset( $flows[ $name ] ) ) {
				WP_CLI::error( "Unknown flow: {$name}" );
			}
		}

		$exit_code = 0;

		foreach ( $to_run as $name ) {
			WP_CLI::log( WP_CLI::colorize( "%c== Running flow: {$name} ==%n" ) );
			$this->failures = array();
			$this->created  = array(
				'users'       => array(),
				'posts'       => array(),
				'proposals'   => array(),
				'orders'      => array(),
				'wallet_txns' => array(),
				'audit_rows'  => array(),
			);

			try {
				$method = $flows[ $name ];
				$this->$method();
			} catch ( \Throwable $e ) {
				$this->fail( "flow threw exception: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}" );
			}

			if ( $cleanup ) {
				$this->cleanup();
			}

			if ( $this->failures ) {
				$exit_code = 1;
				WP_CLI::log( WP_CLI::colorize( "%R[FAIL]%n {$name} — " . count( $this->failures ) . ' failure(s):' ) );
				foreach ( $this->failures as $msg ) {
					WP_CLI::log( "  - {$msg}" );
				}
			} else {
				WP_CLI::log( WP_CLI::colorize( "%G[PASS]%n {$name}" ) );
			}
		}

		if ( $exit_code ) {
			WP_CLI::halt( $exit_code );
		}
	}

	/**
	 * Flow: service purchase (cart → gateway → order).
	 *
	 * Seeds a vendor and a service, adds the service to the buyer's cart,
	 * invokes StandaloneOrderProvider::create_orders_from_cart() to simulate
	 * the gateway completing payment, and asserts the order row exists with
	 * the expected shape.
	 */
	private function flow_service_purchase(): void {
		$vendor_id = $this->seed_user( 'vendor' );
		$buyer_id  = $this->seed_user( 'buyer' );
		$service   = $this->seed_service( $vendor_id, 49.0 );

		$cart_item = array(
			'service_id' => $service['id'],
			'package_id' => 0,
			'addons'     => array(),
			'total'      => 49.0,
			'added_at'   => current_time( 'mysql', true ),
		);
		update_user_meta( $buyer_id, '_wpss_cart', array( 'item1' => $cart_item ) );

		$this->assert_eq(
			array( 'item1' => $cart_item ),
			get_user_meta( $buyer_id, '_wpss_cart', true ),
			'cart persisted in user meta'
		);

		$provider  = new StandaloneOrderProvider();
		$order_ids = $provider->create_orders_from_cart(
			array( $cart_item ),
			'test',
			'test_txn_' . wp_generate_password( 8, false ),
			$buyer_id
		);

		if ( empty( $order_ids ) ) {
			$this->fail( 'create_orders_from_cart returned no order IDs' );
			return;
		}

		$order_id                  = (int) $order_ids[0];
		$this->created['orders'][] = $order_id;

		$order = wpss_get_order( $order_id );
		$this->assert_true( $order instanceof ServiceOrder, 'order row exists' );

		if ( ! $order instanceof ServiceOrder ) {
			return;
		}

		$this->assert_eq( $buyer_id, $order->customer_id, 'customer_id matches buyer' );
		$this->assert_eq( $vendor_id, $order->vendor_id, 'vendor_id matches vendor' );
		$this->assert_eq( $service['id'], $order->service_id, 'service_id matches seeded service' );
		$this->assert_eq( ServiceOrder::STATUS_PENDING_PAYMENT, $order->status, 'new order starts in pending_payment' );
		$this->assert_eq( 49.0, (float) $order->subtotal, 'subtotal matches package price' );
		$this->assert_true( (float) $order->vendor_earnings > 0, 'vendor_earnings calculated' );
		$this->assert_true( (float) $order->commission_rate >= 0, 'commission_rate recorded' );
	}

	/**
	 * Flow: buyer request → proposal → order.
	 *
	 * Exercises the corrected buyer-request accept flow: creates a request,
	 * submits a proposal, accepts it via BuyerRequestService::convert_to_order(),
	 * and asserts the resulting order row has the expected platform/status
	 * shape and that the proposal was marked accepted.
	 */
	private function flow_buyer_request(): void {
		$buyer_id  = $this->seed_user( 'buyer' );
		$vendor_id = $this->seed_user( 'vendor' );

		$request_id = wp_insert_post(
			array(
				'post_type'    => 'wpss_request',
				'post_title'   => 'Test Flow Request ' . wp_generate_password( 6, false ),
				'post_content' => 'Need help with something.',
				'post_status'  => 'publish',
				'post_author'  => $buyer_id,
				'meta_input'   => array(
					'_wpss_request_budget_min'    => 100,
					'_wpss_request_budget_max'    => 500,
					'_wpss_request_delivery_days' => 7,
					'_wpss_request_status'        => BuyerRequestService::STATUS_OPEN,
				),
			)
		);

		if ( is_wp_error( $request_id ) || ! $request_id ) {
			$this->fail( 'failed to insert buyer request post' );
			return;
		}
		$this->created['posts'][] = (int) $request_id;

		$proposal_service = new ProposalService();
		$proposal_id      = $proposal_service->submit(
			(int) $request_id,
			$vendor_id,
			array(
				'description'   => 'I can help.',
				'price'         => 250.00,
				'delivery_days' => 5,
			)
		);

		if ( ! $proposal_id ) {
			$this->fail( 'ProposalService::submit returned false' );
			return;
		}
		$this->created['proposals'][] = (int) $proposal_id;

		$request_service = new BuyerRequestService();
		$result          = $request_service->convert_to_order( (int) $request_id, (int) $proposal_id );

		$this->assert_true( ! empty( $result['success'] ), 'convert_to_order returned success' );

		if ( empty( $result['success'] ) ) {
			$this->fail( 'convert_to_order failure message: ' . ( $result['message'] ?? 'n/a' ) );
			return;
		}

		$order_id                  = (int) $result['order_id'];
		$this->created['orders'][] = $order_id;

		$order = wpss_get_order( $order_id );
		$this->assert_true( $order instanceof ServiceOrder, 'order row exists after convert_to_order' );

		if ( ! $order instanceof ServiceOrder ) {
			return;
		}

		$this->assert_eq( $buyer_id, (int) $order->customer_id, 'customer_id matches request author' );
		$this->assert_eq( $vendor_id, (int) $order->vendor_id, 'vendor_id matches proposing vendor' );
		$this->assert_eq( ServiceOrder::STATUS_PENDING_PAYMENT, $order->status, 'order starts in pending_payment' );
		$this->assert_eq( 250.0, (float) $order->total, 'total matches proposal price' );
		$this->assert_eq( 'request', $order->platform, 'platform = request' );
		$this->assert_eq( (int) $request_id, (int) $order->platform_order_id, 'platform_order_id links to request' );

		// Proposal was marked accepted and other proposals (none in this case) rejected.
		$proposal_after = $proposal_service->get( (int) $proposal_id );
		$this->assert_true( $proposal_after !== null, 'proposal still loadable after accept' );
		$this->assert_eq( ProposalService::STATUS_ACCEPTED, $proposal_after->status ?? '', 'proposal marked accepted' );
	}

	/**
	 * Flow: order cancel → earnings reversal (happy path).
	 *
	 * Seeds a vendor with recorded earnings, creates a paid/completed order,
	 * then cancels it via OrderWorkflowManager::handle_order_cancelled() and
	 * asserts that the reversal wallet transaction was created, the vendor
	 * profile was decremented, and the order's commission fields were cleared.
	 * This also proves the new try/catch + ROLLBACK wrapper doesn't break the
	 * happy path (bug #9706086705 regression guard).
	 */
	private function flow_order_cancel_rollback(): void {
		global $wpdb;

		$vendor_id = $this->seed_user( 'vendor' );
		$buyer_id  = $this->seed_user( 'buyer' );

		// Seed a vendor profile row so reverse_order_earnings has something to decrement.
		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';
		$wpdb->delete( $profiles_table, array( 'user_id' => $vendor_id ) );
		$wpdb->insert(
			$profiles_table,
			array(
				'user_id'          => $vendor_id,
				'total_earnings'   => 500.0,
				'net_earnings'     => 400.0,
				'total_commission' => 100.0,
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%f', '%f', '%s', '%s' )
		);

		// Insert an order directly with recorded earnings, skipping the gateway flow.
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$wpdb->insert(
			$orders_table,
			array(
				'order_number'    => 'TF-' . wp_generate_password( 8, false ),
				'customer_id'     => $buyer_id,
				'vendor_id'       => $vendor_id,
				'service_id'      => 0,
				'platform'        => 'standalone',
				'subtotal'        => 100.0,
				'total'           => 100.0,
				'currency'        => 'USD',
				'status'          => ServiceOrder::STATUS_COMPLETED,
				'payment_status'  => 'paid',
				'commission_rate' => 20.0,
				'platform_fee'    => 20.0,
				'vendor_earnings' => 80.0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s' )
		);

		$order_id                  = (int) $wpdb->insert_id;
		$this->created['orders'][] = $order_id;

		$order = wpss_get_order( $order_id );
		$this->assert_true( $order instanceof ServiceOrder, 'seeded order loadable' );

		if ( ! $order instanceof ServiceOrder ) {
			return;
		}

		$workflow = new OrderWorkflowManager( new OrderService() );
		$workflow->handle_order_cancelled( $order_id, ServiceOrder::STATUS_COMPLETED );

		// Assert: reversal wallet transaction exists.
		$txn_table = $wpdb->prefix . 'wpss_wallet_transactions';
		$reversal  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$txn_table} WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_reversal'",
				$order_id
			)
		);

		$this->assert_true( $reversal !== null, 'reversal wallet_transaction created' );

		if ( $reversal ) {
			$this->created['wallet_txns'][] = (int) $reversal->id;
			$this->assert_eq( -80.0, (float) $reversal->amount, 'reversal amount matches -vendor_earnings' );
		}

		// Assert: vendor profile decremented.
		$profile_after = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$profiles_table} WHERE user_id = %d",
				$vendor_id
			)
		);

		$this->assert_true( $profile_after !== null, 'vendor profile still exists' );

		if ( $profile_after ) {
			$this->assert_eq( 400.0, (float) $profile_after->total_earnings, 'vendor total_earnings decremented by order total' );
			$this->assert_eq( 320.0, (float) $profile_after->net_earnings, 'vendor net_earnings decremented by vendor_earnings' );
			$this->assert_eq( 80.0, (float) $profile_after->total_commission, 'vendor total_commission decremented by platform_fee' );
		}

		// Assert: order commission fields cleared.
		$order_after = wpss_get_order( $order_id );
		$this->assert_true( null === $order_after->vendor_earnings || '' === $order_after->vendor_earnings, 'order vendor_earnings cleared' );
	}

	/**
	 * Flow: order audit trail capture.
	 *
	 * Seeds an order and triggers a non-natural status transition as the
	 * currently-running admin user (CLI is treated as admin). Asserts the
	 * AuditLogService writes an `order.status_change` row with the correct
	 * actor, is_forced flag, and structured context.
	 */
	private function flow_order_audit_trail(): void {
		global $wpdb;

		$vendor_id = $this->seed_user( 'vendor' );
		$buyer_id  = $this->seed_user( 'buyer' );

		// Seed an order directly in `in_progress` — a status the natural
		// state machine allows to move to cancelled/on_hold/etc., but NOT
		// directly to `pending_payment`. Jumping from `in_progress` back to
		// `pending_payment` requires the admin bypass, which is exactly the
		// scenario we want to audit.
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$wpdb->insert(
			$orders_table,
			array(
				'order_number'   => 'TFA-' . wp_generate_password( 8, false ),
				'customer_id'    => $buyer_id,
				'vendor_id'      => $vendor_id,
				'service_id'     => 0,
				'platform'       => 'standalone',
				'subtotal'       => 100.0,
				'total'          => 100.0,
				'currency'       => 'USD',
				'status'         => ServiceOrder::STATUS_IN_PROGRESS,
				'payment_status' => 'paid',
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);
		$order_id                  = (int) $wpdb->insert_id;
		$this->created['orders'][] = $order_id;

		// Switch to an administrator context so can_transition short-circuits
		// and log_status_change records is_forced = true.
		$admin_id = $this->seed_admin_user();
		wp_set_current_user( $admin_id );

		// Baseline: how many audit rows exist for this order before the flow?
		$audit_table = $wpdb->prefix . 'wpss_audit_log';
		$rows_before = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$audit_table} WHERE object_type = 'order' AND object_id = %d",
				$order_id
			)
		);

		$service = new OrderService();
		$ok      = $service->update_status( $order_id, ServiceOrder::STATUS_PENDING_PAYMENT, 'audit test forced transition' );
		$this->assert_true( $ok, 'update_status returned true for forced transition' );

		// Confirm the refactor: the target transition is NOT in the natural map.
		$this->assert_true(
			! $service->can_transition_naturally( ServiceOrder::STATUS_IN_PROGRESS, ServiceOrder::STATUS_PENDING_PAYMENT ),
			'in_progress → pending_payment is outside the natural state machine'
		);

		// New audit row landed.
		$rows_after = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$audit_table} WHERE object_type = 'order' AND object_id = %d",
				$order_id
			)
		);
		$this->assert_eq( $rows_before + 1, $rows_after, 'exactly one new audit row written' );

		$audit_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$audit_table} WHERE object_type = 'order' AND object_id = %d ORDER BY id DESC LIMIT 1",
				$order_id
			)
		);
		$this->assert_true( $audit_row !== null, 'latest audit row retrievable' );

		if ( $audit_row ) {
			$this->created['audit_rows'][] = (int) $audit_row->id;
			$this->assert_eq( 'order.status_change', $audit_row->event_type, 'event_type = order.status_change' );
			$this->assert_eq( $admin_id, (int) $audit_row->actor_id, 'actor_id captured as current admin' );
			$this->assert_eq( 'administrator', $audit_row->actor_role, 'actor_role captured' );
			$this->assert_eq( ServiceOrder::STATUS_IN_PROGRESS, $audit_row->from_value, 'from_value = old status' );
			$this->assert_eq( ServiceOrder::STATUS_PENDING_PAYMENT, $audit_row->to_value, 'to_value = new status' );
			$this->assert_eq( 1, (int) $audit_row->is_forced, 'is_forced flag set for bypass' );
			$this->assert_eq( 'force', $audit_row->action, 'action = force' );

			$context = json_decode( (string) $audit_row->context, true );
			$this->assert_true( is_array( $context ), 'context deserializes to array' );
			$this->assert_true( isset( $context['note'] ), 'context.note present' );
			$this->assert_eq( 'audit test forced transition', $context['note'] ?? '', 'note round-tripped' );
		}

		// AuditLogService::query() filter sanity: by object_type/object_id.
		$audit_service = new AuditLogService();
		$query_result  = $audit_service->query(
			array(
				'object_type' => 'order',
				'object_id'   => $order_id,
				'is_forced'   => true,
				'per_page'    => 10,
			)
		);
		$this->assert_true( (int) $query_result['total'] >= 1, 'query() returns at least one forced row' );
	}

	/**
	 * Create a test administrator user.
	 *
	 * @return int User ID.
	 */
	private function seed_admin_user(): int {
		$login   = self::USER_PREFIX . 'admin_' . wp_generate_password( 6, false );
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $login . '@example.test',
				'user_pass'  => wp_generate_password( 12 ),
				'role'       => 'administrator',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			WP_CLI::error( "Failed to create test admin: {$user_id->get_error_message()}" );
		}

		$this->created['users'][] = (int) $user_id;
		return (int) $user_id;
	}

	/**
	 * Create a test user with the test_flow_ prefix.
	 *
	 * @param string $role Role token for readable username (e.g. 'buyer').
	 * @return int User ID.
	 */
	private function seed_user( string $role ): int {
		$login   = self::USER_PREFIX . $role . '_' . wp_generate_password( 6, false );
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $login . '@example.test',
				'user_pass'  => wp_generate_password( 12 ),
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			WP_CLI::error( "Failed to create test user: {$user_id->get_error_message()}" );
		}

		$this->created['users'][] = (int) $user_id;
		return (int) $user_id;
	}

	/**
	 * Create a minimal publish-ready service post with a single package.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param float $price     Package price.
	 * @return array{id:int, package_id:int}
	 */
	private function seed_service( int $vendor_id, float $price ): array {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wpss_service',
				'post_title'   => 'Test Flow Service ' . wp_generate_password( 6, false ),
				'post_content' => 'Automated test service.',
				'post_status'  => 'publish',
				'post_author'  => $vendor_id,
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			WP_CLI::error( 'Failed to insert test service post' );
		}

		update_post_meta(
			(int) $post_id,
			'_wpss_packages',
			array(
				array(
					'title'         => 'Basic',
					'description'   => 'Basic package',
					'price'         => $price,
					'delivery_days' => 3,
					'revisions'     => 1,
				),
			)
		);

		$this->created['posts'][] = (int) $post_id;
		return array(
			'id'         => (int) $post_id,
			'package_id' => 0,
		);
	}

	/**
	 * Record an assertion failure.
	 */
	private function fail( string $msg ): void {
		$this->failures[] = $msg;
	}

	/**
	 * Assert a condition is truthy.
	 *
	 * @param mixed  $condition Value to check.
	 * @param string $label     Human-readable label.
	 */
	private function assert_true( $condition, string $label ): void {
		if ( $condition ) {
			WP_CLI::log( WP_CLI::colorize( "  %G✓%n {$label}" ) );
			return;
		}
		$this->fail( $label );
		WP_CLI::log( WP_CLI::colorize( "  %R✗%n {$label}" ) );
	}

	/**
	 * Assert equality.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $label    Human-readable label.
	 */
	private function assert_eq( $expected, $actual, string $label ): void {
		if ( $expected === $actual || ( is_float( $expected ) && is_float( $actual ) && abs( $expected - $actual ) < 0.00001 ) ) {
			WP_CLI::log( WP_CLI::colorize( "  %G✓%n {$label}" ) );
			return;
		}
		$this->fail( sprintf( '%s (expected %s, got %s)', $label, var_export( $expected, true ), var_export( $actual, true ) ) );
		WP_CLI::log( WP_CLI::colorize( "  %R✗%n {$label} (expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' ) );
	}

	/**
	 * Tear down everything created during the flow.
	 */
	private function cleanup(): void {
		global $wpdb;

		foreach ( $this->created['wallet_txns'] as $id ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'id' => $id ) );
		}
		foreach ( $this->created['audit_rows'] as $id ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'id' => $id ) );
		}
		foreach ( $this->created['orders'] as $id ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'wpss_order_requirements', array( 'order_id' => $id ) );
			// Sweep any audit rows left by this order that the flow didn't explicitly track.
			$wpdb->delete(
				$wpdb->prefix . 'wpss_audit_log',
				array(
					'object_type' => 'order',
					'object_id'   => $id,
				)
			);
		}
		foreach ( $this->created['proposals'] as $id ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'id' => $id ) );
		}
		foreach ( $this->created['posts'] as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->created['users'] as $id ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $id );
			$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $id ) );
			delete_user_meta( $id, '_wpss_cart' );
		}
	}
}
