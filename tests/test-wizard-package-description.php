<?php
/**
 * The frontend wizard lets a vendor write a description for each package tier, and it survives the
 * save and edit round trip (Basecamp 10244588840).
 *
 * Run: wp eval-file tests/test-wizard-package-description.php
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\ServiceWizard;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$wizard = ( new ReflectionClass( ServiceWizard::class ) )->newInstanceWithoutConstructor();
$call   = static function ( string $method, ...$args ) use ( $wizard ) {
	$m = new ReflectionMethod( ServiceWizard::class, $method );
	$m->setAccessible( true );

	return $m->invoke( $wizard, ...$args );
};

ob_start();
$call( 'render_step_pricing', null );
$html = (string) ob_get_clean();

foreach ( array( 'basic', 'standard', 'premium' ) as $tier ) {
	$check( "the {$tier} tier has a description field bound to its data", false !== strpos( $html, 'x-model="data.packages.' . $tier . '.description"' ) && false !== strpos( $html, 'id="wpss-pkg-' . $tier . '-description"' ) );
}
$check( 'every description field has a label', 3 === preg_match_all( '/<label[^>]*for="wpss-pkg-[a-z]+-description"/', $html ) );

// Round trip: what the vendor typed is saved, then read back into the edit form.
$saved = $call(
	'sanitize_packages',
	array( 'basic' => array( 'name' => 'Basic', 'description' => "Three logo concepts\n<b>x</b>", 'price' => 20, 'delivery_time' => 3, 'revisions' => 1 ) )
);
$check( 'the description is kept by the save sanitiser (text only)', "Three logo concepts\nx" === $saved['basic']['description'] );

$normalized = $call( 'normalize_packages', array( array( 'name' => 'Basic', 'description' => 'Three logo concepts', 'price' => 20, 'delivery_days' => 3, 'revisions' => 1 ) ) );
$check( 'the description comes back into the edit form', 'Three logo concepts' === $normalized['basic']['description'] );

$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/service-wizard.js' );
$check( 'the wizard data seeds an empty description for all three tiers', 3 === preg_match_all( "/name: '(Basic|Standard|Premium)',\s*description: ''/", $js ) );

// The single-service page already shows a description under the tier.
$tpl = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/partials/service-packages.php' );
$check( 'the service page prints the tier description', false !== strpos( $tpl, "package['description']" ) );

exit( $fails ? 1 : 0 );
