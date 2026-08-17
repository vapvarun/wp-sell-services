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
}
