<?php
/**
 * Service Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents a service offering.
 *
 * @since 1.0.0
 */
class Service {

	/**
	 * Service ID (CPT post ID).
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Service title.
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * Service description.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Service excerpt/short description.
	 *
	 * @var string
	 */
	public string $excerpt;

	/**
	 * Vendor/author user ID.
	 *
	 * @var int
	 */
	public int $vendor_id;

	/**
	 * Service status.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Featured image ID.
	 *
	 * @var int|null
	 */
	public ?int $thumbnail_id;

	/**
	 * Category IDs.
	 *
	 * @var int[]
	 */
	public array $categories = [];

	/**
	 * Tag IDs.
	 *
	 * @var int[]
	 */
	public array $tags = [];

	/**
	 * Service packages.
	 *
	 * @var ServicePackage[]
	 */
	public array $packages = [];

	/**
	 * Service add-ons.
	 *
	 * @var ServiceAddon[]
	 */
	public array $addons = [];

	/**
	 * Requirement fields.
	 *
	 * @var array
	 */
	public array $requirements = [];

	/**
	 * Gallery image IDs.
	 *
	 * @var int[]
	 */
	public array $gallery = [];

	/**
	 * FAQs.
	 *
	 * @var array<array{question: string, answer: string}>
	 */
	public array $faqs = [];

	/**
	 * Platform mapping (e.g., WooCommerce product ID).
	 *
	 * @var array<string, int>
	 */
	public array $platform_ids = [];

	/**
	 * Average rating.
	 *
	 * @var float
	 */
	public float $rating = 0.0;

	/**
	 * Total review count.
	 *
	 * @var int
	 */
	public int $review_count = 0;

	/**
	 * Total orders completed.
	 *
	 * @var int
	 */
	public int $orders_completed = 0;

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
	 * Create Service from post object.
	 *
	 * @param \WP_Post $post WordPress post object.
	 * @return self
	 */
	public static function from_post( \WP_Post $post ): self {
		$service = new self();

		$service->id           = $post->ID;
		$service->title        = $post->post_title;
		$service->description  = $post->post_content;
		$service->excerpt      = $post->post_excerpt;
		$service->vendor_id    = (int) $post->post_author;
		$service->status       = $post->post_status;
		$service->thumbnail_id = get_post_thumbnail_id( $post->ID ) ?: null;
		$service->created_at   = new \DateTimeImmutable( $post->post_date_gmt );
		$service->updated_at   = new \DateTimeImmutable( $post->post_modified_gmt );

		// Load categories and tags.
		$service->categories = wp_get_post_terms( $post->ID, 'wpss_service_category', [ 'fields' => 'ids' ] ) ?: [];
		$service->tags       = wp_get_post_terms( $post->ID, 'wpss_service_tag', [ 'fields' => 'ids' ] ) ?: [];

		// Load meta.
		$service->gallery      = get_post_meta( $post->ID, '_wpss_gallery', true ) ?: [];
		$service->requirements = get_post_meta( $post->ID, '_wpss_requirements', true ) ?: [];
		$service->faqs         = get_post_meta( $post->ID, '_wpss_faqs', true ) ?: [];
		$service->platform_ids = get_post_meta( $post->ID, '_wpss_platform_ids', true ) ?: [];

		// Stats.
		$service->rating           = (float) get_post_meta( $post->ID, '_wpss_rating', true );
		$service->review_count     = (int) get_post_meta( $post->ID, '_wpss_review_count', true );
		$service->orders_completed = (int) get_post_meta( $post->ID, '_wpss_orders_completed', true );

		return $service;
	}

	/**
	 * Get the starting price (lowest package price).
	 *
	 * @return float
	 */
	public function get_starting_price(): float {
		if ( empty( $this->packages ) ) {
			return 0.0;
		}

		$prices = array_map( fn( ServicePackage $p ) => $p->price, $this->packages );
		return min( $prices );
	}

	/**
	 * Get the fastest delivery time.
	 *
	 * @return int Days.
	 */
	public function get_fastest_delivery(): int {
		if ( empty( $this->packages ) ) {
			return 0;
		}

		$days = array_map( fn( ServicePackage $p ) => $p->delivery_days, $this->packages );
		return min( $days );
	}

	/**
	 * Get vendor profile.
	 *
	 * @return VendorProfile|null
	 */
	public function get_vendor(): ?VendorProfile {
		return VendorProfile::get_by_user_id( $this->vendor_id );
	}

	/**
	 * Get permalink.
	 *
	 * @return string
	 */
	public function get_permalink(): string {
		return get_permalink( $this->id );
	}

	/**
	 * Get thumbnail URL.
	 *
	 * @param string $size Image size.
	 * @return string
	 */
	public function get_thumbnail_url( string $size = 'large' ): string {
		if ( ! $this->thumbnail_id ) {
			return '';
		}

		return wp_get_attachment_image_url( $this->thumbnail_id, $size ) ?: '';
	}

	/**
	 * Check if service is published and active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return 'publish' === $this->status;
	}
}
