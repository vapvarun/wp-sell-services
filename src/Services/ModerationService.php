<?php
/**
 * Moderation Service
 *
 * Business logic for service moderation queue.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ModerationService class.
 *
 * Handles service moderation queue for admin approval workflow.
 *
 * @since 1.0.0
 */
class ModerationService {

	/**
	 * Post meta key for moderation status.
	 *
	 * @var string
	 */
	public const META_MODERATION_STATUS = '_wpss_moderation_status';

	/**
	 * Post meta key for moderation notes.
	 *
	 * @var string
	 */
	public const META_MODERATION_NOTES = '_wpss_moderation_notes';

	/**
	 * Post meta key for rejection reason (matches ServiceModerationPage).
	 *
	 * @var string
	 */
	public const META_REJECTION_REASON = '_wpss_rejection_reason';

	/**
	 * Post meta key for moderation timestamp.
	 *
	 * @var string
	 */
	public const META_MODERATED_AT = '_wpss_moderated_at';

	/**
	 * Post meta key for moderator ID.
	 *
	 * @var string
	 */
	public const META_MODERATOR_ID = '_wpss_moderator_id';

	/**
	 * Moderation status: pending review.
	 *
	 * @var string
	 */
	public const STATUS_PENDING = 'pending';

	/**
	 * Moderation status: approved.
	 *
	 * @var string
	 */
	public const STATUS_APPROVED = 'approved';

	/**
	 * Moderation status: rejected.
	 *
	 * @var string
	 */
	public const STATUS_REJECTED = 'rejected';

	/**
	 * Service post type.
	 *
	 * @var string
	 */
	private const POST_TYPE = 'wpss_service';

	/**
	 * Check if moderation is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = get_option( 'wpss_vendor', array() );
		return ! empty( $settings['require_service_moderation'] );
	}

	/**
	 * Get services pending moderation.
	 *
	 * @param array $args Optional query arguments.
	 * @return array Array of WP_Post objects.
	 */
	public function get_pending_services( array $args = array() ): array {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'pending',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => self::META_MODERATION_STATUS,
					'value'   => self::STATUS_PENDING,
					'compare' => '=',
				),
				array(
					'key'     => self::META_MODERATION_STATUS,
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$args = wp_parse_args( $args, $defaults );

		return get_posts( $args );
	}

	/**
	 * Get count of pending services.
	 *
	 * @return int
	 */
	public function get_pending_count(): int {
		$posts = $this->get_pending_services(
			array(
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		return count( $posts );
	}

	/**
	 * Approve a service.
	 *
	 * @param int    $service_id Service post ID.
	 * @param string $notes      Optional approval notes.
	 * @return bool True on success, false on failure.
	 */
	public function approve( int $service_id, string $notes = '' ): bool {
		$post = get_post( $service_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		// Update post status to publish.
		$result = wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $result ) || 0 === $result ) {
			return false;
		}

		// Update moderation meta.
		update_post_meta( $service_id, self::META_MODERATION_STATUS, self::STATUS_APPROVED );
		update_post_meta( $service_id, self::META_MODERATED_AT, current_time( 'mysql' ) );
		update_post_meta( $service_id, self::META_MODERATOR_ID, get_current_user_id() );

		if ( ! empty( $notes ) ) {
			update_post_meta( $service_id, self::META_MODERATION_NOTES, sanitize_textarea_field( $notes ) );
		}

		/**
		 * Fires after a service is approved.
		 *
		 * @param int    $service_id Service post ID.
		 * @param string $notes      Approval notes.
		 */
		do_action( 'wpss_service_approved', $service_id, $notes );

		// Send notification to vendor.
		$this->send_approval_notification( $service_id );

		return true;
	}

	/**
	 * Reject a service.
	 *
	 * @param int    $service_id Service post ID.
	 * @param string $reason     Rejection reason (required).
	 * @return bool True on success, false on failure.
	 */
	public function reject( int $service_id, string $reason ): bool {
		$post = get_post( $service_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		if ( empty( $reason ) ) {
			return false;
		}

		// Update post status to draft.
		$result = wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => 'draft',
			)
		);

		if ( is_wp_error( $result ) || 0 === $result ) {
			return false;
		}

		// Update moderation meta.
		update_post_meta( $service_id, self::META_MODERATION_STATUS, self::STATUS_REJECTED );
		update_post_meta( $service_id, self::META_MODERATED_AT, current_time( 'mysql' ) );
		update_post_meta( $service_id, self::META_MODERATOR_ID, get_current_user_id() );
		update_post_meta( $service_id, self::META_MODERATION_NOTES, sanitize_textarea_field( $reason ) );
		// Also store in rejection reason key for compatibility with ServiceModerationPage.
		update_post_meta( $service_id, self::META_REJECTION_REASON, sanitize_textarea_field( $reason ) );

		/**
		 * Fires after a service is rejected.
		 *
		 * @param int    $service_id Service post ID.
		 * @param string $reason     Rejection reason.
		 */
		do_action( 'wpss_service_rejected', $service_id, $reason );

		// Send notification to vendor.
		$this->send_rejection_notification( $service_id, $reason );

		return true;
	}

	/**
	 * Set a service as pending moderation.
	 *
	 * @param int $service_id Service post ID.
	 * @return bool True on success, false on failure.
	 */
	public function set_pending( int $service_id ): bool {
		$post = get_post( $service_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		// Update post status to pending.
		$result = wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => 'pending',
			)
		);

		if ( is_wp_error( $result ) || 0 === $result ) {
			return false;
		}

		update_post_meta( $service_id, self::META_MODERATION_STATUS, self::STATUS_PENDING );

