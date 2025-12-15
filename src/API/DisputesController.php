<?php
/**
 * Disputes REST Controller
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
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\DisputeWorkflowManager;

/**
 * REST API controller for disputes.
 *
 * @since 1.0.0
 */
class DisputesController extends RestController {

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $rest_base = 'disputes';

	/**
	 * Dispute service.
	 *
	 * @var DisputeService
	 */
	private DisputeService $dispute_service;

	/**
	 * Workflow manager.
	 *
	 * @var DisputeWorkflowManager
	 */
	private DisputeWorkflowManager $workflow_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->dispute_service  = new DisputeService();
		$this->workflow_manager = new DisputeWorkflowManager();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get user's disputes.
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
								'enum' => array_keys( DisputeService::get_statuses() ),
							],
						]
					),
				],
			]
		);

		// Open a dispute.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/dispute',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_order_permission' ],
					'args'                => [
						'order_id'    => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'reason'      => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);

		// Get single dispute.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_dispute_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
			]
		);

		// Get dispute by order.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/dispute',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_by_order' ],
					'permission_callback' => [ $this, 'check_order_permission' ],
					'args'                => [
						'order_id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
			]
		);

		// Submit response to dispute.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/respond',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'submit_response' ],
					'permission_callback' => [ $this, 'check_dispute_permission' ],
					'args'                => [
						'id'          => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'response'    => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
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

		// Add evidence.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/evidence',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_evidence' ],
					'permission_callback' => [ $this, 'check_dispute_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_evidence' ],
					'permission_callback' => [ $this, 'check_dispute_permission' ],
					'args'                => [
						'id'          => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'type'        => [
							'required' => true,
							'type'     => 'string',
							'enum'     => [ 'text', 'image', 'file', 'link' ],
						],
						'content'     => [
							'required' => true,
							'type'     => 'string',
						],
						'description' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);

		// Get timeline.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/timeline',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_timeline' ],
					'permission_callback' => [ $this, 'check_dispute_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
			]
		);

		// Escalate dispute.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/escalate',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'escalate' ],
					'permission_callback' => [ $this, 'check_dispute_permission' ],
					'args'                => [
						'id'     => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'reason' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);

		// Cancel dispute.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/cancel',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'cancel' ],
					'permission_callback' => [ $this, 'check_dispute_permission' ],
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

		// Admin: Resolve dispute.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/resolve',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'resolve' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
					'args'                => [
						'id'            => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'resolution'    => [
							'required' => true,
							'type'     => 'string',
							'enum'     => array_keys( DisputeService::get_resolution_types() ),
						],
						'notes'         => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'refund_amount' => [
							'type'    => 'number',
							'default' => 0,
						],
					],
				],
			]
		);

		// Admin: Assign dispute.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/assign',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'assign' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
					'args'                => [
						'id'       => [
							'validate_callback' => [ $this, 'validate_id' ],
						],
						'admin_id' => [
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => [ $this, 'validate_id' ],
						],
					],
				],
			]
		);

		// Get statuses and resolution types.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/options',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_options' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Check if user can access dispute.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_dispute_permission( WP_REST_Request $request ) {
		$permission = $this->check_permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$dispute_id = (int) $request->get_param( 'id' );
		$dispute    = $this->dispute_service->get( $dispute_id );

		if ( ! $dispute ) {
			return new WP_Error(
				'dispute_not_found',
				__( 'Dispute not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		// Check if user is part of the order or admin.
		if ( ! $this->user_owns_resource( $dispute->order_id, 'order' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this dispute.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Check if user can access order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_order_permission( WP_REST_Request $request ) {
		$permission = $this->check_permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$order_id = (int) $request->get_param( 'order_id' );

		if ( ! $this->user_owns_resource( $order_id, 'order' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this order.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Get user's disputes.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$user_id    = get_current_user_id();
		$pagination = $this->get_pagination_args( $request );
		$status     = $request->get_param( 'status' );

		$args = [
			'limit'  => $pagination['per_page'],
			'offset' => $pagination['offset'],
		];

		if ( $status ) {
			$args['status'] = $status;
		}

		if ( current_user_can( 'manage_options' ) ) {
			$disputes = $this->dispute_service->get_all( $args );
			$total    = count( $this->dispute_service->count_by_status() );
		} else {
			$disputes = $this->dispute_service->get_by_user( $user_id, $args );
			$total    = count( $disputes ); // Simplified count.
		}

		$data = array_map( [ $this, 'prepare_dispute_for_response' ], $disputes );

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get single dispute.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$dispute_id = (int) $request->get_param( 'id' );
		$dispute    = $this->dispute_service->get( $dispute_id );

		return new WP_REST_Response( $this->prepare_dispute_for_response( $dispute, true ) );
	}

	/**
	 * Get dispute by order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_by_order( $request ) {
		$order_id = (int) $request->get_param( 'order_id' );
		$dispute  = $this->dispute_service->get_by_order( $order_id );

		if ( ! $dispute ) {
			return new WP_Error(
				'dispute_not_found',
				__( 'No dispute found for this order.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		return new WP_REST_Response( $this->prepare_dispute_for_response( $dispute, true ) );
	}

	/**
	 * Create a dispute.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$order_id    = (int) $request->get_param( 'order_id' );
		$user_id     = get_current_user_id();
		$reason      = $request->get_param( 'reason' );
		$description = $request->get_param( 'description' );

		$dispute_id = $this->dispute_service->open( $order_id, $user_id, $reason, $description );

		if ( ! $dispute_id ) {
			return new WP_Error(
				'dispute_create_failed',
				__( 'Failed to open dispute. A dispute may already exist for this order.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		$dispute = $this->dispute_service->get( $dispute_id );

		return new WP_REST_Response(
			[
				'message' => __( 'Dispute opened successfully.', 'wp-sell-services' ),
				'data'    => $this->prepare_dispute_for_response( $dispute ),
			],
			201
		);
	}

	/**
	 * Submit response to dispute.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_response( $request ) {
		$dispute_id  = (int) $request->get_param( 'id' );
		$user_id     = get_current_user_id();
		$response    = $request->get_param( 'response' );
		$attachments = $request->get_param( 'attachments' );

		$result = $this->workflow_manager->submit_response( $dispute_id, $user_id, $response, $attachments );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'response_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Get dispute evidence.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_evidence( $request ) {
		$dispute_id = (int) $request->get_param( 'id' );
		$evidence   = $this->dispute_service->get_evidence( $dispute_id );

		$data = array_map( [ $this, 'prepare_evidence_for_response' ], $evidence );

		return new WP_REST_Response( $data );
	}

	/**
	 * Add evidence to dispute.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_evidence( $request ) {
		$dispute_id  = (int) $request->get_param( 'id' );
		$user_id     = get_current_user_id();
		$type        = $request->get_param( 'type' );
		$content     = $request->get_param( 'content' );
		$description = $request->get_param( 'description' ) ?? '';

		$evidence_id = $this->dispute_service->add_evidence( $dispute_id, $user_id, $type, $content, $description );

		if ( ! $evidence_id ) {
			return new WP_Error(
				'evidence_add_failed',
				__( 'Failed to add evidence.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response(
			[
				'message'     => __( 'Evidence added successfully.', 'wp-sell-services' ),
				'evidence_id' => $evidence_id,
			],
			201
		);
	}

	/**
	 * Get dispute timeline.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_timeline( $request ) {
		$dispute_id = (int) $request->get_param( 'id' );
		$timeline   = $this->workflow_manager->get_timeline( $dispute_id );

		return new WP_REST_Response( $timeline );
	}

	/**
	 * Escalate dispute.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function escalate( $request ) {
		$dispute_id = (int) $request->get_param( 'id' );
		$user_id    = get_current_user_id();
		$reason     = $request->get_param( 'reason' );

		$result = $this->workflow_manager->escalate( $dispute_id, $reason, $user_id );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'escalate_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Cancel dispute.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel( $request ) {
		$dispute_id = (int) $request->get_param( 'id' );
		$user_id    = get_current_user_id();
		$reason     = $request->get_param( 'reason' ) ?? '';

		$result = $this->workflow_manager->cancel( $dispute_id, $user_id, $reason );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'cancel_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Resolve dispute (admin only).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function resolve( $request ) {
		$dispute_id    = (int) $request->get_param( 'id' );
		$admin_id      = get_current_user_id();
		$resolution    = $request->get_param( 'resolution' );
		$notes         = $request->get_param( 'notes' );
		$refund_amount = (float) $request->get_param( 'refund_amount' );

		$result = $this->dispute_service->resolve( $dispute_id, $resolution, $notes, $admin_id, $refund_amount );

		if ( ! $result ) {
			return new WP_Error(
				'resolve_failed',
				__( 'Failed to resolve dispute.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => __( 'Dispute resolved successfully.', 'wp-sell-services' ),
		] );
	}

	/**
	 * Assign dispute to admin.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function assign( $request ) {
		$dispute_id = (int) $request->get_param( 'id' );
		$admin_id   = (int) $request->get_param( 'admin_id' );

		$result = $this->workflow_manager->assign_to_admin( $dispute_id, $admin_id );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'assign_failed',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Get dispute options.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_options( $request ) {
		return new WP_REST_Response( [
			'statuses'         => DisputeService::get_statuses(),
			'resolution_types' => DisputeService::get_resolution_types(),
		] );
	}

	/**
	 * Prepare dispute for response.
	 *
	 * @param object $dispute Dispute object.
	 * @param bool   $detailed Include full details.
	 * @return array
	 */
	private function prepare_dispute_for_response( object $dispute, bool $detailed = false ): array {
		$opener = get_userdata( $dispute->opened_by );

		$data = [
			'id'          => (int) $dispute->id,
			'order_id'    => (int) $dispute->order_id,
			'reason'      => $dispute->reason,
			'status'      => $dispute->status,
			'status_label' => DisputeService::get_statuses()[ $dispute->status ] ?? $dispute->status,
			'opened_by'   => [
				'id'     => (int) $dispute->opened_by,
				'name'   => $opener ? $opener->display_name : '',
				'avatar' => get_avatar_url( $dispute->opened_by, [ 'size' => 48 ] ),
			],
			'created_at'  => $dispute->created_at,
			'updated_at'  => $dispute->updated_at,
		];

		if ( $detailed ) {
			$data['description']       = $dispute->description;
			$data['meta']              = $dispute->meta ?? [];
			$data['response_deadline'] = $dispute->response_deadline ?? null;
			$data['resolved_at']       = $dispute->resolved_at ?? null;

			// Get assigned admin if any.
			if ( isset( $dispute->meta['assigned_to'] ) ) {
				$admin = get_userdata( $dispute->meta['assigned_to'] );
				$data['assigned_to'] = [
					'id'   => $dispute->meta['assigned_to'],
					'name' => $admin ? $admin->display_name : '',
				];
			}

			// Get resolution details if resolved.
			if ( isset( $dispute->meta['resolution'] ) ) {
				$data['resolution'] = $dispute->meta['resolution'];
			}
		}

		return $data;
	}

	/**
	 * Prepare evidence for response.
	 *
	 * @param object $evidence Evidence object.
	 * @return array
	 */
	private function prepare_evidence_for_response( object $evidence ): array {
		$user = get_userdata( $evidence->user_id );

		return [
			'id'          => (int) $evidence->id,
			'type'        => $evidence->type,
			'content'     => $evidence->content,
			'description' => $evidence->description,
			'user'        => [
				'id'     => (int) $evidence->user_id,
				'name'   => $user ? $user->display_name : '',
				'avatar' => get_avatar_url( $evidence->user_id, [ 'size' => 48 ] ),
			],
			'created_at'  => $evidence->created_at,
		];
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
