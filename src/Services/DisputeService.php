<?php
/**
 * Dispute Service
 *
 * Business logic for dispute management.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

use WPSellServices\Database\Repositories\OrderRepository;
use WPSellServices\Models\Dispute;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;

defined( 'ABSPATH' ) || exit;

/**
 * DisputeService class.
 *
 * @since 1.0.0
 */
class DisputeService {

	/**
	 * Dispute statuses.
	 */
	public const STATUS_OPEN      = 'open';
	public const STATUS_PENDING   = 'pending_review';
	public const STATUS_RESOLVED  = 'resolved';
	public const STATUS_ESCALATED = 'escalated';
	public const STATUS_CLOSED    = 'closed';

	/**
	 * Resolution types.
	 */
	public const RESOLUTION_REFUND         = 'full_refund';
	public const RESOLUTION_PARTIAL_REFUND = 'partial_refund';
	public const RESOLUTION_FAVOR_VENDOR   = 'favor_vendor';
	public const RESOLUTION_FAVOR_BUYER    = 'favor_buyer';
	public const RESOLUTION_MUTUAL         = 'mutual_agreement';

	/**
	 * The dispute state machine. Key: current status; value: statuses it may
	 * move to. transition() is the only writer of disputes.status and refuses
	 * anything not listed here, so resolved and closed are terminal everywhere -
	 * admin select, bulk action, REST and cron alike.
	 *
	 * @since 1.7.1
	 */
	private const TRANSITIONS = array(
		self::STATUS_OPEN      => array( self::STATUS_PENDING, self::STATUS_ESCALATED, self::STATUS_RESOLVED, self::STATUS_CLOSED ),
		self::STATUS_PENDING   => array( self::STATUS_ESCALATED, self::STATUS_RESOLVED, self::STATUS_CLOSED ),
		self::STATUS_ESCALATED => array( self::STATUS_RESOLVED, self::STATUS_CLOSED ),
		self::STATUS_RESOLVED  => array(),
		self::STATUS_CLOSED    => array(),
	);

	/**
	 * Order statuses a dispute may be opened from: paid, work under way or
	 * delivered. Completed is allowed too, inside the dispute window (see
	 * open_guard()). Also the statuses a cancelled dispute may restore the
	 * order to, so OrderService's natural map reads this list.
	 *
	 * @since 1.7.1
	 */
	public const DISPUTABLE_ORDER_STATUSES = array(
		ServiceOrder::STATUS_IN_PROGRESS,
		ServiceOrder::STATUS_PENDING_APPROVAL,
		ServiceOrder::STATUS_REVISION_REQUESTED,
		ServiceOrder::STATUS_LATE,
		ServiceOrder::STATUS_DELIVERED,
		ServiceOrder::STATUS_CANCELLATION_REQUESTED,
	);

	/**
	 * Why the last open()/transition()/resolve() call returned false.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Dispute messages table name.
	 *
	 * @var string
	 */
	private string $messages_table;

