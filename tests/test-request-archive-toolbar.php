<?php
/**
 * Buyer Requests archive: no near-empty toolbar card, and its sort and Filters controls work
 * (Basecamp 10450931965). Since 2.0.0 the bar is the services archive's: search and sort.
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

$view    = new \WPSellServices\Frontend\BuyerRequestArchiveView();
$get_was = $_GET;
$main_was = $GLOBALS['wp_the_query'];

// 2.0.0: the same toolbar as the services archive. It is never a near-empty card: it holds the search and the sort.
$_GET = array( 'sort' => 'oldest', 'min_budget' => '50' );
ob_start();
$view->render_filters_bar();
$bar = (string) ob_get_clean();
$check( 'the bar holds the search box and the sort', str_contains( $bar, 'name="search"' ) && str_contains( $bar, 'wpss-sort-filter' ) );
$check( 'the sort has an accessible name', 1 === preg_match( '/<select[^>]*wpss-sort-filter[^>]*aria-label=/', $bar ) );
$check( 'a search keeps the chosen sort and budget', str_contains( $bar, 'name="sort" value="oldest"' ) && str_contains( $bar, 'name="min_budget" value="50"' ) );

// The search narrows the real archive query, and the results line names it.
$archive = static function ( array $get ) use ( $view ): array {
	$_GET = $get;
	$q    = new WP_Query();
	$q->parse_query( array( 'post_type' => 'wpss_request' ) );
	$GLOBALS['wp_the_query'] = $q;
	$view->modify_archive_query( $q );
	$q->get_posts();
	return array( $q, (int) $q->found_posts );
};
list( , $all )        = $archive( array() );
list( $q, $matched )  = $archive( array( 'search' => 'zzqx-no-such-request' ) );
$check( 'a search with no match finds nothing', 0 === $matched && $all > 0 );
global $wp_query;
$saved    = $wp_query;
$wp_query = $q;
ob_start();
$view->render_results_info();
$info     = (string) ob_get_clean();
$wp_query = $saved;
$check( 'the results line names the search', str_contains( $info, 'zzqx-no-such-request' ) );
$_GET                    = $get_was;
$GLOBALS['wp_the_query'] = $main_was;

$src = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Frontend/BuyerRequestArchiveView.php' );
$check( 'the archive loads the frontend bundle its sort and drawer need', 1 === preg_match( '/function enqueue_assets\(\).*?wpss_enqueue_frontend_assets\(\)/s', $src ) );

$css = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/assets/css/buyer-request.css' );
$check( 'desktop shows the bar across both columns', str_contains( $css, ".wpss-requests-archive .wpss-filters-bar {\n\t\tgrid-column: 1 / -1;" ) );

$js = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/assets/js/frontend.min.js' );
$check( 'the served drawer script keeps aria-expanded in step', str_contains( $js, 'aria-expanded' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
