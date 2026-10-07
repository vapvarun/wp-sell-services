<?php
/**
 * A package keeps its stable id through a save, and a new one gets an id at once.
 *
 * Run: wp eval-file tests/test-package-ids-on-save.php
 *
 * Basecamp 10342028625: the wizard and the wp-admin editor rebuilt packages
 * without `id`, so a save stripped the ids and the next read renumbered them.
 * Uses a throwaway service; it is deleted at the end.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$service_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_title'  => 'dev_f1 package id contract',
		'post_status' => 'draft',
	)
);

try {
	$ids = static fn(): array => array_map( static fn( $p ) => (int) ( $p['id'] ?? 0 ), (array) get_post_meta( $service_id, '_wpss_packages', true ) );

	// A write with no ids (what the savers used to produce) is numbered at once.
	update_post_meta(
		$service_id,
		'_wpss_packages',
		array(
			array( 'name' => 'Basic', 'price' => 10, 'delivery_days' => 3 ),
			array( 'name' => 'Standard', 'price' => 20, 'delivery_days' => 5 ),
		)
	);
	$first = $ids();
	$check( sprintf( 'new packages get ids on write (%s)', implode( ',', $first ) ), $first[0] >= 1000 && $first[1] >= 1000 && $first[0] !== $first[1] );

	// A saver that carries the id keeps it, reordered.
	$packages = get_post_meta( $service_id, '_wpss_packages', true );
	$rebuilt  = array();
	foreach ( array_reverse( $packages ) as $package ) {
		$rebuilt[] = wpss_package_id_from_input( $package ) + array(
			'name'          => $package['name'],
			'price'         => $package['price'],
			'delivery_days' => $package['delivery_days'],
		);
	}
	update_post_meta( $service_id, '_wpss_packages', $rebuilt );
	$check( 'ids follow their package through a reordering save', array( $first[1], $first[0] ) === $ids() );

	$check( 'an empty id from a form is a new package, not id 0', array() === wpss_package_id_from_input( array( 'id' => '' ) ) );
} finally {
	wp_delete_post( $service_id, true );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
