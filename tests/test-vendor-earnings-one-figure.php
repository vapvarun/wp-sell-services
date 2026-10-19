<?php
/**
 * One "earned" figure: the Sales tile, the REST profile and the vendor profile read the ledger,
 * so they cannot disagree with the Earnings page (vendor sweep, 1.9.0: tile ran 159.00 high).
 *
 * Run: wp eval-file tests/test-vendor-earnings-one-figure.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
// Vendors with earnings first: a vendor with nothing proves nothing.
$vendors = array_map( 'intval', $wpdb->get_col( "SELECT user_id FROM {$wpdb->prefix}wpss_wallet_transactions GROUP BY user_id ORDER BY SUM(amount) DESC LIMIT 5" ) );
$repo    = new \WPSellServices\Database\Repositories\OrderRepository();
foreach ( $vendors as $vendor_id ) {
	$tile   = (float) $repo->get_vendor_stats( (int) $vendor_id )['total_earnings'];
	$ledger = (float) wpss_get_ledger_total_earned( (int) $vendor_id );
	$check( "vendor {$vendor_id}: Sales tile equals the ledger total ({$tile} vs {$ledger})", abs( $tile - $ledger ) < 0.005 );
}
$check( 'at least one vendor was compared', (bool) $vendors );

exit( $fails ? 1 : 0 );
