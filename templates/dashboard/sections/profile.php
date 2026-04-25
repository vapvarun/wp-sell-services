<?php
/**
 * Dashboard Section: Profile
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

$user = get_userdata( $user_id );
if ( ! $user ) {
	return;
}
$vendor_profile = $is_vendor ? $vendor_service->get_profile( $user_id ) : null;

/**
 * Fires before the profile dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name  Section identifier ('profile').
 * @param WP_User $current_user Current user object.
 */
do_action( 'wpss_dashboard_section_before', 'profile', $user );
?>

<div class="wpss-section wpss-section--profile">
	<form class="wpss-profile-form" method="post" action="" data-ajax-form="update-profile">
		<?php wp_nonce_field( 'wpss_update_profile', 'wpss_profile_nonce' ); ?>

		<div class="wpss-profile-form__section">
			<h3><?php esc_html_e( 'Basic Information', 'wp-sell-services' ); ?></h3>

			<?php
			// Check user meta first (works for all users), then vendor profile table.
			$avatar_id = (int) get_user_meta( $user_id, '_wpss_avatar_id', true );
			if ( ! $avatar_id && $is_vendor && $vendor_profile ) {
				$avatar_id = (int) ( $vendor_profile->avatar_id ?? 0 );
			}
			$avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : get_avatar_url( $user_id, array( 'size' => 150 ) );
			?>
			<div class="wpss-form-row wpss-avatar-upload">
				<label><?php esc_html_e( 'Profile Photo', 'wp-sell-services' ); ?></label>
				<div class="wpss-avatar-upload__preview">
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php esc_attr_e( 'Profile photo', 'wp-sell-services' ); ?>" class="wpss-avatar-upload__image" id="wpss-avatar-preview" data-gravatar="<?php echo esc_url( get_avatar_url( $user_id, array( 'size' => 150 ) ) ); ?>">
					<input type="hidden" name="avatar_id" id="wpss-avatar-id" value="<?php echo esc_attr( $avatar_id ); ?>">
					<div class="wpss-avatar-upload__actions">
						<button type="button" class="wpss-btn wpss-btn--small wpss-btn--secondary" id="wpss-avatar-upload-btn">
							<?php esc_html_e( 'Upload Photo', 'wp-sell-services' ); ?>
						</button>
						<?php if ( $avatar_id ) : ?>
							<button type="button" class="wpss-btn wpss-btn--small wpss-btn--link" id="wpss-avatar-remove-btn">
								<?php esc_html_e( 'Remove', 'wp-sell-services' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<?php if ( $is_vendor && $vendor_profile ) : ?>
				<?php
				$cover_id  = $vendor_profile->cover_image_id ?? 0;
				$cover_url = $cover_id ? wp_get_attachment_image_url( (int) $cover_id, 'large' ) : '';
				?>
				<div class="wpss-form-row wpss-cover-upload">
					<label><?php esc_html_e( 'Cover Image', 'wp-sell-services' ); ?></label>
					<div class="wpss-cover-upload__preview" style="position:relative;width:100%;max-width:600px;aspect-ratio:3/1;border:2px dashed #ddd;border-radius:8px;overflow:hidden;background:#f9f9f9;">
						<?php if ( $cover_url ) : ?>
							<img src="<?php echo esc_url( $cover_url ); ?>" alt="<?php esc_attr_e( 'Cover image', 'wp-sell-services' ); ?>" id="wpss-cover-preview" style="width:100%;height:100%;object-fit:cover;">
						<?php else : ?>
							<div id="wpss-cover-placeholder" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;color:#999;font-size:14px;">
								<?php esc_html_e( 'No cover image set', 'wp-sell-services' ); ?>
							</div>
							<img src="" alt="<?php esc_attr_e( 'Cover image', 'wp-sell-services' ); ?>" id="wpss-cover-preview" style="width:100%;height:100%;object-fit:cover;display:none;">
						<?php endif; ?>
						<input type="hidden" name="cover_id" id="wpss-cover-id" value="<?php echo esc_attr( $cover_id ); ?>">
					</div>
					<div class="wpss-cover-upload__actions" style="margin-top:8px;">
						<button type="button" class="wpss-btn wpss-btn--small wpss-btn--secondary" id="wpss-cover-upload-btn">
							<?php esc_html_e( 'Upload Cover', 'wp-sell-services' ); ?>
						</button>
						<?php if ( $cover_id ) : ?>
							<button type="button" class="wpss-btn wpss-btn--small wpss-btn--link" id="wpss-cover-remove-btn">
								<?php esc_html_e( 'Remove', 'wp-sell-services' ); ?>
							</button>
						<?php endif; ?>
					</div>
					<p class="wpss-form-hint"><?php esc_html_e( 'Recommended: 1200x400px. Displayed on your public profile.', 'wp-sell-services' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wpss-form-row">
				<label for="display_name"><?php esc_html_e( 'Display Name', 'wp-sell-services' ); ?></label>
				<input type="text" id="display_name" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" class="wpss-input" required>
			</div>

			<div class="wpss-form-row">
				<label for="email"><?php esc_html_e( 'Email Address', 'wp-sell-services' ); ?></label>
				<input type="email" id="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" class="wpss-input" readonly>
				<p class="wpss-form-hint"><?php esc_html_e( 'Email cannot be changed here.', 'wp-sell-services' ); ?></p>
			</div>
		</div>

		<?php if ( $is_vendor && $vendor_profile ) : ?>
			<div class="wpss-profile-form__section">
				<h3><?php esc_html_e( 'Seller Profile', 'wp-sell-services' ); ?></h3>

				<div class="wpss-form-row">
					<label for="tagline"><?php esc_html_e( 'Tagline', 'wp-sell-services' ); ?></label>
					<input type="text" id="tagline" name="tagline" value="<?php echo esc_attr( $vendor_profile->tagline ?? '' ); ?>" class="wpss-input" placeholder="<?php esc_attr_e( 'e.g., Professional Logo Designer', 'wp-sell-services' ); ?>">
				</div>

				<div class="wpss-form-row">
					<label for="bio"><?php esc_html_e( 'Bio', 'wp-sell-services' ); ?></label>
					<textarea id="bio" name="bio" rows="4" class="wpss-textarea" placeholder="<?php esc_attr_e( 'Tell buyers about yourself and your expertise...', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $vendor_profile->bio ?? '' ); ?></textarea>
				</div>

				<div class="wpss-form-row wpss-form-row--half">
					<div>
						<label for="country"><?php esc_html_e( 'Country', 'wp-sell-services' ); ?></label>
						<input type="text" id="country" name="country" value="<?php echo esc_attr( $vendor_profile->country ?? '' ); ?>" class="wpss-input">
					</div>
					<div>
						<label for="city"><?php esc_html_e( 'City', 'wp-sell-services' ); ?></label>
						<input type="text" id="city" name="city" value="<?php echo esc_attr( $vendor_profile->city ?? '' ); ?>" class="wpss-input">
					</div>
				</div>

				<div class="wpss-form-row">
					<label for="website"><?php esc_html_e( 'Website', 'wp-sell-services' ); ?></label>
					<input type="url" id="website" name="website" value="<?php echo esc_url( $vendor_profile->website ?? '' ); ?>" class="wpss-input" placeholder="https://">
				</div>

				<div class="wpss-form-row">
					<label for="intro_video_url"><?php esc_html_e( 'Intro Video', 'wp-sell-services' ); ?></label>
					<input type="url" id="intro_video_url" name="intro_video_url" value="<?php echo esc_url( $vendor_profile->intro_video_url ?? '' ); ?>" class="wpss-input" placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/...">
					<p class="wpss-form-hint"><?php esc_html_e( 'Paste a YouTube or Vimeo URL. Shown on your profile and on every service detail page, so buyers can get a quick pitch before ordering.', 'wp-sell-services' ); ?></p>
				</div>
			</div>

			<div class="wpss-profile-form__section">
				<h3><?php esc_html_e( 'Availability', 'wp-sell-services' ); ?></h3>

				<div class="wpss-form-row">
					<label class="wpss-toggle">
						<input type="checkbox" name="vacation_mode" value="1" <?php checked( ! empty( $vendor_profile->vacation_mode ) ); ?>>
						<span class="wpss-toggle__label"><?php esc_html_e( 'Vacation Mode', 'wp-sell-services' ); ?></span>
					</label>
					<p class="wpss-form-hint"><?php esc_html_e( 'When enabled, your services will be hidden from search and buyers cannot place new orders.', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-form-row">
					<label for="vacation_message"><?php esc_html_e( 'Vacation Message (optional)', 'wp-sell-services' ); ?></label>
					<textarea id="vacation_message" name="vacation_message" rows="3" class="wpss-textarea" placeholder="<?php esc_attr_e( 'e.g., Out of office until March 15. Orders will resume then.', 'wp-sell-services' ); ?>"><?php echo esc_textarea( $vendor_profile->vacation_message ?? '' ); ?></textarea>
					<p class="wpss-form-hint"><?php esc_html_e( 'Shown to buyers on your profile and services while Vacation Mode is on. Leave empty for the default notice.', 'wp-sell-services' ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<div class="wpss-profile-form__actions">
			<?php
			/**
			 * Fires in the profile form before the submit button.
			 *
			 * Allows developers to add custom fields to the profile form.
			 *
			 * @since 1.1.0
			 *
			 * @param int $user_id Current user ID.
			 */
			do_action( 'wpss_profile_form_fields', $user_id );
			?>

			<button type="submit" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Save Changes', 'wp-sell-services' ); ?>
			</button>
		</div>
	</form>

	<?php
	// VS11 (plans/ORDER-FLOW-AUDIT.md): per-vendor email preferences. Lets each
	// vendor mute specific notification categories without affecting others on
	// the platform. Stored in user meta wpss_email_preferences as a key=>bool
	// array. Missing key OR true = receive; explicit false = mute.
	$user_prefs = get_user_meta( $user_id, 'wpss_email_preferences', true );
	if ( ! is_array( $user_prefs ) ) {
		$user_prefs = array();
	}
	$pref_categories = array(
		'orders'      => array(
			'label' => __( 'New orders', 'wp-sell-services' ),
			'desc'  => __( 'When a buyer places a new order on one of your services.', 'wp-sell-services' ),
		),
		'messages'    => array(
			'label' => __( 'New messages', 'wp-sell-services' ),
			'desc'  => __( 'When a buyer sends a message on an active order.', 'wp-sell-services' ),
		),
		'completion'  => array(
			'label' => __( 'Order completion + reviews', 'wp-sell-services' ),
			'desc'  => __( 'When a buyer approves a delivery or leaves a review.', 'wp-sell-services' ),
		),
		'cancellation'=> array(
			'label' => __( 'Cancellation requests', 'wp-sell-services' ),
			'desc'  => __( 'When a buyer requests to cancel an active order.', 'wp-sell-services' ),
		),
		'disputes'    => array(
			'label' => __( 'Disputes', 'wp-sell-services' ),
			'desc'  => __( 'Always recommended — disputes need a quick response to avoid escalation.', 'wp-sell-services' ),
		),
		'tips'        => array(
			'label' => __( 'Tips received', 'wp-sell-services' ),
			'desc'  => __( 'When a buyer sends you a tip on a completed order.', 'wp-sell-services' ),
		),
		'withdrawals' => array(
			'label' => __( 'Withdrawal status updates', 'wp-sell-services' ),
			'desc'  => __( 'When your withdrawal request is approved, paid out, or rejected.', 'wp-sell-services' ),
		),
		'proposals'   => array(
			'label' => __( 'Proposals + milestones + extensions', 'wp-sell-services' ),
			'desc'  => __( 'When a proposal is accepted or a milestone / extension is paid.', 'wp-sell-services' ),
		),
	);
	?>
	<div class="wpss-email-preferences" id="wpss-email-preferences">
		<h3><?php esc_html_e( 'Email preferences', 'wp-sell-services' ); ?></h3>
		<p class="wpss-email-preferences__intro">
			<?php esc_html_e( 'Choose which emails you want to receive. In-app notifications still appear regardless of these settings.', 'wp-sell-services' ); ?>
		</p>

		<form id="wpss-email-prefs-form">
			<?php wp_nonce_field( 'wpss_save_email_prefs', 'wpss_email_prefs_nonce' ); ?>

			<div class="wpss-email-pref-list">
				<?php foreach ( $pref_categories as $key => $category ) : ?>
					<?php $is_enabled = ! array_key_exists( $key, $user_prefs ) || ! empty( $user_prefs[ $key ] ); ?>
					<label class="wpss-email-pref">
						<input type="checkbox" name="prefs[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $is_enabled ); ?>>
						<span class="wpss-email-pref__body">
							<strong><?php echo esc_html( $category['label'] ); ?></strong>
							<small><?php echo esc_html( $category['desc'] ); ?></small>
						</span>
					</label>
				<?php endforeach; ?>
			</div>

			<button type="submit" class="wpss-btn wpss-btn--primary wpss-email-prefs__save">
				<?php esc_html_e( 'Save email preferences', 'wp-sell-services' ); ?>
			</button>
			<span class="wpss-email-prefs__status" data-wpss-prefs-status aria-live="polite"></span>
		</form>
	</div>

	<script>
		(function () {
			var form = document.getElementById( 'wpss-email-prefs-form' );
			if ( ! form ) { return; }
			var statusEl = form.querySelector( '[data-wpss-prefs-status]' );
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				statusEl.textContent = <?php echo wp_json_encode( __( 'Saving…', 'wp-sell-services' ) ); ?>;
				statusEl.style.color = '#6b7280';

				var fd = new FormData( form );
				fd.append( 'action', 'wpss_save_email_preferences' );

				fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
					method: 'POST',
					body: fd,
					credentials: 'same-origin'
				} ).then( function ( r ) { return r.json(); } )
				  .then( function ( res ) {
					if ( res && res.success ) {
						statusEl.textContent = <?php echo wp_json_encode( __( '✓ Preferences saved', 'wp-sell-services' ) ); ?>;
						statusEl.style.color = '#16a34a';
						window.setTimeout( function () { statusEl.textContent = ''; }, 3000 );
					} else {
						statusEl.textContent = ( res && res.data && res.data.message ) || <?php echo wp_json_encode( __( 'Could not save. Try again.', 'wp-sell-services' ) ); ?>;
						statusEl.style.color = '#dc2626';
					}
				} ).catch( function () {
					statusEl.textContent = <?php echo wp_json_encode( __( 'Network error. Try again.', 'wp-sell-services' ) ); ?>;
					statusEl.style.color = '#dc2626';
				} );
			} );
		})();
	</script>
</div>

<?php
/**
 * Fires after the profile dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name  Section identifier ('profile').
 * @param WP_User $current_user Current user object.
 */
do_action( 'wpss_dashboard_section_after', 'profile', $user_id );
?>
