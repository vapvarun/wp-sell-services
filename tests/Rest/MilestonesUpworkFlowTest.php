<?php
/**
 * REST: Milestones Upwork-flow end-to-end test.
 *
 * Drives the REST surface through the full Upwork-style journey to prove
 * the endpoints match the AJAX surface with correct HTTP status codes,
 * ownership checks, and lock-step enforcement:
 *
 *   1. Create a buyer request post.
 *   2. Submit a milestone proposal via REST (3 phases, server derives price).
 *   3. Accept the proposal as the buyer via REST — creates the parent +
 *      pre-created milestone sub-orders via BuyerRequestService::convert_to_order.
 *   4. List milestones via REST — every row has `is_locked` and only
 *      phase 1 is payable.
 *   5. Phase 2 pay attempt → expect HTTP 409 wpss_milestone_locked.
 *   6. Vendor proposes an ad-hoc extra phase via REST → 201.
 *   7. Submit phase 1 as vendor via REST; approve phase 1 as buyer;
 *      confirm phase 2 unlocks.
 *   8. Decline phase 3 as buyer → 200; DELETE it as vendor on a fresh
 *      unpaid phase → 200.
 *
 * All assertions go through `rest_do_request` so we exercise the real
 * permission callbacks and route wiring.
 *
 * @package WPSellServices\Tests\Rest
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Rest;

use WPSellServices\Tests\TestCase;
use WPSellServices\Tests\Factories\UserFactory;
use WPSellServices\Services\MilestoneService;
use WPSellServices\Services\ProposalService;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Models\ServiceOrder;
use WP_REST_Request;
use WP_REST_Server;

/**
 * End-to-end REST exercise of the milestone sub-order flow.
 */
class MilestonesUpworkFlowTest extends TestCase {

	/**
	 * REST server used for every request in this test.
	 *
	 * @var \WP_REST_Server|null
	 */
	private ?WP_REST_Server $server = null;

	/**
	 * Buyer user.
	 *
	 * @var \WP_User|null
	 */
	private $buyer = null;

	/**
	 * Vendor user.
	 *
	 * @var \WP_User|null
	 */
	private $vendor = null;

	/**
	 * Buyer request post ID.
	 *
	 * @var int
	 */
	private int $request_id = 0;

	/**
	 * Parent service-order ID created via convert_to_order.
	 *
	 * @var int
	 */
	private int $parent_order_id = 0;

	/**
	 * Set up: boot the REST server and register routes, then make sure
	 * the rate limiter is inert so rapid fire calls don't hit the 429
	 * guard in check_permissions().
	 */
	protected function set_up(): void {
		parent::set_up();

		// Skip the whole test if we're not running against a real WP DB.
		if ( ! function_exists( 'rest_do_request' ) ) {
			$this->markTestSkipped( 'WordPress REST API not available.' );
		}

		/** @var \WP_REST_Server $wp_rest_server */
		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		// Trigger rest_api_init so plugin routes register against the
		// fresh server instance.
		do_action( 'rest_api_init' );

		// Disable rate limiting during the test — the limiter shares
		// transient storage across calls, which would flake the suite.
		add_filter( 'wpss_rate_limit_enabled', '__return_false' );

		// Build globally-unique logins so the same DB can host many
		// runs of this test without colliding on user_login (the WP
		// test framework does NOT roll back transactions here).
		$salt         = (string) wp_generate_password( 8, false, false );
		$this->buyer  = UserFactory::customer(
			array(
				'user_login' => 'wpss_rest_buyer_' . $salt,
				'user_email' => 'wpss_rest_buyer_' . $salt . '@example.test',
			)
		);
		$this->vendor = UserFactory::vendor(
			array(
				'user_login' => 'wpss_rest_vendor_' . $salt,
				'user_email' => 'wpss_rest_vendor_' . $salt . '@example.test',
			)
		);
	}

