<?php
/**
 * Service Grid block editor: category labels are decoded text, and the sort list offers the same
 * rating / popularity / price orders the storefront has (Basecamp 10415816716).
 *
 * Run: wp eval-file tests/test-service-grid-block-editor.php
 *
 * @package WPSellServices
 */

use WPSellServices\Blocks\BlocksManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$term = wp_insert_term( 'QA Graphics & Design', 'wpss_service_category' );
$id   = is_wp_error( $term ) ? 0 : (int) $term['term_id'];

$method = new ReflectionMethod( BlocksManager::class, 'get_service_categories' );
$method->setAccessible( true );
$labels = wp_list_pluck( $method->invoke( BlocksManager::instance() ), 'label', 'value' );

$check( 'fixture term created', $id > 0 );
$check( 'category label is decoded text', ( $labels[ $id ] ?? '' ) === 'QA Graphics & Design' );
wp_delete_term( $id, 'wpss_service_category' );

$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/blocks.js' );
$grid = substr( $js, (int) strpos( $js, "registerBlockType('wpss/service-grid'" ), 3500 );
foreach ( array( 'rating', 'sales', 'price' ) as $order ) {
	$check( "service-grid offers the {$order} sort", str_contains( $grid, "value: '{$order}'" ) );
}

exit( $fails ? 1 : 0 );
