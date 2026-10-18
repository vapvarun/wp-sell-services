<?php
/**
 * One confirm/prompt dialog: WPSS.showConfirm/showPrompt wrap window.wpssConfirm (wpss-ui.js),
 * which owns role=dialog, focus trap, Esc and the required-field mode. frontend.js must not
 * build its own dialog, and must load after wpss-ui.js.
 *
 * Run: wp eval-file tests/test-confirm-single-dialog.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$root     = dirname( __DIR__ );
$frontend = (string) file_get_contents( $root . '/assets/js/frontend.js' );
$ui       = (string) file_get_contents( $root . '/assets/js/wpss-ui.js' );
$php      = (string) file_get_contents( $root . '/src/Frontend/Frontend.php' );

$check( 'frontend.js has no own confirm/prompt modal markup', ! preg_match( '/wpss-(confirm|prompt)-modal/', $frontend ) );
$check( 'showConfirm delegates to wpssConfirm', (bool) preg_match( '/showConfirm[\s\S]{0,1500}window\.wpssConfirm/', $frontend ) );
$check( 'showPrompt delegates to wpssConfirm', (bool) preg_match( '/showPrompt[\s\S]{0,1500}window\.wpssConfirm/', $frontend ) );
$check( 'wpssConfirm supports a required prompt field', false !== strpos( $ui, 'requiredMessage' ) && false !== strpos( $ui, 'aria-invalid' ) );
$check( 'frontend.js depends on the UI script handle', (bool) preg_match( '/frontend\.js[\s\S]{0,400}HANDLE_UI/', $php ) || (bool) preg_match( '/HANDLE_UI[\s\S]{0,300}frontend/', $php ) );

exit( $fails ? 1 : 0 );
