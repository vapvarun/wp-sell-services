<?php
/**
 * Buyer Requests REST Controller
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
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ProposalService;

/**
 * REST API controller for buyer requests.
 *
 * @since 1.0.0
 */
class BuyerRequestsController extends RestController {

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $rest_base = 'buyer-requests';

	/**
	 * Buyer request service.
	 *
	 * @var BuyerRequestService
	 */
	private BuyerRequestService $request_service;

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
		$this->request_service  = new BuyerRequestService();
		$this->proposal_service = new ProposalService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get all buyer requests (public for vendors).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => '__return_true',
					'args'                => array_merge(
						$this->get_collection_params(),
						[
							'category' => [
								'type'              => 'integer',
								'sanitize_callback' => 'absint',
							],
							'budget_min' => [
								'type'              => 'number',
								'sanitize_callback' => 'floatval',
							],
							'budget_max' => [
								'type'              => 'number',
								'sanitize_callback' => 'floatval',
							],
							'search' => [
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_text_field',
							],
						]
					),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_permissions' ],
					'args'                => [
						'title'       => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						],
						'category'    => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'budget_min'  => [
							'type'              => 'number',
							'sanitize_callback' => 'floatval',
						],
						'budget_max'  => [
							'type'              => 'number',
							'sanitize_callback' => 'floatval',
						],
						'deadline'    => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'attachments' => [
							'type'    => 'array',
							'items'   => [ 'type' => 'integer' ],
							'default' => [],
						],
					],
				],
			]
		);

		// Get user's own requests.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/mine',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_my_requests' ],
					'permission_callback' => [ $this, 'check_permissions' ],
					'args'                => $this->get_collection_params(),
				],
			]
		);

		// Get single request.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_owner_permission' ],
					'args'                => [
						'id'          => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'title'       => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description' => [
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						],
						'budget_min'  => [
							'type'              => 'number',
							'sanitize_callback' => 'floatval',
						],
						'budget_max'  => [
							'type'              => 'number',
							'sanitize_callback' => 'floatval',
						],
						'deadline'    => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'status'      => [
							'type' => 'string',
							'enum' => [ 'open', 'closed', 'hired' ],
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'check_owner_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
			]
		);

		// Get proposals for a request (owner only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/proposals',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_proposals' ],
					'permission_callback' => [ $this, 'check_owner_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
			]
		);

		// Submit proposal (vendor).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/proposals',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'submit_proposal' ],
					'permission_callback' => [ $this, 'check_vendor_permission' ],
					'args'                => [
						'id'              => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'cover_letter'    => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						],
						'price'           => [
							'required'          => true,
							'type'              => 'number',
							'sanitize_callback' => 'floatval',
						],
						'delivery_days'   => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'service_id'      => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// Accept proposal.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/proposals/(?P<proposal_id>[\d]+)/accept',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'accept_proposal' ],
					'permission_callback' => [ $this, 'check_owner_permission' ],
					'args'                => [
						'id'          => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'proposal_id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
			]
		);

		// Reject proposal.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/proposals/(?P<proposal_id>[\d]+)/reject',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'reject_proposal' ],
					'permission_callback' => [ $this, 'check_owner_permission' ],
					'args'                => [
						'id'          => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'proposal_id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'reason'      => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);
	}

	/**
	 * Check if user is the request owner.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_owner_permission( WP_REST_Request $request ) {
		$permission = $this->check_permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$request_id    = (int) $request->get_param( 'id' );
		$buyer_request = get_post( $request_id );

		if ( ! $buyer_request || 'wpss_request' !== $buyer_request->post_type ) {
			return new WP_Error(
				'request_not_found',
				__( 'Buyer request not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		if ( (int) $buyer_request->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this request.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Check if user is a vendor.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_vendor_permission( WP_REST_Request $request ) {
		$permission = $this->check_permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		// Check if user is a vendor using the canonical helper.
		if ( ! wpss_is_vendor() ) {
			return new WP_Error(
				'not_vendor',
				__( 'You must be a vendor to submit proposals.', 'wp-sell-services' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get all buyer requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$pagination = $this->get_pagination_args( $request );

		$args = [
			'limit'  => $pagination['per_page'],
			'offset' => $pagination['offset'],
			'status' => 'open',
		];

		if ( $request->get_param( 'category' ) ) {
			$args['category'] = (int) $request->get_param( 'category' );
		}

		if ( $request->get_param( 'budget_min' ) ) {
			$args['budget_min'] = (float) $request->get_param( 'budget_min' );
		}

		if ( $request->get_param( 'budget_max' ) ) {
			$args['budget_max'] = (float) $request->get_param( 'budget_max' );
		}

		if ( $request->get_param( 'search' ) ) {
			$args['search'] = $request->get_param( 'search' );
		}

		$requests = $this->request_service->get_all( $args );
		$total    = $this->request_service->count( $args );

		$data = array_map( [ $this, 'prepare_request_for_response' ], $requests );

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get user's own requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_my_requests( $request ) {
		$user_id    = get_current_user_id();
		$pagination = $this->get_pagination_args( $request );

		$requests = $this->request_service->get_by_user( $user_id, [
			'limit'  => $pagination['per_page'],
			'offset' => $pagination['offset'],
		] );

		$total = $this->request_service->count_by_user( $user_id );

		$data = array_map( function ( $req ) {
			return $this->prepare_request_for_response( $req, true );
		}, $requests );

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get single request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$request_id    = (int) $request->get_param( 'id' );
		$buyer_request = $this->request_service->get( $request_id );

		if ( ! $buyer_request ) {
			return new WP_Error(
				'request_not_found',
				__( 'Buyer request not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		// Check if owner for detailed view.
		$is_owner = is_user_logged_in() && (int) $buyer_request['author_id'] === get_current_user_id();

		return new WP_REST_Response( $this->prepare_request_for_response( (object) $buyer_request, $is_owner ) );
	}

	/**
	 * Create buyer request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$user_id = get_current_user_id();

		$data = [
			'title'       => $request->get_param( 'title' ),
			'description' => $request->get_param( 'description' ),
			'category'    => $request->get_param( 'category' ),
			'budget_min'  => $request->get_param( 'budget_min' ),
			'budget_max'  => $request->get_param( 'budget_max' ),
			'deadline'    => $request->get_param( 'deadline' ),
			'attachments' => $request->get_param( 'attachments' ),
		];

		$result = $this->request_service->create( $user_id, $data );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'create_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		$buyer_request = $this->request_service->get( $result['request_id'] );

		return new WP_REST_Response(
			[
				'message' => __( 'Request created successfully.', 'wp-sell-services' ),
				'data'    => $this->prepare_request_for_response( (object) $buyer_request ),
			],
			201
		);
	}

	/**
	 * Update buyer request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$request_id = (int) $request->get_param( 'id' );

		$data = [];

		if ( $request->get_param( 'title' ) ) {
			$data['title'] = $request->get_param( 'title' );
		}

		if ( $request->get_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}

		if ( $request->get_param( 'budget_min' ) !== null ) {
			$data['budget_min'] = $request->get_param( 'budget_min' );
		}

		if ( $request->get_param( 'budget_max' ) !== null ) {
			$data['budget_max'] = $request->get_param( 'budget_max' );
		}

		if ( $request->get_param( 'deadline' ) ) {
			$data['deadline'] = $request->get_param( 'deadline' );
		}

		if ( $request->get_param( 'status' ) ) {
			$data['status'] = $request->get_param( 'status' );
		}

		$result = $this->request_service->update( $request_id, $data );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'update_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		$buyer_request = $this->request_service->get( $request_id );

		return new WP_REST_Response( [
			'message' => __( 'Request updated successfully.', 'wp-sell-services' ),
			'data'    => $this->prepare_request_for_response( (object) $buyer_request ),
		] );
	}

	/**
	 * Delete buyer request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$request_id = (int) $request->get_param( 'id' );

		$result = $this->request_service->delete( $request_id );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'delete_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( [ 'message' => __( 'Request deleted successfully.', 'wp-sell-services' ) ] );
	}

	/**
	 * Get proposals for a request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_proposals( $request ) {
		$request_id = (int) $request->get_param( 'id' );
		$proposals  = $this->proposal_service->get_by_request( $request_id );

		$data = array_map( [ $this, 'prepare_proposal_for_response' ], $proposals );

		return new WP_REST_Response( $data );
	}

	/**
	 * Submit proposal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_proposal( $request ) {
		$request_id = (int) $request->get_param( 'id' );
		$vendor_id  = get_current_user_id();

		$data = [
			'cover_letter'  => $request->get_param( 'cover_letter' ),
			'price'         => $request->get_param( 'price' ),
			'delivery_days' => $request->get_param( 'delivery_days' ),
			'service_id'    => $request->get_param( 'service_id' ),
		];

		$result = $this->proposal_service->submit( $request_id, $vendor_id, $data );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'proposal_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response(
			[
				'message'     => __( 'Proposal submitted successfully.', 'wp-sell-services' ),
				'proposal_id' => $result['proposal_id'],
			],
			201
		);
	}

	/**
	 * Accept proposal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function accept_proposal( $request ) {
		$request_id  = (int) $request->get_param( 'id' );
		$proposal_id = (int) $request->get_param( 'proposal_id' );

		$result = $this->request_service->convert_to_order( $request_id, $proposal_id );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'accept_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( [
			'message'  => __( 'Proposal accepted. Order created.', 'wp-sell-services' ),
			'order_id' => $result['order_id'],
		] );
	}

	/**
	 * Reject proposal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject_proposal( $request ) {
		$proposal_id = (int) $request->get_param( 'proposal_id' );
		$reason      = $request->get_param( 'reason' ) ?? '';

		$result = $this->proposal_service->reject( $proposal_id, get_current_user_id(), $reason );

		if ( ! $result ) {
			return new WP_Error(
				'reject_failed',
				__( 'Failed to reject proposal.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( [ 'message' => __( 'Proposal rejected.', 'wp-sell-services' ) ] );
	}

	/**
	 * Prepare request for response.
	 *
	 * @param object $buyer_request Request object.
	 * @param bool   $is_owner Whether current user is owner.
	 * @return array
	 */
	private function prepare_request_for_response( object $buyer_request, bool $is_owner = false ): array {
		$author_id = $buyer_request->author_id ?? $buyer_request->post_author ?? 0;
		$author    = get_userdata( $author_id );

		$data = [
			'id'             => (int) ( $buyer_request->id ?? $buyer_request->ID ),
			'title'          => $buyer_request->title ?? $buyer_request->post_title ?? '',
			'description'    => $buyer_request->description ?? $buyer_request->post_content ?? '',
			'status'         => $buyer_request->status ?? 'open',
			'budget_min'     => (float) ( $buyer_request->budget_min ?? 0 ),
			'budget_max'     => (float) ( $buyer_request->budget_max ?? 0 ),
			'deadline'       => $buyer_request->deadline ?? null,
			'category'       => $buyer_request->category ?? null,
			'proposal_count' => (int) ( $buyer_request->proposal_count ?? 0 ),
			'author'         => [
				'id'     => (int) $author_id,
				'name'   => $author ? $author->display_name : '',
				'avatar' => get_avatar_url( $author_id, [ 'size' => 48 ] ),
			],
			'created_at'     => $buyer_request->created_at ?? $buyer_request->post_date ?? '',
		];

		// Add attachments if owner.
		if ( $is_owner && isset( $buyer_request->attachments ) ) {
			$data['attachments'] = $this->get_attachment_urls( $buyer_request->attachments );
		}

		return $data;
	}

	/**
	 * Prepare proposal for response.
	 *
	 * @param object $proposal Proposal object.
	 * @return array
	 */
	private function prepare_proposal_for_response( object $proposal ): array {
		$vendor = get_userdata( $proposal->vendor_id );

		return [
			'id'            => (int) $proposal->id,
			'vendor'        => [
				'id'     => (int) $proposal->vendor_id,
				'name'   => $vendor ? $vendor->display_name : '',
				'avatar' => get_avatar_url( $proposal->vendor_id, [ 'size' => 48 ] ),
			],
			'cover_letter'  => $proposal->cover_letter,
			'price'         => (float) $proposal->price,
			'delivery_days' => (int) $proposal->delivery_days,
			'service_id'    => $proposal->service_id ? (int) $proposal->service_id : null,
			'status'        => $proposal->status,
			'created_at'    => $proposal->created_at,
		];
	}

	/**
	 * Get attachment URLs.
	 *
	 * @param mixed $attachments Attachment IDs.
	 * @return array
	 */
	private function get_attachment_urls( $attachments ): array {
		if ( ! is_array( $attachments ) ) {
			$attachments = json_decode( $attachments, true ) ?: [];
		}

		$urls = [];
		foreach ( $attachments as $id ) {
			$url = wp_get_attachment_url( $id );
			if ( $url ) {
				$urls[] = [
					'id'       => $id,
					'url'      => $url,
					'filename' => basename( get_attached_file( $id ) ),
					'type'     => get_post_mime_type( $id ),
				];
			}
		}
		return $urls;
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
