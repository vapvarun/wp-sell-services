<?php
/**
 * Orders REST Controller
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
use WPSellServices\Models\ServiceOrder;

/**
 * REST API controller for orders.
 *
 * @since 1.0.0
 */
class OrdersController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'orders';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// List orders.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_permissions' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// Single order.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_item_permissions' ],
					'args'                => [
						'id' => [
							'description' => __( 'Unique identifier for the order.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						],
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_item_permissions' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// Order messages/conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/messages',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_messages' ],
					'permission_callback' => [ $this, 'check_item_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_message' ],
					'permission_callback' => [ $this, 'check_item_permissions' ],
					'args'                => [
						'message' => [
							'description' => __( 'Message content.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						],
					],
				],
			]
		);

		// Order deliverables.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/deliverables',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_deliverables' ],
					'permission_callback' => [ $this, 'check_item_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_deliverable' ],
					'permission_callback' => [ $this, 'check_vendor_permissions' ],
					'args'                => [
						'description' => [
							'description' => __( 'Deliverable description.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						],
						'files' => [
							'description' => __( 'Attachment IDs.', 'wp-sell-services' ),
							'type'        => 'array',
							'items'       => [ 'type' => 'integer' ],
						],
					],
				],
			]
		);

		// Order status actions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/(?P<action>accept|reject|start|deliver|complete|cancel|dispute)',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'perform_action' ],
					'permission_callback' => [ $this, 'check_action_permissions' ],
					'args'                => [
						'id' => [
							'description' => __( 'Order ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						],
						'action' => [
							'description' => __( 'Action to perform.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						],
						'reason' => [
							'description' => __( 'Reason for action (required for reject, cancel, dispute).', 'wp-sell-services' ),
							'type'        => 'string',
						],
					],
				],
			]
		);

		// Order requirements.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/requirements',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_requirements' ],
					'permission_callback' => [ $this, 'check_item_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'submit_requirements' ],
					'permission_callback' => [ $this, 'check_customer_permissions' ],
					'args'                => [
						'requirements' => [
							'description' => __( 'Requirements data.', 'wp-sell-services' ),
							'type'        => 'object',
							'required'    => true,
						],
					],
				],
			]
		);
	}

	/**
	 * Get orders.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$pagination = $this->get_pagination_args( $request );
		$user_id    = get_current_user_id();

		$args = [
			'limit'  => $pagination['per_page'],
			'offset' => $pagination['offset'],
		];

		// Filter by role.
		$role = $request->get_param( 'role' );
		if ( 'vendor' === $role ) {
			$args['vendor_id'] = $user_id;
		} elseif ( 'customer' === $role ) {
			$args['customer_id'] = $user_id;
		} else {
			// Default: show orders where user is either vendor or customer.
			$args['user_id'] = $user_id;
		}

		// Status filter.
		$status = $request->get_param( 'status' );
		if ( $status ) {
			$args['status'] = $status;
		}

		// Service filter.
		$service_id = $request->get_param( 'service_id' );
		if ( $service_id ) {
			$args['service_id'] = (int) $service_id;
		}

		$orders = ServiceOrder::query( $args );
		$total  = ServiceOrder::count( $args );

		$data = [];
		foreach ( $orders as $order ) {
			$data[] = $this->prepare_item_for_response( $order, $request )->get_data();
		}

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get single order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$order_id = (int) $request->get_param( 'id' );
		$order    = ServiceOrder::find( $order_id );

		if ( ! $order ) {
			return new WP_Error(
				'rest_order_not_found',
				__( 'Order not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		return $this->prepare_item_for_response( $order, $request );
	}

	/**
	 * Update order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$order_id = (int) $request->get_param( 'id' );
		$order    = ServiceOrder::find( $order_id );

		if ( ! $order ) {
			return new WP_Error(
				'rest_order_not_found',
				__( 'Order not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		// Only allow updating certain fields.
		$allowed_fields = [ 'vendor_notes' ];

		// Admin can update more fields.
		if ( current_user_can( 'manage_options' ) ) {
			$allowed_fields = array_merge( $allowed_fields, [ 'status', 'due_date' ] );
		}

		$updates = [];
		foreach ( $allowed_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$updates[ $field ] = $request->get_param( $field );
			}
		}

		if ( ! empty( $updates ) ) {
			$order->update( $updates );
		}

		return $this->prepare_item_for_response( $order, $request );
	}

	/**
	 * Get order messages.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_messages( $request ) {
		$order_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_messages';

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at ASC",
				$order_id
			)
		);

		$data = [];
		foreach ( $messages as $message ) {
			$user      = get_userdata( (int) $message->user_id );
			$data[] = [
				'id'         => (int) $message->id,
				'order_id'   => (int) $message->order_id,
				'user_id'    => (int) $message->user_id,
				'user_name'  => $user ? $user->display_name : __( 'Unknown', 'wp-sell-services' ),
				'user_avatar' => get_avatar_url( (int) $message->user_id, [ 'size' => 48 ] ),
				'message'    => $message->message,
				'attachments' => maybe_unserialize( $message->attachments ) ?: [],
				'is_system'  => (bool) $message->is_system,
				'created_at' => $message->created_at,
			];
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * Create order message.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_message( $request ) {
		$order_id = (int) $request->get_param( 'id' );
		$message  = sanitize_textarea_field( $request->get_param( 'message' ) );
		$user_id  = get_current_user_id();

		if ( empty( $message ) ) {
			return new WP_Error(
				'rest_invalid_message',
				__( 'Message cannot be empty.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_messages';

		$attachments = $request->get_param( 'attachments' ) ?: [];

		$result = $wpdb->insert(
			$table,
			[
				'order_id'    => $order_id,
				'user_id'     => $user_id,
				'message'     => $message,
				'attachments' => maybe_serialize( $attachments ),
				'is_system'   => 0,
				'created_at'  => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s', '%d', '%s' ]
		);

		if ( ! $result ) {
			return new WP_Error(
				'rest_message_failed',
				__( 'Failed to create message.', 'wp-sell-services' ),
				[ 'status' => 500 ]
			);
		}

		$message_id = $wpdb->insert_id;

		// Trigger notification.
		do_action( 'wpss_order_message_created', $message_id, $order_id, $user_id );

		$user = get_userdata( $user_id );

		return new WP_REST_Response(
			[
				'id'         => $message_id,
				'order_id'   => $order_id,
				'user_id'    => $user_id,
				'user_name'  => $user->display_name,
				'user_avatar' => get_avatar_url( $user_id, [ 'size' => 48 ] ),
				'message'    => $message,
				'attachments' => $attachments,
				'is_system'  => false,
				'created_at' => current_time( 'mysql' ),
			],
			201
		);
	}

	/**
	 * Get order deliverables.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_deliverables( $request ) {
		$order_id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_deliverables';

		$deliverables = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		$data = [];
		foreach ( $deliverables as $deliverable ) {
			$files = maybe_unserialize( $deliverable->files ) ?: [];
			$file_data = [];

			foreach ( $files as $attachment_id ) {
				$file_data[] = [
					'id'   => $attachment_id,
					'url'  => wp_get_attachment_url( $attachment_id ),
					'name' => get_the_title( $attachment_id ),
					'type' => get_post_mime_type( $attachment_id ),
				];
			}

			$data[] = [
				'id'          => (int) $deliverable->id,
				'order_id'    => (int) $deliverable->order_id,
				'description' => $deliverable->description,
				'files'       => $file_data,
				'status'      => $deliverable->status,
				'created_at'  => $deliverable->created_at,
			];
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * Create order deliverable.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_deliverable( $request ) {
		$order_id    = (int) $request->get_param( 'id' );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) );
		$files       = $request->get_param( 'files' ) ?: [];

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_deliverables';

		$result = $wpdb->insert(
			$table,
			[
				'order_id'    => $order_id,
				'description' => $description,
				'files'       => maybe_serialize( array_map( 'intval', $files ) ),
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $result ) {
			return new WP_Error(
				'rest_deliverable_failed',
				__( 'Failed to create deliverable.', 'wp-sell-services' ),
				[ 'status' => 500 ]
			);
		}

		$deliverable_id = $wpdb->insert_id;

		// Trigger notification.
		do_action( 'wpss_order_deliverable_created', $deliverable_id, $order_id );

		return new WP_REST_Response(
			[
				'id'          => $deliverable_id,
				'order_id'    => $order_id,
				'description' => $description,
				'files'       => $files,
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
			],
			201
		);
	}

	/**
	 * Perform order action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function perform_action( $request ) {
		$order_id = (int) $request->get_param( 'id' );
		$action   = $request->get_param( 'action' );
		$reason   = sanitize_textarea_field( $request->get_param( 'reason' ) ?? '' );

		$order = ServiceOrder::find( $order_id );

		if ( ! $order ) {
			return new WP_Error(
				'rest_order_not_found',
				__( 'Order not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		$user_id     = get_current_user_id();
		$is_vendor   = (int) $order->vendor_id === $user_id;
		$is_customer = (int) $order->customer_id === $user_id;
		$is_admin    = current_user_can( 'manage_options' );

		$result = false;
		$error  = null;

		switch ( $action ) {
			case 'accept':
				if ( ! $is_vendor && ! $is_admin ) {
					$error = __( 'Only the vendor can accept orders.', 'wp-sell-services' );
				} elseif ( 'pending' !== $order->status ) {
					$error = __( 'Order cannot be accepted in current status.', 'wp-sell-services' );
				} else {
					$result = $order->update( [ 'status' => 'accepted' ] );
					do_action( 'wpss_order_accepted', $order_id );
				}
				break;

			case 'reject':
				if ( ! $is_vendor && ! $is_admin ) {
					$error = __( 'Only the vendor can reject orders.', 'wp-sell-services' );
				} elseif ( 'pending' !== $order->status ) {
					$error = __( 'Order cannot be rejected in current status.', 'wp-sell-services' );
				} elseif ( empty( $reason ) ) {
					$error = __( 'Reason is required for rejection.', 'wp-sell-services' );
				} else {
					$result = $order->update(
						[
							'status'       => 'rejected',
							'vendor_notes' => $reason,
						]
					);
					do_action( 'wpss_order_rejected', $order_id, $reason );
				}
				break;

			case 'start':
				if ( ! $is_vendor && ! $is_admin ) {
					$error = __( 'Only the vendor can start work.', 'wp-sell-services' );
				} elseif ( ! in_array( $order->status, [ 'accepted', 'requirements_submitted' ], true ) ) {
					$error = __( 'Order cannot be started in current status.', 'wp-sell-services' );
				} else {
					$result = $order->update(
						[
							'status'     => 'in_progress',
							'started_at' => current_time( 'mysql' ),
						]
					);
					do_action( 'wpss_order_started', $order_id );
				}
				break;

			case 'deliver':
				if ( ! $is_vendor && ! $is_admin ) {
					$error = __( 'Only the vendor can deliver orders.', 'wp-sell-services' );
				} elseif ( 'in_progress' !== $order->status ) {
					$error = __( 'Order cannot be delivered in current status.', 'wp-sell-services' );
				} else {
					$result = $order->update(
						[
							'status'       => 'delivered',
							'delivered_at' => current_time( 'mysql' ),
						]
					);
					do_action( 'wpss_order_delivered', $order_id );
				}
				break;

			case 'complete':
				if ( ! $is_customer && ! $is_admin ) {
					$error = __( 'Only the customer can mark orders as complete.', 'wp-sell-services' );
				} elseif ( 'delivered' !== $order->status ) {
					$error = __( 'Order cannot be completed in current status.', 'wp-sell-services' );
				} else {
					$result = $order->update(
						[
							'status'       => 'completed',
							'completed_at' => current_time( 'mysql' ),
						]
					);
					do_action( 'wpss_order_completed', $order_id );
				}
				break;

			case 'cancel':
				$can_cancel = $is_admin ||
					( $is_customer && in_array( $order->status, [ 'pending', 'accepted' ], true ) ) ||
					( $is_vendor && 'pending' === $order->status );

				if ( ! $can_cancel ) {
					$error = __( 'You cannot cancel this order in its current status.', 'wp-sell-services' );
				} elseif ( empty( $reason ) ) {
					$error = __( 'Reason is required for cancellation.', 'wp-sell-services' );
				} else {
					$cancelled_by = $is_vendor ? 'vendor' : 'customer';
					$result = $order->update(
						[
							'status'       => 'cancelled',
							'vendor_notes' => $reason,
						]
					);
					do_action( 'wpss_order_cancelled', $order_id, $cancelled_by, $reason );
				}
				break;

			case 'dispute':
				if ( ! $is_customer && ! $is_vendor ) {
					$error = __( 'Only order participants can open disputes.', 'wp-sell-services' );
				} elseif ( ! in_array( $order->status, [ 'delivered', 'in_progress' ], true ) ) {
					$error = __( 'Disputes can only be opened for orders in progress or delivered.', 'wp-sell-services' );
				} elseif ( empty( $reason ) ) {
					$error = __( 'Reason is required for disputes.', 'wp-sell-services' );
				} else {
					$opened_by = $is_vendor ? 'vendor' : 'customer';
					$result = $order->update( [ 'status' => 'disputed' ] );

					// Create dispute record.
					global $wpdb;
					$wpdb->insert(
						$wpdb->prefix . 'wpss_order_disputes',
						[
							'order_id'   => $order_id,
							'opened_by'  => $user_id,
							'reason'     => $reason,
							'status'     => 'open',
							'created_at' => current_time( 'mysql' ),
						],
						[ '%d', '%d', '%s', '%s', '%s' ]
					);

					do_action( 'wpss_order_disputed', $order_id, $opened_by, $reason );
				}
				break;

			default:
				$error = __( 'Invalid action.', 'wp-sell-services' );
		}

		if ( $error ) {
			return new WP_Error(
				'rest_action_failed',
				$error,
				[ 'status' => 400 ]
			);
		}

		// Refresh order data.
		$order = ServiceOrder::find( $order_id );

		return $this->prepare_item_for_response( $order, $request );
	}

	/**
	 * Get order requirements.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_requirements( $request ) {
		$order_id = (int) $request->get_param( 'id' );
		$order    = ServiceOrder::find( $order_id );

		if ( ! $order ) {
			return new WP_Error(
				'rest_order_not_found',
				__( 'Order not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		// Get service requirements template.
		$service_id   = $order->service_id;
		$requirements = get_post_meta( $service_id, '_wpss_requirements', true ) ?: [];

		// Get submitted requirements.
		$submitted = get_post_meta( $order_id, '_wpss_submitted_requirements', true ) ?: [];

		return new WP_REST_Response(
			[
				'template'  => $requirements,
				'submitted' => $submitted,
				'status'    => empty( $submitted ) ? 'pending' : 'submitted',
			]
		);
	}

	/**
	 * Submit order requirements.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_requirements( $request ) {
		$order_id     = (int) $request->get_param( 'id' );
		$requirements = $request->get_param( 'requirements' );

		$order = ServiceOrder::find( $order_id );

		if ( ! $order ) {
			return new WP_Error(
				'rest_order_not_found',
				__( 'Order not found.', 'wp-sell-services' ),
				[ 'status' => 404 ]
			);
		}

		// Validate requirements against template.
		$template = get_post_meta( $order->service_id, '_wpss_requirements', true ) ?: [];
		$errors   = [];

		foreach ( $template as $field ) {
			$field_id = $field['id'] ?? '';
			$required = $field['required'] ?? false;

			if ( $required && empty( $requirements[ $field_id ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'wp-sell-services' ),
					$field['label'] ?? $field_id
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'rest_validation_failed',
				implode( ' ', $errors ),
				[ 'status' => 400 ]
			);
		}

		// Save requirements.
		update_post_meta( $order_id, '_wpss_submitted_requirements', $requirements );

		// Update order status if pending.
		if ( in_array( $order->status, [ 'pending', 'accepted' ], true ) ) {
			$order->update( [ 'status' => 'requirements_submitted' ] );
		}

		do_action( 'wpss_order_requirements_submitted', $order_id, $requirements );

		return new WP_REST_Response(
			[
				'success'  => true,
				'message'  => __( 'Requirements submitted successfully.', 'wp-sell-services' ),
				'submitted' => $requirements,
			]
		);
	}

	/**
	 * Check if user can access the specific order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_item_permissions( WP_REST_Request $request ) {
		$permission = $this->check_permissions( $request );

		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$order_id = (int) $request->get_param( 'id' );

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( ! $this->user_owns_resource( $order_id, 'order' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this order.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Check if user is the vendor for this order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_vendor_permissions( WP_REST_Request $request ) {
		$permission = $this->check_item_permissions( $request );

		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$order_id = (int) $request->get_param( 'id' );
		$order    = ServiceOrder::find( $order_id );

		if ( ! $order || (int) $order->vendor_id !== get_current_user_id() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Only the vendor can perform this action.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Check if user is the customer for this order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_customer_permissions( WP_REST_Request $request ) {
		$permission = $this->check_item_permissions( $request );

		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$order_id = (int) $request->get_param( 'id' );
		$order    = ServiceOrder::find( $order_id );

		if ( ! $order || (int) $order->customer_id !== get_current_user_id() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Only the customer can perform this action.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Check action-specific permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_action_permissions( WP_REST_Request $request ) {
		return $this->check_item_permissions( $request );
	}

	/**
	 * Prepare order for response.
	 *
	 * @param ServiceOrder    $order   Order object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $order, $request ): WP_REST_Response {
		$service  = get_post( $order->service_id );
		$vendor   = get_userdata( (int) $order->vendor_id );
		$customer = get_userdata( (int) $order->customer_id );

		$data = [
			'id'              => (int) $order->id,
			'order_number'    => $order->order_number,
			'service_id'      => (int) $order->service_id,
			'service_title'   => $service ? $service->post_title : '',
			'package_id'      => (int) $order->package_id,
			'vendor_id'       => (int) $order->vendor_id,
			'vendor_name'     => $vendor ? $vendor->display_name : '',
			'vendor_avatar'   => get_avatar_url( (int) $order->vendor_id, [ 'size' => 48 ] ),
			'customer_id'     => (int) $order->customer_id,
			'customer_name'   => $customer ? $customer->display_name : '',
			'customer_avatar' => get_avatar_url( (int) $order->customer_id, [ 'size' => 48 ] ),
			'status'          => $order->status,
			'status_label'    => $this->get_status_label( $order->status ),
			'total'           => (float) $order->total,
			'currency'        => $order->currency,
			'formatted_total' => wpss_format_currency( (float) $order->total, $order->currency ),
			'due_date'        => $order->due_date,
			'started_at'      => $order->started_at,
			'delivered_at'    => $order->delivered_at,
			'completed_at'    => $order->completed_at,
			'created_at'      => $order->created_at,
			'updated_at'      => $order->updated_at,
			'available_actions' => $this->get_available_actions( $order ),
		];

		return new WP_REST_Response( $data );
	}

	/**
	 * Get status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_status_label( string $status ): string {
		$labels = [
			'pending'               => __( 'Pending', 'wp-sell-services' ),
			'accepted'              => __( 'Accepted', 'wp-sell-services' ),
			'rejected'              => __( 'Rejected', 'wp-sell-services' ),
			'requirements_submitted' => __( 'Requirements Submitted', 'wp-sell-services' ),
			'in_progress'           => __( 'In Progress', 'wp-sell-services' ),
			'delivered'             => __( 'Delivered', 'wp-sell-services' ),
			'completed'             => __( 'Completed', 'wp-sell-services' ),
			'cancelled'             => __( 'Cancelled', 'wp-sell-services' ),
			'disputed'              => __( 'Disputed', 'wp-sell-services' ),
			'refunded'              => __( 'Refunded', 'wp-sell-services' ),
		];

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Get available actions for order.
	 *
	 * @param ServiceOrder $order Order object.
	 * @return array
	 */
	private function get_available_actions( ServiceOrder $order ): array {
		$user_id     = get_current_user_id();
		$is_vendor   = (int) $order->vendor_id === $user_id;
		$is_customer = (int) $order->customer_id === $user_id;
		$is_admin    = current_user_can( 'manage_options' );

		$actions = [];

		switch ( $order->status ) {
			case 'pending':
				if ( $is_vendor || $is_admin ) {
					$actions[] = 'accept';
					$actions[] = 'reject';
				}
				if ( $is_customer || $is_admin ) {
					$actions[] = 'cancel';
				}
				break;

			case 'accepted':
			case 'requirements_submitted':
				if ( $is_vendor || $is_admin ) {
					$actions[] = 'start';
				}
				if ( $is_customer || $is_admin ) {
					$actions[] = 'cancel';
				}
				break;

			case 'in_progress':
				if ( $is_vendor || $is_admin ) {
					$actions[] = 'deliver';
				}
				if ( $is_customer || $is_vendor ) {
					$actions[] = 'dispute';
				}
				break;

			case 'delivered':
				if ( $is_customer || $is_admin ) {
					$actions[] = 'complete';
				}
				if ( $is_customer || $is_vendor ) {
					$actions[] = 'dispute';
				}
				break;
		}

		return $actions;
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return [
			'page' => [
				'description' => __( 'Current page of the collection.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			],
			'per_page' => [
				'description' => __( 'Maximum number of items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
			],
			'role' => [
				'description' => __( 'Filter by user role (vendor or customer).', 'wp-sell-services' ),
				'type'        => 'string',
				'enum'        => [ 'vendor', 'customer' ],
			],
			'status' => [
				'description' => __( 'Filter by order status.', 'wp-sell-services' ),
				'type'        => 'string',
			],
			'service_id' => [
				'description' => __( 'Filter by service ID.', 'wp-sell-services' ),
				'type'        => 'integer',
			],
		];
	}

	/**
	 * Get item schema.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'order',
			'type'       => 'object',
			'properties' => array_merge(
				$this->get_common_schema_properties(),
				[
					'order_number' => [
						'description' => __( 'Order number.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => [ 'view', 'edit' ],
						'readonly'    => true,
					],
					'service_id' => [
						'description' => __( 'Service ID.', 'wp-sell-services' ),
						'type'        => 'integer',
						'context'     => [ 'view', 'edit' ],
						'readonly'    => true,
					],
					'vendor_id' => [
						'description' => __( 'Vendor user ID.', 'wp-sell-services' ),
						'type'        => 'integer',
						'context'     => [ 'view', 'edit' ],
						'readonly'    => true,
					],
					'customer_id' => [
						'description' => __( 'Customer user ID.', 'wp-sell-services' ),
						'type'        => 'integer',
						'context'     => [ 'view', 'edit' ],
						'readonly'    => true,
					],
					'status' => [
						'description' => __( 'Order status.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => [ 'view', 'edit' ],
					],
					'total' => [
						'description' => __( 'Order total.', 'wp-sell-services' ),
						'type'        => 'number',
						'context'     => [ 'view', 'edit' ],
						'readonly'    => true,
					],
					'currency' => [
						'description' => __( 'Order currency.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => [ 'view', 'edit' ],
						'readonly'    => true,
					],
					'vendor_notes' => [
						'description' => __( 'Vendor notes.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => [ 'view', 'edit' ],
					],
					'due_date' => [
						'description' => __( 'Due date.', 'wp-sell-services' ),
						'type'        => 'string',
						'format'      => 'date-time',
						'context'     => [ 'view', 'edit' ],
					],
				]
			),
		];
	}
}
