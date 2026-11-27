<?php
/**
 * Order view: a digital product's download.
 *
 * Shown on a digital order (wpss_get_order_kind()) to whoever may download the file: the buyer while the
 * order stands, the seller and an administrator. The link always serves the latest version.
 *
 * Override in your theme: wp-sell-services/order/download.php
 *
 * @package WPSellServices
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var \WPSellServices\Models\ServiceOrder $wpss_order The order.
 */

defined( 'ABSPATH' ) || exit;

// The buyer's box: the seller has the order's deliveries and the product's own edit screen.
if ( empty( $wpss_order ) || 'digital' !== wpss_get_order_kind( $wpss_order ) || (int) $wpss_order->customer_id !== get_current_user_id() ) {
	return;
}

$wpss_service_id = (int) $wpss_order->service_id;
$wpss_file       = wpss_can_read_file( 'service', $wpss_service_id ) ? wpss_product_file_for_client( $wpss_service_id ) : null;
?>
<section class="wpss-order-section wpss-order-download" aria-labelledby="wpss-order-download-title">
	<h3 id="wpss-order-download-title" class="wpss-order-download__title"><?php esc_html_e( 'Your download', 'wp-sell-services' ); ?></h3>

	<?php if ( $wpss_file ) : ?>
		<div class="wpss-order-download__row">
			<p class="wpss-order-download__file">
				<i data-lucide="file-down" class="wpss-icon" aria-hidden="true"></i>
				<span>
					<strong><?php echo esc_html( $wpss_file['name'] ); ?></strong>
					<span class="wpss-order-download__meta">
						<?php
						printf(
							/* translators: 1: file size, e.g. 2 MB, 2: version number. */
							esc_html__( '%1$s · version %2$d', 'wp-sell-services' ),
							esc_html( size_format( $wpss_file['size'] ) ),
							(int) $wpss_file['version']
						);
						?>
					</span>
				</span>
			</p>
			<a class="wpss-btn wpss-btn--primary" href="<?php echo esc_url( $wpss_file['url'] ); ?>">
				<i data-lucide="download" class="wpss-icon" aria-hidden="true"></i>
				<?php esc_html_e( 'Download', 'wp-sell-services' ); ?>
			</a>
		</div>
		<p class="wpss-order-download__note"><?php esc_html_e( 'You always get the latest version the seller uploads.', 'wp-sell-services' ); ?></p>
	<?php else : ?>
		<p class="wpss-order-download__note"><?php esc_html_e( 'This download is no longer available for this order.', 'wp-sell-services' ); ?></p>
	<?php endif; ?>
</section>
