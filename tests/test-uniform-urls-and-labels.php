<?php
/**
 * Dashboard links and order status labels have one source (1.9.3 uniformity).
 *
 * Run: wp eval-file tests/test-uniform-urls-and-labels.php
 *
 * - 10504407493: a trashed dashboard page no longer yields a (404) link, and
 *   the checkout redirects use the dashboard section the dashboard reads.
 * - 10504406986: the model's status list is the filterable function's list.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$pages = (array) get_option( 'wpss_pages', array() );
$id    = (int) ( $pages['dashboard'] ?? 0 );

if ( $id && 'publish' === get_post_status( $id ) ) {
	$check( 'a published dashboard page gives a link', '' !== wpss_get_dashboard_url() );
	$check( 'a section is appended the way the dashboard reads it', false !== strpos( wpss_get_dashboard_url( 'orders' ), 'orders' ), wpss_get_dashboard_url( 'orders' ) );

	wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
	$check( 'a drafted dashboard page gives no link (not a 404)', '' === wpss_get_dashboard_url( 'orders' ) );
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
} else {
	echo "SKIP  no published dashboard page on this site\n";
}

foreach ( array( 'src/Checkout/CheckoutIntentService.php', 'src/Integrations/Gateways/OfflineGateway.php', 'src/Integrations/Gateways/TestGateway.php' ) as $file ) {
	$check( basename( $file ) . ' redirects to a dashboard section, not ?tab=', false === strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . $file ), "add_query_arg( 'tab', 'orders'" ) );
}

add_filter( 'wpss_order_statuses', static fn( $s ) => array_merge( $s, array( 'pending_requirements' => 'Renamed by a site' ) ) );
$check( 'a status renamed through the filter shows renamed in the model list too', 'Renamed by a site' === \WPSellServices\Models\ServiceOrder::get_statuses()['pending_requirements'] );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
