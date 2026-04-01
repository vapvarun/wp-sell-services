<?php
/**
 * Vendor Profile Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a vendor/seller profile.
 *
 * @since 1.0.0
 */
class VendorProfile {

	/**
	 * Vendor tier levels.
	 */
	public const TIER_NEW       = 'new';
	public const TIER_RISING    = 'rising';
	public const TIER_TOP_RATED = 'top_rated';
	public const TIER_PRO       = 'pro';

	/**
	 * Profile ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * WordPress user ID.
	 *
	 * @var int
	 */
	public int $user_id;

	/**
	 * Display name.
	 *
	 * @var string
	 */
	public string $display_name;

	/**
	 * Professional title/tagline.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * Bio/description.
	 *
	 * @var string
	 */
	public string $bio = '';

	/**
	 * Profile avatar attachment ID.
	 *
	 * @var int|null
	 */
	public ?int $avatar_id;

	/**
	 * Profile cover image attachment ID.
	 *
	 * @var int|null
	 */
	public ?int $cover_id;

	/**
	 * Country code.
	 *
	 * @var string
	 */
	public string $country = '';

	/**
	 * Languages spoken.
	 *
	 * @var array<string>
	 */
	public array $languages = array();

	/**
	 * Skills/expertise.
	 *
	 * @var array<string>
	 */
	public array $skills = array();

	/**
	 * Certifications/credentials.
	 *
	 * @var array<array{name: string, issuer: string, year: int}>
	 */
	public array $certifications = array();

	/**
	 * Education history.
	 *
	 * @var array<array{degree: string, institution: string, year: int}>
	 */
	public array $education = array();

	/**
	 * Vendor tier.
	 *
	 * @var string
	 */
	public string $tier = self::TIER_NEW;

	/**
	 * Average rating.
	 *
	 * @var float
	 */
	public float $rating = 0.0;

	/**
	 * Total reviews received.
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
	 * Response rate percentage.
	 *
	 * @var float
	 */
	public float $response_rate = 0.0;

	/**
	 * Average response time in hours.
	 *
	 * @var float
	 */
	public float $response_time = 0.0;

	/**
	 * On-time delivery rate percentage.
	 *
	 * @var float
	 */
	public float $delivery_rate = 0.0;

	/**
	 * Order completion rate percentage.
	 *
	 * @var float
	 */
	public float $completion_rate = 0.0;

	/**
	 * Whether vendor is verified.
	 *
	 * @var bool
	 */
	public bool $is_verified = false;

	/**
	 * Whether vendor is currently available.
	 *
	 * @var bool
	 */
	public bool $is_available = true;

	/**
	 * Vendor account status (active, pending, suspended).
	 *
	 * @var string
	 */
	public string $status = 'active';

	/**
	 * Whether vendor has vacation mode enabled (manual toggle, no auto-expiry).
	 *
	 * @var bool
	 */
	public bool $vacation_mode = false;

	/**
	 * Vacation mode end date (reserved for future auto-expiry via hooks).
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $vacation_until;

	/**
	 * Social links.
	 *
	 * @var array<string, string>
	 */
	public array $social_links = array();

	/**
	 * Member since timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $member_since;

	/**
	 * Last active timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $last_active;

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
		$profile = new self();

		$profile->id               = (int) ( $row->id ?? 0 );
		$profile->user_id          = (int) ( $row->user_id ?? 0 );
		$profile->display_name     = $row->display_name ?? '';
		$profile->title            = $row->tagline ?? ''; // DB column is 'tagline'.
		$profile->bio              = $row->bio ?? '';
		$profile->avatar_id        = isset( $row->avatar_id ) && $row->avatar_id ? (int) $row->avatar_id : null;
		$profile->cover_id         = isset( $row->cover_image_id ) && $row->cover_image_id ? (int) $row->cover_image_id : null; // DB column is 'cover_image_id'.
		$profile->country          = $row->country ?? '';
		$profile->tier             = $row->verification_tier ?? self::TIER_NEW;
		$profile->rating           = (float) ( $row->avg_rating ?? 0 );
		$profile->review_count     = (int) ( $row->total_reviews ?? 0 );
		$profile->orders_completed = (int) ( $row->completed_orders ?? 0 );
		$profile->response_time    = (float) ( $row->response_time_hours ?? 0 ); // DB column is 'response_time_hours'.
		$profile->delivery_rate    = (float) ( $row->on_time_delivery_rate ?? 0 ); // DB column is 'on_time_delivery_rate'.
		$profile->is_verified      = isset( $row->verification_tier ) && self::TIER_PRO === $row->verification_tier;
		$profile->is_available     = (bool) ( $row->is_available ?? true );
		$profile->status           = $row->status ?? 'active';
		$profile->social_links     = isset( $row->social_links ) && $row->social_links ? json_decode( $row->social_links, true ) : array();

		// Vacation mode is manual-only (vendor toggles on/off, no auto-expiry).
		$profile->vacation_mode  = ! empty( $row->vacation_mode );
		$profile->vacation_until = null;

		// Timestamps.
		$profile->member_since = isset( $row->created_at ) && $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$profile->last_active  = isset( $row->updated_at ) && $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;
		$profile->created_at   = isset( $row->created_at ) && $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$profile->updated_at   = isset( $row->updated_at ) && $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;

		// Properties not in DB schema (remain as defaults):
		// - languages, skills, certifications, education - future feature.
		// - response_rate, completion_rate - not tracked yet.

		return $profile;
	}

	/**
	 * Get vendor profile by user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return self|null
	 */
	public static function get_by_user_id( int $user_id ): ?self {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);

