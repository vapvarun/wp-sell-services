<?php
/**
 * Review Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

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

		$review->id                   = (int) $row->id;
		$review->order_id             = (int) $row->order_id;
		$review->service_id           = (int) $row->service_id;
		$review->reviewer_id          = (int) $row->reviewer_id;
		$review->reviewed_id          = (int) $row->reviewed_id;
		$review->rating               = (int) $row->rating;
		$review->rating_communication = $row->rating_communication ? (int) $row->rating_communication : null;
		$review->rating_quality       = $row->rating_quality ? (int) $row->rating_quality : null;
		$review->rating_value         = $row->rating_value ? (int) $row->rating_value : null;
		$review->title                = $row->title ?? '';
		$review->content              = $row->content;
		$review->response             = $row->response ?? '';
		$review->status               = $row->status ?? self::STATUS_PENDING;
		$review->is_verified          = (bool) $row->is_verified;
		$review->helpful_count        = (int) $row->helpful_count;

		// Timestamps.
		$review->response_at = $row->response_at ? new \DateTimeImmutable( $row->response_at ) : null;
		$review->created_at  = $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$review->updated_at  = $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;

		return $review;
	}

	/**
	 * Get all review statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_PENDING  => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
		];
	}

	/**
	 * Get reviewer user.
	 *
	 * @return \WP_User|null
	 */
	public function get_reviewer(): ?\WP_User {
		return get_user_by( 'id', $this->reviewer_id ) ?: null;
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
		$ratings = [ $this->rating ];

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
