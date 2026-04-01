<?php
/**
 * Vendor Service
 *
 * Business logic for vendor management.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

use WPSellServices\Database\Repositories\VendorProfileRepository;
use WPSellServices\Database\Repositories\OrderRepository;
use WPSellServices\Database\Repositories\ReviewRepository;
use WPSellServices\Models\VendorProfile;

defined( 'ABSPATH' ) || exit;

/**
 * VendorService class.
 *
 * @since 1.0.0
 */
class VendorService {

	/**
	 * Vendor role name.
	 *
	 * @var string
	 */
	public const ROLE = 'wpss_vendor';

	/**
	 * Profile repository.
	 *
	 * @var VendorProfileRepository
	 */
	private VendorProfileRepository $profile_repo;

	/**
	 * Order repository.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $order_repo;

	/**
	 * Review repository.
	 *
	 * @var ReviewRepository
	 */
	private ReviewRepository $review_repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->profile_repo = new VendorProfileRepository();
		$this->order_repo   = new OrderRepository();
		$this->review_repo  = new ReviewRepository();
	}

	/**
	 * Register a new vendor.
	 *
	 * When admin approval is required (vendor_registration = 'approval'), the vendor profile
	 * is created with 'pending' status but the role, capabilities, and vendor meta are NOT
	 * granted until admin approval. This prevents pending vendors from accessing the dashboard.
	 * When registration is closed (vendor_registration = 'closed'), registration is rejected.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $data    Profile data.
	 * @return bool True on success.
	 */
	public function register( int $user_id, array $data = array() ): bool {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		// Check if already a vendor.
		if ( $this->is_vendor( $user_id ) ) {
			return false;
		}

		// Check if user already has a pending application.
		if ( $this->has_pending_application( $user_id ) ) {
			return false;
		}

		// Determine vendor status based on registration mode setting.
		$vendor_settings   = get_option( 'wpss_vendor', array() );
		$registration_mode = $vendor_settings['vendor_registration'] ?? 'open';

		// If registration is closed, reject immediately.
		if ( 'closed' === $registration_mode ) {
			return false;
		}

		$needs_approval = 'approval' === $registration_mode;
		$default_status = $needs_approval ? 'pending' : 'active';

		// Only grant role, capabilities, and vendor meta when approval is NOT required.
		// When approval is required, these are granted later via grant_vendor_access()
		// when the admin approves the vendor (status changed to 'active').
		if ( ! $needs_approval ) {
			// Add vendor role.
			$user->add_role( self::ROLE );

			// Verify role was actually added before proceeding.
			if ( ! in_array( self::ROLE, $user->roles, true ) ) {
				wpss_log( 'Failed to add vendor role to user ' . $user_id, 'error' );
				return false;
			}

			// Add vendor capabilities.
			$this->add_vendor_capabilities( $user_id );
		}

		// Create vendor profile.
		$profile_data = array(
			'display_name'      => $data['display_name'] ?? $user->display_name,
			'tagline'           => $data['tagline'] ?? '',
			'bio'               => $data['bio'] ?? '',
			'country'           => $data['country'] ?? '',
			'city'              => $data['city'] ?? '',
			'status'            => $data['status'] ?? $default_status,
			'verification_tier' => VendorProfile::TIER_NEW,
		);

		/**
		 * Filter vendor profile data before creating the vendor profile.
		 *
		 * Allows modification of profile fields (display_name, tagline, bio, etc.)
		 * before the vendor profile record is inserted during registration.
		 *
		 * @since 1.1.0
		 * @param array $profile_data Profile data to be saved.
		 * @param int   $user_id      User ID being registered as vendor.
		 */
		$profile_data = apply_filters( 'wpss_pre_vendor_register', $profile_data, $user_id );

		$profile_id = $this->profile_repo->upsert( $user_id, $profile_data );

		if ( $profile_id ) {
			// Only store vendor meta when approval is NOT required.
			// For pending vendors, this meta is set when admin approves via grant_vendor_access().
			if ( ! $needs_approval ) {
				update_user_meta( $user_id, '_wpss_is_vendor', true );
			}

			update_user_meta( $user_id, '_wpss_vendor_since', current_time( 'mysql' ) );

			// Save skills to user meta (not in vendor_profiles DB table).
			if ( ! empty( $data['skills'] ) ) {
				$skills = array_map( 'sanitize_text_field', (array) $data['skills'] );
				$skills = array_filter( $skills );
				update_user_meta( $user_id, '_wpss_vendor_skills', $skills );
			}

			/**
			 * Fires when a new vendor is registered.
			 *
			 * @since 1.0.0
			 * @param int   $user_id     User ID.
			 * @param array $profile_data Profile data.
			 */
			do_action( 'wpss_vendor_registered', $user_id, $profile_data );

			return true;
		}

		return false;
	}

	/**
	 * Register a vendor with detailed response for AJAX handlers.
	 *
	 * Wrapper around register() that returns array response suitable for wp_send_json_*.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $data    Profile data.
	 * @return array{success: bool, message: string} Response array.
	 */
	public function register_vendor( int $user_id, array $data = array() ): array {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid user.', 'wp-sell-services' ),
			);
		}

		// Check if registration is closed.
		$vendor_settings   = get_option( 'wpss_vendor', array() );
		$registration_mode = $vendor_settings['vendor_registration'] ?? 'open';

		if ( 'closed' === $registration_mode ) {
			return array(
				'success' => false,
				'message' => __( 'Vendor registration is currently closed.', 'wp-sell-services' ),
			);
		}

		// Check if already a vendor.
		if ( $this->is_vendor( $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You are already registered as a vendor.', 'wp-sell-services' ),
			);
		}

		// Check if already has a pending application.
		if ( $this->has_pending_application( $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You already have a pending vendor application. Please wait for admin approval.', 'wp-sell-services' ),
			);
		}

		$success = $this->register( $user_id, $data );

		if ( $success ) {
			if ( 'approval' === $registration_mode ) {
				return array(
					'success'          => true,
					'pending_approval' => true,
					'message'          => __( 'Your vendor application has been submitted successfully! It is now pending admin approval. You will be notified once your application is reviewed.', 'wp-sell-services' ),
				);
			}

			return array(
				'success' => true,
				'message' => __( 'Your vendor application has been submitted successfully!', 'wp-sell-services' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to register as vendor. Please try again.', 'wp-sell-services' ),
		);
	}

	/**
	 * Check if user is a vendor.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if vendor.
	 */
	public function is_vendor( int $user_id ): bool {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		// Check role.
		if ( in_array( self::ROLE, $user->roles, true ) ) {
			return true;
		}

		// Check meta.
		return (bool) get_user_meta( $user_id, '_wpss_is_vendor', true );
	}

	/**
	 * Check if a user has a pending vendor application.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if user has a pending application.
	 */
	public function has_pending_application( int $user_id ): bool {
		$profile = $this->profile_repo->get_by_user( $user_id );

		if ( ! $profile ) {
			return false;
		}

		return 'pending' === ( $profile->status ?? '' );
	}

	/**
	 * Get vendor profile status.
	 *
	 * Returns the vendor profile status from the database, or null if no profile exists.
	 *
	 * @param int $user_id User ID.
	 * @return string|null Status ('active', 'pending', 'suspended', 'rejected') or null.
	 */
	public function get_vendor_status( int $user_id ): ?string {
		$profile = $this->profile_repo->get_by_user( $user_id );

		if ( ! $profile ) {
			return null;
		}

		return $profile->status ?? null;
	}

	/**
	 * Check if user is an active (approved) vendor.
	 *
	 * Unlike is_vendor() which only checks role/meta, this method also verifies
	 * the vendor profile has 'active' status. Use this for access control.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if user is a vendor with active status.
	 */
	public function is_active_vendor( int $user_id ): bool {
		if ( ! $this->is_vendor( $user_id ) ) {
			return false;
		}

		return 'active' === $this->get_vendor_status( $user_id );
	}

	/**
	 * Grant vendor access (role, capabilities, and meta).
	 *
	 * Called when an admin approves a pending vendor application.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function grant_vendor_access( int $user_id ): bool {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		// Add vendor role.
		$user->add_role( self::ROLE );

		// Verify role was actually added.
		if ( ! in_array( self::ROLE, $user->roles, true ) ) {
			wpss_log( 'Failed to add vendor role to user ' . $user_id . ' during approval', 'error' );
			return false;
		}

		// Add vendor capabilities.
		$this->add_vendor_capabilities( $user_id );

		// Set vendor meta.
		update_user_meta( $user_id, '_wpss_is_vendor', true );

		/**
		 * Fires when vendor access is granted (after admin approval).
		 *
		 * @since 1.1.0
		 * @param int $user_id User ID.
		 */
		do_action( 'wpss_vendor_access_granted', $user_id );

		return true;
	}

	/**
	 * Revoke vendor access (role, capabilities, and meta).
	 *
	 * Called when an admin suspends or rejects a vendor.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function revoke_vendor_access( int $user_id ): bool {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		// Remove vendor role.
		$user->remove_role( self::ROLE );

		// Remove vendor capabilities.
		$capabilities = array(
			'wpss_vendor',
			'wpss_manage_services',
			'wpss_manage_orders',
			'wpss_view_analytics',
			'wpss_respond_to_requests',
		);

		foreach ( $capabilities as $cap ) {
			$user->remove_cap( $cap );
		}

		// Remove vendor meta.
		delete_user_meta( $user_id, '_wpss_is_vendor' );

		/**
		 * Fires when vendor access is revoked (suspended/rejected).
		 *
		 * @since 1.1.0
		 * @param int $user_id User ID.
		 */
		do_action( 'wpss_vendor_access_revoked', $user_id );

		return true;
	}

	/**
	 * Get vendor profile.
	 *
	 * @param int $user_id User ID.
	 * @return object|null Profile object or null.
	 */
	public function get_profile( int $user_id ): ?object {
		return $this->profile_repo->get_by_user( $user_id );
	}

	/**
	 * Update vendor profile.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $data    Profile data.
	 * @return bool True on success.
	 */
	public function update_profile( int $user_id, array $data ): bool {
		$allowed_fields = array(
			'display_name',
			'tagline',
			'bio',
			'avatar_id',
			'cover_image_id',
			'country',
			'city',
			'timezone',
			'website',
			'social_links',
		);

		/**
		 * Filter the list of allowed vendor profile fields for update.
		 *
		 * Allows adding or removing fields that vendors are permitted to update
		 * via the profile update method.
		 *
		 * @since 1.1.0
		 * @param array $allowed_fields Array of allowed field name strings.
		 */
		$allowed_fields = apply_filters( 'wpss_vendor_profile_allowed_fields', $allowed_fields );

		$filtered_data = array_intersect_key( $data, array_flip( $allowed_fields ) );

		if ( isset( $filtered_data['social_links'] ) && is_array( $filtered_data['social_links'] ) ) {
			$filtered_data['social_links'] = wp_json_encode( $filtered_data['social_links'] );
		}

		$result = $this->profile_repo->upsert( $user_id, $filtered_data );

		if ( $result ) {
			/**
			 * Fires when a vendor profile is updated.
			 *
			 * @since 1.0.0
			 * @param int   $user_id User ID.
			 * @param array $data    Updated data.
			 */
			do_action( 'wpss_vendor_profile_updated', $user_id, $filtered_data );
		}

		return false !== $result;
	}

	/**
	 * Get vendor statistics.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed> Statistics.
	 */
	public function get_stats( int $user_id ): array {
		$order_stats  = $this->order_repo->get_vendor_stats( $user_id );
		$rating_stats = $this->review_repo->get_vendor_rating_summary( $user_id );

		return array_merge( $order_stats, $rating_stats );
	}

	/**
	 * Update vendor statistics.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function update_stats( int $user_id ): bool {
		return $this->profile_repo->update_stats( $user_id );
	}

	/**
	 * Set vacation mode.
	 *
	 * @param int    $user_id User ID.
	 * @param bool   $enabled Enable or disable.
	 * @param string $message Vacation message.
	 * @return bool True on success.
	 */
	public function set_vacation_mode( int $user_id, bool $enabled, string $message = '' ): bool {
		$result = $this->profile_repo->set_vacation_mode( $user_id, $enabled, $message );

		if ( $result ) {
			/**
			 * Fires when vacation mode is toggled.
			 *
			 * @since 1.0.0
			 * @param int    $user_id User ID.
			 * @param bool   $enabled Whether enabled.
			 * @param string $message Vacation message.
			 */
			do_action( 'wpss_vendor_vacation_mode_changed', $user_id, $enabled, $message );
		}

		return $result;
	}

	/**
	 * Set vendor availability.
	 *
	 * @param int  $user_id   User ID.
	 * @param bool $available Availability status.
	 * @return bool True on success.
	 */
	public function set_availability( int $user_id, bool $available ): bool {
		return $this->profile_repo->set_availability( $user_id, $available );
	}

	/**
	 * Update verification tier.
	 *
	 * @param int    $user_id User ID.
	 * @param string $tier    New tier.
	 * @return bool True on success.
	 */
	public function update_verification_tier( int $user_id, string $tier ): bool {
		$valid_tiers = array_keys( VendorProfile::get_tiers() );

		if ( ! in_array( $tier, $valid_tiers, true ) ) {
			return false;
		}

		$result = $this->profile_repo->update_verification_tier( $user_id, $tier );

		if ( $result ) {
			/**
			 * Fires when vendor verification tier changes.
			 *
			 * @since 1.0.0
			 * @param int    $user_id User ID.
			 * @param string $tier    New tier.
			 */
			do_action( 'wpss_vendor_tier_changed', $user_id, $tier );
		}

		return $result;
	}

	/**
	 * Get top vendors.
	 *
	 * @param int $limit Number of vendors.
	 * @return array<object> Array of vendor profiles.
	 */
	public function get_top_vendors( int $limit = 10 ): array {
		return $this->profile_repo->get_top_rated( $limit );
	}

	/**
	 * Search vendors.
	 *
	 * @param string               $search Search term.
	 * @param array<string, mixed> $args   Query arguments.
	 * @return array<object> Array of vendor profiles.
	 */
	public function search( string $search, array $args = array() ): array {
		return $this->profile_repo->search( $search, $args );
	}

	/**
	 * Get vendors by country.
	 *
	 * @param string               $country Country name.
	 * @param array<string, mixed> $args    Query arguments.
	 * @return array<object> Array of vendor profiles.
	 */
	public function get_by_country( string $country, array $args = array() ): array {
		return $this->profile_repo->get_by_country( $country, $args );
	}

	/**
	 * Update last active time.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function update_last_active( int $user_id ): void {
		update_user_meta( $user_id, '_wpss_last_active', current_time( 'mysql' ) );
	}

	/**
	 * Check if vendor is online.
	 *
	 * @param int $user_id User ID.
	 * @param int $minutes Minutes to consider online (default 5).
	 * @return bool True if online.
	 */
	public function is_online( int $user_id, int $minutes = 5 ): bool {
		$last_active = get_user_meta( $user_id, '_wpss_last_active', true );

		if ( ! $last_active ) {
			return false;
		}

		$threshold = time() - ( $minutes * 60 );
		return strtotime( $last_active ) > $threshold;
	}

	/**
	 * Add vendor capabilities to user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function add_vendor_capabilities( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$capabilities = array(
			'wpss_vendor',
			'wpss_manage_services',
			'wpss_manage_orders',
			'wpss_view_analytics',
			'wpss_respond_to_requests',
			'upload_files',
			'edit_posts',
		);

		foreach ( $capabilities as $cap ) {
			$user->add_cap( $cap );
		}
	}

	/**
	 * Get vendor response time.
	 *
	 * @param int $user_id User ID.
	 * @return string|null Response time (e.g., "1 hour", "2 days").
	 */
	public function get_response_time( int $user_id ): ?string {
		$profile = $this->get_profile( $user_id );

		if ( ! $profile || ! $profile->response_time_hours ) {
			return null;
		}

		$hours = (int) $profile->response_time_hours;

		if ( $hours < 1 ) {
			return __( 'Less than 1 hour', 'wp-sell-services' );
		} elseif ( $hours < 24 ) {
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour', '%d hours', $hours, 'wp-sell-services' ),
				$hours
			);
		} else {
			$days = round( $hours / 24 );
			return sprintf(
				/* translators: %d: number of days */
				_n( '%d day', '%d days', $days, 'wp-sell-services' ),
				$days
			);
		}
	}

	/**
	 * Get verification tiers.
	 *
	 * @return array<string, string> Tier slugs and labels.
	 */
	public static function get_tiers(): array {
		return VendorProfile::get_tiers();
	}

	/**
	 * Get available countries.
	 *
	 * @return array<string> Array of country names.
	 */
	public function get_countries(): array {
		return $this->profile_repo->get_countries();
	}
}
