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
				wp_send_json_error(
					array( 'message' => __( 'Could not generate a unique username. Try a different email.', 'wp-sell-services' ) ),
					500
				);
			}
		}

		$user_id = wp_insert_user(
			array(
				'user_login'           => $user_login,
				'user_email'           => $email,
				'user_pass'            => $password,
				'display_name'         => $display_name,
				'first_name'           => $display_name,
				'role'                 => 'subscriber',
				// `show_admin_bar_front` is stored as a string ('true'/'false') in
				// usermeta — wp_insert_user passes the value through unchanged so
				// we set the canonical string form here.
				'show_admin_bar_front' => 'false',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error(
				array( 'message' => $user_id->get_error_message() ),
				500
			);
		}

		// Sign the user in immediately. wp_set_auth_cookie sets the cookies on
		// the response; the next page load (the redirect) will see them logged
		// in.
		wp_set_current_user( (int) $user_id, $user_login );
		wp_set_auth_cookie( (int) $user_id, true );

		/**
		 * Fires after a user signs up via the public signup form.
		 *
		 * Pro plugin or other extensions can hook in to send welcome emails,
		 * track signup analytics, or run any post-signup logic.
		 *
		 * @since 1.1.0
		 *
		 * @param int    $user_id  The newly-created user ID.
		 * @param string $intent   Signup intent: 'buyer' or 'vendor'.
		 */
		do_action( 'wpss_public_signup_complete', (int) $user_id, $intent );

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
