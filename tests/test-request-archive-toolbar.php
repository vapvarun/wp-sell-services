<?php
/**
 * Buyer Requests archive: no near-empty toolbar card, and its sort and Filters controls work
 * (Basecamp 10450931965).
 *
 * Run: wp eval-file tests/test-request-archive-toolbar.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$view = new \WPSellServices\Frontend\BuyerRequestArchiveView();

ob_start();
$view->render_filters_bar();
$bar = (string) ob_get_clean();

global $wp_query;
$saved    = $wp_query;
$wp_query = new WP_Query( array( 'post_type' => 'wpss_request', 'posts_per_page' => 1 ) );
ob_start();
$view->render_results_info();
$info     = (string) ob_get_clean();
$wp_query = $saved;

$check( 'the top bar holds only the drawer toggle', str_contains( $bar, 'wpss-filter-toggle' ) && ! str_contains( $bar, '<select' ) );
$check( 'the sort sits in the results row with the count', str_contains( $info, 'wpss-results-info__count' ) && str_contains( $info, 'wpss-sort-filter' ) );
$check( 'the sort has an accessible name', str_contains( $info, 'aria-label=' ) );

$src = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Frontend/BuyerRequestArchiveView.php' );
$check( 'the archive loads the frontend bundle its sort and drawer need', 1 === preg_match( '/function enqueue_assets\(\).*?wpss_enqueue_frontend_assets\(\)/s', $src ) );

$css = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/assets/css/buyer-request.css' );
$check( 'desktop hides the toggle-only bar', str_contains( $css, ".wpss-requests-archive .wpss-filters-bar {\n\t\tdisplay: none;" ) );

$js = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/assets/js/frontend.min.js' );
$check( 'the served drawer script keeps aria-expanded in step', str_contains( $js, 'aria-expanded' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
