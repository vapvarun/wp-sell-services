<?php
/**
 * A vendor's level badge: the one renderer for service cards, vendor cards and the profile.
 *
 * Every level shows, New included, the same way on every surface. The service card once showed
 * New only while the profile was incomplete, so one vendor had a badge on the directory and the
 * profile and none on their own services.
 *
 * Expects:
 * - $wpss_badge_profile VendorProfile The vendor.
 * - $wpss_badge_size    string        '' (card), 'md' or 'lg'. Optional.
 * - $wpss_badge_full    bool          Long next-level note in the tooltip (profile page). Optional.
 *
 * @package WPSellServices
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_badge_profile ) || ! array_key_exists( (string) $wpss_badge_profile->tier, \WPSellServices\Models\VendorProfile::get_tiers() ) ) {
	return;
}

$wpss_badge_tier = (string) $wpss_badge_profile->tier;
$wpss_badge_size = isset( $wpss_badge_size ) ? (string) $wpss_badge_size : '';
// Colour comes from the .wpss-seller-badge--{tier} modifier in frontend.css (token-driven).
?>
<span class="wpss-seller-badge<?php echo $wpss_badge_size ? ' wpss-seller-badge--' . esc_attr( $wpss_badge_size ) : ''; ?> wpss-seller-badge--<?php echo esc_attr( $wpss_badge_tier ); ?>" title="<?php echo esc_attr( wpss_seller_level_note( (int) $wpss_badge_profile->user_id, $wpss_badge_tier, ! empty( $wpss_badge_full ) ) ); ?>">
	<?php if ( \WPSellServices\Models\VendorProfile::TIER_PRO === $wpss_badge_tier ) : ?>
		<i data-lucide="badge-check" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
	<?php endif; ?>
	<?php echo esc_html( $wpss_badge_profile->get_tier_label() ); ?>
</span>
