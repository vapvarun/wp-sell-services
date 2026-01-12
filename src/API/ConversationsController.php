<?php
/**
 * Conversations REST Controller
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
use WPSellServices\Services\ConversationService;

/**
 * REST API controller for conversations and messages.
 *
 * @since 1.0.0
 */
class ConversationsController extends RestController {

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $rest_base = 'conversations';

	/**
	 * Conversation service.
	 *
	 * @var ConversationService
	 */
	private ConversationService $conversation_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->conversation_service = new ConversationService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get user's conversations.
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
			)
		);

		// Get single conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_conversation_permission' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => array( $this, 'validate_id' ),
						),
					),
				),
			)
		);

		// Get conversation by order.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/conversation',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_by_order' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
					'args'                => array(
						'order_id' => array(
							'validate_callback' => array( $this, 'validate_id' ),
						),
					),
				),
			)
		);

		// Get messages in a conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/messages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_messages' ),
					'permission_callback' => array( $this, 'check_conversation_permission' ),
					'args'                => array_merge(
						array(
							'id' => array(
								'validate_callback' => array( $this, 'validate_id' ),
							),
						),
						$this->get_collection_params()
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_message' ),
					'permission_callback' => array( $this, 'check_conversation_permission' ),
					'args'                => array(
						'id'          => array(
							'validate_callback' => array( $this, 'validate_id' ),
						),
						'content'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						),
						'attachments' => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'integer' ),
							'default' => array(),
						),
					),
				),
			)
		);

		// Mark messages as read.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/read',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_as_read' ),
					'permission_callback' => array( $this, 'check_conversation_permission' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => array( $this, 'validate_id' ),
						),
					),
				),
			)
		);

		// Get unread count.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/unread-count',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_unread_count' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Check if user can access conversation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_conversation_permission( WP_REST_Request $request ) {
		$permission = $this->check_permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$conversation_id = (int) $request->get_param( 'id' );
		$conversation    = $this->conversation_service->get( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error(
				'conversation_not_found',
				__( 'Conversation not found.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		$user_id = get_current_user_id();

		if ( ! $this->conversation_service->user_can_access( $conversation_id, $user_id ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this conversation.', 'wp-sell-services' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check if user can access order conversation.
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
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get user's conversations.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$user_id    = get_current_user_id();
		$pagination = $this->get_pagination_args( $request );

		$conversations = $this->conversation_service->get_by_user(
			$user_id,
			array(
				'limit'  => $pagination['per_page'],
				'offset' => $pagination['offset'],
			)
		);

		$total = $this->conversation_service->count_by_user( $user_id );

		$data = array_map( array( $this, 'prepare_conversation_for_response' ), $conversations );

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get single conversation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$conversation_id = (int) $request->get_param( 'id' );
		$conversation    = $this->conversation_service->get( $conversation_id );

		return new WP_REST_Response( $this->prepare_conversation_for_response( $conversation ) );
	}

	/**
	 * Get conversation by order.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_by_order( $request ) {
		$order_id     = (int) $request->get_param( 'order_id' );
		$conversation = $this->conversation_service->get_by_order( $order_id );

		if ( ! $conversation ) {
			return new WP_Error(
				'conversation_not_found',
				__( 'No conversation found for this order.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->prepare_conversation_for_response( $conversation ) );
	}

	/**
	 * Get messages in a conversation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_messages( $request ) {
		$conversation_id = (int) $request->get_param( 'id' );
		$pagination      = $this->get_pagination_args( $request );

		$messages = $this->conversation_service->get_messages(
			$conversation_id,
			array(
				'limit'  => $pagination['per_page'],
				'offset' => $pagination['offset'],
			)
		);

		$total = $this->conversation_service->count_messages( $conversation_id );

		$data = array_map( array( $this, 'prepare_message_for_response' ), $messages );

		return $this->paginated_response( $data, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Send a message.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_message( $request ) {
		$conversation_id = (int) $request->get_param( 'id' );
		$user_id         = get_current_user_id();
		$content         = $request->get_param( 'content' );
		$attachments     = $request->get_param( 'attachments' );

		$message = $this->conversation_service->send_message( $conversation_id, $user_id, $content, $attachments );

		if ( ! $message ) {
			return new WP_Error(
				'message_send_failed',
				__( 'Failed to send message. You may not have permission to message in this conversation.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Message sent successfully.', 'wp-sell-services' ),
				'data'    => $this->prepare_message_for_response( $message ),
			),
			201
		);
	}

	/**
	 * Mark conversation messages as read.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mark_as_read( $request ) {
		$conversation_id = (int) $request->get_param( 'id' );
		$user_id         = get_current_user_id();

		$result = $this->conversation_service->mark_as_read( $conversation_id, $user_id );

		if ( ! $result ) {
			return new WP_Error(
				'mark_read_failed',
				__( 'Failed to mark messages as read.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response( array( 'message' => __( 'Messages marked as read.', 'wp-sell-services' ) ) );
	}

	/**
	 * Get unread message count.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_unread_count( $request ) {
		$user_id = get_current_user_id();
		$count   = $this->conversation_service->get_unread_count( $user_id );

		return new WP_REST_Response( array( 'unread_count' => $count ) );
	}

	/**
	 * Prepare conversation for response.
	 *
	 * @param object $conversation Conversation object.
	 * @return array
	 */
	private function prepare_conversation_for_response( object $conversation ): array {
		$user_id = get_current_user_id();

		// Get the other participant from participants array.
		$participants  = $conversation->participants ?? array();
		$other_user_id = 0;
		foreach ( $participants as $participant_id ) {
			if ( (int) $participant_id !== $user_id ) {
				$other_user_id = (int) $participant_id;
				break;
			}
		}
		$other_user = $other_user_id ? get_userdata( $other_user_id ) : null;

		// Get service_id from the associated order.
		$order      = $conversation->get_order();
		$service_id = $order ? (int) $order->service_id : 0;

		$last_message = $this->conversation_service->get_last_message( $conversation->id );

		return array(
			'id'            => (int) $conversation->id,
			'order_id'      => (int) $conversation->order_id,
			'subject'       => $conversation->subject ?? '',
			'service_id'    => $service_id,
			'service_title' => $service_id ? get_the_title( $service_id ) : '',
			'other_user'    => array(
				'id'     => $other_user_id,
				'name'   => $other_user ? $other_user->display_name : '',
				'avatar' => get_avatar_url( $other_user_id, array( 'size' => 48 ) ),
			),
			'last_message'  => $last_message ? array(
				'content'    => wp_trim_words( wp_strip_all_tags( $last_message->content ), 10 ),
				'sender_id'  => (int) $last_message->sender_id,
				'created_at' => $last_message->created_at ?? null,
			) : null,
			'unread_count'  => $this->conversation_service->get_unread_count_for_conversation( $conversation->id, $user_id ),
			'is_closed'     => $conversation->is_closed ?? false,
			'created_at'    => $conversation->created_at ?? null,
			'updated_at'    => $conversation->updated_at ?? null,
		);
	}

	/**
	 * Prepare message for response.
	 *
	 * @param object $message Message object.
	 * @return array
	 */
	private function prepare_message_for_response( object $message ): array {
		$user_id     = get_current_user_id();
		$sender      = get_userdata( $message->sender_id );
		$attachments = array();

		// Handle attachments - could be array already or JSON string.
		$attachment_data = $message->attachments ?? array();
		if ( is_string( $attachment_data ) ) {
			$decoded         = json_decode( $attachment_data, true );
			$attachment_data = $decoded ? $decoded : array();
		}

		foreach ( $attachment_data as $attachment ) {
			// Support both attachment ID format and full object format.
			if ( is_array( $attachment ) && isset( $attachment['id'] ) ) {
				$attachments[] = $attachment;
			} elseif ( is_numeric( $attachment ) ) {
				$id  = (int) $attachment;
				$url = wp_get_attachment_url( $id );
				if ( $url ) {
					$attached_file = get_attached_file( $id );
					$attachments[] = array(
						'id'        => $id,
						'url'       => $url,
						'filename'  => $attached_file ? basename( $attached_file ) : '',
						'type'      => get_post_mime_type( $id ),
						'thumbnail' => wp_get_attachment_image_url( $id, 'thumbnail' ),
					);
				}
			}
		}

		// Check if current user has read this message.
		$read_by = $message->read_by ?? array();
		$is_read = ! empty( $read_by[ $user_id ] );

		return array(
			'id'          => (int) $message->id,
			'type'        => $message->type ?? 'text',
			'sender'      => array(
				'id'     => (int) $message->sender_id,
				'name'   => $sender ? $sender->display_name : ( 0 === $message->sender_id ? __( 'System', 'wp-sell-services' ) : '' ),
				'avatar' => $message->sender_id ? get_avatar_url( $message->sender_id, array( 'size' => 48 ) ) : '',
			),
			'content'     => $message->content ?? '',
			'attachments' => $attachments,
			'is_read'     => $is_read,
			'is_edited'   => $message->is_edited ?? false,
			'created_at'  => $message->created_at ?? null,
		);
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return array(
			'page'     => array(
				'description' => __( 'Current page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Items per page.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			),
		);
	}
}
