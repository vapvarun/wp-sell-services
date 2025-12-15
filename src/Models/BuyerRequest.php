<?php
/**
 * Buyer Request Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents a buyer request (job post).
 *
 * @since 1.0.0
 */
class BuyerRequest {

	/**
	 * Request ID.
	 *
	 * @var int
	 */
	public int $id = 0;

	/**
	 * Request title.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * Request description.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * Author (buyer) ID.
	 *
	 * @var int
	 */
	public int $author_id = 0;

	/**
	 * Category ID.
	 *
	 * @var int|null
	 */
	public ?int $category_id = null;

	/**
	 * Minimum budget.
	 *
	 * @var float
	 */
	public float $budget_min = 0.0;

	/**
	 * Maximum budget.
	 *
	 * @var float
	 */
	public float $budget_max = 0.0;

	/**
	 * Deadline date.
	 *
	 * @var string|null
	 */
	public ?string $deadline = null;

	/**
	 * Request status.
	 *
	 * @var string
	 */
	public string $status = 'open';

	/**
	 * Attachments (IDs).
	 *
	 * @var array
	 */
	public array $attachments = [];

	/**
	 * Number of proposals.
	 *
	 * @var int
	 */
	public int $proposal_count = 0;

	/**
	 * Created timestamp.
	 *
	 * @var string
	 */
	public string $created_at = '';

	/**
	 * Updated timestamp.
	 *
	 * @var string
	 */
	public string $updated_at = '';

	/**
	 * Valid statuses.
	 */
	public const STATUS_OPEN    = 'open';
	public const STATUS_CLOSED  = 'closed';
	public const STATUS_HIRED   = 'hired';
	public const STATUS_EXPIRED = 'expired';

	/**
	 * Create from post object.
	 *
	 * @param \WP_Post $post Post object.
	 * @return self
	 */
	public static function from_post( \WP_Post $post ): self {
		$request = new self();

		$request->id             = $post->ID;
		$request->title          = $post->post_title;
		$request->description    = $post->post_content;
		$request->author_id      = (int) $post->post_author;
		$request->status         = get_post_meta( $post->ID, '_wpss_status', true ) ?: self::STATUS_OPEN;
		$request->budget_min     = (float) get_post_meta( $post->ID, '_wpss_budget_min', true );
		$request->budget_max     = (float) get_post_meta( $post->ID, '_wpss_budget_max', true );
		$request->deadline       = get_post_meta( $post->ID, '_wpss_deadline', true ) ?: null;
		$request->attachments    = get_post_meta( $post->ID, '_wpss_attachments', true ) ?: [];
		$request->proposal_count = (int) get_post_meta( $post->ID, '_wpss_proposal_count', true );
		$request->created_at     = $post->post_date;
		$request->updated_at     = $post->post_modified;

		// Get category.
		$categories = wp_get_post_terms( $post->ID, 'wpss_service_category', [ 'fields' => 'ids' ] );
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$request->category_id = $categories[0];
		}

		return $request;
	}

	/**
	 * Get the buyer user object.
	 *
	 * @return \WP_User|false
	 */
	public function get_buyer() {
		return get_userdata( $this->author_id );
	}

	/**
	 * Get category term.
	 *
	 * @return \WP_Term|null
	 */
	public function get_category(): ?\WP_Term {
		if ( ! $this->category_id ) {
			return null;
		}

		$term = get_term( $this->category_id, 'wpss_service_category' );
		return ! is_wp_error( $term ) ? $term : null;
	}

	/**
	 * Get attachment URLs.
	 *
	 * @return array
	 */
	public function get_attachment_urls(): array {
		$urls = [];
		foreach ( $this->attachments as $id ) {
			$url = wp_get_attachment_url( $id );
			if ( $url ) {
				$urls[] = [
					'id'       => $id,
					'url'      => $url,
					'filename' => basename( get_attached_file( $id ) ),
					'type'     => get_post_mime_type( $id ),
				];
			}
		}
		return $urls;
	}

	/**
	 * Check if request is open for proposals.
	 *
	 * @return bool
	 */
	public function is_open(): bool {
		return self::STATUS_OPEN === $this->status;
	}

	/**
	 * Check if request is expired.
	 *
	 * @return bool
	 */
	public function is_expired(): bool {
		if ( ! $this->deadline ) {
			return false;
		}
		return strtotime( $this->deadline ) < time();
	}

	/**
	 * Get budget display string.
	 *
	 * @return string
	 */
	public function get_budget_display(): string {
		if ( $this->budget_min && $this->budget_max ) {
			if ( function_exists( 'wpss_format_currency' ) ) {
				return sprintf(
					'%s - %s',
					wpss_format_currency( $this->budget_min ),
					wpss_format_currency( $this->budget_max )
				);
			}
			return sprintf( '$%s - $%s', number_format( $this->budget_min, 2 ), number_format( $this->budget_max, 2 ) );
		}

		if ( $this->budget_max ) {
			if ( function_exists( 'wpss_format_currency' ) ) {
				return sprintf( __( 'Up to %s', 'wp-sell-services' ), wpss_format_currency( $this->budget_max ) );
			}
			return sprintf( __( 'Up to $%s', 'wp-sell-services' ), number_format( $this->budget_max, 2 ) );
		}

		return __( 'Open budget', 'wp-sell-services' );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'             => $this->id,
			'title'          => $this->title,
			'description'    => $this->description,
			'author_id'      => $this->author_id,
			'category_id'    => $this->category_id,
			'budget_min'     => $this->budget_min,
			'budget_max'     => $this->budget_max,
			'deadline'       => $this->deadline,
			'status'         => $this->status,
			'attachments'    => $this->attachments,
			'proposal_count' => $this->proposal_count,
			'created_at'     => $this->created_at,
			'updated_at'     => $this->updated_at,
		];
	}

	/**
	 * Get available statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_OPEN    => __( 'Open', 'wp-sell-services' ),
			self::STATUS_CLOSED  => __( 'Closed', 'wp-sell-services' ),
			self::STATUS_HIRED   => __( 'Hired', 'wp-sell-services' ),
			self::STATUS_EXPIRED => __( 'Expired', 'wp-sell-services' ),
		];
	}
}
