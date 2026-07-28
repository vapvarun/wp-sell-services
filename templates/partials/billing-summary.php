<?php
/**
 * Billing address as recorded on an order — read-only display.
 *
 * ONE implementation for every surface that shows a billing address: the buyer
 * order view, the admin order screen, and any invoice or export. The editable
 * counterpart is partials/billing-fields.php; keep the two in step.
 *
 * Reads the ORDER SNAPSHOT (wpss_orders.billing_address), never the live
 * profile — an invoice has to show what was billed at the time, and a later
 * profile edit must not rewrite it.
 *
 * @package WPSellServices
 * @since   1.2.3
 *
 * @var ServiceOrder|object $wpss_order The order.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_order ) ) {
	return;
}

// Accepts either shape. ServiceOrder decodes billing_address to an array, but
// admin screens pass a raw $wpdb row where it is still a JSON string — so this
// partial handles both rather than forcing every caller to hydrate a model.
$wpss_raw     = $wpss_order->billing_address ?? null;
$wpss_billing = is_array( $wpss_raw ) ? $wpss_raw : json_decode( (string) $wpss_raw, true );
$wpss_billing = is_array( $wpss_billing ) ? $wpss_billing : array();

// Orders placed before DB_VERSION 1.5.0 have no snapshot. Render nothing rather
// than an empty card, and never fall back to the live profile — that would show
// an address the buyer was not actually billed under.
if ( empty( array_filter( $wpss_billing ) ) ) {
	return;
}

$wpss_name = trim( ( $wpss_billing['billing_first_name'] ?? '' ) . ' ' . ( $wpss_billing['billing_last_name'] ?? '' ) );

// Country through the shared resolver so it matches every other country
// surface, and so a stored ISO code renders as a readable name.
$wpss_country = ! empty( $wpss_billing['billing_country'] )
	? wpss_get_country_name( (string) $wpss_billing['billing_country'] )
	: '';

$wpss_lines = array_filter(
	array(
		$wpss_billing['billing_address_1'] ?? '',
		$wpss_billing['billing_address_2'] ?? '',
		trim( ( $wpss_billing['billing_city'] ?? '' ) . ' ' . ( $wpss_billing['billing_postcode'] ?? '' ) ),
		$wpss_billing['billing_state'] ?? '',
		$wpss_country,
	)
);
?>
<div class="wpss-billing-summary">
	<h3 class="wpss-billing-summary__title"><?php esc_html_e( 'Billed to', 'wp-sell-services' ); ?></h3>

	<address class="wpss-billing-summary__address">
		<?php if ( $wpss_name ) : ?>
			<strong class="wpss-billing-summary__name"><?php echo esc_html( $wpss_name ); ?></strong>
		<?php endif; ?>

		<?php if ( ! empty( $wpss_billing['billing_company'] ) ) : ?>
			<span class="wpss-billing-summary__company"><?php echo esc_html( $wpss_billing['billing_company'] ); ?></span>
		<?php endif; ?>

		<?php foreach ( $wpss_lines as $wpss_line ) : ?>
			<span class="wpss-billing-summary__line"><?php echo esc_html( $wpss_line ); ?></span>
		<?php endforeach; ?>

		<?php if ( ! empty( $wpss_billing['billing_email'] ) ) : ?>
			<span class="wpss-billing-summary__contact"><?php echo esc_html( $wpss_billing['billing_email'] ); ?></span>
		<?php endif; ?>

		<?php if ( ! empty( $wpss_billing['billing_phone'] ) ) : ?>
			<span class="wpss-billing-summary__contact"><?php echo esc_html( $wpss_billing['billing_phone'] ); ?></span>
		<?php endif; ?>
	</address>

	<?php if ( ! empty( $wpss_billing['billing_gst'] ) ) : ?>
		<?php
		// The reason the field exists: a B2B buyer needs their registration
		// number on the invoice to claim input credit, so it gets its own line
		// rather than being buried in the address block.
		?>
		<p class="wpss-billing-summary__gst">
			<span class="wpss-billing-summary__gst-label"><?php esc_html_e( 'GST / VAT number', 'wp-sell-services' ); ?></span>
			<span class="wpss-billing-summary__gst-value"><?php echo esc_html( $wpss_billing['billing_gst'] ); ?></span>
		</p>
	<?php endif; ?>
</div>
