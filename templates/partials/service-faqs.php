<?php
/**
 * Template Partial: Service FAQs
 *
 * Displays the FAQ accordion for a service.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var WPSellServices\Models\Service $service    Service object.
 * @var int                            $service_id Service post ID.
 * @var array                          $faqs       Array of FAQ items.
 */

defined( 'ABSPATH' ) || exit;

$service_id = get_the_ID();
$faqs       = get_post_meta( $service_id, '_wpss_faqs', true ) ?: [];

if ( empty( $faqs ) ) {
	return;
}

/**
 * Fires before the service FAQs section.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_before_service_faqs', $service_id );
?>

<div class="wpss-service-faqs">
	<h2><?php esc_html_e( 'Frequently Asked Questions', 'wp-sell-services' ); ?></h2>

	<div class="wpss-faq-list">
		<?php foreach ( $faqs as $index => $faq ) : ?>
			<?php if ( ! empty( $faq['question'] ) && ! empty( $faq['answer'] ) ) : ?>
				<div class="wpss-faq-item">
					<button type="button"
							class="wpss-faq-question"
							aria-expanded="false"
							aria-controls="wpss-faq-<?php echo esc_attr( $index ); ?>">
						<span><?php echo esc_html( $faq['question'] ); ?></span>
						<span class="wpss-faq-icon" aria-hidden="true"></span>
					</button>
					<div class="wpss-faq-answer"
						 id="wpss-faq-<?php echo esc_attr( $index ); ?>"
						 hidden>
						<?php echo wp_kses_post( wpautop( $faq['answer'] ) ); ?>
					</div>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>

<?php
/**
 * Fires after the service FAQs section.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_after_service_faqs', $service_id );
?>
