<?php
/**
 * Order activity timeline.
 *
 * @package WPSellServices
 * @since   1.3.1
 */

namespace WPSellServices\Services;

/**
 * Builds the "what happened on this order" event log.
 *
 * The order page renders a four-step progress tracker (placed, started,
 * delivered, completed). That is a *status* indicator, not a history: it shows
 * where an order is, never what was done to it, and it is hardcoded in the
 * template. This class answers the other question - the ordered list of things
 * that actually happened - so an order detail screen has an Activity tab that
 * agrees with the record rather than being reconstructed from notifications.
 *
 * Everything is derived from stored rows. There is no new table and nothing to
 * backfill, so orders created before 1.3.1 have the same history as new ones.
 *
 * @since 1.3.1
 */
class OrderTimelineService {

	/**
	 * Build the timeline for an order, oldest event first.
	 *
	 * @since 1.3.1
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, array<string, mixed>> Timeline events.
	 */
	public function get_timeline( int $order_id ): array {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return array();
		}

		$events = array_merge(
			$this->lifecycle_events( $order ),
			$this->requirement_events( $order_id ),
			$this->delivery_events( $order_id ),
			$this->sub_order_events( $order_id ),
			$this->status_events( $order )
		);

		// Oldest first: an activity log reads top-down in the order things
		// happened. Ties keep insertion order, which puts "order placed"
		// before a status change stamped the same second.
		usort(
			$events,
			static function ( array $a, array $b ): int {
				return strtotime( (string) $a['created_at'] ) <=> strtotime( (string) $b['created_at'] );
			}
		);