		/**
		 * Fires when a service is submitted for moderation.
		 *
		 * @param int $service_id Service post ID.
		 */
		do_action( 'wpss_service_pending_moderation', $service_id );

		// Send notification to admins.
		$this->send_admin_notification( $service_id );

		return true;
	}

	/**
	 * Get moderation history for a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return array Moderation data.
	 */
	public function get_moderation_data( int $service_id ): array {
		return array(
			'status'       => get_post_meta( $service_id, self::META_MODERATION_STATUS, true ),
			'notes'        => get_post_meta( $service_id, self::META_MODERATION_NOTES, true ),
			'moderated_at' => get_post_meta( $service_id, self::META_MODERATED_AT, true ),
			'moderator_id' => get_post_meta( $service_id, self::META_MODERATOR_ID, true ),
		);
	}

	/**
	 * Send approval notification to vendor.
	 *
	 * @param int $service_id Service post ID.
	 * @return void
	 */
	private function send_approval_notification( int $service_id ): void {
		$post = get_post( $service_id );
		if ( ! $post ) {
			return;
		}

		$vendor = get_user_by( 'id', $post->post_author );
		if ( ! $vendor ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: Service title */
			__( 'Your service "%s" has been approved', 'wp-sell-services' ),
			$post->post_title
		);

		$message = sprintf(
			/* translators: 1: User name, 2: Service title, 3: Service URL */
			__(
				'Hi %1$s,

Great news! Your service "%2$s" has been approved and is now live on the marketplace.

View your service: %3$s

Thank you for being a valued seller on our platform.',
				'wp-sell-services'
			),
			$vendor->display_name,
			$post->post_title,
			get_permalink( $service_id )
		);

		if ( EmailService::is_type_enabled( 'moderation_approved' ) ) {
			wp_mail( $vendor->user_email, $subject, $message );
		}

		// Also add platform notification.
		if ( function_exists( 'wpss_add_notification' ) ) {
			wpss_add_notification(
				$vendor->ID,
				'service_approved',
				sprintf(
					/* translators: %s: Service title */
					__( 'Your service "%s" has been approved and is now live.', 'wp-sell-services' ),
					$post->post_title
				),
				array(
					'service_id' => $service_id,
					'link'       => get_permalink( $service_id ),
				)
			);
		}
	}

	/**
	 * Send rejection notification to vendor.
	 *
	 * @param int    $service_id Service post ID.
	 * @param string $reason     Rejection reason.
	 * @return void
	 */
	private function send_rejection_notification( int $service_id, string $reason ): void {
		$post = get_post( $service_id );
		if ( ! $post ) {
			return;
		}

		$vendor = get_user_by( 'id', $post->post_author );
		if ( ! $vendor ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: Service title */
			__( 'Your service "%s" requires changes', 'wp-sell-services' ),
			$post->post_title
		);

		$edit_url = add_query_arg(
			array(
				'action'     => 'edit',
				'service_id' => $service_id,
			),
			wc_get_endpoint_url( 'vendor-services', '', wc_get_page_permalink( 'myaccount' ) )
		);

		$message = sprintf(
			/* translators: 1: User name, 2: Service title, 3: Rejection reason, 4: Edit URL */
			__(
				'Hi %1$s,

Unfortunately, your service "%2$s" could not be approved at this time.

Reason: %3$s

Please review and update your service based on the feedback above, then resubmit for approval.

Edit your service: %4$s

If you have any questions, please contact our support team.',
				'wp-sell-services'
			),
			$vendor->display_name,
			$post->post_title,
			$reason,
			$edit_url
		);

		if ( EmailService::is_type_enabled( 'moderation_rejected' ) ) {
			wp_mail( $vendor->user_email, $subject, $message );
		}

		// Also add platform notification.
		if ( function_exists( 'wpss_add_notification' ) ) {
			wpss_add_notification(
				$vendor->ID,
				'service_rejected',
				sprintf(
					/* translators: %s: Service title */
					__( 'Your service "%s" requires changes before approval.', 'wp-sell-services' ),
					$post->post_title
				),
				array(
					'service_id' => $service_id,
					'reason'     => $reason,
					'link'       => $edit_url,
				)
			);
		}
	}

	/**
	 * Send notification to admins about new pending service.
	 *
	 * @param int $service_id Service post ID.
	 * @return void
	 */
	private function send_admin_notification( int $service_id ): void {
		$post = get_post( $service_id );
		if ( ! $post ) {
			return;
		}

		$vendor = get_user_by( 'id', $post->post_author );

		$subject = sprintf(
			/* translators: %s: Service title */
			__( 'New service pending review: %s', 'wp-sell-services' ),
			$post->post_title
		);

		$review_url = add_query_arg(
			array(
				'page'       => 'wpss-moderation',
				'service_id' => $service_id,
			),
			admin_url( 'admin.php' )
		);

		$message = sprintf(
			/* translators: 1: Service title, 2: Vendor name, 3: Review URL */
			__(
				'A new service is pending review:

Service: %1$s
Submitted by: %2$s

Review this service: %3$s',
				'wp-sell-services'
			),
			$post->post_title,
			$vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ),
			$review_url
		);

		// Send to admin email (respects email settings).
		if ( EmailService::is_type_enabled( 'moderation_pending' ) ) {
			wp_mail( get_option( 'admin_email' ), $subject, $message );
		}
	}

	/**
	 * Get all moderation statuses with labels.
	 *
	 * @return array
	 */
	public static function get_statuses(): array {
		return array(
			self::STATUS_PENDING  => __( 'Pending Review', 'wp-sell-services' ),
			self::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
		);
	}
}
