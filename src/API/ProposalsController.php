<?php
/**
 * Proposals REST Controller
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
use WPSellServices\Services\ProposalService;

/**
 * REST API controller for vendor proposals.
 *
 * @since 1.0.0
 */
class ProposalsController extends RestController {

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $rest_base = 'proposals';

	/**
	 * Proposal service.
	 *
	 * @var ProposalService
	 */
	private ProposalService $proposal_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->proposal_service = new ProposalService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get vendor's proposals.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_permissions' ],
					'args'                => array_merge(
						$this->get_collection_params(),
						[
							'status' => [
								'type' => 'string',
								'enum' => [ 'pending', 'accepted', 'rejected', 'withdrawn' ],
							],
						]
					),
				],
			]
		);

		// Get single proposal.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_proposal_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_vendor_permission' ],
					'args'                => [
						'id'            => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'cover_letter'  => [
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						],
						'price'         => [
							'type'              => 'number',
							'sanitize_callback' => 'floatval',
						],
						'delivery_days' => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// Withdraw proposal (vendor).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/withdraw',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'withdraw' ],
					'permission_callback' => [ $this, 'check_vendor_permission' ],
					'args'                => [
						'id'     => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'reason' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);

		// Get proposal stats for vendor.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_stats' ],
					'permission_callback' => [ $this, 'check_permissions' ],
				],
			]
		);
	}

	/**
	 * Check if user can access proposal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_proposal_permission( WP_REST_Request $request ) {
		$permission = $this->check_permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$proposal_id = (int) $request->get_param( 'id' );
		$proposal    = $this->proposal_service->get( $proposal_id );

		if ( ! $proposal ) {
			return new WP_Error(
				'proposal_not_found',
				__( 'Proposal not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		$user_id = get_current_user_id();

		// Vendor can view their own proposals.
		if ( (int) $proposal->vendor_id === $user_id ) {
			return true;
		}

		// Request owner can view proposals.
		$request_post = get_post( $proposal->request_id );
		if ( $request_post && (int) $request_post->post_author === $user_id ) {
			return true;
		}

		// Admin can view all.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to access this proposal.', 'wp-sell-services' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Check if user is the vendor who submitted proposal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_vendor_permission( WP_REST_Request $request ) {
		$permission = $this->check_permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$proposal_id = (int) $request->get_param( 'id' );
		$proposal    = $this->proposal_service->get( $proposal_id );

		if ( ! $proposal ) {
			return new WP_Error(
				'proposal_not_found',
				__( 'Proposal not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		if ( (int) $proposal->vendor_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You can only modify your own proposals.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Get vendor's proposals.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$vendor_id  = get_current_user_id();
		$pagination = $this->get_pagination_args( $request );
		$status     = $request->get_param( 'status' );

		$args = [
			'limit'  => $pagination['per_page'],
			'offset' => $pagination['offset'],
		];

		if ( $status ) {
			$args['status'] = $status;
		}

		$proposals = $this->proposal_service->get_by_vendor( $vendor_id, $args );
		$total     = $this->proposal_service->count_by_vendor( $vendor_id, $status ? [ 'status' => $status ] : [] );

		$data = array_map( [ $this, 'prepare_proposal_for_response' ], $proposals );

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get single proposal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$proposal_id = (int) $request->get_param( 'id' );
		$proposal    = $this->proposal_service->get( $proposal_id );

		return new WP_REST_Response( $this->prepare_proposal_for_response( $proposal, true ) );
	}

	/**
	 * Update proposal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$proposal_id = (int) $request->get_param( 'id' );
		$proposal    = $this->proposal_service->get( $proposal_id );

		// Can only update pending proposals.
		if ( 'pending' !== $proposal->status ) {
			return new WP_Error(
				'cannot_update',
				__( 'You can only update pending proposals.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		$data = [];

		if ( $request->get_param( 'cover_letter' ) !== null ) {
			$data['cover_letter'] = $request->get_param( 'cover_letter' );
		}

		if ( $request->get_param( 'price' ) !== null ) {
			$data['price'] = $request->get_param( 'price' );
		}

		if ( $request->get_param( 'delivery_days' ) !== null ) {
			$data['delivery_days'] = $request->get_param( 'delivery_days' );
		}

		if ( empty( $data ) ) {
			return new WP_Error(
				'no_data',
				__( 'No data to update.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		$result = $this->proposal_service->update( $proposal_id, $data );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'update_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		$updated_proposal = $this->proposal_service->get( $proposal_id );

		return new WP_REST_Response( [
			'message' => __( 'Proposal updated successfully.', 'wp-sell-services' ),
			'data'    => $this->prepare_proposal_for_response( $updated_proposal ),
		] );
	}

	/**
	 * Withdraw proposal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function withdraw( $request ) {
		$proposal_id = (int) $request->get_param( 'id' );

		$result = $this->proposal_service->withdraw( $proposal_id, get_current_user_id() );

		if ( ! $result ) {
			return new WP_Error(
				'withdraw_failed',
				__( 'Failed to withdraw proposal.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( [ 'message' => __( 'Proposal withdrawn successfully.', 'wp-sell-services' ) ] );
	}

	/**
	 * Get proposal stats.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_stats( $request ) {
		$vendor_id = get_current_user_id();
		$stats     = $this->proposal_service->get_vendor_stats( $vendor_id );

		return new WP_REST_Response( $stats );
	}

	/**
	 * Prepare proposal for response.
	 *
	 * @param object $proposal Proposal object.
	 * @param bool   $detailed Include full details.
	 * @return array
	 */
	private function prepare_proposal_for_response( object $proposal, bool $detailed = false ): array {
		$vendor  = get_userdata( $proposal->vendor_id );
		$request = get_post( $proposal->request_id );

		$data = [
			'id'            => (int) $proposal->id,
			'request_id'    => (int) $proposal->request_id,
			'request_title' => $request ? $request->post_title : '',
			'vendor'        => [
				'id'     => (int) $proposal->vendor_id,
				'name'   => $vendor ? $vendor->display_name : '',
				'avatar' => get_avatar_url( $proposal->vendor_id, [ 'size' => 48 ] ),
			],
			'price'         => (float) $proposal->price,
			'delivery_days' => (int) $proposal->delivery_days,
			'status'        => $proposal->status,
			'created_at'    => $proposal->created_at,
		];

		if ( $detailed ) {
			$data['cover_letter'] = $proposal->cover_letter;
			$data['service_id']   = $proposal->service_id ? (int) $proposal->service_id : null;

			if ( $proposal->service_id ) {
				$data['service_title'] = get_the_title( $proposal->service_id );
			}

			// Include request details.
			if ( $request ) {
				$data['request'] = [
					'id'          => $request->ID,
					'title'       => $request->post_title,
					'budget_min'  => (float) get_post_meta( $request->ID, '_wpss_budget_min', true ),
					'budget_max'  => (float) get_post_meta( $request->ID, '_wpss_budget_max', true ),
					'deadline'    => get_post_meta( $request->ID, '_wpss_deadline', true ),
				];
			}

			if ( isset( $proposal->rejection_reason ) && $proposal->rejection_reason ) {
				$data['rejection_reason'] = $proposal->rejection_reason;
			}

			if ( isset( $proposal->withdrawal_reason ) && $proposal->withdrawal_reason ) {
				$data['withdrawal_reason'] = $proposal->withdrawal_reason;
			}
		}

		return $data;
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return [
			'page'     => [
				'description' => __( 'Current page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			],
			'per_page' => [
				'description' => __( 'Items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			],
		];
	}
}
