<?php
/**
 * Why a member cannot apply to sell: application not approved, or account suspended.
 *
 * Rendered by wpss_vendor_closed_notice() in place of the "Start selling" form.
 *
 * This template can be overridden by copying it to:
 * yourtheme/wp-sell-services/partials/vendor-closed-notice.php
 *
 * @package WPSellServices\Templates
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var string $wpss_status 'rejected' or 'suspended'.
 * @var string $wpss_reason Reason the admin gave, or ''.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wpss-vendor-closed wpss-notice wpss-notice--warning" role="status">
	<p class="wpss-vendor-closed__title">
		<strong>
			<?php
			if ( 'rejected' === $wpss_status ) {
				esc_html_e( 'Your vendor application was not approved.', 'wp-sell-services' );
			} else {
				esc_html_e( 'Your vendor account is suspended.', 'wp-sell-services' );
			}
			?>
		</strong>
	</p>
	<?php if ( '' !== $wpss_reason ) : ?>
		<p class="wpss-vendor-closed__reason">
			<?php
			/* translators: %s: reason the admin gave */
			echo esc_html( sprintf( __( 'Reason: %s', 'wp-sell-services' ), $wpss_reason ) );
			?>
		</p>
	<?php endif; ?>
	<p class="wpss-vendor-closed__help"><?php esc_html_e( 'If you have questions, please contact us.', 'wp-sell-services' ); ?></p>
</div>
