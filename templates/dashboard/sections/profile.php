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

$user           = get_userdata( $user_id );
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
			$avatar_id  = $is_vendor && $vendor_profile ? ( $vendor_profile->avatar_id ?? 0 ) : 0;
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
				$cover_id  = $vendor_profile->cover_id ?? 0;
				$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';
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
