<?php
/**
 * A dashboard section registered ONLY on wpss_dashboard_sections is a known route; an add-on needs no
 * second filter, and an unregistered slug is still refused.
 *
 * Run: wp eval-file tests/test-dashboard-section-registry.php
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

$check( 'before registering, the slug is not a known route', '' === wpss_normalize_dashboard_section( 'acme-report' ) );

add_filter(
	'wpss_dashboard_sections',
	static function ( array $sections ): array {
		$sections['acme'] = array(
			'label' => 'Acme',
			'items' => array( 'acme-report' => array( 'icon' => 'chart-bar', 'label' => 'Acme report' ) ),
		);
		return $sections;
	}
);

$check( 'a section registered only on wpss_dashboard_sections is a known route', 'acme-report' === wpss_normalize_dashboard_section( 'acme-report' ) );
$check( 'a slug nobody registered is still refused', '' === wpss_normalize_dashboard_section( 'not-a-section' ) );
$check( 'the built-in sections still resolve', 'orders' === wpss_normalize_dashboard_section( 'orders' ) );

wp_set_current_user( 0 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
