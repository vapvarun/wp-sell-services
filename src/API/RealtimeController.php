<?php
/**
 * Realtime REST Controller
 *
 * @package WPSellServices\API
 * @since   1.2.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use WP_REST_Request;
use WPSellServices\Services\RealtimeService;

/**
 * REST controller for realtime (WebSocket) channel authorization.
 *
 * Implements the Pusher private-channel auth contract: the browser client
 * POSTs its socket_id and the channel it wants, and — only after channel
 * ownership is verified — receives an HMAC auth payload signed with the
 * app secret. The secret itself never leaves the server.
 *
 * Allowed channels:
 *   - private-wpss-user-{ID}   Only the user themself.
 *   - private-wpss-order-{ID}  The order's customer, its vendor, or admins.
 *
 * @since 1.2.0
 */
class RealtimeController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'realtime';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /realtime/auth - Authorize a private-channel subscription.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/auth',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'auth' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'socket_id'    => array(
							'description' => __( 'Pusher socket ID of the connecting client.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
							'pattern'     => '^\d+\.\d+$',
						),
						'channel_name' => array(
							'description' => __( 'Private channel the client wants to subscribe to.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Authorize a private-channel subscription.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function auth( WP_REST_Request $request ) {
		$service = new RealtimeService();

		if ( ! $service->is_enabled() ) {
			return $this->error(
				'wpss_realtime_disabled',
				__( 'Real-time updates are not enabled on this site.', 'wp-sell-services' ),
				// 501, not 404: the route exists, the feature is switched off.
				// A 404 tells a client the endpoint does not exist, which it
				// then caches — so turning realtime on later does not help.
				501
			);
		}

		$socket_id = sanitize_text_field( (string) $request->get_param( 'socket_id' ) );
		$channel   = sanitize_text_field( (string) $request->get_param( 'channel_name' ) );

		if ( ! $this->user_can_subscribe( $channel, get_current_user_id() ) ) {
			return $this->error(
				'wpss_realtime_forbidden',
				__( 'You are not allowed to subscribe to this channel.', 'wp-sell-services' ),
				403
			);
		}

		return rest_ensure_response( $service->auth_signature( $socket_id, $channel ) );
	}

	/**
	 * Channel ownership check.
	 *
	 * @param string $channel Requested channel name.
	 * @param int    $user_id Current user ID.
	 * @return bool True when the user may subscribe to the channel.
	 */
	private function user_can_subscribe( string $channel, int $user_id ): bool {
		// Per-user channel: only the user themself.
		if ( preg_match( '/^private-wpss-user-(\d+)$/', $channel, $matches ) ) {
			return (int) $matches[1] === $user_id;
		}

		// Per-order channel: the order's customer, its vendor, or admins.
		if ( preg_match( '/^private-wpss-order-(\d+)$/', $channel, $matches ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}

			$order = wpss_get_order( (int) $matches[1] );

			return $order
				&& ( (int) $order->customer_id === $user_id || (int) $order->vendor_id === $user_id );
		}

		// Any other channel shape is refused.
		return false;
	}
}
