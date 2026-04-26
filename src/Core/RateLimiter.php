<?php
/**
 * Rate Limiter
 *
 * Provides rate limiting functionality for AJAX handlers and API endpoints.
 *
 * @package WPSellServices\Core
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rate Limiter class.
 *
 * Uses WordPress transients to track request counts and enforce rate limits.
 *
 * @since 1.0.0
 */
class RateLimiter {

	/**
	 * Default rate limits by action type.
	 *
	 * @var array<string, array{requests: int, window: int}>
	 */
	private const DEFAULT_LIMITS = array(
		'message'         => array(
			'requests' => 30,
			'window'   => 60,
		), // 30 messages per minute.
		'review'          => array(
			'requests' => 5,
			'window'   => 3600,
		), // 5 reviews per hour.
		'dispute'         => array(
			'requests' => 3,
			'window'   => 3600,
		), // 3 disputes per hour.
		'service_create'  => array(
			'requests' => 10,
			'window'   => 3600,
		), // 10 services per hour.
		'vendor_register' => array(
			'requests' => 3,
			'window'   => 86400,
		), // 3 attempts per day.
		'file_upload'     => array(
			'requests' => 50,
			'window'   => 3600,
		), // 50 uploads per hour.
		'helpful_vote'    => array(
			'requests' => 20,
			'window'   => 60,
		), // 20 votes per minute.
		'live_search'     => array(
			'requests' => 30,
			'window'   => 60,
		), // 30 live-search queries per minute (per IP for guests, per user for logged-in).
		'contact'         => array(
			'requests' => 5,
			'window'   => 300,
		), // 5 contact requests per 5 minutes.
		'order_action'    => array(
			'requests' => 30,
			'window'   => 60,
		), // 30 order actions per minute.
		'requirements'    => array(
			'requests' => 10,
			'window'   => 60,
		), // 10 requirement submissions per minute.
		'delivery'        => array(
			'requests' => 10,
			'window'   => 3600,
		), // 10 deliveries per hour.
		'default'         => array(
			'requests' => 60,
			'window'   => 60,
		), // 60 requests per minute default.
	);

	/**
	 * Check if a request should be rate limited.
	 *
	 * @param string   $action  Action type.
	 * @param int|null $user_id User ID (null for IP-based limiting).
	 * @return bool True if rate limited (should be blocked), false if allowed.
	 */
	public static function is_limited( string $action, ?int $user_id = null ): bool {
		$identifier = self::get_identifier( $user_id );
		$key        = self::get_cache_key( $action, $identifier );
		$limits     = self::get_limits( $action );

		$data = get_transient( $key );

		if ( false === $data ) {
			return false;
		}

		return $data['count'] >= $limits['requests'];
	}

	/**
	 * Track a request for rate limiting.
	 *
	 * @param string   $action  Action type.
	 * @param int|null $user_id User ID (null for IP-based limiting).
	 * @return void
	 */
	public static function track( string $action, ?int $user_id = null ): void {
		$identifier = self::get_identifier( $user_id );
		$key        = self::get_cache_key( $action, $identifier );
		$limits     = self::get_limits( $action );

		$data = get_transient( $key );

		if ( false === $data ) {
			$data = array(
				'count'      => 0,
				'first_time' => time(),
			);
		}

		++$data['count'];

		set_transient( $key, $data, $limits['window'] );
	}

	/**
	 * Check and track in one operation. Returns true if blocked.
	 *
	 * @param string   $action  Action type.
	 * @param int|null $user_id User ID (null for IP-based limiting).
	 * @return bool True if rate limited (should be blocked), false if allowed.
	 */
	public static function check_and_track( string $action, ?int $user_id = null ): bool {
		if ( self::is_limited( $action, $user_id ) ) {
			return true;
		}

		self::track( $action, $user_id );
		return false;
	}

	/**
	 * Get remaining requests before rate limit.
	 *
	 * @param string   $action  Action type.
	 * @param int|null $user_id User ID (null for IP-based limiting).
	 * @return int Remaining requests, or -1 for unlimited.
	 */
	public static function get_remaining( string $action, ?int $user_id = null ): int {
		$identifier = self::get_identifier( $user_id );
		$key        = self::get_cache_key( $action, $identifier );
		$limits     = self::get_limits( $action );

		$data = get_transient( $key );

		if ( false === $data ) {
			return $limits['requests'];
		}

		return max( 0, $limits['requests'] - $data['count'] );
	}

	/**
	 * Get time until rate limit resets.
	 *
	 * @param string   $action  Action type.
	 * @param int|null $user_id User ID (null for IP-based limiting).
	 * @return int Seconds until reset, or 0 if not limited.
	 */
	public static function get_reset_time( string $action, ?int $user_id = null ): int {
		$identifier = self::get_identifier( $user_id );
		$key        = self::get_cache_key( $action, $identifier );
		$limits     = self::get_limits( $action );

		$data = get_transient( $key );

		if ( false === $data ) {
			return 0;
		}

		$reset_time = $data['first_time'] + $limits['window'];
		$remaining  = $reset_time - time();

		return max( 0, $remaining );
	}

	/**
	 * Clear rate limit for a specific action and user.
	 *
	 * @param string   $action  Action type.
	 * @param int|null $user_id User ID (null for IP-based limiting).
	 * @return void
	 */
	public static function clear( string $action, ?int $user_id = null ): void {
		$identifier = self::get_identifier( $user_id );
		$key        = self::get_cache_key( $action, $identifier );
		delete_transient( $key );
	}

	/**
	 * Get unique identifier for rate limiting.
	 *
	 * @param int|null $user_id User ID.
	 * @return string Identifier string.
	 */
	private static function get_identifier( ?int $user_id ): string {
		if ( $user_id && $user_id > 0 ) {
			return 'user_' . $user_id;
		}

		// Fall back to IP address for guests.
		$ip = self::get_client_ip();
		return 'ip_' . md5( $ip );
	}

	/**
	 * Get cache key for rate limiting.
	 *
	 * @param string $action     Action type.
	 * @param string $identifier User/IP identifier.
	 * @return string Cache key.
	 */
	private static function get_cache_key( string $action, string $identifier ): string {
		return 'wpss_rate_' . $action . '_' . $identifier;
	}

	/**
	 * Get rate limits for an action.
	 *
	 * @param string $action Action type.
	 * @return array{requests: int, window: int}
	 */
	private static function get_limits( string $action ): array {
		$limits = self::DEFAULT_LIMITS[ $action ] ?? self::DEFAULT_LIMITS['default'];

		/**
		 * Filter rate limits for a specific action.
		 *
		 * @param array  $limits Rate limit config with 'requests' and 'window'.
		 * @param string $action Action type.
		 */
		return apply_filters( 'wpss_rate_limits', $limits, $action );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP.
	 */
	private static function get_client_ip(): string {
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs (X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Send rate limit exceeded error.
	 *
	 * @param string $action Action type.
	 * @return void Exits after sending JSON response.
	 */
	public static function send_error( string $action = 'default' ): void {
		$current_user_id = get_current_user_id();
		$reset_time      = self::get_reset_time( $action, $current_user_id > 0 ? $current_user_id : null );

		$message = sprintf(
			/* translators: %d: seconds until rate limit resets */
			__( 'Too many requests. Please try again in %d seconds.', 'wp-sell-services' ),
			$reset_time
		);

		wp_send_json_error(
			array(
				'message'    => $message,
				'code'       => 'rate_limited',
				'reset_time' => $reset_time,
			),
			429
		);
	}
}
