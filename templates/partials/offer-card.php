<?php
/**
 * A private offer inside a conversation thread.
 *
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/partials/offer-card.php
 *
 * @package WPSellServices\Templates
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var array<string,mixed> $offer   The offer, from wpss_private_offer_for_client().
 * @var int                 $user_id Viewer.
 */

defined( 'ABSPATH' ) || exit;

$wpss_offer_states = array(
	'accepted'  => __( 'Accepted', 'wp-sell-services' ),
	'declined'  => __( 'Declined', 'wp-sell-services' ),
	'withdrawn' => __( 'Withdrawn', 'wp-sell-services' ),
	'expired'   => __( 'Expired', 'wp-sell-services' ),
	'pending'   => __( 'Waiting for an answer', 'wp-sell-services' ),
);
?>
<div class="wpss-offer-card wpss-offer-card--<?php echo esc_attr( $offer['state'] ); ?>" data-offer-id="<?php echo esc_attr( (string) $offer['id'] ); ?>">
	<p class="wpss-offer-card__label">
		<?php esc_html_e( 'Private offer', 'wp-sell-services' ); ?>
		<span class="<?php echo esc_attr( wpss_status_class( $offer['state'] ) ); ?>"><?php echo esc_html( $wpss_offer_states[ $offer['state'] ] ?? '' ); ?></span>
	</p>
	<h4 class="wpss-offer-card__title"><?php echo esc_html( $offer['title'] ); ?></h4>
	<p class="wpss-offer-card__description"><?php echo wp_kses_post( nl2br( esc_html( $offer['description'] ) ) ); ?></p>
	<dl class="wpss-offer-card__terms">
		<div>
			<dt><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></dt>
			<dd><?php echo wp_kses_post( wpss_format_price( (float) $offer['price'] ) ); ?></dd>
		</div>
		<div>
			<dt><?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?></dt>
			<?php /* translators: %d: number of days. */ ?>
			<dd><?php echo esc_html( sprintf( _n( '%d day', '%d days', (int) $offer['delivery_days'], 'wp-sell-services' ), (int) $offer['delivery_days'] ) ); ?></dd>
		</div>
		<div>
			<dt><?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?></dt>
			<dd><?php echo esc_html( number_format_i18n( (int) $offer['revisions'] ) ); ?></dd>
		</div>
		<?php if ( 'pending' === $offer['state'] && $offer['expires_at'] ) : ?>
			<div class="wpss-offer-card__term--wide">
				<dt><?php esc_html_e( 'Valid until', 'wp-sell-services' ); ?></dt>
				<dd><?php echo esc_html( wpss_date_label( (string) $offer['expires_at'] ) ); ?></dd>
			</div>
		<?php endif; ?>
	</dl>
	<?php if ( $offer['can_respond'] ) : ?>
		<div class="wpss-offer-card__actions">
			<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--sm" data-wpss-offer="accept" data-route="<?php echo esc_attr( $offer['accept_route'] ); ?>"><?php esc_html_e( 'Accept and pay', 'wp-sell-services' ); ?></button>
			<button type="button" class="wpss-btn wpss-btn--outline wpss-btn--sm" data-wpss-offer="decline" data-route="<?php echo esc_attr( $offer['decline_route'] ); ?>"><?php esc_html_e( 'Decline', 'wp-sell-services' ); ?></button>
		</div>
	<?php elseif ( 'pending' === $offer['state'] ) : ?>
		<?php /* translators: %s: buyer's name. */ ?>
		<p class="wpss-offer-card__note"><?php echo esc_html( sprintf( __( 'Sent to %s. They can accept it until it expires.', 'wp-sell-services' ), $offer['buyer']['name'] ?? '' ) ); ?></p>
	<?php elseif ( 'accepted' === $offer['state'] && $offer['order_url'] ) : ?>
		<p class="wpss-offer-card__note"><a href="<?php echo esc_url( $offer['order_url'] ); ?>"><?php esc_html_e( 'View the order', 'wp-sell-services' ); ?></a></p>
	<?php endif; ?>
</div>
