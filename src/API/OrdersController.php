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
use WPSellServices\CustomFields\FieldValidator;
use WPSellServices\Services\ConversationService;
use WPSellServices\Services\DeliveryService;

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
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Single order.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_item_permissions' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the order.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_item_permissions' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Order messages/conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/messages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_messages' ),
					'permission_callback' => array( $this, 'check_item_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_message' ),
					'permission_callback' => array( $this, 'check_item_permissions' ),
					'args'                => array(
						'message' => array(
							'description' => __( 'Message content.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// Order deliverables.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/deliverables',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_deliverables' ),
					'permission_callback' => array( $this, 'check_item_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_deliverable' ),
					'permission_callback' => array( $this, 'check_vendor_permissions' ),
					'args'                => array(
						'description' => array(
							'description' => __( 'Deliverable description.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'files'       => array(
							'description' => __( 'Attachment IDs.', 'wp-sell-services' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);

		// Order status actions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/(?P<action>accept|reject|start|deliver|complete|cancel|dispute)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'perform_action' ),
					'permission_callback' => array( $this, 'check_action_permissions' ),
					'args'                => array(
						'id'     => array(
							'description' => __( 'Order ID.', 'wp-sell-services' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'action' => array(
							'description' => __( 'Action to perform.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'reason' => array(
							'description' => __( 'Reason for action (required for reject, cancel, dispute).', 'wp-sell-services' ),
							'type'        => 'string',
						),
					),
				),
			)
		);

		// Order requirements.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/requirements',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_requirements' ),
					'permission_callback' => array( $this, 'check_item_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_requirements' ),
					'permission_callback' => array( $this, 'check_customer_permissions' ),
					'args'                => array(
						'requirements' => array(
							'description' => __( 'Requirements data.', 'wp-sell-services' ),
							'type'        => 'object',
							'required'    => true,
						),
					),
				),
			)
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

		$args = array(
			'limit'  => $pagination['per_page'],
			'offset' => $pagination['offset'],
		);

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

		$data = array();
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
				array( 'status' => 404 )
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
				array( 'status' => 404 )
			);
		}

		// Only allow updating certain fields.
		$allowed_fields = array( 'vendor_notes' );

		// Admin can update more fields.
		if ( current_user_can( 'manage_options' ) ) {
			$allowed_fields = array_merge( $allowed_fields, array( 'status', 'due_date' ) );
		}

		$updates = array();
		foreach ( $allowed_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$value = $request->get_param( $field );

				$updates[ $field ] = match ( $field ) {
					'vendor_notes' => sanitize_textarea_field( $value ),
					'status'       => sanitize_key( $value ),
					'due_date'     => sanitize_text_field( $value ),
					default        => sanitize_text_field( $value ),
				};
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

		$conversation_service = new ConversationService();
		$conversation         = $conversation_service->get_by_order( $order_id );

		if ( ! $conversation ) {
			// Create conversation if it doesn't exist yet.
			$conversation = $conversation_service->create_for_order( $order_id );
		}

		if ( ! $conversation ) {
			return new WP_REST_Response( array() );
		}

		$messages = $conversation_service->get_messages( (int) $conversation->id );

		$data = array();
		foreach ( $messages as $message ) {
			$user   = get_userdata( (int) $message->sender_id );
			$data[] = array(
				'id'          => (int) $message->id,
				'order_id'    => $order_id,
				'user_id'     => (int) $message->sender_id,
				'user_name'   => $user ? $user->display_name : __( 'Unknown', 'wp-sell-services' ),
				'user_avatar' => get_avatar_url( (int) $message->sender_id, array( 'size' => 48 ) ),
				'message'     => $message->content,
				'attachments' => $message->attachments ? json_decode( $message->attachments, true ) : array(),
				'is_system'   => 'system' === $message->type,
				'created_at'  => $message->created_at,
			);
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
		$order_id     = (int) $request->get_param( 'id' );
		$message_text = sanitize_textarea_field( $request->get_param( 'message' ) );
		$user_id      = get_current_user_id();

		if ( empty( $message_text ) ) {
			return new WP_Error(
				'rest_invalid_message',
				__( 'Message cannot be empty.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		$conversation_service = new ConversationService();
		$conversation         = $conversation_service->get_by_order( $order_id );

		if ( ! $conversation ) {
			$conversation = $conversation_service->create_for_order( $order_id );
		}

		if ( ! $conversation ) {
			return new WP_Error(
				'rest_conversation_failed',
				__( 'Failed to create or find conversation.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		$attachments_raw = $request->get_param( 'attachments' );
		$attachments     = is_array( $attachments_raw ) ? $attachments_raw : array();

		$message = $conversation_service->send_message(
			(int) $conversation->id,
			$user_id,
			$message_text,
			$attachments
		);

		if ( ! $message ) {
			return new WP_Error(
				'rest_message_failed',
				__( 'Failed to create message.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		// Trigger notification.
		do_action( 'wpss_order_message_created', (int) $message->id, $order_id, $user_id );

		$user = get_userdata( $user_id );

		return new WP_REST_Response(
			array(
				'id'          => (int) $message->id,
				'order_id'    => $order_id,
				'user_id'     => $user_id,
				'user_name'   => $user->display_name,
				'user_avatar' => get_avatar_url( $user_id, array( 'size' => 48 ) ),
				'message'     => $message->content,
				'attachments' => $attachments,
				'is_system'   => false,
				'created_at'  => $message->created_at,
			),
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

		$delivery_service = new DeliveryService();
		$deliveries       = $delivery_service->get_order_deliveries( $order_id );

		$data = array();
		foreach ( $deliveries as $delivery ) {
			$attachments = $delivery->attachments ? json_decode( $delivery->attachments, true ) : array();
			$file_data   = array();

			if ( is_array( $attachments ) ) {
				foreach ( $attachments as $attachment_id ) {
					$file_data[] = array(
						'id'   => $attachment_id,
						'url'  => wp_get_attachment_url( $attachment_id ),
						'name' => get_the_title( $attachment_id ),
						'type' => get_post_mime_type( $attachment_id ),
					);
				}
			}

			$data[] = array(
				'id'               => (int) $delivery->id,
				'order_id'         => (int) $delivery->order_id,
				'description'      => $delivery->message,
				'files'            => $file_data,
				'status'           => $delivery->status,
				'version'          => (int) $delivery->version,
				'response_message' => $delivery->response_message,
				'responded_at'     => $delivery->responded_at,
				'created_at'       => $delivery->created_at,
			);
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
		$files_raw   = $request->get_param( 'files' );
		$files       = is_array( $files_raw ) ? $files_raw : array();

		// Use DeliveryService to create the delivery.
		$delivery_service = new DeliveryService();
		$result           = $delivery_service->submit( $order_id, $description, $files );

		if ( ! $result ) {
			return new WP_Error(
				'rest_deliverable_failed',
				__( 'Failed to create deliverable. Order may not be in correct status.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		// Get the latest delivery to return in response.
		$deliveries = $delivery_service->get_order_deliveries( $order_id );
		$delivery   = ! empty( $deliveries ) ? end( $deliveries ) : null;

		if ( ! $delivery ) {
			return new WP_Error(
				'rest_deliverable_failed',
				__( 'Delivery created but could not be retrieved.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		// Decode attachments if JSON string.
		$attachments = $delivery->attachments ?? array();
		if ( is_string( $attachments ) ) {
			$decoded     = json_decode( $attachments, true );
			$attachments = is_array( $decoded ) ? $decoded : array();
		}

		return new WP_REST_Response(
			array(
				'id'          => (int) $delivery->id,
				'order_id'    => $order_id,
				'description' => $delivery->message ?? $description,
				'files'       => $attachments,
				'version'     => (int) ( $delivery->version ?? 1 ),
				'status'      => $delivery->status ?? 'pending',
				'created_at'  => $delivery->created_at ?? current_time( 'mysql' ),
			),
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
				array( 'status' => 404 )
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
					$result = $order->update( array( 'status' => 'accepted' ) );
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
						array(
							'status'       => 'rejected',
							'vendor_notes' => $reason,
						)
					);
					do_action( 'wpss_order_rejected', $order_id, $reason );
				}
				break;

			case 'start':
				if ( ! $is_vendor && ! $is_admin ) {
					$error = __( 'Only the vendor can start work.', 'wp-sell-services' );
				} elseif ( ! in_array( $order->status, array( 'accepted', 'requirements_submitted' ), true ) ) {
					$error = __( 'Order cannot be started in current status.', 'wp-sell-services' );
				} else {
					$result = $order->update(
						array(
							'status'     => 'in_progress',
							'started_at' => current_time( 'mysql' ),
						)
					);
					do_action( 'wpss_order_started', $order_id );
				}
				break;

			case 'deliver':
				if ( ! $is_vendor && ! $is_admin ) {
					$error = __( 'Only the vendor can deliver orders.', 'wp-sell-services' );
				} elseif ( ! in_array( $order->status, array( 'in_progress', 'revision_requested', 'late' ), true ) ) {
					$error = __( 'Order cannot be delivered in current status.', 'wp-sell-services' );
				} else {
					$result = $order->update(
						array(
							'status' => 'delivered',
						)
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
						array(
							'status'       => 'completed',
							'completed_at' => current_time( 'mysql' ),
						)
					);
					do_action( 'wpss_order_completed', $order_id );
				}
				break;

			case 'cancel':
				$can_cancel = $is_admin ||
					( $is_customer && in_array( $order->status, array( 'pending', 'accepted' ), true ) ) ||
					( $is_vendor && 'pending' === $order->status );

				if ( ! $can_cancel ) {
					$error = __( 'You cannot cancel this order in its current status.', 'wp-sell-services' );
				} elseif ( empty( $reason ) ) {
					$error = __( 'Reason is required for cancellation.', 'wp-sell-services' );
				} else {
					$cancelled_by = $is_vendor ? 'vendor' : 'customer';
					$result       = $order->update(
						array(
							'status'       => 'cancelled',
							'vendor_notes' => $reason,
						)
					);
					do_action( 'wpss_order_cancelled', $order_id, $cancelled_by, $reason );
				}
				break;

			case 'dispute':
				if ( ! $is_customer && ! $is_vendor ) {
					$error = __( 'Only order participants can open disputes.', 'wp-sell-services' );
				} elseif ( ! in_array( $order->status, array( 'in_progress', 'pending_approval', 'revision_requested', 'delivered' ), true ) ) {
					$error = __( 'Disputes can only be opened for active orders.', 'wp-sell-services' );
				} elseif ( empty( $reason ) ) {
					$error = __( 'Reason is required for disputes.', 'wp-sell-services' );
				} else {
					$opened_by = $is_vendor ? 'vendor' : 'customer';

					// Create dispute using DisputeService.
					$dispute_service = new \WPSellServices\Services\DisputeService();
					$dispute_id      = $dispute_service->open( $order_id, $user_id, $reason );

					if ( $dispute_id ) {
						$result = $order->update( array( 'status' => 'disputed' ) );
						do_action( 'wpss_order_disputed', $order_id, $opened_by, $reason );
					} else {
						$error = __( 'Failed to open dispute. A dispute may already exist for this order.', 'wp-sell-services' );
					}
				}
				break;

			default:
				$error = __( 'Invalid action.', 'wp-sell-services' );
		}

		if ( $error ) {
			return new WP_Error(
				'rest_action_failed',
				$error,
				array( 'status' => 400 )
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
				array( 'status' => 404 )
			);
		}

		// Get service requirements template.
		$service_id   = $order->service_id;
		$requirements = get_post_meta( $service_id, '_wpss_requirements', true ) ?: array();

		// Get submitted requirements from database table.
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_requirements';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT field_data, attachments, submitted_at FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				$order_id
			)
		);

		$submitted = array();
		if ( $row && ! empty( $row->field_data ) ) {
			$decoded   = json_decode( $row->field_data, true );
			$submitted = is_array( $decoded ) ? $decoded : array();
		}

		return new WP_REST_Response(
			array(
				'template'     => $requirements,
				'submitted'    => $submitted,
				'status'       => empty( $submitted ) ? 'pending' : 'submitted',
				'submitted_at' => $row->submitted_at ?? null,
			)
		);
	}

	/**
	 * Submit order requirements.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_requirements( $request ) {
		$order_id        = (int) $request->get_param( 'id' );
		$requirements    = $request->get_param( 'requirements' );
		$attachments_raw = $request->get_param( 'attachments' );
		$attachments     = is_array( $attachments_raw ) ? $attachments_raw : array();

		// Ensure requirements is an array.
		if ( ! is_array( $requirements ) ) {
			$requirements = array();
		}

		$order = ServiceOrder::find( $order_id );

		if ( ! $order ) {
			return new WP_Error(
				'rest_order_not_found',
				__( 'Order not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		// Get requirements template.
		$template = get_post_meta( $order->service_id, '_wpss_requirements', true ) ?: array();

		// Validate using FieldValidator for type-aware validation.
		$validator         = new FieldValidator();
		$validation_result = $validator->validate_all( $template, $requirements );

		if ( is_wp_error( $validation_result ) ) {
			return new WP_Error(
				'rest_validation_failed',
				implode( ' ', $validation_result->get_error_messages() ),
				array( 'status' => 400 )
			);
		}

		// Sanitize requirements using type-aware sanitization.
		$sanitized_requirements = $validator->sanitize_all( $template, $requirements );

		// Save sanitized requirements to database table.
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_requirements';

		// Check if requirements already exist for this order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE order_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				$order_id
			)
		);

		$now = current_time( 'mysql' );

		if ( $existing ) {
			// Update existing requirements.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'field_data'   => wp_json_encode( $sanitized_requirements ),
					'attachments'  => wp_json_encode( array_map( 'absint', $attachments ) ),
					'submitted_at' => $now,
				),
				array( 'id' => $existing ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new requirements.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				array(
					'order_id'     => $order_id,
					'field_data'   => wp_json_encode( $sanitized_requirements ),
					'attachments'  => wp_json_encode( array_map( 'absint', $attachments ) ),
					'submitted_at' => $now,
				),
				array( '%d', '%s', '%s', '%s' )
			);
		}

		// Update order status if pending.
		if ( in_array( $order->status, array( 'pending', 'accepted' ), true ) ) {
			$order->update( array( 'status' => 'requirements_submitted' ) );
		}

		do_action( 'wpss_order_requirements_submitted', $order_id, $sanitized_requirements );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'message'      => __( 'Requirements submitted successfully.', 'wp-sell-services' ),
				'submitted'    => $sanitized_requirements,
				'submitted_at' => $now,
			)
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
				array( 'status' => 403 )
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
				array( 'status' => 403 )
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
				array( 'status' => 403 )
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

		$data = array(
			'id'                => (int) $order->id,
			'order_number'      => $order->order_number,
			'service_id'        => (int) $order->service_id,
			'service_title'     => $service ? $service->post_title : '',
			'package_id'        => (int) $order->package_id,
			'vendor_id'         => (int) $order->vendor_id,
			'vendor_name'       => $vendor ? $vendor->display_name : '',
			'vendor_avatar'     => get_avatar_url( (int) $order->vendor_id, array( 'size' => 48 ) ),
			'customer_id'       => (int) $order->customer_id,
			'customer_name'     => $customer ? $customer->display_name : '',
			'customer_avatar'   => get_avatar_url( (int) $order->customer_id, array( 'size' => 48 ) ),
			'status'            => $order->status,
			'status_label'      => $this->get_status_label( $order->status ),
			'total'             => (float) $order->total,
			'currency'          => $order->currency,
			'formatted_total'   => wpss_format_currency( (float) $order->total, $order->currency ),
			'due_date'          => $order->delivery_deadline ? $order->delivery_deadline->format( 'Y-m-d H:i:s' ) : null,
			'started_at'        => $order->started_at ? $order->started_at->format( 'Y-m-d H:i:s' ) : null,
			'completed_at'      => $order->completed_at ? $order->completed_at->format( 'Y-m-d H:i:s' ) : null,
			'created_at'        => $order->created_at ? $order->created_at->format( 'Y-m-d H:i:s' ) : null,
			'updated_at'        => $order->updated_at ? $order->updated_at->format( 'Y-m-d H:i:s' ) : null,
			'available_actions' => $this->get_available_actions( $order ),
		);

		return new WP_REST_Response( $data );
	}

	/**
	 * Get status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_status_label( string $status ): string {
		$labels = array(
			'pending'                => __( 'Pending', 'wp-sell-services' ),
			'accepted'               => __( 'Accepted', 'wp-sell-services' ),
			'rejected'               => __( 'Rejected', 'wp-sell-services' ),
			'requirements_submitted' => __( 'Requirements Submitted', 'wp-sell-services' ),
			'in_progress'            => __( 'In Progress', 'wp-sell-services' ),
			'delivered'              => __( 'Delivered', 'wp-sell-services' ),
			'completed'              => __( 'Completed', 'wp-sell-services' ),
			'cancelled'              => __( 'Cancelled', 'wp-sell-services' ),
			'disputed'               => __( 'Disputed', 'wp-sell-services' ),
			'refunded'               => __( 'Refunded', 'wp-sell-services' ),
		);

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

		$actions = array();

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
			case 'late':
				if ( $is_vendor || $is_admin ) {
					$actions[] = 'deliver';
				}
				if ( $is_customer || $is_vendor ) {
					$actions[] = 'dispute';
				}
				break;

			case 'revision_requested':
				if ( $is_vendor || $is_admin ) {
					$actions[] = 'deliver';
				}
				if ( $is_customer || $is_vendor ) {
					$actions[] = 'dispute';
				}
				break;

			case 'pending_approval':
			case 'delivered':
				if ( $is_customer || $is_admin ) {
					$actions[] = 'complete';
					if ( $order->can_request_revision() ) {
						$actions[] = 'revision';
					}
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
		return array(
			'page'       => array(
				'description' => __( 'Current page of the collection.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'   => array(
				'description' => __( 'Maximum number of items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'role'       => array(
				'description' => __( 'Filter by user role (vendor or customer).', 'wp-sell-services' ),
				'type'        => 'string',
				'enum'        => array( 'vendor', 'customer' ),
			),
			'status'     => array(
				'description' => __( 'Filter by order status.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'service_id' => array(
				'description' => __( 'Filter by service ID.', 'wp-sell-services' ),
				'type'        => 'integer',
			),
		);
	}

	/**
	 * Get item schema.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'order',
			'type'       => 'object',
			'properties' => array_merge(
				$this->get_common_schema_properties(),
				array(
					'order_number' => array(
						'description' => __( 'Order number.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'service_id'   => array(
						'description' => __( 'Service ID.', 'wp-sell-services' ),
						'type'        => 'integer',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'vendor_id'    => array(
						'description' => __( 'Vendor user ID.', 'wp-sell-services' ),
						'type'        => 'integer',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'customer_id'  => array(
						'description' => __( 'Customer user ID.', 'wp-sell-services' ),
						'type'        => 'integer',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'status'       => array(
						'description' => __( 'Order status.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
					),
					'total'        => array(
						'description' => __( 'Order total.', 'wp-sell-services' ),
						'type'        => 'number',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'currency'     => array(
						'description' => __( 'Order currency.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'vendor_notes' => array(
						'description' => __( 'Vendor notes.', 'wp-sell-services' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
					),
					'due_date'     => array(
						'description' => __( 'Due date.', 'wp-sell-services' ),
						'type'        => 'string',
						'format'      => 'date-time',
						'context'     => array( 'view', 'edit' ),
					),
				)
			),
		);
	}
}
