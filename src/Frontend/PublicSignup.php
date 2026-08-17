<?php
/**
 * Public Signup
 *
 * Handles inline signup for logged-out visitors who arrive on the
 * `[wpss_vendor_registration]` shortcode (or any future surface that
 * needs a public signup form). Creates the WP user, optionally promotes
 * to vendor, signs them in, and returns the redirect target.
 *
 * @package WPSellServices\Frontend
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

use WPSellServices\Services\VendorService;
use WPSellServices\Core\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * Public signup AJAX handler + form renderer.
 *
 * @since 1.1.0
 */
class PublicSignup {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_wpss_public_signup', array( $this, 'ajax_signup' ) );
		add_action( 'wp_ajax_nopriv_wpss_public_signup', array( $this, 'ajax_signup' ) );

		// Checkout's account step. nopriv only: a signed-in buyer has no use for
		// it, and answering them would just be a second way to reach signup.
		add_action( 'wp_ajax_nopriv_wpss_checkout_create_account', array( $this, 'ajax_checkout_account' ) );
	}

	/**
	 * Handle the inline signup AJAX submission.
	 *
	 * Accepts: email, password, display_name, intent (buyer|vendor), nonce.
	 * Creates the user, signs them in, optionally promotes to vendor, and
	 * returns the redirect URL the client should navigate to.
	 *
	 * @return void
	 */
	public function ajax_signup(): void {
		check_ajax_referer( 'wpss_public_signup', 'nonce' );

		if ( is_user_logged_in() ) {
			wp_send_json_error(
				array( 'message' => __( 'You are already signed in.', 'wp-sell-services' ) ),
				400
			);
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		// Passwords are intentionally NOT sanitized — wp_insert_user hashes
		// them via wp_hash_password and any sanitization would corrupt the
		// raw input the user typed (e.g. stripping special characters).
		$password     = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$intent       = isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : 'buyer';

		// Field-level validation. Each error returns a `field` key so the
		// frontend can route the message to the correct input via the
		// WpssFormError primitive.
		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'field'   => 'email',
					'message' => __( 'Enter a valid email address.', 'wp-sell-services' ),
				),
				400
			);
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error(
				array(
					'field'   => 'email',
					'message' => __( 'An account with this email already exists. Sign in instead.', 'wp-sell-services' ),
				),
				400
			);
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error(
				array(
					'field'   => 'password',
					'message' => __( 'Password must be at least 8 characters.', 'wp-sell-services' ),
				),
				400
			);
		}

		if ( empty( $display_name ) ) {
			wp_send_json_error(
				array(
					'field'   => 'display_name',
					'message' => __( 'Tell us how you would like to be addressed.', 'wp-sell-services' ),
				),
				400
			);
		}

		$user_id = self::create_account(
			array(
				'email'        => $email,
				'password'     => $password,
				'display_name' => $display_name,
				'intent'       => $intent,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error(
				array( 'message' => $user_id->get_error_message() ),
				500
			);
		}

		$redirect_url = (string) ( wpss_get_page_url( 'dashboard' ) ?: home_url() );

		// If the visitor came in to become a vendor, immediately promote them
		// using the existing VendorService. The same registration mode rules
		// apply — open mode = active vendor, approval mode = pending.
		if ( 'vendor' === $intent ) {
			$vendor_service = new VendorService();
			$result         = $vendor_service->register( (int) $user_id );

			if ( false === $result ) {
				// User is created + signed in even if vendor promotion failed —
				// they can retry from the logged-in dashboard. Surface a friendly
				// notice but still redirect. `register()` returns bool (not
				// WP_Error), so we send a generic message; specific failure
				// reasons are logged inside VendorService::register().
				wp_send_json_success(
					array(
						'redirect' => $redirect_url,
						'message'  => __( 'Account created, but vendor promotion failed. Try again from the dashboard.', 'wp-sell-services' ),
						'warning'  => true,
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'redirect' => $redirect_url,
				'message'  => __( 'Welcome! Redirecting…', 'wp-sell-services' ),
			)
		);
	}

	/**
	 * Create the buyer's account mid-checkout, from the billing details.
	 *
	 * Called by the checkout form BEFORE the payment request, so the order is
	 * inserted against a real `customer_id` and the buyer can immediately submit
	 * requirements and message the seller. Reads the billing name and email the
	 * form already collects - those three fields are locked on
	 * ({@see wpss_get_required_billing_fields()}) precisely because an order has
	 * to be attributable to someone contactable - so checkout gains no new inputs.
	 *
	 * Returns a FRESH checkout nonce. WordPress nonces are bound to the user, so
	 * the one rendered for a logged-out visitor stops verifying the instant they
	 * are signed in; without swapping it the payment request that follows would
	 * fail its own security check.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	public function ajax_checkout_account(): void {
		check_ajax_referer( 'wpss_checkout', 'nonce' );

		if ( ! wpss_checkout_creates_accounts() ) {
			wp_send_json_error(
				array( 'message' => __( 'Please log in to complete your purchase.', 'wp-sell-services' ) ),
				403
			);
		}

		if ( is_user_logged_in() ) {
			wp_send_json_error(
				array( 'message' => __( 'You are already signed in.', 'wp-sell-services' ) ),
				400
			);
		}

		// Unauthenticated and it creates users, so it is an abuse surface. Keyed
		// by IP because there is no user yet.
		if ( RateLimiter::check_and_track( 'checkout_account' ) ) {
			RateLimiter::send_error( 'checkout_account' );
		}

		$email      = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
		$first_name = isset( $_POST['billing_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) : '';
		$last_name  = isset( $_POST['billing_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'field'   => 'billing_email',
					'message' => __( 'Enter a valid email address so we can send your order updates.', 'wp-sell-services' ),
				),
				400
			);
		}

		if ( '' === $first_name ) {
			wp_send_json_error(
				array(
					'field'   => 'billing_first_name',
					'message' => __( 'Enter your first name.', 'wp-sell-services' ),
				),
				400
			);
		}

		/*
		 * The email already has an account.
		 *
		 * We must not attach the order to it: anyone who knows your email could
		 * otherwise place an order that lands in your history. And we must not
		 * create a second account on the same address. So no money is taken and
		 * nothing is attached - the buyer is asked to sign in, and the checkout
		 * URL is handed back so they return to exactly this purchase.
		 *
		 * Owner-approved default, recorded on Basecamp 10163575694.
		 */
		if ( email_exists( $email ) ) {
			wp_send_json_error(
				array(
					'code'      => 'account_exists',
					'field'     => 'billing_email',
					'message'   => __( 'You already have an account with this email. Please log in to continue - your purchase is saved.', 'wp-sell-services' ),
					'login_url' => wp_login_url( self::checkout_return_url() ),
				),
				409
			);
		}

		$user_id = self::create_account(
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'intent'     => 'buyer',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error(
				array( 'message' => $user_id->get_error_message() ),
				400
			);
		}

		// Persist what they typed onto the new account, through the same helper
		// every gateway uses, so the address is theirs from the first order
		// rather than being re-asked next time.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified at the top of this handler.
		wpss_save_billing_from_request( $_POST );

		wp_send_json_success(
			array(
				'user_id'        => (int) $user_id,
				'checkout_nonce' => wp_create_nonce( 'wpss_checkout' ),
				'message'        => __( 'Account created. Completing your purchase…', 'wp-sell-services' ),
			)
		);
	}

	/**
	 * Where to send the buyer back after they log in.
	 *
	 * The client sends the checkout URL it is actually on, because this request
	 * arrives at admin-ajax.php and the referer is not something to trust with a
	 * redirect. Validated against the site's own host and falls back to the
	 * mapped checkout page, so a tampered value can only ever send the buyer to
	 * their own checkout.
	 *
	 * @since 1.6.0
	 *
	 * @return string
	 */
	private static function checkout_return_url(): string {
		$fallback = (string) ( wpss_get_checkout_base_url() ?: home_url() );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling handler; this only chooses a redirect target.
		$posted = isset( $_POST['checkout_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['checkout_url'] ) ) : '';

		if ( '' === $posted ) {
			return $fallback;
		}

		$posted_host = wp_parse_url( $posted, PHP_URL_HOST );
		$home_host   = wp_parse_url( home_url(), PHP_URL_HOST );

		return ( $posted_host && $posted_host === $home_host ) ? $posted : $fallback;
	}

	/**
	 * Create a buyer/vendor account and sign the person in.
	 *
	 * THE single account-creation path. Both the inline signup form and checkout
	 * call this: a second copy would drift on username derivation, on the
	 * `wpss_public_signup_complete` contract Pro listens to, or on the sign-in
	 * step, and the failure would be invisible until a Pro feature stopped
	 * firing for one of the two.
	 *
	 * Signs in immediately, because the caller needs a real user id before it
	 * writes anything that has an owner - a checkout that creates the order first
	 * would have nobody to attach it to.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, mixed> $args Account arguments.
	 *
	 *     @type string $email        Required.
	 *     @type string $password     Optional. Generated when empty, and a
	 *                                set-password email is sent instead.
	 *     @type string $display_name Optional. Falls back to first+last, then the
	 *                                email local-part.
	 *     @type string $first_name   Optional.
	 *     @type string $last_name    Optional.
	 *     @type string $intent       Optional. 'buyer' (default) or 'vendor'.
	 *
	 * @return int|\WP_Error New user ID.
	 */
	public static function create_account( array $args ) {
		$email      = sanitize_email( (string) ( $args['email'] ?? '' ) );
		$first_name = sanitize_text_field( (string) ( $args['first_name'] ?? '' ) );
		$last_name  = sanitize_text_field( (string) ( $args['last_name'] ?? '' ) );
		$intent     = sanitize_key( (string) ( $args['intent'] ?? 'buyer' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return new \WP_Error( 'wpss_invalid_email', __( 'Enter a valid email address.', 'wp-sell-services' ) );
		}

		if ( email_exists( $email ) ) {
			return new \WP_Error( 'wpss_email_exists', __( 'An account with this email already exists. Sign in instead.', 'wp-sell-services' ) );
		}

		$display_name = sanitize_text_field( (string) ( $args['display_name'] ?? '' ) );

		if ( '' === $display_name ) {
			$display_name = trim( $first_name . ' ' . $last_name );
		}

		if ( '' === $display_name ) {
			$display_name = (string) ( strstr( $email, '@', true ) ?: $email );
		}

		// No password means the buyer was never asked for one. Generate a strong
		// one they will never see and send a set-password link, so nobody has to
		// invent a password in the middle of a purchase.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw by design; wp_insert_user hashes it and sanitising would corrupt what the user typed.
		$password     = (string) ( $args['password'] ?? '' );
		$sent_by_mail = '' === $password;

		if ( $sent_by_mail ) {
			$password = wp_generate_password( 24, true, true );
		}

		// Username derived from the local-part of the email + a numeric suffix
		// when collisions occur. Email is the user-facing identifier; the
		// username is just a unique slug for the wp_users table.
		$base_login = sanitize_user( strstr( $email, '@', true ) ?: 'user', true );
		$user_login = $base_login;
		$suffix     = 1;

		while ( username_exists( $user_login ) ) {
			$user_login = $base_login . $suffix;
			++$suffix;

			if ( $suffix > 999 ) {
				return new \WP_Error(
					'wpss_username_unavailable',
					__( 'Could not generate a unique username. Try a different email.', 'wp-sell-services' )
				);
			}
		}

		$user_id = wp_insert_user(
			array(
				'user_login'           => $user_login,
				'user_email'           => $email,
				'user_pass'            => $password,
				'display_name'         => $display_name,
				'first_name'           => '' !== $first_name ? $first_name : $display_name,
				'last_name'            => $last_name,
				'role'                 => 'subscriber',
				// `show_admin_bar_front` is stored as a string ('true'/'false') in
				// usermeta — wp_insert_user passes the value through unchanged so
				// we set the canonical string form here.
				'show_admin_bar_front' => 'false',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user_id = (int) $user_id;

		/*
		 * Sign in immediately - and make the session token visible to THIS request.
		 *
		 * wp_set_auth_cookie() only sends Set-Cookie headers; it does not touch
		 * $_COOKIE. wp_create_nonce() hashes wp_get_session_token(), which reads
		 * the logged-in cookie out of $_COOKIE - so a nonce minted right after
		 * signing in is built against an EMPTY token while the browser's next
		 * request carries a real one, and the nonce fails.
		 *
		 * That is not theoretical: it is exactly what happened on the first run of
		 * the checkout flow. The account was created, the fresh checkout nonce was
		 * returned, and the payment request that followed died on
		 * "Security check failed" with the buyer's account already made.
		 *
		 * So the token is created explicitly, handed to wp_set_auth_cookie(), and
		 * written into $_COOKIE. Any nonce this request goes on to create now
		 * matches the one the browser will send back.
		 */
		$expiration = time() + (int) apply_filters( 'auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, true );
		$manager    = \WP_Session_Tokens::get_instance( $user_id );
		$token      = $manager->create( $expiration );

		wp_set_current_user( $user_id, $user_login );
		wp_set_auth_cookie( $user_id, true, '', $token );

		// Guarded because the constant is defined during WP's cookie bootstrap, not
		// at load: an early caller would otherwise fatal on it.
		if ( defined( 'LOGGED_IN_COOKIE' ) ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Deliberate: keeps wp_get_session_token() correct for the rest of this request.
			$_COOKIE[ constant( 'LOGGED_IN_COOKIE' ) ] = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );
		}

		if ( $sent_by_mail ) {
			// WordPress's own "set your password" mail. Deliberately core's, not a
			// branded one: it carries a single-use key tied to this account and
			// re-implementing that is how password resets get broken.
			wp_new_user_notification( $user_id, null, 'user' );
		}

		/**
		 * Fires after a user signs up via the public signup form or checkout.
		 *
		 * Pro plugin or other extensions can hook in to send welcome emails,
		 * track signup analytics, or run any post-signup logic.
		 *
		 * @since 1.1.0
		 *
		 * @param int    $user_id  The newly-created user ID.
		 * @param string $intent   Signup intent: 'buyer' or 'vendor'.
		 */
		do_action( 'wpss_public_signup_complete', $user_id, $intent );

		return $user_id;
	}

	/**
	 * Render the inline signup form HTML.
	 *
	 * Used by the `[wpss_vendor_registration]` shortcode for logged-out
	 * visitors. Returns markup compatible with the WpssFormError primitive
	 * (form-level summary + field-level error containers + aria-describedby).
	 *
	 * @param string $intent Signup intent: 'buyer' or 'vendor'. Affects the
	 *                       hidden form field + the heading.
	 * @return void
	 */
	public function render_form( string $intent = 'vendor' ): void {
		$intent    = in_array( $intent, array( 'buyer', 'vendor' ), true ) ? $intent : 'vendor';
		$nonce     = wp_create_nonce( 'wpss_public_signup' );
		$ajax_url  = admin_url( 'admin-ajax.php' );
		$login_url = wp_login_url( get_permalink() );
		?>
		<form class="wpss-signup-form" data-wpss-signup-form data-intent="<?php echo esc_attr( $intent ); ?>">
			<?php /* Form-level error summary populated by WpssFormError.summary(). */ ?>
			<div class="wpss-form-error-summary" hidden>
				<p class="wpss-form-error-summary__title"><?php esc_html_e( 'Please fix the following:', 'wp-sell-services' ); ?></p>
				<ul class="wpss-form-error-summary__list"></ul>
			</div>

			<div class="wpss-form-group">
				<label class="wpss-form-label" for="wpss-signup-display-name">
					<?php esc_html_e( 'Your name', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<input type="text"
					id="wpss-signup-display-name"
					name="display_name"
					class="wpss-form-input"
					autocomplete="name"
					aria-describedby="wpss-signup-display-name-error"
					required>
				<p id="wpss-signup-display-name-error" class="wpss-form-error" hidden></p>
			</div>

			<div class="wpss-form-group">
				<label class="wpss-form-label" for="wpss-signup-email">
					<?php esc_html_e( 'Email', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<input type="email"
					id="wpss-signup-email"
					name="email"
					class="wpss-form-input"
					autocomplete="email"
					aria-describedby="wpss-signup-email-error"
					required>
				<p id="wpss-signup-email-error" class="wpss-form-error" hidden></p>
			</div>

			<div class="wpss-form-group">
				<label class="wpss-form-label" for="wpss-signup-password">
					<?php esc_html_e( 'Password', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<input type="password"
					id="wpss-signup-password"
					name="password"
					class="wpss-form-input"
					autocomplete="new-password"
					aria-describedby="wpss-signup-password-hint wpss-signup-password-error"
					minlength="8"
					required>
				<p id="wpss-signup-password-hint" class="wpss-form-hint">
					<?php esc_html_e( 'At least 8 characters.', 'wp-sell-services' ); ?>
				</p>
				<p id="wpss-signup-password-error" class="wpss-form-error" hidden></p>
			</div>

			<input type="hidden" name="intent" value="<?php echo esc_attr( $intent ); ?>">
			<input type="hidden" name="action" value="wpss_public_signup">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

			<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg wpss-signup-form__submit">
				<?php
				if ( 'vendor' === $intent ) {
					esc_html_e( 'Create vendor account', 'wp-sell-services' );
				} else {
					esc_html_e( 'Create account', 'wp-sell-services' );
				}
				?>
			</button>

			<p class="wpss-signup-form__signin">
				<?php esc_html_e( 'Already have an account?', 'wp-sell-services' ); ?>
				<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign in', 'wp-sell-services' ); ?></a>
			</p>
		</form>

		<script>
			(function () {
				var form = document.querySelector( '[data-wpss-signup-form]' );
				if ( ! form ) { return; }
				var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
				var submitBtn = form.querySelector( '.wpss-signup-form__submit' );
				var submitLabel = submitBtn.textContent;
				var fieldIds = [ 'wpss-signup-display-name', 'wpss-signup-email', 'wpss-signup-password' ];
				var fieldByName = {
					display_name: 'wpss-signup-display-name',
					email: 'wpss-signup-email',
					password: 'wpss-signup-password'
				};

				form.addEventListener( 'submit', function ( e ) {
					e.preventDefault();

					// Clear stale errors from any previous attempt.
					if ( window.WpssFormError ) {
						fieldIds.forEach( function ( id ) { window.WpssFormError.clear( id ); } );
						window.WpssFormError.summary( form, [] );
					}

					submitBtn.disabled = true;
					submitBtn.textContent = <?php echo wp_json_encode( __( 'Creating your account…', 'wp-sell-services' ) ); ?>;

					var formData = new FormData( form );
					fetch( ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							if ( res && res.success && res.data && res.data.redirect ) {
								window.location.href = res.data.redirect;
								return;
							}

							var msg = ( res && res.data && res.data.message ) || <?php echo wp_json_encode( __( 'Could not create your account. Try again.', 'wp-sell-services' ) ); ?>;
							var fieldName = res && res.data && res.data.field;

							if ( window.WpssFormError ) {
								if ( fieldName && fieldByName[ fieldName ] ) {
									window.WpssFormError.show( fieldByName[ fieldName ], msg );
								}
								window.WpssFormError.summary( form, [ msg ] );
								window.WpssFormError.scrollToFirst( form );
							}

							submitBtn.disabled = false;
							submitBtn.textContent = submitLabel;
						} )
						.catch( function () {
							var fallback = <?php echo wp_json_encode( __( 'Network error. Please try again.', 'wp-sell-services' ) ); ?>;
							if ( window.WpssFormError ) {
								window.WpssFormError.summary( form, [ fallback ] );
							}
							submitBtn.disabled = false;
							submitBtn.textContent = submitLabel;
						} );
				} );
			} )();
		</script>
		<?php
	}
}
