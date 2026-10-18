<?php
/**
 * An AJAX card that reloads the page after success clears Free's unsaved-changes guard first, or the
 * owner gets a false "Leave site?" prompt (Basecamp 10415816650).
 *
 * Run: wp eval-file tests/pro/test-admin-reload-clears-dirty-guard.php
 *
 * @package WPSellServices
 */

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$dir = WPSS_PRO_PLUGIN_DIR . 'assets/js/';
foreach ( array( 'admin.js', 'admin.min.js' ) as $file ) {
	$js = (string) file_get_contents( $dir . $file );
	foreach ( array( '#wpss-migrate-vendors', '#wpss-pp-create-batch' ) as $id ) {
		$check( "{$file}: cardClean on {$id}", (bool) preg_match( '/cardClean\([^)]{0,12}[\'"]' . preg_quote( $id, '/' ) . '[\'"]\)/', $js ) );
	}
}

exit( $fails ? 1 : 0 );