		return array_values( array_filter( $events, static fn( array $e ): bool => '' !== (string) $e['created_at'] ) );
	}

	/**
	 * Events derived from the order's own timestamp columns.
	 *
	 * @since 1.3.1
	 *
	 * @param object $order Order row.
	 * @return array<int, array<string, mixed>>
	 */
	private function lifecycle_events( object $order ): array {
		$customer = (int) ( $order->customer_id ?? 0 );
		$vendor   = (int) ( $order->vendor_id ?? 0 );

		$events = array();

		$events[] = $this->event( 'order_placed', $this->datetime( $order->created_at ?? null ), $customer, __( 'Order placed.', 'wp-sell-services' ) );

		if ( ! empty( $order->paid_at ) ) {
			$events[] = $this->event(
				'payment_received',
				$this->datetime( $order->paid_at ),
				0,
				sprintf(
					/* translators: %s: formatted order total. */
					__( 'Payment received (%s).', 'wp-sell-services' ),
					wpss_format_price( (float) ( $order->total ?? 0 ), (string) ( $order->currency ?? '' ) )
				)
			);
		}

		if ( ! empty( $order->started_at ) ) {
			$events[] = $this->event( 'work_started', $this->datetime( $order->started_at ), $vendor, __( 'Work started.', 'wp-sell-services' ) );
		}

		if ( ! empty( $order->completed_at ) ) {
			$events[] = $this->event( 'order_completed', $this->datetime( $order->completed_at ), $customer, __( 'Order completed.', 'wp-sell-services' ) );
		}

		return $events;
	}

	/**
	 * Requirement submissions.
	 *
	 * @since 1.3.1
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function requirement_events( int $order_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_order_requirements';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT submitted_at FROM {$table} WHERE order_id = %d", $order_id ) );

		$order    = wpss_get_order( $order_id );
		$customer = (int) ( $order->customer_id ?? 0 );

		$events = array();

		foreach ( $rows ?: array() as $row ) {
			$events[] = $this->event( 'requirements_submitted', (string) $row->submitted_at, $customer, __( 'Requirements submitted.', 'wp-sell-services' ) );
		}

		return $events;
	}

	/**
	 * Deliveries and the buyer's response to them.
	 *
	 * @since 1.3.1
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function delivery_events( int $order_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_deliveries';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT vendor_id, version, status, responded_at, created_at FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		$order    = wpss_get_order( $order_id );
		$customer = (int) ( $order->customer_id ?? 0 );

		$events = array();

		foreach ( $rows ?: array() as $row ) {
			$events[] = $this->event(
				'delivery_submitted',
				(string) $row->created_at,
				(int) $row->vendor_id,
				sprintf(
					/* translators: %d: delivery version number. */
					__( 'Delivery #%d submitted.', 'wp-sell-services' ),
					(int) $row->version
				)
			);

			if ( empty( $row->responded_at ) ) {
				continue;
			}

			// The buyer answered a delivery: accepted it, or sent it back.
			$accepted = in_array( (string) $row->status, array( 'accepted', 'approved' ), true );

			$events[] = $this->event(
				$accepted ? 'delivery_accepted' : 'revision_requested',
				(string) $row->responded_at,
				$customer,
				$accepted
					? __( 'Delivery accepted.', 'wp-sell-services' )
					: __( 'Revision requested.', 'wp-sell-services' )
			);
		}

		return $events;
	}

	/**
	 * Tips, extras and other sub-orders raised against this order.
	 *
	 * @since 1.3.1
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function sub_order_events( int $order_id ): array {
		global $wpdb;

		$table     = $wpdb->prefix . 'wpss_orders';
		$platforms = wpss_get_sub_order_platforms();

		if ( empty( $platforms ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $platforms ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, customer_id, platform, total, currency, payment_status, created_at
				FROM {$table}
				WHERE platform_order_id = %d AND platform IN ({$placeholders})",
				array_merge( array( $order_id ), $platforms )
			)
		);

		$events = array();

		foreach ( $rows ?: array() as $row ) {
			$events[] = $this->event(
				'sub_order_' . (string) $row->platform,
				(string) $row->created_at,
				(int) $row->customer_id,
				sprintf(
					/* translators: 1: sub-order type (tip, extra), 2: formatted amount. */
					__( '%1$s added (%2$s).', 'wp-sell-services' ),
					ucfirst( (string) $row->platform ),
					wpss_format_price( (float) $row->total, (string) $row->currency )
				)
			);
		}

		return $events;
	}

	/**
	 * Status changes recorded in the order's meta.
	 *
	 * Statuses already described by a richer event above are skipped, so the
	 * log does not say "Delivery #1 submitted" and "Status changed to
	 * delivered" one line apart for the same act.
	 *
	 * @since 1.3.1
	 *
	 * @param object $order Order row.
	 * @return array<int, array<string, mixed>>
	 */
	private function status_events( object $order ): array {
		$meta = $order->meta ?? array();

		if ( is_string( $meta ) ) {
			$meta = json_decode( $meta, true );
		}

		$history = is_array( $meta ) ? ( $meta['status_history'] ?? array() ) : array();

		if ( ! is_array( $history ) ) {
			return array();
		}

		$covered = array( 'completed', 'delivered', 'requirements_submitted', 'in_progress' );

		$events = array();

		foreach ( $history as $entry ) {
			$status = (string) ( $entry['status'] ?? '' );

			if ( '' === $status || in_array( $status, $covered, true ) ) {
				continue;
			}

			$events[] = $this->event(
				'status_changed',
				(string) ( $entry['timestamp'] ?? '' ),
				0,
				sprintf(
					/* translators: %s: human-readable order status. */
					__( 'Status changed to %s.', 'wp-sell-services' ),
					wpss_get_order_status_label( $status )
				),
				array( 'status' => $status )
			);
		}

		return $events;
	}

	/**
	 * Shape one timeline event.
	 *
	 * Every event carries the same keys whatever its type, so a client can
	 * render an unknown future type instead of breaking on it.
	 *
	 * @since 1.3.1
	 *
	 * @param string               $type       Event type slug.
	 * @param string               $created_at MySQL datetime.
	 * @param int                  $actor_id   User who acted, 0 for the system.
	 * @param string               $message    Plain-text description.
	 * @param array<string, mixed> $data       Optional type-specific payload.
	 * @return array<string, mixed>
	 */
	private function event( string $type, string $created_at, int $actor_id, string $message, array $data = array() ): array {
		return array(
			'type'       => $type,
			'created_at' => $created_at,
			'actor'      => $this->actor( $actor_id ),
			'message'    => $message,
			'data'       => $data,
		);
	}

	/**
	 * Normalise a stored timestamp to a MySQL datetime string.
	 *
	 * The order model hydrates its date columns into DateTimeImmutable objects,
	 * while the sibling tables are read as raw rows and hand back strings. Both
	 * feed this timeline, so both are accepted here rather than each caller
	 * remembering which is which.
	 *
	 * @since 1.3.1
	 *
	 * @param mixed $value DateTimeInterface, MySQL datetime string, or null.
	 * @return string MySQL datetime, or '' when there is no usable value.
	 */
	private function datetime( $value ): string {
		if ( $value instanceof \DateTimeInterface ) {
			return $value->format( 'Y-m-d H:i:s' );
		}

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Describe the user behind an event.
	 *
	 * @since 1.3.1
	 *
	 * @param int $user_id User ID, 0 for system-generated events.
	 * @return array<string, mixed>|null Null when the system did it.
	 */
	private function actor( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return null;
		}

		return array(
			'id'     => $user_id,
			'name'   => $user->display_name,
			'avatar' => get_avatar_url( $user_id ),
		);
	}
}
