<?php
/**
 * The public vendor profile lists every portfolio item, featured first (Basecamp 10513127686).
 *
 * Run: wp eval-file tests/test-portfolio-profile-lists-every-item.php
 *
 * The profile asked for featured items only, so a vendor who added three projects and
 * featured none showed no portfolio to buyers. Featuring pins an item to the top; it
 * does not decide whether the item is shown.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$table  = $wpdb->prefix . 'wpss_portfolio_items';
$vendor = 987654;
$wpdb->delete( $table, array( 'vendor_id' => $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array( array( 'Plain A', 0 ), array( 'Featured B', 1 ), array( 'Plain C', 0 ) ) as $i => $row ) {
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'vendor_id'   => $vendor,
			'title'       => $row[0],
			'description' => 'x',
			'media'       => '[]',
			'tags'        => '[]',
			'is_featured' => $row[1],
			'sort_order'  => $i,
			'created_at'  => gmdate( 'Y-m-d H:i:s' ),
		)
	);
}

$items  = ( new \WPSellServices\Services\PortfolioService() )->get_by_vendor( $vendor, array( 'limit' => 24 ) );
$titles = array_column( $items, 'title' );
$check( 'all three items come back, not only the featured one', 3 === count( $titles ) );
$check( 'the featured item is first', isset( $titles[0] ) && 'Featured B' === $titles[0] );

$wpdb->delete( $table, array( 'vendor_id' => $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$root = dirname( __DIR__ );
$check( 'the vendor profile no longer asks for featured items only', false === strpos( (string) file_get_contents( $root . '/templates/vendor/profile.php' ), 'get_featured(' ) );
$check( 'the service page fallback no longer asks for featured items only', false === strpos( (string) file_get_contents( $root . '/src/Frontend/SingleServiceView.php' ), 'get_featured(' ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nAll passed\n";
exit( $fails ? 1 : 0 );
