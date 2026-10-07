<?php
/**
 * Real login cookies for HTTP checks against the running site.
 *
 * @package WPSellServices
 */

namespace WPSellServices\Tests\Factories;

/**
 * Builds the auth + logged-in cookie pair WordPress expects, on a session
 * token it can revoke.
 */
class SessionCookies {

	/**
	 * Cookies for a user, valid for an hour. 0 means logged out.
	 *
	 * @param int $user_id User.
	 * @return \WP_Http_Cookie[]
	 */
	public static function for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$expires = time() + HOUR_IN_SECONDS;
		$token   = \WP_Session_Tokens::get_instance( $user_id )->create( $expires );

		return array(
			new \WP_Http_Cookie( array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $user_id, $expires, 'auth', $token ) ) ),
			new \WP_Http_Cookie( array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $user_id, $expires, 'logged_in', $token ) ) ),
		);
	}

	/**
	 * Revoke every session the check opened for a user.
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public static function end( int $user_id ): void {
		if ( $user_id > 0 ) {
			\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}
	}
}
