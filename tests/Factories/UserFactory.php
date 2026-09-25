<?php
/**
 * User Factory for testing.
 *
 * @package WPSellServices\Tests\Factories
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Factories;

use WP_User;

/**
 * Creates test users with various roles.
 */
class UserFactory {

	/**
	 * Counter for unique user generation.
	 *
	 * @var int
	 */
	private static int $counter = 0;

	/**
	 * Users this run created, removed with what they own at shutdown.
	 *
	 * The suite runs against a real site database with no transaction
	 * rollback, and nothing removed what a test made: every run of the REST
	 * suite left its users, orders, proposals and requests behind (Basecamp
	 * 10336652112, 315 wpss_rest_* users on wss.local).
	 *
	 * @var int[]
	 */
	private static array $created = array();

	/**
	 * Create a customer user.
	 *
	 * @param array $attrs Override attributes.
	 * @return WP_User
	 */
	public static function customer( array $attrs = array() ): WP_User {
		return self::create(
			array_merge(
				array(
					'role'         => 'customer',
					'display_name' => 'Test Customer',
				),
				$attrs
			)
		);
	}

	/**
	 * Create a vendor user.
	 *
	 * @param array $attrs Override attributes.
	 * @return WP_User
	 */
	public static function vendor( array $attrs = array() ): WP_User {
		$user = self::create(
			array_merge(
				array(
					'role'         => 'wpss_vendor',
					'display_name' => 'Test Vendor',
				),
				$attrs
			)
		);

		// A vendor is an ACTIVE profile row, not a role (wpss_is_vendor() reads
		// the row); the role already carries every vendor capability.
		( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->upsert(
			$user->ID,
			array(
				'display_name'      => $user->display_name,
				'status'            => 'active',
				'verification_tier' => \WPSellServices\Models\VendorProfile::TIER_NEW,
			)
		);

		return $user;
	}

	/**
	 * Create an admin user.
	 *
	 * @param array $attrs Override attributes.
	 * @return WP_User
	 */
	public static function admin( array $attrs = array() ): WP_User {
		$user = self::create(
			array_merge(
				array(
					'role'         => 'administrator',
					'display_name' => 'Test Admin',
				),
				$attrs
			)
		);

		// Add admin capabilities.
		$user->add_cap( 'manage_options', true );
		$user->add_cap( 'manage_wpss', true );
		$user->add_cap( 'moderate_wpss_services', true );

		return $user;
	}

	/**
	 * Delete the users this run created and every row they own.
	 *
	 * Orders where they are buyer or vendor go first, with every table keyed
	 * by order_id (and the messages and dispute messages hanging off those),
	 * then every table keyed by user_id / vendor_id / customer_id, then the
	 * users themselves - which removes their posts (services, requests).
	 *
	 * @return void
	 */
	public static function cleanup(): void {
		global $wpdb;

		$users = array_values( array_unique( self::$created ) );
		self::$created = array();

		if ( ! $users || ! function_exists( 'wp_delete_user' ) && ! is_file( ABSPATH . 'wp-admin/includes/user.php' ) ) {
			return;
		}

		$in     = static fn( array $ids ): string => implode( ',', array_map( 'intval', $ids ) );
		$prefix = $wpdb->prefix . 'wpss_';
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
		$orders = $wpdb->get_col( "SELECT id FROM {$prefix}orders WHERE customer_id IN ({$in( $users )}) OR vendor_id IN ({$in( $users )})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $orders ) {
			$wpdb->query( "DELETE FROM {$prefix}messages WHERE conversation_id IN ( SELECT id FROM {$prefix}conversations WHERE order_id IN ({$in( $orders )}) )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM {$prefix}dispute_messages WHERE dispute_id IN ( SELECT id FROM {$prefix}disputes WHERE order_id IN ({$in( $orders )}) )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM {$prefix}audit_log WHERE object_type = 'order' AND object_id IN ({$in( $orders )})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		foreach ( $tables as $table ) {
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $orders && in_array( 'order_id', $columns, true ) ) {
				$wpdb->query( "DELETE FROM {$table} WHERE order_id IN ({$in( $orders )})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			foreach ( array( 'user_id', 'vendor_id', 'customer_id' ) as $owner ) {
				if ( in_array( $owner, $columns, true ) ) {
					$wpdb->query( "DELETE FROM {$table} WHERE {$owner} IN ({$in( $users )})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			}
		}

		if ( $orders ) {
			$wpdb->query( "DELETE FROM {$prefix}orders WHERE id IN ({$in( $orders )})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $users as $user_id ) {
			wp_delete_user( $user_id );
		}
	}

	/**
	 * Create a user with given attributes.
	 *
	 * @param array $attrs User attributes.
	 * @return WP_User
	 */
	private static function create( array $attrs ): WP_User {
		++self::$counter;

		$defaults = array(
			'user_login'   => 'testuser_' . self::$counter,
			'user_email'   => 'testuser_' . self::$counter . '@example.com',
			'display_name' => 'Test User ' . self::$counter,
			'role'         => 'subscriber',
		);

		$attrs = array_merge( $defaults, $attrs );

		// If WordPress test framework is available, use it.
		if ( function_exists( 'wp_insert_user' ) && ! defined( 'WPSS_STUB_MODE' ) ) {
			$user_id = wp_insert_user(
				array(
					'user_login' => $attrs['user_login'],
					'user_email' => $attrs['user_email'],
					'user_pass'  => wp_generate_password(),
					'role'       => $attrs['role'],
				)
			);

			if ( is_wp_error( $user_id ) ) {
				throw new \RuntimeException( 'Failed to create user: ' . $user_id->get_error_message() );
			}

			if ( ! self::$created ) {
				register_shutdown_function( array( self::class, 'cleanup' ) );
			}
			self::$created[] = (int) $user_id;

			$user = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				throw new \RuntimeException( 'Failed to retrieve created user.' );
			}

			$user->display_name = $attrs['display_name'];
			return $user;
		}

		// Standalone mode - create stub user.
		$user               = new WP_User( self::$counter );
		$user->user_login   = $attrs['user_login'];
		$user->user_email   = $attrs['user_email'];
		$user->display_name = $attrs['display_name'];
		$user->roles        = array( $attrs['role'] );

		return $user;
	}

	/**
	 * Reset the counter (for test isolation).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$counter = 0;
	}
}