	/**
	 * Tear down: reset global REST server + current user.
	 */
	protected function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		$this->server   = null;

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * The canonical end-to-end drive. Kept as one method so the sequence
	 * reads as a story and the PHPUnit report shows one passing
	 * acceptance case — a failure at any step stops the rest.
	 */
	public function test_full_upwork_flow_via_rest(): void {
		$this->create_buyer_request();

		$proposal_id = $this->submit_milestone_proposal_via_rest( 3 );
		$this->assertIsInt( $proposal_id );
		$this->assertGreaterThan( 0, $proposal_id );

		$this->accept_proposal_via_rest( $proposal_id );

		$this->assertGreaterThan( 0, $this->parent_order_id, 'Parent order ID should be set after accept.' );

		// Milestones should be 3 pending_payment rows, only phase 1 is payable.
		$listed = $this->list_milestones_via_rest();
		$this->assertCount( 3, $listed );
		$this->assertSame( 'pending_payment', $listed[0]['status'] );
		$this->assertFalse( $listed[0]['is_locked'], 'Phase 1 must not be locked.' );
		$this->assertTrue( $listed[1]['is_locked'], 'Phase 2 must be locked while phase 1 is open.' );
		$this->assertTrue( $listed[2]['is_locked'], 'Phase 3 must be locked while phase 1 is open.' );

		// --- Lock-step: phase 2 pay attempt must 409.
		wp_set_current_user( $this->buyer->ID );
		$phase_2_id  = (int) $listed[1]['id'];
		$pay_attempt = $this->do_rest( 'POST', "/wpss/v1/milestones/{$phase_2_id}/pay" );
		$this->assertSame( 409, $pay_attempt->get_status(), 'Locked milestone must return 409.' );
		$data = $pay_attempt->get_data();
		$this->assertSame( 'wpss_milestone_locked', $data['code'] ?? ( $data['data']['code'] ?? null ) );

		// --- Vendor proposes an additional ad-hoc phase.
		wp_set_current_user( $this->vendor->ID );
		$propose = $this->do_rest(
			'POST',
			"/wpss/v1/orders/{$this->parent_order_id}/milestones",
			array(
				'title'       => 'Phase 4 ad-hoc',
				'description' => 'Extra work',
				'amount'      => 75.0,
				'days'        => 2,
			)
		);
		$this->assertSame( 201, $propose->get_status(), 'Propose must return 201.' );
		$propose_data       = $propose->get_data();
		$ad_hoc_milestone_id = (int) ( $propose_data['milestone_id'] ?? 0 );
		$this->assertGreaterThan( 0, $ad_hoc_milestone_id );

		// --- Simulate phase 1 paid: the service layer is the authority,
		// so mark the row paid and fire the hook the gateway webhook
		// would fire, which flips it to in_progress.
		$phase_1_id = (int) $listed[0]['id'];
		$this->mark_sub_order_paid( $phase_1_id );

		// --- Submit phase 1 as vendor.
		wp_set_current_user( $this->vendor->ID );
		$submit = $this->do_rest( 'POST', "/wpss/v1/milestones/{$phase_1_id}/submit", array( 'note' => 'Done.' ) );
		$this->assertSame( 200, $submit->get_status(), 'Submit must return 200. Body: ' . wp_json_encode( $submit->get_data() ) );

		// --- Approve phase 1 as buyer.
		wp_set_current_user( $this->buyer->ID );
		$approve = $this->do_rest( 'POST', "/wpss/v1/milestones/{$phase_1_id}/approve" );
		$this->assertSame( 200, $approve->get_status() );

		// Phase 2 should now be unlocked.
		$after = $this->list_milestones_via_rest();
		$phase_2_after = null;
		foreach ( $after as $row ) {
			if ( (int) $row['id'] === $phase_2_id ) {
				$phase_2_after = $row;
				break;
			}
		}
		$this->assertNotNull( $phase_2_after );
		$this->assertFalse( $phase_2_after['is_locked'], 'Phase 2 must unlock once phase 1 is completed.' );

		// --- Buyer declines phase 3 (still pending_payment and now the
		// next-in-sequence after phase 2).
		$phase_3_id = (int) $listed[2]['id'];
		$decline    = $this->do_rest( 'POST', "/wpss/v1/milestones/{$phase_3_id}/decline" );
		$this->assertSame( 200, $decline->get_status(), 'Decline of unpaid phase must return 200.' );

		// --- Vendor DELETEs the ad-hoc phase they proposed earlier.
		wp_set_current_user( $this->vendor->ID );
		$delete = $this->do_rest( 'DELETE', "/wpss/v1/milestones/{$ad_hoc_milestone_id}" );
		$this->assertSame( 200, $delete->get_status(), 'DELETE on unpaid vendor-proposed milestone must return 200.' );
	}

