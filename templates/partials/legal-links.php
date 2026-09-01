<?php
/**
 * Terms / Privacy links at checkout.
 *
 * We create neither page: Terms is a mapping to the owner's own page, Privacy
 * is core's. wpss_get_legal_links() returns null for anything unmapped, so a
 * site that has set neither renders nothing at all - no empty link, no nag.
 *
 * @package WPSellServices
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

$wpss_legal = array_filter( wpss_get_legal_links() );

if ( empty( $wpss_legal ) ) {
	return;
}
?>
<p class="wpss-co-legal">
	<?php
	$wpss_labels = array(
		'terms_url'          => __( 'Terms & Conditions', 'wp-sell-services' ),
		'privacy_policy_url' => __( 'Privacy Policy', 'wp-sell-services' ),
	);

	$wpss_links = array();

	foreach ( $wpss_labels as $wpss_key => $wpss_label ) {
		if ( empty( $wpss_legal[ $wpss_key ] ) ) {
			continue;
		}

		$wpss_links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $wpss_legal[ $wpss_key ] ),
			esc_html( $wpss_label )
		);
	}

	$wpss_allowed = array(
		'a' => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
		),
	);

	printf(
		/* translators: %s: Terms and/or Privacy Policy links. */
		esc_html__( 'By placing this order you agree to our %s.', 'wp-sell-services' ),
		wp_kses( implode( __( ' and ', 'wp-sell-services' ), $wpss_links ), $wpss_allowed )
	);
	?>
</p>
