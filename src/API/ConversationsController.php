<?php
/**
 * Conversations REST Controller
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

		// Send a message to an order's conversation (creating the conversation
		// if it does not exist yet). This is the order-thread composer's REST
		// twin of the legacy wpss_send_message admin-ajax action - it keeps the
		// "create the conversation on first message" behaviour the order page
		// relies on, which the conversation-id-scoped route cannot provide.
		register_rest_route(
			$this->namespace,
			'/orders/(?P<order_id>[\d]+)/conversation/messages',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_order_message' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
					'args'                => array(
						'order_id' => array(
							'validate_callback' => array( $this, 'validate_id' ),
						),
						'content'  => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'wp_kses_post',
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
							'id'       => array(
								'validate_callback' => array( $this, 'validate_id' ),
							),
							'since'    => array(
								'description' => __( 'Only return messages after this ISO 8601 datetime.', 'wp-sell-services' ),
								'type'        => 'string',
								'format'      => 'date-time',
							),
							'after_id' => array(
								'description'       => __( 'Only return messages with an ID greater than this (incremental polling).', 'wp-sell-services' ),
								'type'              => 'integer',
								'default'           => 0,
								'sanitize_callback' => 'absint',
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
						// Not required at the schema level: a message may be
						// attachments-only. The callback enforces "content OR
						// at least one attachment".
						'content'     => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
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
				'wpss_not_owner',
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
				'wpss_not_owner',
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
		$since           = $request->get_param( 'since' );
		$after_id        = (int) $request->get_param( 'after_id' );

		$query_args = array(
			'limit'  => $pagination['per_page'],
			'offset' => $pagination['offset'],
		);

		// Incremental polling by message ID (only messages newer than after_id).
		if ( $after_id > 0 ) {
			$query_args['after_id'] = $after_id;
		}

		// Support 'since' parameter for efficient mobile polling.
		if ( $since ) {
			$query_args['since'] = sanitize_text_field( $since );
		}

		$messages = $this->conversation_service->get_messages(
			$conversation_id,
			$query_args
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
		return $this->do_send_message( (int) $request->get_param( 'id' ), $request );
	}

	/**
	 * Refuse the send if either party has blocked the other.
	 *
	 * Direction-blind, per {@see wpss_is_blocked_between()}: a block someone can
	 * walk around by messaging first is not a block.
	 *
	 * The refusal deliberately does NOT say which way round it is. Telling the
	 * sender "they blocked you" hands an unwanted contact the one piece of
	 * information most likely to push them onto another channel to argue about
	 * it, and telling them nothing costs a blocked-by-mistake member only an
	 * unblock they can perform themselves.
	 *
	 * @since 1.5.1
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         Sender.
	 * @return WP_Error|null Error when blocked, null when clear.
	 */
	private function blocked_participant( int $conversation_id, int $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$participants = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT participants FROM {$wpdb->prefix}wpss_conversations WHERE id = %d",
				$conversation_id
			)
		);

		$participants = json_decode( (string) $participants, true );

		if ( ! is_array( $participants ) ) {
			return null;
		}

		foreach ( $participants as $participant_id ) {
			$participant_id = (int) $participant_id;

			if ( $participant_id === $user_id ) {
				continue;
			}

			if ( wpss_is_blocked_between( $user_id, $participant_id ) ) {
				return new WP_Error(
					'wpss_conversation_blocked',
					__( 'You can no longer send messages in this conversation.', 'wp-sell-services' ),
					array( 'status' => 403 )
				);
			}
		}

		return null;
	}

	/**
	 * Send a message to an order's conversation, creating it if needed.
	 *
	 * Order-thread composer twin of the legacy wpss_send_message action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_order_message( $request ) {
		$order_id = (int) $request->get_param( 'order_id' );

		$conversation = $this->conversation_service->get_by_order( $order_id );
		if ( ! $conversation ) {
			$conversation = $this->conversation_service->create_for_order( $order_id );
		}

		if ( ! $conversation ) {
			return new WP_Error(
				'conversation_unavailable',
				__( 'Failed to start the conversation for this order.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		return $this->do_send_message( (int) $conversation->id, $request );
	}

	/**
	 * Shared message-send routine for both the conversation-scoped and
	 * order-scoped send endpoints.
	 *
	 * @param int             $conversation_id Conversation ID.
	 * @param WP_REST_Request $request         Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	private function do_send_message( int $conversation_id, $request ) {
		$user_id     = get_current_user_id();
		$content     = (string) $request->get_param( 'content' );
		$attachments = (array) $request->get_param( 'attachments' );

		// A block has to actually stop contact, or it is only a preference.
		// Checked here rather than in the two callers because this is the one
		// place both message routes pass through — a guard on send_message()
		// alone would leave the order-thread composer as the way around it.
		$blocked = $this->blocked_participant( $conversation_id, $user_id );

		if ( $blocked ) {
			return $blocked;
		}

		// Standing is deliberately NOT checked here. Messaging is how a member
		// finishes an order someone already paid for, and cutting it off would
		// strand the counterparty mid-delivery with no way to reach the person
		// holding their money. Suspension stops new supply; it does not sever a
		// conversation about work in flight.

		// Multipart file uploads (the order/dashboard composer posts raw files
		// as attachments[]). Validate + ingest them through the shared helper so
		// the allow-list/MIME/size rules match the legacy admin-ajax path. The
		// resulting attachment objects are merged with any integer attachment
		// IDs passed in the JSON body.
		$skipped     = array();
		$file_params = $request->get_file_params();
		if ( ! empty( $file_params['attachments'] ) ) {
			$uploaded    = wpss_handle_message_attachments( (array) $file_params['attachments'] );
			$attachments = array_merge( $attachments, $uploaded['attachments'] );
			$skipped     = $uploaded['skipped'];
		}

		// A message must carry text or at least one attachment.
		if ( '' === trim( $content ) && empty( $attachments ) ) {
			return new WP_Error(
				'message_empty',
				__( 'Please enter a message or attach a file.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		$message = $this->conversation_service->send_message( $conversation_id, $user_id, $content, $attachments );

		if ( ! $message ) {
			return new WP_Error(
				'message_send_failed',
				__( 'Failed to send message. You may not have permission to message in this conversation.', 'wp-sell-services' ),
				array( 'status' => 400 )
			);
		}

		$data = $this->prepare_message_for_response( $message );

		$response = array(
			'message'         => __( 'Message sent successfully.', 'wp-sell-services' ),
			'data'            => $data,
			// Top-level rendered row so the (server-rendered) thread UIs can
			// append markup identical to first paint without re-rendering.
			'html'            => $data['html'] ?? '',
			// Echo the resolved conversation so an order composer that created
			// the conversation on this first send can begin polling it.
			'conversation_id' => $conversation_id,
		);

		if ( ! empty( $skipped ) ) {
			$response['warnings'] = $skipped;
		}

		return new WP_REST_Response( $response, 201 );
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

		// Get service_id from the associated order.
		$order      = $conversation->get_order();
		$service_id = $order ? (int) $order->service_id : 0;

		// Get last message from conversation data (already fetched by repository with optimized query).
		$last_message = null;
		if ( ! empty( $conversation->last_message ) ) {
			$last_message = (object) array(
				'content'    => $conversation->last_message,
				'sender_id'  => $conversation->last_message_sender_id ?? 0,
				'created_at' => $conversation->last_message_created_at ?? null,
			);
		}

		return array(
			'id'            => (int) $conversation->id,
			'order_id'      => (int) $conversation->order_id,
			'subject'       => $conversation->subject ?? '',
			'service_id'    => $service_id,
			'service_title' => $service_id ? get_the_title( $service_id ) : '',
			'other_user'    => wpss_rest_user( (int) $other_user_id ),
			'last_message'  => $last_message ? array(
				'content'    => wp_trim_words( wp_strip_all_tags( $last_message->content ), 10 ),
				'sender_id'  => (int) $last_message->sender_id,
				'created_at' => $this->format_datetime( $last_message->created_at ?? null ),
			) : null,
			'unread_count'  => $this->conversation_service->get_unread_count_for_conversation( $conversation->id, $user_id ),
			'is_closed'     => $conversation->is_closed ?? false,
			'created_at'    => $this->format_datetime( $conversation->created_at ?? null ),
			'updated_at'    => $this->format_datetime( $conversation->updated_at ?? null ),
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

			/*
			 * The shared actor shape for a real sender, and the System shape kept
			 * for sender_id 0 - wpss_rest_user() returns null there, and a client
			 * rendering a thread needs an object either way.
			 *
			 * `deleted` matters here for the same reason it does on an order: a
			 * conversation outlives the people in it, and a thread has to
			 * distinguish "this member's account is gone" from "the system said
			 * this" (Basecamp 10154919636).
			 */
			'sender'      => 0 === (int) $message->sender_id
				? array(
					'id'      => 0,
					'name'    => __( 'System', 'wp-sell-services' ),
					'avatar'  => '',
					'deleted' => false,
				)
				: wpss_rest_user( (int) $message->sender_id ),
			'content'     => $message->content ?? '',
			'attachments' => $attachments,
			'is_read'     => $is_read,
			'is_edited'   => $message->is_edited ?? false,
			'created_at'  => $this->format_datetime( $message->created_at ?? null ),
			// Additive: server-rendered message row so the (server-rendered)
			// thread UIs append byte-identical markup. Mirrors the reviews
			// pattern; structured fields above remain the canonical contract.
			'html'        => wpss_render_message_row( $message, $user_id ),
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
