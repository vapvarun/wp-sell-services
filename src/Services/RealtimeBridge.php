<?php
/**
 * Realtime Bridge
 *
 * @package WPSellServices\Services
 * @since   1.2.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Relays existing plugin events to the realtime (Pusher-protocol) layer.
 *
 * Listens to the already-fired `wpss_message_sent` and
 * `wpss_notification_created` actions and publishes compact payloads to
 * the relevant private channels. Every publish is guarded by
 * {@see RealtimeService::is_enabled()}, so the bridge is a no-op until
 * realtime is configured.
 *
 * Channel conventions:
 *   - private-wpss-user-{ID}   Per-user feed (notifications + incoming messages).
 *   - private-wpss-order-{ID}  Per-order feed (conversation updates).
 *
 * @since 1.2.0
 */
class RealtimeBridge {

	/**
	 * Realtime publisher.
	 *
	 * @var RealtimeService
	 */
	private RealtimeService $realtime;

	/**
	 * Constructor.
	 *
	 * @param RealtimeService|null $realtime Optional publisher (for tests).
	 */
	public function __construct( ?RealtimeService $realtime = null ) {
		$this->realtime = $realtime ?? new RealtimeService();
	}

	/**
	 * Hook into plugin events.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wpss_message_sent', array( $this, 'on_message_sent' ), 10, 2 );
		add_action( 'wpss_notification_created', array( $this, 'on_notification_created' ), 10, 4 );
	}

	/**
	 * Publish a conversation message to the order channel and the
	 * recipient's user channel.
	 *
	 * @param \WPSellServices\Models\Message|mixed      $message      The message that was sent.
	 * @param \WPSellServices\Models\Conversation|mixed $conversation The conversation it belongs to.
	 * @return void
	 */
	public function on_message_sent( $message, $conversation ): void {
		if ( ! $message || ! $conversation || ! $this->realtime->is_enabled() ) {
			return;
		}

		$order_id  = (int) $conversation->order_id;
		$sender_id = (int) $message->sender_id;

		$payload = array(
			'order_id'   => $order_id,
			'sender_id'  => $sender_id,
			'message_id' => (int) $message->id,
			'excerpt'    => mb_substr( wp_strip_all_tags( (string) $message->content ), 0, 120 ),
		);

		$this->realtime->publish( 'private-wpss-order-' . $order_id, 'message.created', $payload );

		$recipient = $this->resolve_recipient( $order_id, $sender_id, $conversation );
		if ( $recipient > 0 ) {
			$this->realtime->publish( 'private-wpss-user-' . $recipient, 'message.created', $payload );
		}
	}

	/**
	 * Publish a notification to the recipient's user channel.
	 *
	 * @param int    $notification_id Notification ID.
	 * @param int    $user_id         Recipient user ID.
	 * @param string $type            Notification type.
	 * @param array  $data            Notification data (unused — payload stays minimal).
	 * @return void
	 */
	public function on_notification_created( int $notification_id, int $user_id, string $type, array $data = array() ): void {
		unset( $data ); // Intentionally not relayed — clients fetch details via REST.

		if ( $user_id <= 0 || ! $this->realtime->is_enabled() ) {
			return;
		}

		$this->realtime->publish(
			'private-wpss-user-' . $user_id,
			'notification.created',
			array(
				'id'   => $notification_id,
				'type' => $type,
			)
		);
	}

	/**
	 * Work out who should receive a message: the order participant
	 * (customer or vendor) who is not the sender, falling back to the
	 * conversation participants list for edge cases.
	 *
	 * @param int   $order_id     Order ID.
	 * @param int   $sender_id    Sending user ID.
	 * @param mixed $conversation Conversation object with a participants array.
	 * @return int Recipient user ID, or 0 when none could be resolved.
	 */
	private function resolve_recipient( int $order_id, int $sender_id, $conversation ): int {
		$order = $order_id > 0 ? wpss_get_order( $order_id ) : null;

		if ( $order ) {
			if ( (int) $order->customer_id !== $sender_id ) {
				return (int) $order->customer_id;
			}
			if ( (int) $order->vendor_id !== $sender_id ) {
				return (int) $order->vendor_id;
			}
		}

		if ( ! empty( $conversation->participants ) && is_array( $conversation->participants ) ) {
			foreach ( $conversation->participants as $participant_id ) {
				if ( (int) $participant_id !== $sender_id ) {
					return (int) $participant_id;
				}
			}
		}

		return 0;
	}
}
