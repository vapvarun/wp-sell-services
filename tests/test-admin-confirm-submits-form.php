<?php
/**
 * Admin confirm dialogs submit the form through the real method.
 *
 * Run: wp eval-file tests/test-admin-confirm-submits-form.php
 *
 * WordPress submit_button() renders an input named "submit", which shadows form.submit() on the form
 * (it becomes the input, so calling it throws). Every confirm handler must call
 * HTMLFormElement.prototype.submit instead; a Confirm that does nothing leaves Completed, Cancelled
 * and Mark refund sent dead.
 *
 * @package WPSellServices
 */

$fails = 0;
foreach ( glob( dirname( __DIR__ ) . '/assets/js/admin*.js' ) as $file ) {
	if ( str_ends_with( $file, '.min.js' ) ) {
		continue;
	}
	$bad = preg_match_all( '/\bform\.submit\(\)/', (string) file_get_contents( $file ) );
	echo ( $bad ? 'FAIL  ' : 'PASS  ' ) . basename( $file ) . ' has no form.submit()' . "\n";
	$fails += $bad ? 1 : 0;
}
echo $fails ? "{$fails} FAILED\n" : "All passed\n";