	/**
	 * Order repository.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $order_repo;

	/**
	 * Order service for status changes that fire hooks.
	 *
	 * @var OrderService
	 */
	private OrderService $order_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table          = $wpdb->prefix . 'wpss_disputes';
		$this->messages_table = $wpdb->prefix . 'wpss_dispute_messages';
		$this->order_repo     = new OrderRepository();
		$this->order_service  = new OrderService();
	}

	/**
	 * Why the last open(), transition() or resolve() call was refused.
	 *
	 * @since 1.7.1
	 * @return string Translated sentence, empty when the last call succeeded.
	 */
	public function last_error(): string {
		return $this->last_error;
	}

	/**
	 * Whether a dispute may move from one status to another.
	 *
	 * @since 1.7.1
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @return bool
	 */
	public function can_transition( string $from, string $to ): bool {
		return in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true );
	}

	/**
	 * Check whether a dispute can be opened for a given order.
	 *
	 * Used by templates to decide whether to show the "Open Dispute" button.
	 * Same guard as open(), so the button never shows when submit would fail.
	 *
	 * @param object $order Order object (ServiceOrder or raw DB row).
	 * @return bool
	 */
	public function can_open_dispute( object $order ): bool {
		if ( ! wpss_get_option( 'orders', 'allow_disputes' ) ) {
			return false;
		}

		return null === $this->open_guard( $order );
	}

	/**
	 * The one reason a dispute cannot be opened on this order, or null.
	 *
	 * Shared by can_open_dispute() (button) and open() (submit) so the two
	 * cannot drift. Product rule (docs/website/disputes-resolution): one
	 * dispute per order, ever - a resolved or closed one is final.
	 *
	 * @since 1.7.1
	 * @param object $order Order object (ServiceOrder or raw DB row).
	 * @return string|null Translated refusal, or null when a dispute may be opened.
	 */
	private function open_guard( object $order ): ?string {
		if ( $this->get_by_order( (int) $order->id ) ) {
			return __( 'A dispute already exists for this order.', 'wp-sell-services' );
		}

		if ( in_array( $order->status, self::DISPUTABLE_ORDER_STATUSES, true ) ) {
			return null;
		}

		if ( ServiceOrder::STATUS_COMPLETED === $order->status && ! empty( $order->completed_at ) ) {
			$dispute_window_days = (int) wpss_get_option( 'orders', 'dispute_window_days' );

			if ( $dispute_window_days <= 0 ) {
				return __( 'Disputes cannot be opened on completed orders.', 'wp-sell-services' );
			}

			$completed_time = $order->completed_at instanceof \DateTimeInterface
				? $order->completed_at->getTimestamp()
				: strtotime( (string) $order->completed_at );

			if ( time() > $completed_time + ( $dispute_window_days * DAY_IN_SECONDS ) ) {
				return sprintf(
					/* translators: %d: number of days after completion a dispute may be opened. */
					__( 'The %d-day dispute window for this order has passed.', 'wp-sell-services' ),
					$dispute_window_days
				);
			}

			return null;
		}

		return __( 'Disputes can only be opened on paid orders that are in progress, delivered, or recently completed.', 'wp-sell-services' );
	}

	/**
	 * Open a dispute.
	 *
	 * The dispute row and the order's move to `disputed` are one unit of work:
	 * either both land or neither does, so a refused order transition can no
	 * longer leave a dispute row on an order that never moved.
	 *
	 * @param int                  $order_id Order ID.
	 * @param int                  $opened_by User ID who opened dispute.
	 * @param string               $reason Dispute reason.
	 * @param string               $description Detailed description.
	 * @param array<string, mixed> $meta Additional metadata.
	 * @return int|false Dispute ID, or false with the reason in last_error().
	 */
	public function open( int $order_id, int $opened_by, string $reason, string $description, array $meta = array() ): int|false {
		global $wpdb;

		$this->last_error = '';
		$order            = $this->order_repo->find( $order_id );

		if ( ! $order ) {
			$this->last_error = __( 'Order not found.', 'wp-sell-services' );
			return false;
		}

		// Cast to int since database returns string values.
		if ( (int) $order->customer_id !== $opened_by && (int) $order->vendor_id !== $opened_by ) {
			$this->last_error = __( 'Only the buyer or the vendor on this order can open a dispute.', 'wp-sell-services' );
			return false;
		}

		$refusal = $this->open_guard( $order );

		if ( null !== $refusal ) {
			$this->last_error = $refusal;
			return false;
		}

		// Determine the respondent (the other party on the order).
		$respondent_id = ( (int) $order->customer_id === $opened_by )
			? (int) $order->vendor_id
			: (int) $order->customer_id;

		$dispute_data = array(
			'order_id'      => $order_id,
			'initiated_by'  => $opened_by,
			'respondent_id' => $respondent_id,
			'reason'        => sanitize_text_field( $reason ),
			'description'   => sanitize_textarea_field( $description ),
			'status'        => self::STATUS_OPEN,
			'evidence'      => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		/**
		 * Filter dispute data before saving to the database.
		 *
		 * Allows modification of the dispute reason, description, and other data
		 * before the dispute record is inserted.
		 *
		 * @since 1.1.0
		 * @param array $dispute_data Dispute data including reason and description.
		 * @param int   $order_id     Order ID.
		 */
		$dispute_data = apply_filters( 'wpss_pre_open_dispute', $dispute_data, $order_id );

		$wpdb->query( 'START TRANSACTION' );

		// The row goes in first: the `disputed` status mail reads the dispute
		// by order, so it must exist when update_status() fires the hooks.
		$result = $wpdb->insert( $this->table, $dispute_data );

		if ( ! $result ) {
			$wpdb->query( 'ROLLBACK' );
			$this->last_error = __( 'Failed to open dispute.', 'wp-sell-services' );
			return false;
		}

		$dispute_id = (int) $wpdb->insert_id;

		// Record the pre-dispute status BEFORE overwriting it. $order was loaded
		// above, before any status change, so it holds the real one; cancel()
		// restores the order to it.
		$order_meta                          = is_string( $order->meta ?? null ) ? ( json_decode( $order->meta, true ) ?: array() ) : ( (array) ( $order->meta ?? array() ) );
		$order_meta['status_before_dispute'] = $order->status;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'wpss_orders',
			array( 'meta' => wp_json_encode( $order_meta ) ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Via OrderService so the order hooks (notifications, emails) fire.
		if ( ! $this->order_service->update_status( $order_id, ServiceOrder::STATUS_DISPUTED ) ) {
			$wpdb->query( 'ROLLBACK' );
			$this->last_error = __( 'This order cannot be moved into dispute from its current status.', 'wp-sell-services' );
			return false;
		}

		$wpdb->query( 'COMMIT' );

		/**
		 * Fires when a dispute is opened.
		 *
		 * @since 1.0.0
		 * @param int   $dispute_id Dispute ID.
		 * @param int   $order_id   Order ID.
		 * @param int   $opened_by  User ID.
		 * @param array $dispute_data Dispute data.
		 */
		do_action( 'wpss_dispute_opened', $dispute_id, $order_id, $opened_by, $dispute_data );

		return $dispute_id;
	}

	/**
	 * Get dispute by ID.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return Dispute|null Dispute model or null.
	 */
	public function get( int $dispute_id ): ?Dispute {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$dispute_id
			)
		);

		// Hydrate the MODEL, never hand back a raw row.
		//
		// $wpdb returns every column as a string. Consumers of this method run
		// under strict_types and pass these values into int-typed helpers, so a
		// raw row threw `Argument #1 ($order_id) must be of type int, string
		// given` in DisputeWorkflowManager::restore_order_status() - AFTER the
		// dispute status had already been written, leaving the dispute closed
		// while its order stayed `disputed` with no open dispute to resolve it.
		// from_db() also decodes evidence/meta and maps the column names.
		return $row ? Dispute::from_db( $row ) : null;
	}

	/**
	 * Get dispute by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return Dispute|null Dispute model or null.
	 */
	public function get_by_order( int $order_id ): ?Dispute {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);

		// Same contract as get() - hydrate, never return a raw row.
		return $row ? Dispute::from_db( $row ) : null;
	}

	/**
	 * Add evidence to a dispute.
	 *
	 * Evidence is stored in the dispute's evidence JSON column.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param int    $user_id User ID submitting evidence.
	 * @param string $type Evidence type (text, image, file, link).
	 * @param string $content Evidence content.
	 * @param string $description Evidence description.
	 * @return bool True on success, false on failure.
	 */
	public function add_evidence( int $dispute_id, int $user_id, string $type, string $content, string $description = '' ): bool {
		global $wpdb;

		$dispute = $this->get( $dispute_id );

		if ( ! $dispute || $dispute->status === self::STATUS_CLOSED ) {
			return false;
		}

		// Sanitize content based on evidence type. image/file/link all carry a
		// URL (the AJAX handler stores the wp_handle_upload() URL, not an
		// attachment ID) — absint() here silently zeroed every uploaded file,
		// so the frontend rendered a broken link. Treat them all as URLs.
		$sanitized_type    = sanitize_key( $type );
		$sanitized_content = match ( $sanitized_type ) {
			'link', 'image', 'file' => esc_url_raw( $content ),
			default                 => sanitize_textarea_field( $content ),
		};

		// Written to the messages table, NOT to the disputes row's `evidence`
		// JSON. Those were two stores for one conversation: this method fed the
		// JSON, the opening statement and admin replies fed the table, and each
		// surface read only its own — so members saw "No messages yet" on a
		// dispute the admin could read in full. One store now.
		$sender_role = 'response';

		if ( user_can( $user_id, 'manage_options' ) ) {
			$sender_role = 'admin_response';
		} elseif ( (int) $dispute->initiator_id === $user_id ) {
			$sender_role = 'opener_response';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$this->messages_table,
			array(
				'dispute_id'   => $dispute_id,
				'sender_id'    => $user_id,
				'sender_role'  => $sender_role,
				'message'      => $sanitized_content,
				'message_type' => $sanitized_type,
				'description'  => sanitize_textarea_field( $description ),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		// Keep the dispute row's timestamp moving so "last activity" sorting
		// still reflects the conversation.
		if ( false !== $result ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $dispute_id )
			);
		}

		if ( false !== $result ) {
			/**
			 * Fires when evidence is added to a dispute.
			 *
			 * @since 1.0.0
			 * @param int $dispute_id Dispute ID.
			 * @param int $user_id    User ID.
			 */
			do_action( 'wpss_dispute_evidence_added', $dispute_id, $user_id );

			return true;
		}

		return false;
	}

	/**
	 * Move legacy evidence out of the disputes row's JSON and into the
	 * messages table, so no existing dispute loses its history.
	 *
	 * Idempotent: a dispute is only migrated once, and the JSON column is
	 * cleared as each one is done, so re-running cannot duplicate rows. Rows
	 * are matched on the sender + timestamp + content already present, which
	 * also covers a half-finished run.
	 *
	 * @since 1.6.0
	 *
	 * @return int Number of evidence items moved.
	 */
	public function migrate_evidence_to_messages(): int {
		global $wpdb;

		// Column is `initiated_by`; the model exposes it as initiator_id.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT id, initiated_by, evidence FROM {$this->table}
			 WHERE evidence IS NOT NULL AND evidence != '' AND evidence != '[]'"
		);

		if ( ! $rows ) {
			return 0;
		}

		$moved = 0;

		foreach ( $rows as $row ) {
			$items = json_decode( (string) $row->evidence, true );

			if ( ! is_array( $items ) ) {
				continue;
			}

			// Status notes are dispute STATUS history written by
			// update_status(), not conversation, and they share this column.
			// They stay put — clearing the column wholesale would delete them.
			$keep = array();

			foreach ( $items as $item ) {
				// Oldest rows are bare filename strings rather than objects.
				// They are still evidence someone attached, so carry them over
				// as files instead of dropping them on the floor.
				if ( is_string( $item ) ) {
					$item = array(
						'user_id'     => 0,
						'type'        => 'file',
						'content'     => $item,
						'description' => '',
						'created_at'  => current_time( 'mysql' ),
					);
				}

				if ( ! is_array( $item ) ) {
					continue;
				}

				if ( 'status_note' === ( $item['type'] ?? '' ) ) {
					$keep[] = $item;
					continue;
				}

				$user_id    = (int) ( $item['user_id'] ?? 0 );
				$content    = (string) ( $item['content'] ?? '' );
				$created_at = (string) ( $item['created_at'] ?? current_time( 'mysql' ) );

				// Already carried over by an earlier partial run.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$this->messages_table}
						 WHERE dispute_id = %d AND sender_id = %d AND created_at = %s AND message = %s",
						(int) $row->id,
						$user_id,
						$created_at,
						$content
					)
				);

				if ( $exists ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$inserted = $wpdb->insert(
					$this->messages_table,
					array(
						'dispute_id'   => (int) $row->id,
						'sender_id'    => $user_id,
						'sender_role'  => $user_id === (int) $row->initiated_by ? 'opener_response' : 'response',
						'message'      => $content,
						'message_type' => (string) ( $item['type'] ?? 'text' ),
						'description'  => (string) ( $item['description'] ?? '' ),
						'created_at'   => $created_at,
					),
					array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
				);

				if ( $inserted ) {
					++$moved;
				}
			}

			// Rewritten only after its items are in the table, so an
			// interrupted run resumes instead of losing the remainder. Keeps
			// the status notes, which were never conversation.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				// $keep is appended to with [], so it is already a list and
				// encodes as a JSON array.
				array( 'evidence' => wp_json_encode( $keep ) ),
				array( 'id' => (int) $row->id )
			);
		}

		return $moved;
	}

	/**
	 * Get the dispute conversation: every message and piece of evidence.
	 *
	 * Reads the messages table, which is the single store. This used to return
	 * the disputes row's `evidence` JSON, which held only what members posted
	 * through add_evidence() — never the opening statement or admin replies,
	 * which have always gone to the table. The member-facing thread therefore
	 * announced "No messages yet" on disputes the admin could read in full.
	 *
	 * Shape is unchanged for callers: id / user_id / type / content /
	 * description / created_at, plus attachments.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return array<array<string, mixed>> Conversation items, oldest first.
	 */
	public function get_evidence( int $dispute_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} WHERE dispute_id = %d ORDER BY created_at ASC, id ASC",
				$dispute_id
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		$items = array();

		foreach ( $rows as $row ) {
			$attachments = ! empty( $row['attachments'] ) ? json_decode( (string) $row['attachments'], true ) : array();

			$items[] = array(
				'id'          => (int) $row['id'],
				'user_id'     => (int) $row['sender_id'],
				'sender_role' => (string) ( $row['sender_role'] ?? '' ),
				// Rows written before the columns existed are plain messages.
				'type'        => (string) ( $row['message_type'] ?? '' ) ?: 'text',
				'content'     => (string) $row['message'],
				'description' => (string) ( $row['description'] ?? '' ),
				'attachments' => is_array( $attachments ) ? $attachments : array(),
				'created_at'  => (string) $row['created_at'],
			);
		}

		return $items;
	}

	/**
	 * Move a dispute to a new status. The ONLY writer of disputes.status.
	 *
	 * Refuses anything outside self::TRANSITIONS and says why in last_error().
	 * Re-submitting the current (non-terminal) status is a no-op that still
	 * records the note, so the admin form can save a note without moving.
	 *
	 * Callers that need the order moved too (closing restores it, resolving
	 * moves money) do that in their own transaction around this call:
	 * DisputeWorkflowManager::cancel() and self::resolve().
	 *
	 * @since 1.7.1
	 *
	 * @param int                  $dispute_id Dispute ID.
	 * @param string               $to         Target status.
	 * @param array<string, mixed> $context    Optional. `note` (string) is stored as a
	 *                                         status_note in the evidence JSON;
	 *                                         `fields` (array) are extra columns
	 *                                         written in the same UPDATE.
	 * @return bool True when the row moved (or the no-op note was saved).
	 */
	public function transition( int $dispute_id, string $to, array $context = array() ): bool {
		global $wpdb;

		$this->last_error = '';
		$dispute          = $this->get( $dispute_id );

		if ( ! $dispute ) {
			$this->last_error = __( 'Dispute not found.', 'wp-sell-services' );
			return false;
		}

		$from  = (string) $dispute->status;
		$no_op = $to === $from && ! empty( self::TRANSITIONS[ $from ] );

		if ( ! $no_op && ! $this->can_transition( $from, $to ) ) {
			$labels           = self::get_statuses();
			$this->last_error = sprintf(
				/* translators: 1: current dispute status label, 2: requested status label. */
				__( 'A dispute that is %1$s cannot be moved to %2$s.', 'wp-sell-services' ),
				$labels[ $from ] ?? $from,
				$labels[ $to ] ?? $to
			);
			return false;
		}

		$data = array_merge(
			(array) ( $context['fields'] ?? array() ),
			array(
				'status'     => $to,
				'updated_at' => current_time( 'mysql' ),
			)
		);

		$note = (string) ( $context['note'] ?? '' );

		if ( '' !== $note ) {
			$evidence         = $dispute->evidence;
			$evidence[]       = array(
				'id'         => uniqid( 'note_' ),
				'type'       => 'status_note',
				'note'       => sanitize_textarea_field( $note ),
				'status'     => $to,
				'created_at' => current_time( 'mysql' ),
			);
			$data['evidence'] = wp_json_encode( $evidence );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $this->table, $data, array( 'id' => $dispute_id ) );

		if ( false === $result ) {
			$this->last_error = __( 'Failed to update dispute.', 'wp-sell-services' );
			return false;
		}

		if ( ! $no_op ) {
			/**
			 * Fires when dispute status changes.
			 *
			 * @since 1.0.0
			 * @param int    $dispute_id Dispute ID.
			 * @param string $status     New status.
			 * @param string $old_status Old status.
			 */
			do_action( 'wpss_dispute_status_changed', $dispute_id, $to, $from );
		}

		return true;
	}

	/**
	 * Resolve a dispute.
	 *
	 * Money first, status second, both in one transaction: the refund or
	 * completion runs through OrderService and only when the order actually
	 * moved is the dispute written `resolved`. A refused money move rolls
	 * everything back and returns false with the reason in last_error(). A
	 * resolved or closed dispute is refused outright, so money cannot move
	 * twice from any surface.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param string $resolution Resolution type.
	 * @param string $notes Resolution notes.
	 * @param int    $resolved_by Admin user ID.
	 * @param float  $refund_amount Optional refund amount.
	 * @return bool True on success.
	 */
	public function resolve( int $dispute_id, string $resolution, string $notes, int $resolved_by, float $refund_amount = 0.0 ): bool {
		global $wpdb;

		$this->last_error = '';
		$dispute          = $this->get( $dispute_id );

		if ( ! $dispute ) {
			$this->last_error = __( 'Dispute not found.', 'wp-sell-services' );
			return false;
		}

		if ( ! $this->can_transition( (string) $dispute->status, self::STATUS_RESOLVED ) ) {
			$this->last_error = __( 'This dispute has already been resolved or closed.', 'wp-sell-services' );
			return false;
		}

		$resolution = sanitize_key( $resolution );

		if ( ! isset( self::get_resolution_types()[ $resolution ] ) ) {
			$this->last_error = __( 'Please select a resolution type when resolving a dispute.', 'wp-sell-services' );
			return false;
		}

		// A Partial Refund with no amount used to reach apply_refund_status()
		// as 0.0, which it read as "refund everything" (Basecamp 10240143362).
		// Validated here, once, for the admin form and REST alike.
		if ( self::RESOLUTION_PARTIAL_REFUND === $resolution ) {
			$order = $this->order_service->get( (int) $dispute->order_id );
			$total = $order ? (float) $order->total : 0.0;

			if ( $refund_amount <= 0 ) {
				$this->last_error = __( 'Enter the amount to refund. A partial refund needs a number greater than zero.', 'wp-sell-services' );
				return false;
			}

			if ( $total > 0 && $refund_amount >= $total ) {
				$this->last_error = sprintf(
					/* translators: %s: formatted order total. */
					__( 'A partial refund must be less than the %s order total. Choose Full Refund to return all of it.', 'wp-sell-services' ),
					wpss_format_price( $total, $order->currency ?? '' )
				);
				return false;
			}
		} else {
			$refund_amount = 0.0;
		}

		$evidence = $dispute->evidence;

		if ( $refund_amount > 0 ) {
			$evidence[] = array(
				'id'            => uniqid( 'refund_' ),
				'type'          => 'refund_info',
				'refund_amount' => $refund_amount,
				'created_at'    => current_time( 'mysql' ),
			);
		}

		$wpdb->query( 'START TRANSACTION' );

		try {
			$moved = $this->handle_resolution( $dispute, $resolution, $refund_amount );
		} catch ( \Throwable $e ) {
			$moved = false;
		}

		if ( ! $moved ) {
			$wpdb->query( 'ROLLBACK' );
			$this->last_error = __( 'The order could not be moved for this resolution, so the dispute stays open. Check the order status and try again.', 'wp-sell-services' );
			return false;
		}

		$resolved = $this->transition(
			$dispute_id,
			self::STATUS_RESOLVED,
			array(
				'fields' => array(
					'resolution'       => $resolution,
					'resolution_notes' => sanitize_textarea_field( $notes ),
					'refund_amount'    => $refund_amount > 0 ? round( $refund_amount, 2 ) : null,
					'resolved_by'      => $resolved_by,
					'resolved_at'      => current_time( 'mysql' ),
					'evidence'         => wp_json_encode( $evidence ),
				),
			)
		);

		if ( ! $resolved ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->query( 'COMMIT' );

		/**
		 * Fires when a dispute is resolved.
		 *
		 * @since 1.0.0
		 * @param int    $dispute_id    Dispute ID.
		 * @param string $resolution    Resolution type.
		 * @param object $dispute       Dispute object.
		 * @param float  $refund_amount Refund amount.
		 */
		do_action( 'wpss_dispute_resolved', $dispute_id, $resolution, $dispute, $refund_amount );

		return true;
	}

	/**
	 * Move the order for a resolution. Every path goes through OrderService so
	 * the hooks (refund, commission, emails) fire, and every path reports
	 * whether the order actually moved.
	 *
	 * @param object $dispute Dispute object.
	 * @param string $resolution Resolution type.
	 * @param float  $refund_amount Refund amount.
	 * @return bool True when the order moved.
	 */
	private function handle_resolution( object $dispute, string $resolution, float $refund_amount ): bool {
		$order_id = (int) $dispute->order_id;

		switch ( $resolution ) {
			case self::RESOLUTION_REFUND:
			case self::RESOLUTION_FAVOR_BUYER:
				// Ruling for the buyer returns everything they paid; NULL asks
				// apply_refund_status() to resolve that to the order total.
				return $this->order_service->apply_refund_status( $order_id, null, ServiceOrder::STATUS_REFUNDED );

			case self::RESOLUTION_PARTIAL_REFUND:
				return $this->order_service->apply_refund_status( $order_id, $refund_amount, ServiceOrder::STATUS_PARTIALLY_REFUNDED );

			case self::RESOLUTION_FAVOR_VENDOR:
			case self::RESOLUTION_MUTUAL:
				return $this->order_service->update_status( $order_id, ServiceOrder::STATUS_COMPLETED );
		}

		return false;
	}

	/**
	 * Get disputes by user.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of disputes.
	 */
	public function get_by_user( int $user_id, array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		// User can be initiator or part of the order.
		$where[]  = '(d.initiated_by = %d OR o.customer_id = %d OR o.vendor_id = %d)';
		$values[] = $user_id;
		$values[] = $user_id;
		$values[] = $user_id;

		if ( $args['status'] ) {
			$where[]  = 'd.status = %s';
			$values[] = $args['status'];
		}

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$where_clause = implode( ' AND ', $where );

		// Validate order_by against allowlist to prevent SQL injection.
		$allowed_order_by = array( 'created_at', 'updated_at', 'status' );
		$order_by_col     = in_array( $args['order_by'], $allowed_order_by, true ) ? $args['order_by'] : 'created_at';
		$order_dir        = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_clause uses %s/%d placeholders; $order_by_col + $order_dir validated against allowlist above (lines 575-577).
		$sql = $wpdb->prepare(
			"SELECT d.*, o.customer_id, o.vendor_id, o.service_id
			FROM {$this->table} d
			LEFT JOIN {$orders_table} o ON d.order_id = o.id
			WHERE {$where_clause}
			ORDER BY d.{$order_by_col} {$order_dir}
			LIMIT %d OFFSET %d",
			array_merge( $values, array( $args['limit'], $args['offset'] ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql produced by $wpdb->prepare() above; static analyser can't trace through the local var.
		$rows = $wpdb->get_results( $sql );

		// Hydrate so every read method on this service returns the SAME shape.
		// Previously get()/get_by_order() returned decoded rows while these two
		// returned raw JOINed rows with evidence/meta still JSON strings - and
		// all four feed DisputesController::prepare_dispute_for_response().
		// The JOINed o.customer_id/o.vendor_id/o.service_id columns are not read
		// by any consumer of these two methods (verified: DisputesController is
		// the only caller, and DisputesListTable runs its own queries).
		return array_map( array( Dispute::class, 'from_db' ), $rows ?: array() );
	}

	/**
	 * Get all disputes for admin.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of disputes.
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[]  = 'd.status = %s';
			$values[] = $args['status'];
		}

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$where_clause = implode( ' AND ', $where );

		// Validate order_by against allowlist to prevent SQL injection.
		$allowed_order_by = array( 'created_at', 'updated_at', 'status' );
		$order_by_col     = in_array( $args['order_by'], $allowed_order_by, true ) ? $args['order_by'] : 'created_at';
		$order_dir        = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'DESC';

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_clause uses %s/%d placeholders; $order_by_col + $order_dir validated against allowlist above (lines 575-577).
		$sql = $wpdb->prepare(
			"SELECT d.*, o.customer_id, o.vendor_id, o.service_id
			FROM {$this->table} d
			LEFT JOIN {$orders_table} o ON d.order_id = o.id
			WHERE {$where_clause}
			ORDER BY d.{$order_by_col} {$order_dir}
			LIMIT %d OFFSET %d",
			$values
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql produced by $wpdb->prepare() above; static analyser can't trace through the local var.
		$rows = $wpdb->get_results( $sql );

		// Hydrate so every read method on this service returns the SAME shape.
		// Previously get()/get_by_order() returned decoded rows while these two
		// returned raw JOINed rows with evidence/meta still JSON strings - and
		// all four feed DisputesController::prepare_dispute_for_response().
		// The JOINed o.customer_id/o.vendor_id/o.service_id columns are not read
		// by any consumer of these two methods (verified: DisputesController is
		// the only caller, and DisputesListTable runs its own queries).
		return array_map( array( Dispute::class, 'from_db' ), $rows ?: array() );
	}

	/**
	 * Count disputes by status.
	 *
	 * @return array<string, int> Status counts.
	 */
	public function count_by_status(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$this->table}
			GROUP BY status"
		);

		$counts = array(
			self::STATUS_OPEN      => 0,
			self::STATUS_PENDING   => 0,
			self::STATUS_RESOLVED  => 0,
			self::STATUS_ESCALATED => 0,
			self::STATUS_CLOSED    => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Get available dispute statuses.
	 *
	 * @return array<string, string> Status slugs and labels.
	 */
	public static function get_statuses(): array {
		return array(
			self::STATUS_OPEN      => __( 'Open', 'wp-sell-services' ),
			self::STATUS_PENDING   => __( 'Pending Review', 'wp-sell-services' ),
			self::STATUS_RESOLVED  => __( 'Resolved', 'wp-sell-services' ),
			self::STATUS_ESCALATED => __( 'Escalated', 'wp-sell-services' ),
			self::STATUS_CLOSED    => __( 'Closed', 'wp-sell-services' ),
		);
	}

	/**
	 * Get available resolution types.
	 *
	 * @return array<string, string> Resolution slugs and labels.
	 */
	public static function get_resolution_types(): array {
		return array(
			self::RESOLUTION_REFUND         => __( 'Full Refund', 'wp-sell-services' ),
			self::RESOLUTION_PARTIAL_REFUND => __( 'Partial Refund', 'wp-sell-services' ),
			self::RESOLUTION_FAVOR_VENDOR   => __( 'In Favor of Vendor', 'wp-sell-services' ),
			self::RESOLUTION_FAVOR_BUYER    => __( 'In Favor of Buyer', 'wp-sell-services' ),
			self::RESOLUTION_MUTUAL         => __( 'Mutual Agreement', 'wp-sell-services' ),
		);
	}
}
