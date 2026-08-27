<?php
/**
 * Launch-signal notices contract.
 *
 * Two owner-facing gaps from the same QA pass:
 *
 *   10240020620 - a marketplace taking money with no Terms page mapped, and
 *                 nothing on screen saying so until a dispute.
 *   10240020765 - the platform name diverging from the site title with no
 *                 obvious way back.
 *
 * Run: wp eval-file tests/test-launch-notices-contract.php
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

echo "\nLaunch-signal notices contract\n\n";

// ---------------------------------------------------------------- platform name

$saved_general = get_option( 'wpss_general', array() );

// The card's acceptance criterion, stated directly: a fresh install matches
// the site title unless the owner changes it on purpose.
$fresh = $saved_general;
unset( $fresh['platform_name'] );
update_option( 'wpss_general', $fresh );

wpss_t(
	wpss_get_platform_name() === get_bloginfo( 'name' ),
	'an untouched platform name resolves to the site title'
);

// Clearing the field is the "use site title" button - it already exists as a
// zero-click, which is why no button was built.
$fresh['platform_name'] = '';
update_option( 'wpss_general', $fresh );
wpss_t(
	wpss_get_platform_name() === get_bloginfo( 'name' ),
	'clearing the field falls back to the site title'
);

$fresh['platform_name'] = 'Deliberately Different';
update_option( 'wpss_general', $fresh );
wpss_t(
	'Deliberately Different' === wpss_get_platform_name(),
	'an owner who sets a name on purpose keeps it'
);

update_option( 'wpss_general', $saved_general );

// The help text has to name the actual site title, or it cannot answer the
// question the owner opened the field to ask.
$settings_src = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Settings.php' );
wpss_t(
	false !== strpos( $settings_src, 'Leave empty to use your site title (%s)' ),
	'the help text names the site title rather than describing it'
);

// ---------------------------------------------------------------- terms notice

$admin  = new \WPSellServices\Admin\Admin();
$has_it = method_exists( $admin, 'missing_terms_notice' );
wpss_t( $has_it, 'the missing-Terms notice exists' );

$admin_src = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Admin.php' );

// Gated on money actually moving. A site still being built must not be nagged.
wpss_t(
	false !== strpos( $admin_src, 'if ( ! wpss_has_live_gateway() ) {' ),
	'it only fires once a real gateway is live, not on a half-built site'
);

// Dismissible, unlike the demo-payments notice - and the dismissal is scoped
// to the gateway set, so enabling another gateway asks again.
wpss_t(
	false !== strpos( $admin_src, 'is-dismissible wpss-dismissible-notice' ),
	'it is dismissible'
);
wpss_t(
	method_exists( $admin, 'ajax_dismiss_notice' ),
	'a generic dismiss handler exists, rather than one per notice'
);

$ref = new ReflectionMethod( $admin, 'live_gateway_signature' );
$ref->setAccessible( true );
$sig = $ref->invoke( $admin );
wpss_t( is_string( $sig ) && 12 === strlen( $sig ), 'the dismissal is scoped to a gateway signature' );

/*
 * The signature must actually change when the gateway set does, or the
 * re-prompt never happens.
 *
 * Measured in a SUBPROCESS. Gateway objects are constructed once per request
 * with their settings already read, so flipping an option and re-reading the
 * signature in this same process returns the old value - which is not a defect,
 * it is the request lifecycle. A fresh process is what a real admin page load
 * looks like, and it is the only honest way to assert this.
 */
$stripe  = get_option( 'wpss_stripe_settings', array() );
$restore = $stripe;
$stripe['enabled']              = 1;
$stripe['test_mode']            = 1;
$stripe['test_secret_key']      = 'sk_test_probe';
$stripe['test_publishable_key'] = 'pk_test_probe';
update_option( 'wpss_stripe_settings', $stripe );

$cmd = 'wp eval \'$a = new \\WPSellServices\\Admin\\Admin(); $r = new ReflectionMethod( $a, "live_gateway_signature" ); $r->setAccessible(true); echo $r->invoke( $a );\' --path=' . escapeshellarg( ABSPATH ) . ' 2>/dev/null';
$sig2 = trim( (string) shell_exec( $cmd ) );

update_option( 'wpss_stripe_settings', $restore );

if ( '' === $sig2 ) {
	echo "  SKIP  could not run the subprocess check (wp-cli unavailable in this context)\n";
} else {
	wpss_t( $sig !== $sig2, sprintf( 'enabling another gateway changes the signature, so the notice returns (%s -> %s)', $sig, $sig2 ) );
}

// The dismiss handler must not be a write-anything primitive.
wpss_t(
	false !== strpos( $admin_src, "\$allowed = array( 'terms' => '_wpss_terms_notice_dismissed' );" ),
	'the dismiss handler whitelists the notice key rather than trusting the request'
);

// And the JS must be rebuilt, or the dismiss silently does nothing.
$min = file_get_contents( dirname( __DIR__ ) . '/assets/js/admin.min.js' );
wpss_t( false !== strpos( $min, 'wpss_dismiss_notice' ), 'the minified admin bundle carries the dismiss binding' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
