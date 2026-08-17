<?php
/**
 * App token expiry enforcement.
 *
 * @package WPSellServices\API
 * @since   1.6.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the `expires` field on a mobile token mean something.
 *
 * WordPress application passwords have no expiry of their own - once issued
 * they are valid until somebody deletes them. POST /auth/login returned
 * `expires: null` and meant it: a stolen token worked forever (Basecamp
 * 10154918753).
 *
 * Expiry is enforced HERE, at authentication, rather than by a cron job that
 * deletes old rows. A sweep would leave a window between a token expiring and
 * the sweep running, and would silently stop enforcing anything the moment
 * cron stopped firing on a site - which is the failure mode you would never
 * notice. Checking on use has no window and no dependency.
 *
 * @since 1.6.0
 */
class AppTokenGuard {

	/**
	 * Reject an expired application password.
	 *
	 * Hooked to core's own extension point for this. Core builds a WP_Error,
	 * hands it to listeners, and rejects the request if anything was added to
	 * it - so adding an error here produces a normal 401 through core's path,
	 * with no duplicated authentication logic of our own.
	 *
	 * Scoped to tokens this plugin issued. An application password a member
	 * created by hand in their WordPress profile is not ours to expire, and
	 * killing one would break whatever script they built with it.
	 *
	 * @since 1.6.0
	 *
	 * @param \WP_Error            $error    Error object to add to.
	 * @param \WP_User             $user     User being authenticated.
	 * @param array<string, mixed> $item     The application password record.
	 * @param string               $password The submitted password.
	 * @return void
	 */
	public function reject_expired_token( $error, $user, $item, $password ): void {
		unset( $user, $password );

		if ( ! $error instanceof \WP_Error || ! is_array( $item ) ) {
			return;
		}

		// Already failing for another reason - do not pile on.
		if ( $error->has_errors() ) {
			return;
		}

		if ( ! str_starts_with( (string) ( $item['name'] ?? '' ), 'WPSS' ) ) {
			return;
		}

		if ( ! wpss_app_token_is_expired( $item ) ) {
			return;
		}

		$error->add(
			'wpss_token_expired',
			__( 'This sign-in has expired. Please sign in again.', 'wp-sell-services' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Let a client reach the sign-in routes with a dead token in its header.
	 *
	 * Without this, expiry is a LOCKOUT rather than a prompt to sign in again.
	 *
	 * WordPress turns a failed application password into a 401 for the whole
	 * request, whatever was being asked - so `POST /auth/login` with a stale
	 * token in the Authorization header answers 401 and never reaches the
	 * handler, even when the body carries the correct password. Mobile clients
	 * attach the stored token to every request from one interceptor, so an app
	 * that has just been told "expired, sign in again" sends the expired token
	 * along with the sign-in attempt and gets 401 forever. The member cannot
	 * recover without clearing the app's storage.
	 *
	 * That behaviour predates this plugin and is not new - a garbage token has
	 * always done the same thing. What is new is that WPSS tokens now actually
	 * go bad on their own, so a correct client WILL hit it, where before it
	 * could not.
	 *
	 * These three routes take their credentials from the request body and
	 * ignore whoever the caller appears to be, so running them as an anonymous
	 * visitor is exactly right: a failed credential should mean "you are nobody"
	 * on a public endpoint, not "your request is refused". Nothing is granted -
	 * the expired token does NOT authenticate the request, it is simply no
	 * longer fatal to it.
	 *
	 * @since 1.6.0
	 *
	 * @param \WP_Error|null|true $errors Current authentication result.
	 * @return \WP_Error|null|true
	 */
	public function allow_anonymous_auth_routes( $errors ) {
		if ( ! is_wp_error( $errors ) ) {
			return $errors;
		}

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] )
			? (string) $GLOBALS['wp']->query_vars['rest_route']
			: '';

		if ( '' === $route ) {
			return $errors;
		}

		/**
		 * Filter the routes reachable without a valid token.
		 *
		 * Keep this list to endpoints that authenticate from the request body
		 * and grant nothing on their own. Adding an endpoint that reads the
		 * current user would make it callable by anyone.
		 *
		 * @since 1.6.0
		 *
		 * @param array<int, string> $routes Route paths, relative to the namespace.
		 */
		$open = (array) apply_filters(
			'wpss_token_recovery_routes',
			array(
				'/wpss/v1/auth/login',
				'/wpss/v1/auth/register',
				'/wpss/v1/auth/forgot-password',
			)
		);

		foreach ( $open as $path ) {
			if ( untrailingslashit( $route ) === untrailingslashit( (string) $path ) ) {
				// Drop the authentication error only. The request continues as
				// a logged-out visitor, which is what a failed credential
				// means.
				return null;
			}
		}

		return $errors;
	}
}
