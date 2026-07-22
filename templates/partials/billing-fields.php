<?php
/**
 * Billing details block.
 *
 * OWNED BY US, NOT BY THE GATEWAY. Rendered as its own section above the
 * payment block, from wpss_get_billing_fields(), so it is identical whether the
 * buyer pays by Stripe, PayPal, Razorpay or WooCommerce. The gateway is handed
 * these values at confirm time; it never renders or owns them.
 *
 * Stripe's Address Element was used here originally and was wrong: it rendered
 * inside the card iframe, only appeared for Stripe, and has no company or tax
 * number field — so the GST an invoice needs could not be collected at all.
 *
 * Collapses to a one-line summary when the profile is already complete, which
 * is the whole point: a returning buyer enters card details and nothing else.
 *
 * @package WPSellServices
 * @since   1.2.3
 *
 * @var array<string,string> $wpss_billing  Current values (profile or posted).
 * @var bool                 $wpss_complete Whether every required field is set.
 */

defined( 'ABSPATH' ) || exit;

$wpss_billing  = isset( $wpss_billing ) && is_array( $wpss_billing ) ? $wpss_billing : wpss_get_billing_address();
$wpss_complete = isset( $wpss_complete ) ? (bool) $wpss_complete : wpss_is_billing_address_complete( $wpss_billing );
$wpss_fields   = wpss_get_billing_fields();

// One-line summary for the collapsed state.
$wpss_summary_name = trim( ( $wpss_billing['billing_first_name'] ?? '' ) . ' ' . ( $wpss_billing['billing_last_name'] ?? '' ) );
$wpss_summary_addr = array_filter(
	array(
		$wpss_billing['billing_address_1'] ?? '',
		$wpss_billing['billing_city'] ?? '',
		$wpss_billing['billing_state'] ?? '',
		$wpss_billing['billing_postcode'] ?? '',
		$wpss_billing['billing_country'] ?? '',
	)
);
?>
<section class="wpss-billing" data-wpss-billing data-complete="<?php echo $wpss_complete ? '1' : '0'; ?>">
	<div class="wpss-billing__header">
		<h3 class="wpss-billing__title"><?php esc_html_e( 'Billing details', 'wp-sell-services' ); ?></h3>
		<?php if ( $wpss_complete ) : ?>
			<button type="button" class="wpss-btn wpss-btn--link wpss-billing__edit" data-wpss-billing-edit>
				<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $wpss_complete ) : ?>
		<div class="wpss-billing__summary" data-wpss-billing-summary>
			<strong><?php echo esc_html( $wpss_summary_name ); ?></strong>
			<?php if ( ! empty( $wpss_billing['billing_company'] ) ) : ?>
				<span class="wpss-billing__company"><?php echo esc_html( $wpss_billing['billing_company'] ); ?></span>
			<?php endif; ?>
			<span class="wpss-billing__address"><?php echo esc_html( implode( ', ', $wpss_summary_addr ) ); ?></span>
			<?php if ( ! empty( $wpss_billing['billing_gst'] ) ) : ?>
				<span class="wpss-billing__gst">
					<?php
					printf(
						/* translators: %s: tax registration number */
						esc_html__( 'GST / VAT: %s', 'wp-sell-services' ),
						esc_html( $wpss_billing['billing_gst'] )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="wpss-billing__form" data-wpss-billing-form <?php echo $wpss_complete ? 'hidden' : ''; ?>>
		<?php foreach ( $wpss_fields as $wpss_key => $wpss_field ) : ?>
			<?php
			$wpss_value = $wpss_billing[ $wpss_key ] ?? '';
			$wpss_id    = 'wpss-' . str_replace( '_', '-', $wpss_key );
			?>
			<div class="wpss-form-group wpss-billing__field wpss-billing__field--<?php echo esc_attr( $wpss_key ); ?>">
				<label for="<?php echo esc_attr( $wpss_id ); ?>">
					<?php echo esc_html( $wpss_field['label'] ); ?>
					<?php if ( ! empty( $wpss_field['required'] ) ) : ?>
						<span class="wpss-required" aria-hidden="true">*</span>
					<?php endif; ?>
				</label>

				<?php if ( 'country' === $wpss_field['type'] ) : ?>
					<select id="<?php echo esc_attr( $wpss_id ); ?>"
						name="<?php echo esc_attr( $wpss_key ); ?>"
						class="wpss-input"
						autocomplete="<?php echo esc_attr( $wpss_field['autocomplete'] ); ?>"
						<?php echo ! empty( $wpss_field['required'] ) ? 'required' : ''; ?>>
						<option value=""><?php esc_html_e( 'Select a country…', 'wp-sell-services' ); ?></option>
						<?php foreach ( wpss_get_countries() as $wpss_code => $wpss_label ) : ?>
							<option value="<?php echo esc_attr( $wpss_code ); ?>" <?php selected( $wpss_value, $wpss_code ); ?>>
								<?php echo esc_html( $wpss_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="<?php echo esc_attr( $wpss_field['type'] ); ?>"
						id="<?php echo esc_attr( $wpss_id ); ?>"
						name="<?php echo esc_attr( $wpss_key ); ?>"
						class="wpss-input"
						value="<?php echo esc_attr( $wpss_value ); ?>"
						autocomplete="<?php echo esc_attr( $wpss_field['autocomplete'] ); ?>"
						<?php echo ! empty( $wpss_field['required'] ) ? 'required' : ''; ?>>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
