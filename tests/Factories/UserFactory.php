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
					'role'         => 'vendor',
					'display_name' => 'Test Vendor',
				),
				$attrs
			)
		);

		// Add vendor capabilities.
		$user->add_cap( 'publish_wpss_services', true );
		$user->add_cap( 'edit_wpss_services', true );
		$user->add_cap( 'delete_wpss_services', true );

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
