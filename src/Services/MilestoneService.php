<?php
/**
 * Milestone Service
 *
 * Handles order milestones for large projects.
 * Allows breaking orders into deliverable chunks with individual payments.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages order milestones for large projects.
 *
 * Milestones are stored as JSON in order meta until a dedicated table is added.
 *
 * @since 1.0.0
 */
class MilestoneService {

	/**
	 * Milestone status constants.
	 */
	public const STATUS_PENDING     = 'pending';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_SUBMITTED   = 'submitted';
	public const STATUS_APPROVED    = 'approved';
	public const STATUS_REJECTED    = 'rejected';

	/**
	 * Meta key for storing milestones.
	 */
	private const META_KEY = 'wpss_milestones';

	/**
	 * Create a new milestone for an order.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Milestone data.
	 * @return array{success: bool, milestone_id: string|null, message: string}
	 */
	public function create( int $order_id, array $data ): array {
		// Validate required fields.
		if ( empty( $data['title'] ) ) {
			return array(
				'success'      => false,
				'milestone_id' => null,
				'message'      => __( 'Milestone title is required.', 'wp-sell-services' ),
			);
		}

		if ( ! isset( $data['amount'] ) || $data['amount'] <= 0 ) {
			return array(
				'success'      => false,
				'milestone_id' => null,
				'message'      => __( 'Milestone amount must be greater than zero.', 'wp-sell-services' ),
			);
		}

		// Validate order exists.
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return array(
				'success'      => false,
				'milestone_id' => null,
				'message'      => __( 'Order not found.', 'wp-sell-services' ),
			);
		}

		// Get existing milestones.
		$milestones = $this->get_order_milestones( $order_id );

		// Generate milestone ID.
		$milestone_id = 'ms_' . uniqid();

		// Create milestone.
		$milestone = array(
			'id'           => $milestone_id,
			'title'        => sanitize_text_field( $data['title'] ),
			'description'  => sanitize_textarea_field( $data['description'] ?? '' ),
			'amount'       => (float) $data['amount'],
			'due_date'     => $data['due_date'] ?? null,
			'status'       => self::STATUS_PENDING,
			'deliverables' => sanitize_textarea_field( $data['deliverables'] ?? '' ),
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
			'submitted_at' => null,
			'approved_at'  => null,
		);

		$milestones[] = $milestone;

		// Save milestones.
		$this->save_milestones( $order_id, $milestones );

		/**
		 * Fires after a milestone is created.
		 *
		 * @since 1.0.0
		 *
		 * @param string $milestone_id Milestone ID.
		 * @param int    $order_id     Order ID.
		 * @param array  $milestone    Milestone data.
		 */
		do_action( 'wpss_milestone_created', $milestone_id, $order_id, $milestone );

