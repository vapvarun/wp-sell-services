<?php
/**
 * Review Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a service review/rating.
 *
 * @since 1.0.0
 */
class Review {

	/**
	 * Review statuses.
	 */
	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	/**
	 * Review ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	public int $order_id;

	/**
	 * Service ID.
	 *
	 * @var int
	 */
	public int $service_id;

	/**
	 * Reviewer user ID.
	 *
	 * @var int
	 */
	public int $reviewer_id;

	/**
	 * Original author name for guest/legacy reviews with no WP account
	 * (reviewer_id = 0). NULL for native reviews, which always come from a
	 * real logged-in user resolved via get_userdata( reviewer_id ).
	 *
	 * @var string|null
	 */
	public ?string $reviewer_name = null;

	/**
	 * Reviewed user ID (vendor).
	 *
	 * @var int
	 */
	public int $reviewed_id;

	/**
	 * Overall rating (1-5).
	 *
	 * @var int
	 */
	public int $rating;

	/**
	 * Communication rating (1-5).
	 *
	 * @var int|null
	 */
	public ?int $rating_communication;

	/**
	 * Service quality rating (1-5).
	 *
	 * @var int|null
	 */
	public ?int $rating_quality;

	/**
	 * Value for money rating (1-5).
	 *
	 * @var int|null
	 */
	public ?int $rating_value;

	/**
	 * Review title.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * Review content.
	 *
	 * @var string
	 */
	public string $content;

	/**
	 * Vendor response.
	 *
	 * @var string
	 */
	public string $response = '';

	/**
	 * Response timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $response_at;

	/**
	 * Review status.
	 *
	 * @var string
	 */
	public string $status = self::STATUS_PENDING;

	/**
	 * Whether review is verified purchase.
	 *
	 * @var bool
	 */
	public bool $is_verified = true;

	/**
	 * Helpful votes count.
	 *
	 * @var int
	 */
	public int $helpful_count = 0;

	/**
	 * Created timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $created_at;

	/**
	 * Updated timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $updated_at;

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_db( object $row ): self {
		$review = new self();

		$review->id          = (int) $row->id;
		$review->order_id    = (int) $row->order_id;
		$review->service_id  = (int) $row->service_id;
		$review->reviewer_id = (int) $row->reviewer_id;
		// Carried-over guest author name (WooCommerce comment_author). NULL/empty
		// for native reviews, which resolve their name from the user account.
		$review->reviewer_name = isset( $row->reviewer_name ) && '' !== (string) $row->reviewer_name
			? (string) $row->reviewer_name
			: null;
		// Map reviewee_id from DB to reviewed_id property.
		$review->reviewed_id = (int) ( $row->reviewee_id ?? $row->vendor_id ?? 0 );
		$review->rating      = (int) $row->rating;
		// Map DB column names to model properties.
		$review->rating_communication = isset( $row->communication_rating ) ? (int) $row->communication_rating : null;
		$review->rating_quality       = isset( $row->quality_rating ) ? (int) $row->quality_rating : null;
		$review->rating_value         = isset( $row->delivery_rating ) ? (int) $row->delivery_rating : null;
		$review->title                = '';
		// Map review column from DB to content property.
		$review->content = $row->review ?? '';
		// Map vendor_reply column from DB to response property.
		$review->response      = $row->vendor_reply ?? '';
		$review->status        = $row->status ?? self::STATUS_PENDING;
		$review->is_verified   = isset( $row->is_public ) ? (bool) $row->is_public : true;
		$review->helpful_count = (int) ( $row->helpful_count ?? 0 );

		// Timestamps.
		$review->response_at = ! empty( $row->vendor_reply_at ) ? new \DateTimeImmutable( $row->vendor_reply_at ) : null;
		$review->created_at  = ! empty( $row->created_at ) ? new \DateTimeImmutable( $row->created_at ) : null;
		$review->updated_at  = null;

		return $review;
	}

	/**
	 * Get all review statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return array(
			self::STATUS_PENDING  => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
		);
	}

	/**
	 * Get reviewer user.
	 *
	 * @return \WP_User|null
	 */
	public function get_reviewer(): ?\WP_User {
		$user = get_user_by( 'id', $this->reviewer_id );
		return $user ? $user : null;
	}

	/**
	 * Get reviewer name for display.
	 *
	 * @return string
	 */
	public function get_reviewer_name(): string {
		return self::resolve_reviewer_name( $this->reviewer_id, $this->reviewer_name );
	}

	/**
	 * Resolve a reviewer's display name from an ID + stored guest name.
	 *
	 * Canonical name-resolution used by every review display surface
	 * (frontend, admin moderation, REST, SEO schema) so migrated guest
	 * reviews (reviewer_id = 0, name carried in reviewer_name) never fall
	 * back to "Anonymous" when the original author is known.
	 *
	 * Order of precedence:
	 *   1. Registered user's display_name (get_userdata( reviewer_id )).
	 *   2. Stored guest name (reviewer_name — e.g. WooCommerce comment_author).
	 *   3. "Anonymous" for genuinely nameless rows.
	 *
	 * @param int         $reviewer_id   Reviewer user ID (0 for guest/legacy).
	 * @param string|null $reviewer_name Stored guest author name, if any.
	 * @return string
	 */
	public static function resolve_reviewer_name( int $reviewer_id, ?string $reviewer_name = null ): string {
		if ( $reviewer_id > 0 ) {
			$user = get_userdata( $reviewer_id );
			if ( $user && '' !== (string) $user->display_name ) {
				return $user->display_name;
			}
		}

		$reviewer_name = null !== $reviewer_name ? trim( $reviewer_name ) : '';
		if ( '' !== $reviewer_name ) {
			return $reviewer_name;
		}

		return __( 'Anonymous', 'wp-sell-services' );
	}

	/**
	 * Get reviewed vendor profile.
	 *
	 * @return VendorProfile|null
	 */
	public function get_vendor(): ?VendorProfile {
		return VendorProfile::get_by_user_id( $this->reviewed_id );
	}

	/**
	 * Get service.
	 *
	 * @return Service|null
	 */
	public function get_service(): ?Service {
		$post = get_post( $this->service_id );
		return $post ? Service::from_post( $post ) : null;
	}

	/**
	 * Get average of all rating categories.
	 *
	 * @return float
	 */
	public function get_average_rating(): float {
		$ratings = array( $this->rating );

		if ( null !== $this->rating_communication ) {
			$ratings[] = $this->rating_communication;
		}
		if ( null !== $this->rating_quality ) {
			$ratings[] = $this->rating_quality;
		}
		if ( null !== $this->rating_value ) {
			$ratings[] = $this->rating_value;
		}

		return array_sum( $ratings ) / count( $ratings );
	}

	/**
	 * Check if review has vendor response.
	 *
	 * @return bool
	 */
	public function has_response(): bool {
		return ! empty( $this->response );
	}

	/**
	 * Check if review is approved.
	 *
	 * @return bool
	 */
	public function is_approved(): bool {
		return self::STATUS_APPROVED === $this->status;
	}
}
