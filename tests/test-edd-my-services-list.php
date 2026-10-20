<?php
/**
 * EDD rail My Services lists the vendor's services (24 per page) and the tile counts the same set.
 *
 * Run: wp eval-file tests/test-edd-my-services-list.php
 *
 * Seeds 26 service downloads and 1 plain download for a vendor, removes them after. EDD need not be
 * active: the renderer only reads the download post type and meta. Skips when the Pro adapter is absent.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$class = '\\WPSellServicesPro\\Integrations\\EDD\\EDDAccountProvider';
if ( ! class_exists( $class ) ) {
	echo "SKIP  EDD adapter not loadable\n";
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
for ( $i = 1; $i <= 27; $i++ ) {
	$ids[] = $id = wp_insert_post( array( 'post_type' => 'download', 'post_status' => 'publish', 'post_title' => "QA-RFT edd {$i}", 'post_author' => $vendor ) );
	if ( $i <= 26 ) {
		update_post_meta( $id, '_edd_download_type', 'service' );
	}
}

$render = static function ( int $page ) use ( $class ) {
	$_GET['services_page'] = $page;
	ob_start();
	( new $class() )->render_services_endpoint();
	return ob_get_clean();
};

$p1 = $render( 1 );
$p2 = $render( 2 );
unset( $_GET['services_page'] );

$check( 'tile counts the 26 services, not the plain download', str_contains( $p1, '<span class="stat-number">26</span>' ) );
$check( 'page 1 lists 24 cards, not the empty message', 24 === substr_count( $p1, 'class="wpss-service-card"' ) && ! str_contains( $p1, 'not created any services' ) );
$check( 'page 2 lists the other 2', 2 === substr_count( $p2, 'class="wpss-service-card"' ) && $p1 !== $p2 );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}

exit( $fails ? 1 : 0 );
