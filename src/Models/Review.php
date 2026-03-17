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
	 * Get reviewer name.
	 *
	 * @return string
	 */
	public function get_reviewer_name(): string {
		$user = $this->get_reviewer();
		return $user ? $user->display_name : __( 'Anonymous', 'wp-sell-services' );
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

	/**
	 * Get star HTML.
	 *
	 * @param int $rating Rating value.
	 * @return string
	 */
	public static function get_stars_html( int $rating ): string {
		/* translators: %d: star rating (1-5) */
		$html = '<span class="wpss-stars" aria-label="' . esc_attr( sprintf( __( '%d out of 5 stars', 'wp-sell-services' ), $rating ) ) . '">';

		for ( $i = 1; $i <= 5; $i++ ) {
			if ( $i <= $rating ) {
				$html .= '<span class="wpss-star wpss-star--filled">★</span>';
			} else {
				$html .= '<span class="wpss-star wpss-star--empty">☆</span>';
			}
		}

		$html .= '</span>';

		return $html;
	}
}