		return $row ? self::from_db( $row ) : null;
	}

	/**
	 * Get all tier levels.
	 *
	 * @return array<string, string>
	 */
	public static function get_tiers(): array {
		return array(
			self::TIER_NEW       => __( 'New Seller', 'wp-sell-services' ),
			self::TIER_RISING    => __( 'Rising Seller', 'wp-sell-services' ),
			self::TIER_TOP_RATED => __( 'Top Rated', 'wp-sell-services' ),
			self::TIER_PRO       => __( 'Pro Seller', 'wp-sell-services' ),
		);
	}

	/**
	 * Get tier label.
	 *
	 * @return string
	 */
	public function get_tier_label(): string {
		$tiers = self::get_tiers();
		return $tiers[ $this->tier ] ?? $this->tier;
	}

	/**
	 * Get WordPress user.
	 *
	 * @return \WP_User|null
	 */
	public function get_user(): ?\WP_User {
		return get_user_by( 'id', $this->user_id ) ?: null;
	}

	/**
	 * Get avatar URL.
	 *
	 * @param string $size Image size.
	 * @return string
	 */
	public function get_avatar_url( string $size = 'thumbnail' ): string {
		if ( $this->avatar_id ) {
			$url = wp_get_attachment_image_url( $this->avatar_id, $size );
			if ( $url ) {
				return $url;
			}
		}

		return get_avatar_url( $this->user_id, array( 'size' => 150 ) );
	}

	/**
	 * Get cover image URL.
	 *
	 * @param string $size Image size.
	 * @return string
	 */
	public function get_cover_url( string $size = 'large' ): string {
		if ( ! $this->cover_id ) {
			return '';
		}

		return wp_get_attachment_image_url( $this->cover_id, $size ) ?: '';
	}

	/**
	 * Get profile URL.
	 *
	 * @return string
	 */
	public function get_profile_url(): string {
		return wpss_get_vendor_url( $this->user_id );
	}

	/**
	 * Check if vendor is on vacation.
	 *
	 * @return bool
	 */
	public function is_on_vacation(): bool {
		// Manual toggle — no date-based expiry.
		// Filter allows Pro or custom code to add auto-expiry logic later.
		return (bool) apply_filters( 'wpss_vendor_is_on_vacation', $this->vacation_mode, $this );
	}

	/**
	 * Get response time label.
	 *
	 * @return string
	 */
	public function get_response_time_label(): string {
		if ( $this->response_time < 1 ) {
			return __( 'Within an hour', 'wp-sell-services' );
		}

		if ( $this->response_time < 24 ) {
			/* translators: %d: number of hours */
			return sprintf(
				_n( 'Within %d hour', 'Within %d hours', (int) $this->response_time, 'wp-sell-services' ),
				(int) $this->response_time
			);
		}

		$days = (int) ( $this->response_time / 24 );
		/* translators: %d: number of days */
		return sprintf(
			_n( 'Within %d day', 'Within %d days', $days, 'wp-sell-services' ),
			$days
		);
	}

	/**
	 * Check if vendor account is active (not pending or suspended).
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return 'active' === $this->status;
	}

	/**
	 * Check if vendor is suspended.
	 *
	 * @return bool
	 */
	public function is_suspended(): bool {
		return 'suspended' === $this->status;
	}

	/**
	 * Check if vendor is pending approval.
	 *
	 * @return bool
	 */
	public function is_pending(): bool {
		return 'pending' === $this->status;
	}

	/**
	 * Check if vendor can create new services.
	 *
	 * Vendors must have an active account to create services.
	 *
	 * @return bool
	 */
	public function can_create_services(): bool {
		return $this->is_active();
	}

	/**
	 * Check if vendor has reached the maximum services limit.
	 *
	 * Counts published and pending services against the max_services_per_vendor setting.
	 * Draft services are not counted since they are not yet submitted.
	 *
	 * @return bool True if the vendor has reached or exceeded the limit.
	 */
	public function has_reached_service_limit(): bool {
		$vendor_settings = get_option( 'wpss_vendor', array() );
		$max_services    = isset( $vendor_settings['max_services_per_vendor'] )
			? absint( $vendor_settings['max_services_per_vendor'] )
			: 0;

		// 0 means unlimited.
		if ( 0 === $max_services ) {
			return false;
		}

		$current_count = $this->get_service_count();

		return $current_count >= $max_services;
	}

	/**
	 * Get the number of active services for this vendor.
	 *
	 * Counts published and pending services (not drafts).
	 *
	 * @return int Number of services.
	 */
	public function get_service_count(): int {
		$services = get_posts(
			array(
				'post_type'   => 'wpss_service',
				'author'      => $this->user_id,
				'post_status' => array( 'publish', 'pending' ),
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		return count( $services );
	}

	/**
	 * Get the maximum number of services allowed for this vendor.
	 *
	 * @return int Maximum services. 0 means unlimited.
	 */
	public function get_max_services(): int {
		$vendor_settings = get_option( 'wpss_vendor', array() );

		return isset( $vendor_settings['max_services_per_vendor'] )
			? absint( $vendor_settings['max_services_per_vendor'] )
			: 0;
	}

	/**
	 * Check if vendor can accept new orders.
	 *
	 * Requires active status, available flag, and not on vacation.
	 *
	 * @return bool
	 */
	public function can_accept_orders(): bool {
		return $this->is_active() && $this->is_available && ! $this->is_on_vacation();
	}
}
