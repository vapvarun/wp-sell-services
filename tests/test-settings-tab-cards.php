<?php
/**
 * Every direct child of a settings panel is a card, so nothing floats on the grey panel
 * (Basecamp 10412306799).
 *
 * Run: wp eval-file tests/test-settings-tab-cards.php
 *
 * Renders each tab through its own render method and walks the top-level elements.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

wp_set_current_user( 1 );
$settings = new \WPSellServices\Admin\Settings();

foreach ( array( 'payouts', 'commission', 'vendor', 'orders', 'emails' ) as $tab ) {
	$method = new ReflectionMethod( $settings, 'render_' . $tab . '_tab' );
	$method->setAccessible( true );

	ob_start();
	$method->invoke( $settings );
	$html = (string) ob_get_clean();

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
	libxml_clear_errors();

	$loose = array();
	foreach ( $dom->getElementsByTagName( 'body' )->item( 0 )->childNodes as $node ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			continue;
		}
		if ( false === strpos( ' ' . $node->getAttribute( 'class' ) . ' ', ' wpss-card ' ) && ! in_array( $node->nodeName, array( 'script', 'style', 'template' ), true ) ) {
			$loose[] = $node->nodeName . '.' . $node->getAttribute( 'class' );
		}
	}
	$check( "{$tab} tab: no element outside a card (" . implode( ', ', $loose ) . ')', ! $loose );
}

exit( $fails ? 1 : 0 );
