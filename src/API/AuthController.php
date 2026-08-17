<?php
/**
 * Auth REST Controller
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_User;
use WP_Application_Passwords;

/**
 * REST controller for authentication operations.
 *
 * Provides token-based auth for mobile apps using WordPress Application Passwords.
 *
 * @since 1.0.0
 */
class AuthController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'auth';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /auth/login - Authenticate and get token.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/login',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'login' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'username'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'password'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'device_name' => array(
							'description' => __( 'Device name for app password.', 'wp-sell-services' ),
							'type'        => 'string',
							'default'     => 'WPSS Mobile App',
						),
					),
				),
			)
		);

		// POST /auth/register - Register new user.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/register',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						// Username and password are OPTIONAL as of 1.6.0, so the app
						// can offer the same low-friction signup the web checkout
						// does: the username is derived from the email and, with no
						// password, the buyer gets a set-password email instead of
						// being asked to invent one mid-purchase. Email is the only
						// thing genuinely required.
						'username'     => array(
							'type' => 'string',
						),
						'email'        => array(
							'type'     => 'string',
							'format'   => 'email',
							'required' => true,
						),
						'password'     => array(
							'type' => 'string',
						),
						'display_name' => array(
							'type' => 'string',
						),
						'first_name'   => array(
							'type' => 'string',
						),
						'last_name'    => array(
							'type' => 'string',
						),
						'role'         => array(
							'description' => __( 'User role (customer or vendor).', 'wp-sell-services' ),
							'type'        => 'string',
							'enum'        => array( 'customer', 'vendor' ),
							'default'     => 'customer',
						),
					),
				),
			)
		);

		// POST /auth/logout - Revoke app password.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/logout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'logout' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /auth/me - Get current user profile.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_me' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /auth/forgot-password - Send password reset email.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/forgot-password',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'forgot_password' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'email' => array(
							'type'     => 'string',
							'format'   => 'email',
							'required' => true,
						),
					),
				),
			)
		);

		// POST /auth/change-password - Change password for logged-in user.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/change-password',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'change_password' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'current_password' => array(
							'type'     => 'string',
							'required' => true,
						),
						'new_password'     => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		// GET + POST /auth/devices - list and register push devices.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/devices',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_devices' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_device' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'token'       => array(
							'description' => __( 'Push notification token (FCM/APNs).', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'platform'    => array(
							'type'     => 'string',
							'enum'     => array( 'ios', 'android', 'web' ),
							'required' => true,
						),
						'device_id'   => array(
							'description' => __( 'Unique device identifier.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'device_name' => array(
							'description' => __( 'Human-readable device label shown in the account\'s device list.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => false,
						),
					),
				),
			)
		);

		// DELETE /auth/devices/{device_id} - Unregister device.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/devices/(?P<device_id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'unregister_device' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		/*
		 * GET /auth/sessions - the member's active sign-ins.
		 * DELETE /auth/sessions/{uuid} - revoke one of them.
		 *
		 * Deliberately NOT hung off /auth/devices. That path already exists and
		 * means something else entirely: PUSH NOTIFICATION tokens, stored in
		 * _wpss_push_devices. Reading the route list, "devices" looks like it
		 * answers "where am I signed in?" and it does not - which is how this
		 * card came to be filed with a devices endpoint already shipped
		 * (Basecamp 10154918753). A session is not a device; one phone can hold
		 * both, and revoking a push token must not sign anybody out.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sessions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sessions' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sessions/(?P<uuid>[a-zA-Z0-9\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke_session' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'uuid' => array(
							'description'       => __( 'Session identifier from GET /auth/sessions.', 'wp-sell-services' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * List the member's active app sign-ins.
	 *
	 * @since 1.6.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_sessions( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$user_id  = get_current_user_id();
		$sessions = array();

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return new WP_REST_Response( $sessions );
		}

		// Which one is making THIS request, so a client can mark it "this
		// device" and avoid offering to revoke the session it is using.
		$current = function_exists( 'rest_get_authenticated_app_password' )
			? (string) rest_get_authenticated_app_password()
			: '';

		foreach ( (array) WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
			// Only sign-ins this plugin issued. An application password the
			// member created by hand in their WordPress profile belongs to
			// whatever script they built with it, and is not ours to list as a
			// "sign-in" or to offer for revocation here.
			if ( ! str_starts_with( (string) ( $item['name'] ?? '' ), 'WPSS' ) ) {
				continue;
			}

			$expires_at = wpss_app_token_expires_at( $item );

			$sessions[] = array(
				'uuid'       => (string) ( $item['uuid'] ?? '' ),
				// The name is stored as "WPSS <device>"; give back the device.
				'device'     => trim( substr( (string) ( $item['name'] ?? '' ), 4 ) ),
				'created'    => wpss_rest_date( gmdate( 'Y-m-d H:i:s', (int) ( $item['created'] ?? 0 ) ) ),
				'last_used'  => ! empty( $item['last_used'] )
					? wpss_rest_date( gmdate( 'Y-m-d H:i:s', (int) $item['last_used'] ) )
					: null,
				'last_ip'    => (string) ( $item['last_ip'] ?? '' ),
				'expires'    => null !== $expires_at ? wpss_rest_date( gmdate( 'Y-m-d H:i:s', $expires_at ) ) : null,
				'expired'    => wpss_app_token_is_expired( $item ),
				'is_current' => '' !== $current && $current === (string) ( $item['uuid'] ?? '' ),
			);
		}

		return new WP_REST_Response( $sessions );
	}

	/**
	 * Revoke one app sign-in.
	 *
	 * @since 1.6.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revoke_session( WP_REST_Request $request ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return new WP_Error(
				'wpss_sessions_unavailable',
				__( 'Application passwords are not available on this site.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		$user_id = get_current_user_id();
		$uuid    = (string) $request->get_param( 'uuid' );

		// Look the session up on THIS member before touching it. Without this
		// the uuid alone would decide what gets deleted, and one member could
		// revoke another's sign-in by guessing it.
		$match = null;

		foreach ( (array) WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
			if ( (string) ( $item['uuid'] ?? '' ) === $uuid && str_starts_with( (string) ( $item['name'] ?? '' ), 'WPSS' ) ) {
				$match = $item;
				break;
			}
		}

		if ( null === $match ) {
			return new WP_Error(
				'wpss_session_not_found',
				__( 'That sign-in was not found. It may already have been revoked.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		$deleted = WP_Application_Passwords::delete_application_password( $user_id, $uuid );

		if ( is_wp_error( $deleted ) || ! $deleted ) {
			return new WP_Error(
				'wpss_session_revoke_failed',
				__( 'Could not revoke that sign-in. Please try again.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a single app sign-in is revoked.
		 *
		 * @since 1.6.0
		 *
		 * @param int    $user_id Member whose session was revoked.
		 * @param string $uuid    Session identifier.
		 */
		do_action( 'wpss_app_session_revoked', $user_id, $uuid );

		return new WP_REST_Response( array( 'revoked' => true ) );
	}

	/**
	 * Check rate limit for an action.
	 *
	 * @param string $action  Action identifier (e.g. 'login', 'register').
	 * @param int    $limit   Max attempts allowed in the window.
	 * @param int    $window  Time window in seconds.
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited.
	 */
	private function check_rate_limit( string $action, int $limit = 5, int $window = 300 ) {
		$ip        = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
		$cache_key = 'wpss_rate_' . $action . '_' . md5( $ip );
		$attempts  = (int) get_transient( $cache_key );

		if ( $attempts >= $limit ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Too many attempts. Please try again later.', 'wp-sell-services' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $cache_key, $attempts + 1, $window );

		return true;
	}

	/**
	 * Authenticate user and return application password.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function login( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'login', 5, 300 );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$username = sanitize_user( $request->get_param( 'username' ) );
		$password = $request->get_param( 'password' );

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'invalid_credentials',
				__( 'Invalid username or password.', 'wp-sell-services' ),
				array( 'status' => 401 )
			);
		}

		/*
		 * A token must not be able to mint more tokens (Basecamp 10154918753).
		 *
		 * WordPress authenticates application passwords through this same
		 * wp_authenticate() chain, so passing a previously issued token as the
		 * `password` succeeded here and was handed a brand new one - verified
		 * over HTTP returning 200. Whoever stole one token had an unlimited
		 * supply, and revoking the original changed nothing.
		 *
		 * Checked AFTER wp_authenticate() rather than by calling
		 * wp_authenticate_username_password() directly: bypassing the chain
		 * would also bypass any two-factor or SSO plugin on the site, trading
		 * this hole for a worse one.
		 */
		if ( wpss_password_is_app_token( $user, (string) $password ) ) {
			return new WP_Error(
				'wpss_token_cannot_mint',
				__( 'Sign in with your account password. An existing app sign-in cannot be used to create another one.', 'wp-sell-services' ),
				array( 'status' => 401 )
			);
		}

		$device_name = sanitize_text_field( $request->get_param( 'device_name' ) );
		$app_pass    = $this->create_app_password( $user, $device_name );

		if ( is_wp_error( $app_pass ) ) {
			return $app_pass;
		}

		// `expires` was hardcoded null and the server enforced nothing, so a
		// token was valid forever. It is now the real deadline, enforced at
		// authentication by AppTokenGuard - see wpss_app_token_lifetime().
		// Still nullable, because a site can switch expiry off with that
		// filter, and a client must read null as "no expiry" rather than
		// assuming a number is always there.
		$expires_at = wpss_app_token_expires_at( $app_pass['item'] );

		return new WP_REST_Response(
			array(
				'token'   => base64_encode( $user->user_login . ':' . $app_pass['password'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'user'    => $this->format_user( $user ),
				'expires' => null !== $expires_at ? wpss_rest_date( gmdate( 'Y-m-d H:i:s', $expires_at ) ) : null,
			)
		);
	}

	/**
	 * Register a new user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'register', 3, 600 );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		/*
		 * Either WordPress registration is open, or the owner has switched on
		 * account-at-checkout - which is itself a decision that buyers may create
		 * accounts. Without the second clause the app and the web would disagree:
		 * a site with WP registration off but checkout account creation on would
		 * let a browser buyer sign up mid-purchase and refuse the same buyer in the
		 * app, for the same purchase.
		 */
		if ( ! get_option( 'users_can_register' ) && ! wpss_checkout_creates_accounts() ) {
			return new WP_Error( 'registration_disabled', __( 'User registration is disabled.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		$username     = sanitize_user( (string) $request->get_param( 'username' ) );
		$email        = sanitize_email( (string) $request->get_param( 'email' ) );
		$password     = (string) $request->get_param( 'password' );
		$display_name = sanitize_text_field( (string) ( $request->get_param( 'display_name' ) ?: $username ) );
		$role         = $request->get_param( 'role' );

		if ( '' !== $username && username_exists( $username ) ) {
			return new WP_Error( 'username_exists', __( 'Username already exists.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// A password is optional now, matching the web flow: omit it and the buyer
		// gets a set-password email instead of being asked to invent one. Supply
		// one and it still has to be strong.
		if ( '' !== $password && strlen( $password ) < 8 ) {
			return new WP_Error( 'weak_password', __( 'Password must be at least 8 characters.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		/*
		 * Creation goes through the ONE account path.
		 *
		 * This method used to run its own wp_insert_user(), which meant the app
		 * created users differently from the web: no first/last name, no
		 * show_admin_bar_front, no wpss_public_signup_complete for Pro to hook,
		 * and a username that had to be supplied rather than derived. Two ways to
		 * make a user is precisely the shape that produced most of 1.6.0's bugs.
		 *
		 * email_exists() is checked inside create_account(), which returns
		 * wpss_email_exists - mapped below so the app keeps the code it expects.
		 */
		$user_id = \WPSellServices\Frontend\PublicSignup::create_account(
			array(
				'email'        => $email,
				'password'     => $password,
				'display_name' => $display_name,
				'first_name'   => sanitize_text_field( (string) $request->get_param( 'first_name' ) ),
				'last_name'    => sanitize_text_field( (string) $request->get_param( 'last_name' ) ),
				'intent'       => 'vendor' === $role ? 'vendor' : 'buyer',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			// Keep the documented error codes the app already branches on.
			$map = array(
				'wpss_email_exists'  => array( 'email_exists', 400 ),
				'wpss_invalid_email' => array( 'invalid_email', 400 ),
			);

			$code = $user_id->get_error_code();

			if ( isset( $map[ $code ] ) ) {
				return new WP_Error( $map[ $code ][0], $user_id->get_error_message(), array( 'status' => $map[ $code ][1] ) );
			}

			return $user_id;
		}

		// Vendor promotion is NOT repeated here: create_account() was given the
		// intent above and the signup path already routes it through
		// VendorService::register(), which honours the site's
		// open/approval/closed vendor_registration mode. Calling it twice would
		// re-run that decision.

		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return new \WP_Error(
				'user_lookup_failed',
				__( 'Account created but user lookup failed. Please log in.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		$app_pass = $this->create_app_password( $user, 'WPSS Mobile App' );

		if ( is_wp_error( $app_pass ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'user'    => $this->format_user( $user ),
					'message' => __( 'Account created. Please log in.', 'wp-sell-services' ),
				),
				201
			);
		}

		// Registration hands back a token too, so it carries the same expiry
		// contract as /auth/login - a client should not have to learn that one
		// entry point expires and the other does not.
		$expires_at = wpss_app_token_expires_at( $app_pass['item'] );

		return new WP_REST_Response(
			array(
				'token'   => base64_encode( $user->user_login . ':' . $app_pass['password'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'user'    => $this->format_user( $user ),
				'expires' => null !== $expires_at ? wpss_rest_date( gmdate( 'Y-m-d H:i:s', $expires_at ) ) : null,
			),
			201
		);
	}

	/**
	 * Logout and revoke current app password.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function logout( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();

		// Revoke every WPSS app password and forget the push devices.
		//
		// Shared with account deletion, which needs the identical operation.
		// See wpss_revoke_app_sessions() for why the WPSS name prefix decides
		// what gets revoked and what is left alone.
		wpss_revoke_app_sessions( $user_id );

		return new WP_REST_Response( array( 'logged_out' => true ) );
	}

	/**
	 * Get current user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_me( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();

		return new WP_REST_Response( $this->format_user( $user ) );
	}

	/**
	 * Send password reset email.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function forgot_password( WP_REST_Request $request ) {
		$rate_check = $this->check_rate_limit( 'forgot_password', 3, 600 );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$email = sanitize_email( $request->get_param( 'email' ) );
		$user  = get_user_by( 'email', $email );

		// Always return success to prevent email enumeration.
		if ( ! $user ) {
			return new WP_REST_Response(
				array( 'message' => __( 'If an account exists with that email, a password reset link has been sent.', 'wp-sell-services' ) )
			);
		}

		$result = retrieve_password( $user->user_login );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'If an account exists with that email, a password reset link has been sent.', 'wp-sell-services' ) )
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'If an account exists with that email, a password reset link has been sent.', 'wp-sell-services' ) )
		);
	}

	/**
	 * Change password for logged-in user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function change_password( WP_REST_Request $request ) {
		$user             = wp_get_current_user();
		$current_password = $request->get_param( 'current_password' );
		$new_password     = $request->get_param( 'new_password' );

		if ( ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
			return new WP_Error( 'incorrect_password', __( 'Current password is incorrect.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		if ( strlen( $new_password ) < 8 ) {
			return new WP_Error( 'weak_password', __( 'New password must be at least 8 characters.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		wp_set_password( $new_password, $user->ID );

		return new WP_REST_Response( array( 'message' => __( 'Password changed successfully.', 'wp-sell-services' ) ) );
	}

	/**
	 * Register device for push notifications.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function register_device( WP_REST_Request $request ): WP_REST_Response {
		$user_id   = get_current_user_id();
		$token     = sanitize_text_field( $request->get_param( 'token' ) );
		$platform  = sanitize_text_field( $request->get_param( 'platform' ) );
		$device_id = sanitize_text_field( $request->get_param( 'device_id' ) );
		$label     = sanitize_text_field( (string) $request->get_param( 'device_name' ) );

		$devices = get_user_meta( $user_id, '_wpss_push_devices', true ) ?: array();
		$now     = current_time( 'mysql', true );

		// Re-registering the same device must not look like a new one in the
		// account's device list, so keep the original registration date and
		// only move last_seen_at forward.
		$existing = is_array( $devices[ $device_id ] ?? null ) ? $devices[ $device_id ] : array();

		$devices[ $device_id ] = array(
			'token'         => $token,
			'platform'      => $platform,
			'device_name'   => '' !== $label ? $label : (string) ( $existing['device_name'] ?? '' ),
			'registered_at' => (string) ( $existing['registered_at'] ?? $now ),
			'last_seen_at'  => $now,
		);

		update_user_meta( $user_id, '_wpss_push_devices', $devices );

		return new WP_REST_Response(
			array(
				'registered' => true,
				'device_id'  => $device_id,
				'platform'   => $platform,
			),
			201
		);
	}

	/**
	 * List the current user's registered push devices.
	 *
	 * Powers "this device / other devices" in account settings, which could not
	 * be built before: registration and revocation both existed, but nothing
	 * told the client which device ids the account actually had.
	 *
	 * Push tokens are deliberately not returned. They are the secret that lets
	 * something send to the device, the client already knows its own, and a
	 * settings screen has no use for anybody else's.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_devices( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$devices = get_user_meta( $user_id, '_wpss_push_devices', true );
		$devices = is_array( $devices ) ? $devices : array();

		$items = array();

		foreach ( $devices as $device_id => $device ) {
			if ( ! is_array( $device ) ) {
				continue;
			}

			// Devices registered before 1.4.0 carry neither a label nor a
			// last-seen stamp; fall back to the registration date so the list
			// still sorts and renders instead of showing blanks.
			$registered = (string) ( $device['registered_at'] ?? '' );

			$items[] = array(
				'device_id'    => (string) $device_id,
				'platform'     => (string) ( $device['platform'] ?? '' ),
				'device_name'  => (string) ( $device['device_name'] ?? '' ),
				'created_at'   => $registered,
				'last_seen_at' => (string) ( $device['last_seen_at'] ?? $registered ),
			);
		}

		// Most recently used first — that is the order a person scans the list in.
		usort(
			$items,
			static function ( array $a, array $b ): int {
				return strcmp( $b['last_seen_at'], $a['last_seen_at'] );
			}
		);

		$total      = count( $items );
		$pagination = $this->get_pagination_args( $request );

		$response = new WP_REST_Response( array_slice( $items, $pagination['offset'], $pagination['per_page'] ) );

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $pagination['per_page'] ) );

		return $response;
	}

	/**
	 * Unregister device from push notifications.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function unregister_device( WP_REST_Request $request ): WP_REST_Response {
		$user_id   = get_current_user_id();
		$device_id = sanitize_text_field( $request->get_param( 'device_id' ) );

		$devices = get_user_meta( $user_id, '_wpss_push_devices', true ) ?: array();

		unset( $devices[ $device_id ] );

		update_user_meta( $user_id, '_wpss_push_devices', $devices );

		return new WP_REST_Response( array( 'unregistered' => true ) );
	}

	/**
	 * Create application password for user.
	 *
	 * @param WP_User $user        User object.
	 * @param string  $device_name Device name.
	 * @return array{password: string, item: array<string, mixed>}|WP_Error
	 */
	private function create_app_password( WP_User $user, string $device_name ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return new WP_Error( 'app_passwords_unavailable', __( 'Application passwords are not available.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		// Clean up old WPSS app passwords (keep max 5 per user).
		$existing       = WP_Application_Passwords::get_user_application_passwords( $user->ID );
		$wpss_passwords = array_filter(
			$existing,
			function ( $p ) {
				return str_starts_with( $p['name'], 'WPSS' );
			}
		);

		if ( count( $wpss_passwords ) >= 5 ) {
			// Remove the oldest.
			usort(
				$wpss_passwords,
				function ( $a, $b ) {
					return ( $a['created'] ?? 0 ) <=> ( $b['created'] ?? 0 );
				}
			);
			WP_Application_Passwords::delete_application_password( $user->ID, $wpss_passwords[0]['uuid'] );
		}

		$result = WP_Application_Passwords::create_new_application_password(
			$user->ID,
			array( 'name' => 'WPSS ' . $device_name )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Both halves: the unhashed password, which is the only moment it is
		// ever readable, and the stored record, which carries the `created`
		// timestamp the expiry deadline is computed from.
		return array(
			'password' => $result[0],
			'item'     => (array) $result[1],
		);
	}

	/**
	 * Format user data for response.
	 *
	 * @param WP_User $user User object.
	 * @return array
	 */
	private function format_user( WP_User $user ): array {
		$is_vendor = wpss_is_vendor( $user->ID );

		return array(
			'id'            => $user->ID,
			'username'      => $user->user_login,
			'email'         => $user->user_email,
			'display_name'  => $user->display_name,
			// Same size as /me. These two endpoints describe the same user and
			// were handing back the same field at two different sizes, so a
			// client still had to know which one it had called - the very thing
			// aligning their shapes was meant to end.
			'avatar'        => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
			'is_vendor'     => $is_vendor,
			// Canonical profile status — _wpss_vendor_status was never written.
			'vendor_status' => $is_vendor ? ( wpss_get_vendor_status( $user->ID ) ?: 'active' ) : null,
			'is_admin'      => $user->has_cap( 'manage_options' ),
			// user_registered is site-local MySQL with no zone. Every other date
			// in the API is ISO-8601 with an offset (Basecamp 10154919636), and
			// this one was missed on the first pass because the audit probed
			// collection endpoints, not /auth/login and /me.
			'registered'    => wpss_rest_date( $user->user_registered ),
			// /me and this endpoint both answer "who is the current user?" but
			// returned different shapes, so a client had two contracts for one
			// question and had to know which endpoint it had called. Both are
			// supersets now: these are /me's fields, added here rather than
			// dropping anything, so neither existing consumer breaks.
			'capabilities'  => array(
				'can_create_services' => current_user_can( 'wpss_manage_services' ) && $is_vendor,
				'can_manage_orders'   => current_user_can( 'wpss_manage_orders' ) || current_user_can( 'manage_options' ),
			),
			// Same meta keys /me reads, so the two endpoints cannot report
			// different numbers for the same user.
			'rating'        => (float) get_user_meta( $user->ID, '_wpss_rating_average', true ) ?: 0,
			'review_count'  => (int) get_user_meta( $user->ID, '_wpss_rating_count', true ) ?: 0,
		);
	}
}
