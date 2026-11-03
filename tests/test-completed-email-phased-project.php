<?php
/**
 * The vendor's order-completed email never says "Payment of $0.00".
 *
 * A phased (milestone) project carries no money on its main order: each phase is
 * paid out as the buyer approves it. Completing the project emailed the vendor
 * "Payment of $0.00 will be released to your earnings."
 *
 * Run: wp eval-file tests/test-completed-email-phased-project.php
 *
 * Renders the template from one existing order row with the fields under test replaced; writes nothing.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$render = static function ( array $row ): string {
	global $wpdb;
	$base  = (array) $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}wpss_orders LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB
	$order = ServiceOrder::from_db( (object) ( $row + $base ) );
	ob_start();
	wpss_get_template(
		'emails/order-completed.php',
		array(
			'order'         => $order,
			'recipient'     => wp_get_current_user(),
			'email_heading' => 'QA',
			'base_color'    => '#000',
			'is_customer'   => false,
			'vendor_name'   => 'QA vendor',
			'customer_name' => 'QA buyer',
			'email'         => null,
		)
	);
	return wp_strip_all_tags( (string) ob_get_clean() );
};

$phased = $render( array( 'platform' => 'request', 'total' => 0, 'vendor_earnings' => 0, 'meta' => wp_json_encode( array( 'contract_type' => 'milestone' ) ) ) );
$check( 'phased project: no "Payment of" a zero amount', false === strpos( $phased, 'Payment of' ) );
$check( 'phased project: says each phase was paid as approved', false !== strpos( $phased, 'Each phase of this project' ) );

$fixed = $render( array( 'platform' => 'standalone', 'total' => 100, 'vendor_earnings' => 80, 'meta' => '{}' ) );
$check( 'service order: still states the amount', false !== strpos( $fixed, 'Payment of' ) && false !== strpos( $fixed, '80' ) );

echo $fails ? "FAILED {$fails}\n" : "OK\n";
