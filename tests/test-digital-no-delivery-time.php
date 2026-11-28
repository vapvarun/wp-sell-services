<?php
/**
 * A digital product has no delivery time on any surface: structured data, REST, the card.
 *
 * The wizard stores delivery_days = 1 on a digital product's packages. The cards and the order box
 * asked the type first, but the JSON-LD said "Delivery within 1 days" and the REST card said 1 (7
 * when empty). The one reader, wpss_get_service_delivery_days(), now answers 0 for a type delivered
 * at payment, so every caller agrees.
 *
 * Run: wp eval-file tests/test-digital-no-delivery-time.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f       = wpss_test_fixtures();
$digital = ( $f->service )( 'publish' );
$fixed   = ( $f->service )( 'publish' );
wpss_set_service_type( $digital, 'digital' );
foreach ( array( $digital, $fixed ) as $id ) {
	update_post_meta( $id, '_wpss_delivery_days', 1 ); // What the wizard stores on a digital product.
}

$check( 'digital: no delivery days', 0 === wpss_get_service_delivery_days( $digital ) );
$check( 'fixed: keeps its delivery days', 1 === wpss_get_service_delivery_days( $fixed ) );

$schema = wp_json_encode( ( new \WPSellServices\SEO\SchemaMarkup() )->get_service_schema( $digital ) );
$check( 'digital: structured data promises no delivery time', ! str_contains( (string) $schema, 'Delivery within' ), (string) $schema );
$check( 'fixed: structured data keeps it', str_contains( (string) wp_json_encode( ( new \WPSellServices\SEO\SchemaMarkup() )->get_service_schema( $fixed ) ), 'Delivery within' ) );

$check( 'digital: REST card delivery time is 0', 0 === wpss_rest_service_card( get_post( $digital ) )['delivery']['time'] );
$check( 'fixed: REST card delivery time is 1', 1 === wpss_rest_service_card( get_post( $fixed ) )['delivery']['time'] );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
