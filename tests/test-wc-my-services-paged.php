<?php
/**
 * WooCommerce rail My Services is paginated at 24 and never loads the whole list.
 *
 * Run: wp eval-file tests/test-wc-my-services-paged.php
 *
 * Basecamp 10422089720. Seeds 30 QA-RFT services for a vendor, removes them after.
 * Skips when the WooCommerce adapter class is not loadable.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$class = '\\WPSellServicesPro\\Integrations\\WooCommerce\\WCAccountProvider';
if ( ! class_exists( $class ) ) {
	echo "SKIP  WooCommerce adapter not loadable\n";
	exit( 0 );
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$vendor = 0;
foreach ( get_users( array( 'number' => 50 ) ) as $u ) {
	if ( wpss_is_vendor( $u->ID ) ) {
		$vendor = $u->ID;
		break;
	}
}
wp_set_current_user( $vendor );

$ids = array();
for ( $i = 1; $i <= 30; $i++ ) {
	$ids[] = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => "QA-RFT paged {$i}", 'post_author' => $vendor ) );
}

$render = static function ( int $paged ) use ( $class ) {
	set_query_var( 'paged', $paged );
	ob_start();
	( new $class() )->render_services_endpoint();
	return ob_get_clean();
};

$p1 = $render( 1 );
$p2 = $render( 2 );
$check( 'page 1 shows 24 service cards', 24 === substr_count( $p1, 'class="wpss-service-card"' ) );
$check( 'page 1 has pagination', str_contains( $p1, 'wpss-pagination' ) );
$check( 'page 2 shows the rest', substr_count( $p2, 'class="wpss-service-card"' ) >= 6 && substr_count( $p2, 'class="wpss-service-card"' ) < 24 );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}

exit( $fails ? 1 : 0 );