		return array(
			'success'      => true,
			'milestone_id' => $milestone_id,
			'message'      => __( 'Milestone created successfully.', 'wp-sell-services' ),
		);
	}

	/**
	 * Update a milestone.
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $milestone_id Milestone ID.
	 * @param array  $data         Data to update.
	 * @return array{success: bool, message: string}
	 */
	public function update( int $order_id, string $milestone_id, array $data ): array {
		$milestones = $this->get_order_milestones( $order_id );
		$index      = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return array(
				'success' => false,
				'message' => __( 'Milestone not found.', 'wp-sell-services' ),
			);
		}

		// Update allowed fields with sanitization.
		$allowed = array( 'title', 'description', 'amount', 'due_date', 'deliverables' );
		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$milestones[ $index ][ $field ] = match ( $field ) {
					'title'                        => sanitize_text_field( $data[ $field ] ),
					'description', 'deliverables'  => sanitize_textarea_field( $data[ $field ] ),
					'amount'                       => (float) $data[ $field ],
					'due_date'                     => sanitize_text_field( $data[ $field ] ),
					default                        => $data[ $field ],
				};
			}
		}

		$milestones[ $index ]['updated_at'] = current_time( 'mysql' );

		$this->save_milestones( $order_id, $milestones );

		return array(
			'success' => true,
			'message' => __( 'Milestone updated successfully.', 'wp-sell-services' ),
		);
	}

	/**
	 * Get a single milestone.
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $milestone_id Milestone ID.
	 * @return array|null Milestone data or null.
	 */
	public function get( int $order_id, string $milestone_id ): ?array {
		$milestones = $this->get_order_milestones( $order_id );
		$index      = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return null;
		}

		return $milestones[ $index ];
	}

	/**
	 * Get all milestones for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array Array of milestones.
	 */
	public function get_order_milestones( int $order_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// Get order meta (stored as JSON in a meta field or separate column).
		// For now, we use WordPress post meta on the associated post if available.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $order || ! isset( $order->service_id ) ) {
			return array();
		}

		// Get milestones from order meta stored as post meta on service.
		$meta_key   = self::META_KEY . '_' . $order_id;
		$milestones = get_post_meta( $order->service_id, $meta_key, true );

		if ( ! is_array( $milestones ) ) {
			return array();
		}

		return $milestones;
	}

	/**
	 * Submit a milestone for approval.
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $milestone_id Milestone ID.
	 * @param string $message      Submission message.
	 * @param array  $attachments  Optional attachments.
	 * @return array{success: bool, message: string}
	 */
	public function submit( int $order_id, string $milestone_id, string $message = '', array $attachments = array() ): array {
		$milestones = $this->get_order_milestones( $order_id );
		$index      = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return array(
				'success' => false,
				'message' => __( 'Milestone not found.', 'wp-sell-services' ),
			);
		}

		$milestone = $milestones[ $index ];

		// Check if milestone can be submitted.
		if ( ! in_array( $milestone['status'], array( self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_REJECTED ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'This milestone cannot be submitted in its current state.', 'wp-sell-services' ),
			);
		}

		$milestones[ $index ]['status']         = self::STATUS_SUBMITTED;
		$milestones[ $index ]['submitted_at']   = current_time( 'mysql' );
		$milestones[ $index ]['updated_at']     = current_time( 'mysql' );
		$milestones[ $index ]['submit_message'] = $message;
		$milestones[ $index ]['attachments']    = $attachments;

		$this->save_milestones( $order_id, $milestones );

		/**
		 * Fires when a milestone is submitted.
		 *
		 * @since 1.0.0
		 *
		 * @param string $milestone_id Milestone ID.
		 * @param int    $order_id     Order ID.
		 */
		do_action( 'wpss_milestone_submitted', $milestone_id, $order_id );

		return array(
			'success' => true,
			'message' => __( 'Milestone submitted for approval.', 'wp-sell-services' ),
		);
	}

	/**
	 * Approve a milestone.
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $milestone_id Milestone ID.
	 * @return array{success: bool, message: string}
	 */
	public function approve( int $order_id, string $milestone_id ): array {
		$milestones = $this->get_order_milestones( $order_id );
		$index      = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return array(
				'success' => false,
				'message' => __( 'Milestone not found.', 'wp-sell-services' ),
			);
		}

		if ( self::STATUS_SUBMITTED !== $milestones[ $index ]['status'] ) {
			return array(
				'success' => false,
				'message' => __( 'Only submitted milestones can be approved.', 'wp-sell-services' ),
			);
		}

		$milestones[ $index ]['status']      = self::STATUS_APPROVED;
		$milestones[ $index ]['approved_at'] = current_time( 'mysql' );
		$milestones[ $index ]['updated_at']  = current_time( 'mysql' );

		$this->save_milestones( $order_id, $milestones );

		/**
		 * Fires when a milestone is approved.
		 *
		 * @since 1.0.0
		 *
		 * @param string $milestone_id Milestone ID.
		 * @param int    $order_id     Order ID.
		 * @param float  $amount       Milestone amount.
		 */
		do_action( 'wpss_milestone_approved', $milestone_id, $order_id, $milestones[ $index ]['amount'] );

		return array(
			'success' => true,
			'message' => __( 'Milestone approved successfully.', 'wp-sell-services' ),
		);
	}

	/**
	 * Reject a milestone with feedback.
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $milestone_id Milestone ID.
	 * @param string $feedback     Rejection feedback.
	 * @return array{success: bool, message: string}
	 */
	public function reject( int $order_id, string $milestone_id, string $feedback ): array {
		$milestones = $this->get_order_milestones( $order_id );
		$index      = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return array(
				'success' => false,
				'message' => __( 'Milestone not found.', 'wp-sell-services' ),
			);
		}

		if ( self::STATUS_SUBMITTED !== $milestones[ $index ]['status'] ) {
			return array(
				'success' => false,
				'message' => __( 'Only submitted milestones can be rejected.', 'wp-sell-services' ),
			);
		}

		$milestones[ $index ]['status']             = self::STATUS_REJECTED;
		$milestones[ $index ]['rejection_feedback'] = $feedback;
		$milestones[ $index ]['updated_at']         = current_time( 'mysql' );

		$this->save_milestones( $order_id, $milestones );

		/**
		 * Fires when a milestone is rejected.
		 *
		 * @since 1.0.0
		 *
		 * @param string $milestone_id Milestone ID.
		 * @param int    $order_id     Order ID.
		 * @param string $feedback     Rejection feedback.
		 */
		do_action( 'wpss_milestone_rejected', $milestone_id, $order_id, $feedback );

		return array(
			'success' => true,
			'message' => __( 'Milestone rejected. Vendor can revise and resubmit.', 'wp-sell-services' ),
		);
	}

	/**
	 * Delete a milestone.
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $milestone_id Milestone ID.
	 * @return array{success: bool, message: string}
	 */
	public function delete( int $order_id, string $milestone_id ): array {
		$milestones = $this->get_order_milestones( $order_id );
		$index      = $this->find_milestone_index( $milestones, $milestone_id );

		if ( false === $index ) {
			return array(
				'success' => false,
				'message' => __( 'Milestone not found.', 'wp-sell-services' ),
			);
		}

		// Can only delete pending milestones.
		if ( self::STATUS_PENDING !== $milestones[ $index ]['status'] ) {
			return array(
				'success' => false,
				'message' => __( 'Only pending milestones can be deleted.', 'wp-sell-services' ),
			);
		}

		array_splice( $milestones, $index, 1 );
		$this->save_milestones( $order_id, $milestones );

		return array(
			'success' => true,
			'message' => __( 'Milestone deleted.', 'wp-sell-services' ),
		);
	}

	/**
	 * Get milestone progress for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array Progress summary.
	 */
	public function get_progress( int $order_id ): array {
		$milestones = $this->get_order_milestones( $order_id );

		$total           = count( $milestones );
		$approved        = 0;
		$total_amount    = 0;
		$released_amount = 0;

		foreach ( $milestones as $milestone ) {
			$total_amount += $milestone['amount'];
			if ( self::STATUS_APPROVED === $milestone['status'] ) {
				++$approved;
				$released_amount += $milestone['amount'];
			}
		}

		return array(
			'total_milestones'    => $total,
			'approved_milestones' => $approved,
			'pending_milestones'  => $total - $approved,
			'total_amount'        => $total_amount,
			'released_amount'     => $released_amount,
			'pending_amount'      => $total_amount - $released_amount,
			'completion_percent'  => $total > 0 ? round( ( $approved / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get status labels.
	 *
	 * @return array<string, string> Status labels.
	 */
	public static function get_status_labels(): array {
		return array(
			self::STATUS_PENDING     => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_IN_PROGRESS => __( 'In Progress', 'wp-sell-services' ),
			self::STATUS_SUBMITTED   => __( 'Awaiting Approval', 'wp-sell-services' ),
			self::STATUS_APPROVED    => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED    => __( 'Needs Revision', 'wp-sell-services' ),
		);
	}

	/**
	 * Save milestones to storage.
	 *
	 * @param int   $order_id   Order ID.
	 * @param array $milestones Milestones array.
	 * @return bool Success.
	 */
	private function save_milestones( int $order_id, array $milestones ): bool {
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$meta_key = self::META_KEY . '_' . $order_id;
		return (bool) update_post_meta( $order->service_id, $meta_key, $milestones );
	}

	/**
	 * Find milestone index by ID.
	 *
	 * @param array  $milestones   Milestones array.
	 * @param string $milestone_id Milestone ID to find.
	 * @return int|false Index or false.
	 */
	private function find_milestone_index( array $milestones, string $milestone_id ) {
		foreach ( $milestones as $index => $milestone ) {
			if ( $milestone['id'] === $milestone_id ) {
				return $index;
			}
		}
		return false;
	}

	/**
	 * Get order by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Order row or null.
	 */
	private function get_order( int $order_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $order ? $order : null;
	}
}
