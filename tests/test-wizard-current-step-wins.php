<?php
/**
 * The wizard's current step always reads as current (Basecamp 10475157221).
 *
 * Run: wp eval-file tests/test-wizard-current-step-wins.php
 *
 * Editing a service opens on step 1 with steps 1-3 already complete, so step 1
 * carries both --active and --completed. The Completed rules came after the
 * Active ones, so the step you were on showed green like every finished step.
 * Green on the finished steps is intended; the current one must win.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

foreach ( array( 'service-wizard.css', 'service-wizard-rtl.css' ) as $sheet ) {
	$css = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/' . $sheet );
	foreach ( array( 'icon', 'label' ) as $part ) {
		$active    = strpos( $css, ".wpss-wizard__progress-item--active .wpss-wizard__progress-{$part}" );
		$completed = strpos( $css, ".wpss-wizard__progress-item--completed .wpss-wizard__progress-{$part}" );
		$check( "{$sheet}: the active {$part} rule comes after the completed one", false !== $active && false !== $completed && $active > $completed );
	}
}

$wizard = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Frontend/ServiceWizard.php' );
$check( 'the current step carries aria-current="step"', false !== strpos( $wizard, ":aria-current=\"currentStep === '<?php echo esc_attr( \$key ); ?>' ? 'step' : false\"" ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