	/**
	 * 409 must trigger even on the orders/{id}/pay path, not just the
	 * milestones/{id}/pay path — both entry points share the same guard.
	 */
	public function test_pay_order_endpoint_enforces_milestone_lock(): void {
		$this->create_buyer_request();
		$proposal_id = $this->submit_milestone_proposal_via_rest( 3 );
		$this->accept_proposal_via_rest( $proposal_id );
		$listed = $this->list_milestones_via_rest();

		wp_set_current_user( $this->buyer->ID );
		$phase_2_id = (int) $listed[1]['id'];
		$response   = $this->do_rest( 'POST', "/wpss/v1/orders/{$phase_2_id}/pay" );
		$this->assertSame( 409, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'wpss_milestone_locked', $data['code'] ?? ( $data['data']['code'] ?? null ) );
	}

	/**
	 * A non-participant must be blocked with 403 from every write
	 * endpoint on a milestone they don't own.
	 */
	public function test_non_participant_gets_403_on_milestone_actions(): void {
		$this->create_buyer_request();
		$proposal_id = $this->submit_milestone_proposal_via_rest( 1 );
		$this->accept_proposal_via_rest( $proposal_id );
		$listed = $this->list_milestones_via_rest();
		$ms_id  = (int) $listed[0]['id'];

		$salt     = (string) wp_generate_password( 8, false, false );
		$stranger = UserFactory::customer(
			array(
				'user_login' => 'wpss_rest_stranger_' . $salt,
				'user_email' => 'wpss_rest_stranger_' . $salt . '@example.test',
			)
		);
		wp_set_current_user( $stranger->ID );

		$approve = $this->do_rest( 'POST', "/wpss/v1/milestones/{$ms_id}/approve" );
		$this->assertSame( 403, $approve->get_status() );

		$submit = $this->do_rest( 'POST', "/wpss/v1/milestones/{$ms_id}/submit" );
		$this->assertSame( 403, $submit->get_status() );

		$decline = $this->do_rest( 'POST', "/wpss/v1/milestones/{$ms_id}/decline" );
		$this->assertSame( 403, $decline->get_status() );
	}

