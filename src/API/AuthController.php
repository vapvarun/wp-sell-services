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
						'username'     => array(
							'type'     => 'string',
							'required' => true,
						),
						'email'        => array(
							'type'     => 'string',
							'format'   => 'email',
							'required' => true,
						),
						'password'     => array(
							'type'     => 'string',
							'required' => true,
						),
						'display_name' => array(
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

		// POST /auth/devices - Register device for push notifications.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/devices',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_device' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'token'     => array(
							'description' => __( 'Push notification token (FCM/APNs).', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
						),
						'platform'  => array(
							'type'     => 'string',
							'enum'     => array( 'ios', 'android', 'web' ),
							'required' => true,
						),
						'device_id' => array(
							'description' => __( 'Unique device identifier.', 'wp-sell-services' ),
							'type'        => 'string',
							'required'    => true,
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

		$device_name = sanitize_text_field( $request->get_param( 'device_name' ) );
		$app_pass    = $this->create_app_password( $user, $device_name );

		if ( is_wp_error( $app_pass ) ) {
			return $app_pass;
		}

		return new WP_REST_Response(
			array(
				'token'   => base64_encode( $user->user_login . ':' . $app_pass ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'user'    => $this->format_user( $user ),
				'expires' => null,
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

		if ( ! get_option( 'users_can_register' ) ) {
			return new WP_Error( 'registration_disabled', __( 'User registration is disabled.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		$username     = sanitize_user( $request->get_param( 'username' ) );
		$email        = sanitize_email( $request->get_param( 'email' ) );
		$password     = $request->get_param( 'password' );
		$display_name = sanitize_text_field( $request->get_param( 'display_name' ) ?: $username );
		$role         = $request->get_param( 'role' );

		if ( username_exists( $username ) ) {
			return new WP_Error( 'username_exists', __( 'Username already exists.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'email_exists', __( 'Email already exists.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		if ( strlen( $password ) < 8 ) {
			return new WP_Error( 'weak_password', __( 'Password must be at least 8 characters.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $display_name,
				'role'         => get_option( 'default_role', 'subscriber' ),
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( 'vendor' === $role ) {
			update_user_meta( $user_id, '_wpss_is_vendor', '1' );
			update_user_meta( $user_id, '_wpss_vendor_status', 'pending' );
		}

		$user     = get_user_by( 'ID', $user_id );
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

		return new WP_REST_Response(
			array(
				'token' => base64_encode( $user->user_login . ':' . $app_pass ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'user'  => $this->format_user( $user ),
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

		// Revoke all WPSS app passwords for this user.
		if ( class_exists( 'WP_Application_Passwords' ) ) {
			$app_passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );

			foreach ( $app_passwords as $app_password ) {
				if ( str_starts_with( $app_password['name'], 'WPSS' ) ) {
					WP_Application_Passwords::delete_application_password( $user_id, $app_password['uuid'] );
				}
			}
		}

		// Remove push notification devices.
		delete_user_meta( $user_id, '_wpss_push_devices' );

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

		$devices = get_user_meta( $user_id, '_wpss_push_devices', true ) ?: array();

		// Replace if device_id already exists, otherwise add.
		$devices[ $device_id ] = array(
			'token'         => $token,
			'platform'      => $platform,
			'registered_at' => current_time( 'mysql', true ),
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
	 * @return string|WP_Error
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

		return $result[0]; // The unhashed password.
	}

	/**
	 * Format user data for response.
	 *
	 * @param WP_User $user User object.
	 * @return array
	 */
	private function format_user( WP_User $user ): array {
		$is_vendor = (bool) get_user_meta( $user->ID, '_wpss_is_vendor', true );

		return array(
			'id'            => $user->ID,
			'username'      => $user->user_login,
			'email'         => $user->user_email,
			'display_name'  => $user->display_name,
			'avatar'        => get_avatar_url( $user->ID, array( 'size' => 256 ) ),
			'is_vendor'     => $is_vendor,
			'vendor_status' => $is_vendor ? ( get_user_meta( $user->ID, '_wpss_vendor_status', true ) ?: 'active' ) : null,
			'is_admin'      => $user->has_cap( 'manage_options' ),
			'registered'    => $user->user_registered,
		);
	}
}
