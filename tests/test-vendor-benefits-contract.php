<?php
/**
 * Become-a-Vendor copy contract.
 *
 * The page promised "Build unlimited service listings" while Free caps a
 * vendor at 20 by default, and promised an "Analytics dashboard" that is a Pro
 * feature. Members registered expecting both and met a wall (Basecamp
 * 10235849910).
 *
 * The listings claim is now generated from the configured cap rather than
 * asserted, so it cannot go stale when an owner changes the setting. And the
 * bullets live in ONE partial - they used to be written out twice, in the
 * logged-out and signed-in branches, so the page could tell two different
 * stories depending on who was reading it.
 *
 * Run: wp eval-file tests/test-vendor-benefits-contract.php
 *
 * @package WPSellServices
 */

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

/**
 * Render the shared partial at a given cap.
 *
 * @param int|null $cap Cap to configure, null to remove the key entirely.
 * @return string Rendered markup.
 */
function wpss_render_benefits( $cap ) {
	$settings = get_option( 'wpss_vendor', array() );

	if ( null === $cap ) {
		unset( $settings['max_services_per_vendor'] );
	} else {
		$settings['max_services_per_vendor'] = $cap;
	}

	update_option( 'wpss_vendor', $settings );

	ob_start();
	require WPSS_PLUGIN_DIR . 'templates/partials/vendor-benefits.php';
	return (string) ob_get_clean();
}

echo "\nBecome-a-Vendor copy contract\n\n";

$saved = get_option( 'wpss_vendor', array() );

// 1. The claim tracks the configured cap, in all three shapes.
$out = wpss_render_benefits( 20 );
wpss_t( false !== strpos( $out, 'Publish up to 20 service listings' ), 'a cap of 20 is stated as 20' );
wpss_t( false === strpos( $out, 'unlimited' ), 'a capped install never says unlimited' );

$out = wpss_render_benefits( 1 );
wpss_t( false !== strpos( $out, 'Publish up to 1 service listing with' ), 'a cap of 1 is singular, not "1 listings"' );

$out = wpss_render_benefits( 0 );
wpss_t( false !== strpos( $out, 'unlimited service listings' ), 'a cap of 0 genuinely is unlimited' );

// An install that never set the key states the seeded default - the same
// number the Settings screen renders and VendorProfile enforces (both read
// wpss_settings_defaults() since 1.7.1). Saying "unlimited" here while the
// vendor is walled at 20 is the original defect all over again.
$wpss_default_cap = absint( wpss_settings_defaults()['wpss_vendor']['max_services_per_vendor'] );
$out              = wpss_render_benefits( null );
wpss_t(
	$wpss_default_cap > 0 && false !== strpos( $out, 'Publish up to ' . number_format_i18n( $wpss_default_cap ) . ' service listings' ),
	'an unset cap states the seeded default, the same cap enforcement uses'
);

update_option( 'wpss_vendor', $saved );

// 2. No Pro promise on a Free page.
$partial = file_get_contents( WPSS_PLUGIN_DIR . 'templates/partials/vendor-benefits.php' );
wpss_t(
	false === strpos( $partial, 'Analytics dashboard to track performance and revenue' ),
	'the Pro analytics dashboard is no longer promised on the Free page'
);

// 3. One copy of the bullets, not two. This is what made the original defect
//    survivable - a correction in one branch left the other lying.
$shortcodes = file_get_contents( WPSS_PLUGIN_DIR . 'src/Frontend/Shortcodes.php' );
wpss_t(
	2 === substr_count( $shortcodes, "templates/partials/vendor-benefits.php" ),
	'both branches require the shared partial'
);
// Matched on the MARKUP, not the class name: Shortcodes.php still carries an
// inline <style> block that styles .wpss-vr__feature-icon, and a crude
// class-name search flags that stylesheet as a duplicate bullet.
wpss_t(
	false === strpos( $shortcodes, "<strong><?php esc_html_e( 'Create Services'" ),
	'no branch still carries its own inline copy of the bullets'
);

// 4. Pitch stats. Every number on the hero has to be one this marketplace can
//    stand behind - a thin site advertising "2 sellers already here" argues
//    against itself, and the commission line must read the real setting rather
//    than a number someone typed into copy.
$saved_commission = get_option( 'wpss_commission', array() );

$stats  = wpss_get_vendor_pitch_stats();
$labels = wp_list_pluck( $stats, 'label' );
wpss_t(
	in_array( 'of every order is yours', $labels, true ),
	'the commission-kept line is always shown, whatever the counts are'
);

update_option( 'wpss_commission', array( 'commission_rate' => 25 ) );
$kept = '';
foreach ( wpss_get_vendor_pitch_stats() as $stat ) {
	if ( 'of every order is yours' === $stat['label'] ) {
		$kept = $stat['value'];
	}
}
wpss_t( '75%' === $kept, 'a 25% commission advertises 75% kept, not a hardcoded 90%' );

update_option( 'wpss_commission', $saved_commission );

// A count under the threshold is dropped, not printed. Asserted against the
// source, because the alternative is deleting real vendor rows to test copy.
$vendors_src = file_get_contents( WPSS_PLUGIN_DIR . 'src/functions/vendors.php' );
wpss_t(
	false !== strpos( $vendors_src, '$vendors >= 5' ) && false !== strpos( $vendors_src, '$services >= 5' ),
	'counts below 5 are dropped rather than advertised as an empty room'
);

// 5. Both branches render the same pitch layout. The whole point of the
//    redesign is that a visitor and a signed-in buyer see one page, not two.
wpss_t(
	2 === substr_count( $shortcodes, '$this->render_vendor_pitch_hero();' )
		&& 2 === substr_count( $shortcodes, '$this->render_vendor_pitch_steps();' ),
	'both branches render the hero and the steps'
);
wpss_t(
	2 === substr_count( $shortcodes, 'class="wpss-vr wpss-vr--pitch"' ),
	'both branches opt into the wide pitch layout'
);

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
