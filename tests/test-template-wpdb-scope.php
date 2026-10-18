<?php
/**
 * A template runs inside a function, so $wpdb is only there if the file declares it.
 * order-view.php used $wpdb without it and the order page returned HTTP 500 for a buyer
 * who had tipped (member sweep, 1.9.0). Templates should ask a service instead.
 *
 * Run: wp eval-file tests/test-template-wpdb-scope.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$bad = array();
$dirs = array( WPSS_PLUGIN_DIR . 'templates' );
if ( defined( 'WPSS_PRO_PLUGIN_DIR' ) ) {
	$dirs[] = WPSS_PRO_PLUGIN_DIR . 'templates';
}
foreach ( $dirs as $dir ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		// Comments mention $wpdb in prose; only code counts.
		$code = '';
		foreach ( token_get_all( (string) file_get_contents( $file->getPathname() ) ) as $tok ) {
			if ( is_array( $tok ) && in_array( $tok[0], array( T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML ), true ) ) {
				continue;
			}
			$code .= is_array( $tok ) ? $tok[1] : $tok;
		}
		if ( false !== strpos( $code, '$wpdb' ) && ! preg_match( '/global\s+[^;]*\$wpdb/', $code ) ) {
			$bad[] = substr( $file->getPathname(), strlen( dirname( $dir ) ) + 1 );
		}
	}
}
$check( 'every template that uses $wpdb declares it global' . ( $bad ? ' (' . implode( ', ', $bad ) . ')' : '' ), ! $bad );

$tips = new \WPSellServices\Services\TippingService();
$check( 'a buyer with no tip has no tip order row', null === $tips->get_buyer_tip_order( 0, 0 ) );

exit( $fails ? 1 : 0 );
