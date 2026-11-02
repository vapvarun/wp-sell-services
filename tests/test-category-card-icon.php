<?php
/**
 * Category cards draw the saved Lucide icon, not an empty dashicons circle (Basecamp 10450931661).
 *
 * Run: wp eval-file tests/test-category-card-icon.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$with    = wp_insert_term( 'QA icon cat ' . wp_rand(), 'wpss_service_category' );
$without = wp_insert_term( 'QA plain cat ' . wp_rand(), 'wpss_service_category' );
update_term_meta( $with['term_id'], '_wpss_icon', 'sparkles' );

$render = static function ( int $term_id ) {
	ob_start();
	wpss_get_template_part( 'partials/category-card', '', array( 'category' => get_term( $term_id ), 'show_image' => false, 'show_icon' => true ) );
	return (string) ob_get_clean();
};

$a = $render( (int) $with['term_id'] );
$b = $render( (int) $without['term_id'] );
$check( 'saved icon is drawn as a Lucide icon', str_contains( $a, 'data-lucide="sparkles"' ) );
$check( 'no dashicons class built from the saved name', ! str_contains( $a, 'dashicons' ) );
$check( 'no saved icon: the folder fallback', str_contains( $b, 'data-lucide="folder"' ) );

wp_delete_term( $with['term_id'], 'wpss_service_category' );
wp_delete_term( $without['term_id'], 'wpss_service_category' );
echo $fails ? "FAILED: {$fails}\n" : "OK\n";
