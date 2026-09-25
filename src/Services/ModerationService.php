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
		/**
		 * Filter whether new/updated services require moderation.
		 *
		 * The Plugin.php settings bridge resolves the default from
		 * wpss_vendor['require_service_moderation']; third parties can
		 * override the moderation gate programmatically.
		 *
		 * @since 1.2.0
		 *
		 * @param bool $require Whether services land as pending for review.
		 */
		return (bool) apply_filters( 'wpss_require_service_moderation', (bool) wpss_get_option( 'vendor', 'require_service_moderation' ) );
	}

	/**
	 * Get services pending moderation.
	 *
	 * @param array $args Optional query arguments.
	 * @return array Array of WP_Post objects.
	 */
	public function get_pending_services( array $args = array() ): array {
		$defaults = array(
			'post_type'             => self::POST_TYPE,
			'post_status'           => 'pending',
			'posts_per_page'        => 20,
			'orderby'               => 'date',
			'order'                 => 'ASC',
			'wpss_moderation_state' => self::STATUS_PENDING,
			'suppress_filters'      => false, // The state filter is a posts_where filter.
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
		return wpss_count_pending_services();
	}

	/**
	 * Approve a service and put it live.
	 *
	 * The one approval path: the moderation screen (single and bulk) and REST
	 * all call this. They used to carry four copies that disagreed - REST never
	 * wrote the moderation meta, so a REST-approved service stayed "pending"
	 * and hidden - and none of them checked the publish rules, so approving an
	 * incomplete service reported success and notified the vendor while the
	 * editor's rules quietly put it back to draft (Basecamp 10337188110).
	 *
	 * @param int    $service_id Service post ID.
	 * @param string $notes      Optional approval notes.
	 * @return true|\WP_Error
	 */
	public function approve( int $service_id, string $notes = '' ) {
		$post = get_post( $service_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'wpss_service_not_found', __( 'Service not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$missing = wpss_get_service_publish_errors( $service_id );

		if ( $missing ) {
			return new \WP_Error(
				'wpss_service_incomplete',
				/* translators: %s: what the service is missing, as sentences. */
				sprintf( __( 'This service cannot go live yet. %s', 'wp-sell-services' ), implode( ' ', $missing ) ),
				array( 'status' => 400 )
			);
		}

		// The meta first: the publish guard lets a service go live only once it
		// is approved.
		$previous = get_post_meta( $service_id, self::META_MODERATION_STATUS, true );
		update_post_meta( $service_id, self::META_MODERATION_STATUS, self::STATUS_APPROVED );

		wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => 'publish',
			)
		);

		if ( 'publish' !== get_post_status( $service_id ) ) {
			update_post_meta( $service_id, self::META_MODERATION_STATUS, $previous ? $previous : self::STATUS_PENDING );
			return new \WP_Error( 'wpss_service_not_published', __( 'The service could not be published.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		delete_post_meta( $service_id, self::META_REJECTION_REASON );
		$this->record( $service_id, 'approved', $notes );

		/**
		 * Fires after a service is approved.
		 *
		 * @param int    $service_id Service post ID.
		 * @param string $notes      Approval notes.
		 */
		do_action( 'wpss_service_approved', $service_id, $notes );

		return true;
	}

	/**
	 * Reject a service and take it off the marketplace.
	 *
	 * The one rejection path (see approve()). A rejected service is a draft
	 * carrying the rejected state, which is what the vendor dashboard keys its
	 * "Resubmit for review" on. The reason is optional, as the screen says.
	 *
	 * @param int    $service_id Service post ID.
	 * @param string $reason     Rejection reason shown to the vendor.
	 * @return true|\WP_Error
	 */
	public function reject( int $service_id, string $reason = '' ) {
		$post = get_post( $service_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'wpss_service_not_found', __( 'Service not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$reason = sanitize_textarea_field( $reason );

		update_post_meta( $service_id, self::META_MODERATION_STATUS, self::STATUS_REJECTED );

		if ( '' !== $reason ) {
			update_post_meta( $service_id, self::META_REJECTION_REASON, $reason );
		} else {
			delete_post_meta( $service_id, self::META_REJECTION_REASON );
		}

		if ( 'draft' !== $post->post_status ) {
			wp_update_post(
				array(
					'ID'          => $service_id,
					'post_status' => 'draft',
				)
			);
		}

		$this->record( $service_id, 'rejected', $reason );

		/**
		 * Fires after a service is rejected.
		 *
		 * @param int    $service_id Service post ID.
		 * @param string $reason     Rejection reason.
		 */
		do_action( 'wpss_service_rejected', $service_id, $reason );

		return true;
	}

	/**
	 * Record who moderated a service, when, and why, including the history
	 * REST serves at GET /moderation/{id}.
	 *
	 * @param int    $service_id Service post ID.
	 * @param string $action     'approved' or 'rejected'.
	 * @param string $notes      Notes or reason.
	 * @return void
	 */
	private function record( int $service_id, string $action, string $notes ): void {
		update_post_meta( $service_id, self::META_MODERATED_AT, current_time( 'mysql' ) );
		update_post_meta( $service_id, self::META_MODERATOR_ID, get_current_user_id() );
		update_post_meta( $service_id, self::META_MODERATION_NOTES, sanitize_textarea_field( $notes ) );

		$history   = get_post_meta( $service_id, '_wpss_moderation_history', true );
		$history   = is_array( $history ) ? $history : array();
		$history[] = array(
			'action'     => $action,
			'notes'      => sanitize_textarea_field( $notes ),
			'admin_id'   => get_current_user_id(),
			'admin_name' => wp_get_current_user()->display_name,
			'date'       => current_time( 'mysql', true ),
		);
		update_post_meta( $service_id, '_wpss_moderation_history', $history );
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
			'status'       => wpss_get_service_moderation_state( $service_id ),
			'notes'        => get_post_meta( $service_id, self::META_MODERATION_NOTES, true ),
			'moderated_at' => get_post_meta( $service_id, self::META_MODERATED_AT, true ),
			'moderator_id' => get_post_meta( $service_id, self::META_MODERATOR_ID, true ),
		);
	}

	/**
	 * Tell the vendor their service was approved: branded mail plus in-app row.
	 *
	 * Bound to `wpss_service_approved` in Plugin.php, so every surface that
	 * approves a service reaches this once.
	 *
	 * @param int $service_id Service post ID.
	 * @return void
	 */
	public function notify_approved( int $service_id ): void {
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

		( new EmailService() )->send(
			$vendor->user_email,
			$subject,
			EmailService::TYPE_MODERATION_APPROVED,
			array(
				'recipient'     => $vendor,
				'service_title' => $post->post_title,
				'service_url'   => get_permalink( $service_id ),
			)
		);

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
	 * Tell the vendor their service needs changes: branded mail plus in-app row.
	 *
	 * Bound to `wpss_service_rejected` in Plugin.php.
	 *
	 * @param int    $service_id Service post ID.
	 * @param string $reason     Rejection reason.
	 * @return void
	 */
	public function notify_rejected( int $service_id, string $reason ): void {
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

		// The service wizard lives at the `create` section and reads `?id=`.
		// This link used to name a section (`edit-service`) that has no
		// template and an argument (`service_id`) the wizard does not read, so
		// a vendor who followed a moderation email hit "Section Not Available"
		// with no way back to the service they were asked to change.
		$edit_url = add_query_arg(
			'id',
			$service_id,
			wpss_get_dashboard_url( 'create' )
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

		( new EmailService() )->send(
			$vendor->user_email,
			$subject,
			EmailService::TYPE_MODERATION_REJECTED,
			array(
				'recipient'        => $vendor,
				'service_title'    => $post->post_title,
				'rejection_reason' => $reason,
				'edit_url'         => $edit_url,
			)
		);

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
			$admin_email = get_option( 'admin_email' );
			( new EmailService() )->send(
				$admin_email,
				$subject,
				EmailService::TYPE_MODERATION_PENDING,
				array(
					'recipient'     => get_user_by( 'email', $admin_email ),
					'service_title' => $post->post_title,
					'vendor_name'   => $vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ),
					'review_url'    => $review_url,
				)
			);
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