	/**
	 * Proposal GET must return milestones as an array, not a JSON string.
	 */
	public function test_proposal_get_returns_milestones_as_array(): void {
		$this->create_buyer_request();
		$proposal_id = $this->submit_milestone_proposal_via_rest( 2 );

		wp_set_current_user( $this->vendor->ID );
		$response = $this->do_rest( 'GET', "/wpss/v1/proposals/{$proposal_id}" );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'milestones', $data );
		$this->assertIsArray( $data['milestones'] );
		$this->assertCount( 2, $data['milestones'] );
		$this->assertSame( ProposalService::CONTRACT_TYPE_MILESTONE, $data['contract_type'] );
	}

	// ---------------------------------------------------------------------
	// Fixtures + helpers.
	// ---------------------------------------------------------------------

	/**
	 * Create a buyer request post as the buyer.
	 */
	private function create_buyer_request(): void {
		wp_set_current_user( $this->buyer->ID );
		$this->request_id = wp_insert_post(
			array(
				'post_type'   => 'wpss_request',
				'post_title'  => 'Logo design project',
				'post_status' => 'publish',
				'post_author' => $this->buyer->ID,
				'meta_input'  => array(
					'_wpss_status'        => BuyerRequestService::STATUS_OPEN,
					'_wpss_budget_min'    => 100,
					'_wpss_budget_max'    => 500,
					'_wpss_delivery_days' => 14,
				),
			)
		);
		$this->assertIsInt( $this->request_id );
		$this->assertGreaterThan( 0, $this->request_id );
	}

	/**
	 * Submit a milestone-contract proposal via REST as the vendor.
	 *
	 * @param int $phases Number of milestones to include.
	 * @return int Proposal ID.
	 */
	private function submit_milestone_proposal_via_rest( int $phases ): int {
		wp_set_current_user( $this->vendor->ID );

		$milestones = array();
		for ( $i = 1; $i <= $phases; $i++ ) {
			$milestones[] = array(
				'title'        => sprintf( 'Phase %d', $i ),
				'description'  => sprintf( 'Phase %d description', $i ),
				'deliverables' => sprintf( 'Phase %d deliverables', $i ),
				'amount'       => 100.0 * $i,
				'days'         => 3 * $i,
			);
		}

		$response = $this->do_rest(
			'POST',
			'/wpss/v1/proposals',
			array(
				'request_id'    => $this->request_id,
				'cover_letter'  => 'I can do this in phases.',
				'contract_type' => ProposalService::CONTRACT_TYPE_MILESTONE,
				'milestones'    => $milestones,
			)
		);

		$this->assertSame(
			201,
			$response->get_status(),
			'Proposal submit must return 201. Body: ' . wp_json_encode( $response->get_data() )
		);

		$data = $response->get_data();
		return (int) $data['proposal_id'];
	}

	/**
	 * Accept a proposal as the buyer via the existing REST endpoint.
	 * This creates the parent order + pre-created milestone sub-orders.
	 *
	 * @param int $proposal_id Proposal ID.
	 */
	private function accept_proposal_via_rest( int $proposal_id ): void {
		wp_set_current_user( $this->buyer->ID );

		$response = $this->do_rest(
			'POST',
			"/wpss/v1/buyer-requests/{$this->request_id}/proposals/{$proposal_id}/accept"
		);

		$this->assertSame( 200, $response->get_status(), 'Accept must return 200. Body: ' . wp_json_encode( $response->get_data() ) );

		$data                  = $response->get_data();
		$this->parent_order_id = (int) ( $data['order_id'] ?? 0 );
		$this->assertGreaterThan( 0, $this->parent_order_id );
	}

	/**
	 * List milestones on the parent order via REST.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function list_milestones_via_rest(): array {
		wp_set_current_user( $this->buyer->ID );

		$response = $this->do_rest( 'GET', "/wpss/v1/orders/{$this->parent_order_id}/milestones" );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'milestones', $data );
		$this->assertIsArray( $data['milestones'] );

		return $data['milestones'];
	}

	/**
	 * Drive the gateway-webhook path: flag the sub-order paid, flip
	 * payment_status, and fire wpss_order_paid so
	 * MilestoneService::handle_order_paid flips the row to in_progress
	 * and credits the vendor via the same code path production uses.
	 *
	 * @param int $milestone_id Sub-order ID.
	 */
	private function mark_sub_order_paid( int $milestone_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'wpss_orders',
			array(
				'payment_status' => 'paid',
				'paid_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $milestone_id )
		);

		do_action( 'wpss_order_paid', $milestone_id );
	}

	/**
	 * Thin REST dispatcher that runs through the real server so
	 * permission_callback is exercised exactly like production.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  REST route.
	 * @param array  $body   Body params.
	 * @return \WP_REST_Response
	 */
	private function do_rest( string $method, string $route, array $body = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $body as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}
}
