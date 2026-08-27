<?php
/**
 * Registration page contract.
 *
 * The header Register button used to open wp-login.php?action=register - stock
 * WordPress chrome as a marketplace's first impression. That button belongs to
 * the theme, so the fix is core's `register_url` filter: one hook redirects
 * every Register link on the site, ours and the theme's, with no template edits.
 *
 * The form behind it also needed fixing. [wpss_register] rendered its own
 * username/email/password form whose nonce nothing handled and whose submit
 * nothing bound - it posted the page back to itself and silently did nothing.
 * PublicSignup::render_form( 'buyer' ) is the real one, and its 'buyer' intent
 * had existed since 1.1.0 without a single caller.
 *
 * Run: wp eval-file tests/test-registration-page-contract.php
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

echo "\nRegistration page contract\n\n";

// 1. The page is a first-class member of the one registry every creator reads.
$defs = wpss_get_page_definitions();
wpss_t( isset( $defs['registration'] ), 'registration is in the page registry' );
wpss_t(
	isset( $defs['registration'] ) && '[wpss_register]' === $defs['registration']['shortcode'],
	'it uses the existing [wpss_register] shortcode, not a new one'
);
wpss_t(
	isset( $defs['registration'] ) && empty( $defs['registration']['required'] ),
	'it is optional - an SSO or membership-plugin site is not nagged for it'
);

// 2. The filter redirects Register, and degrades to core when unmapped.
$saved = get_option( 'wpss_pages', array() );
$mapped = ! empty( $saved['registration'] ) ? (int) $saved['registration'] : 0;

if ( $mapped ) {
	wpss_t(
		wp_registration_url() === get_permalink( $mapped ),
		'wp_registration_url() resolves to the mapped page (so the theme button does too)'
	);
	wpss_t(
		false === strpos( wp_registration_url(), 'wp-login.php' ),
		'Register no longer lands on wp-login.php'
	);
}

// Unmapped must leave core alone: a site using SSO keeps its own flow.
$restore = $saved;
unset( $saved['registration'] );
update_option( 'wpss_pages', $saved );
wp_cache_delete( 'wpss_pages', 'options' );
wpss_t(
	false !== strpos( wpss_marketplace_register_url( 'https://sso.example/signup' ), 'sso.example' ),
	'an unmapped site keeps whatever registration URL it already had'
);
update_option( 'wpss_pages', $restore );

// 3. The shortcode renders the form that actually works.
$html = do_shortcode( '[wpss_register]' );
wpss_t( false !== strpos( $html, 'data-wpss-signup-form' ), '[wpss_register] renders PublicSignup::render_form()' );
wpss_t( false !== strpos( $html, 'value="buyer"' ), 'it renders the buyer intent, not vendor' );
wpss_t( false !== strpos( $html, 'wpss_public_signup' ), 'the form posts to a handler that exists' );
wpss_t( false === strpos( $html, 'wpss_register_nonce' ), 'the old dead form is gone' );

// 4. That handler is genuinely reachable logged out - the whole point.
wpss_t( has_action( 'wp_ajax_nopriv_wpss_public_signup' ) !== false, 'wpss_public_signup is registered for logged-out visitors' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
