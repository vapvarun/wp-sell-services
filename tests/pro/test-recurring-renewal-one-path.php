<?php
/**
 * Recurring services on the shared order skeleton (Basecamp 10551488386, payment modules phase 3, T10).
 *
 * One billing interface (pause and resume included) chosen by the order's gateway through one filter;
 * a renewal is credited through mark_as_paid() with the charge id, once per charge, and starts at once
 * because the first order already holds the requirements; subscription currency is stored upper case.
 * Recurring stays switched off in 2.0.0 (owner, 2026-11-27): this proves the plumbing, not the feature.
 *
 * Run: wp eval-file tests/pro/test-recurring-renewal-one-path.php
 *
 * @package WPSellServices
 */

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

use WPSellServicesPro\RecurringServices\Contracts\RecurringBillingInterface;
use WPSellServicesPro\RecurringServices\RecurringOrderFactory;
use WPSellServicesPro\RecurringServices\RecurringServiceManager;
use WPSellServicesPro\RecurringServices\RecurringSubscriptionRepository;
use WPSellServicesPro\RecurringServices\StripeRecurringBilling;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( RecurringServiceManager::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

// 1. One interface, one resolver.
$iface = new ReflectionClass( RecurringBillingInterface::class );
$check( 'the billing interface covers pause and resume', $iface->hasMethod( 'pause_subscription' ) && $iface->hasMethod( 'resume_subscription' ) );
$check( '  Stripe billing implements it', in_array( RecurringBillingInterface::class, class_implements( StripeRecurringBilling::class ), true ) );
$check( 'Stripe orders resolve to a billing provider', RecurringServiceManager::billing_for( 'stripe' ) instanceof RecurringBillingInterface );
$check( '  a gateway with no provider resolves to none', null === RecurringServiceManager::billing_for( 'offline' ) );
add_filter( 'wpss_pro_recurring_billing_providers', $add = static fn( $p ) => $p + array( 'qa_gw' => static fn() => new StripeRecurringBilling( new RecurringSubscriptionRepository() ) ) );
$check( '  another gateway registers through the filter', RecurringServiceManager::billing_for( 'qa_gw' ) instanceof RecurringBillingInterface );
remove_filter( 'wpss_pro_recurring_billing_providers', $add );

$src = static fn( string $f ) => (string) file_get_contents( WPSS_PRO_PLUGIN_DIR . 'src/' . $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
foreach ( array( 'RecurringServices/RecurringServiceManager.php', 'API/RecurringServiceController.php', 'Account/ProAccountDeletion.php' ) as $f ) {
	$check( "{$f} types against no Stripe class", ! preg_match( '/(private|\?)\s*StripeRecurringBilling\s+\$|new StripeRecurringBilling\(\s*\$/', $src( $f ) ) );
}

// 2. A renewal goes through mark_as_paid, once per charge.
$users = get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) );
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
if ( ! $users || ! $admin ) {
	echo "SKIP  needs a subscriber and an administrator\n";
	return;
}
global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$wpdb->insert(
	$orders,
	array(
		'order_number'   => 'QA-RECUR-FIRST',
		'customer_id'    => (int) $users[0],
		'vendor_id'      => $admin,
		'service_id'     => 0,
		'subtotal'       => 25,
		'total'          => 25,
		'currency'       => wpss_get_currency(),
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'payment_method' => 'stripe',
		'platform'       => 'standalone',
	)
);
$first = (int) $wpdb->insert_id;
$repo  = new RecurringSubscriptionRepository();
$sub   = $repo->create(
	array(
		'customer_id'            => (int) $users[0],
		'vendor_id'              => $admin,
		'service_id'             => 0,
		'original_order_id'      => $first,
		'stripe_subscription_id' => 'sub_qa_one_path',
		'amount'                 => 25,
		'currency'               => 'usd',
	)
);
$row = $repo->get_by_id( $sub );
$check( 'subscription currency is stored upper case', 'USD' === ( $row->currency ?? '' ), (string) ( $row->currency ?? '' ) );

$paid = 0;
add_action( 'wpss_order_paid', $count = static function () use ( &$paid ) {
	++$paid;
} );
$factory = new RecurringOrderFactory();
$renewal = $factory->create_renewal_order( $row, 'pi_qa_renewal_1' );
$order   = $renewal ? wpss_get_order( $renewal ) : null;
$check( 'a renewal order is created', (bool) $order );
$check( '  credited through mark_as_paid: paid, with the charge id and the first order\'s gateway', $order && 'paid' === $order->payment_status && 'pi_qa_renewal_1' === $order->transaction_id && 'stripe' === $order->payment_method, $order ? "{$order->payment_status} {$order->transaction_id} {$order->payment_method}" : '' );
$check( '  the paid hook fired once', 1 === $paid, (string) $paid );
$check( '  it starts at once, not waiting for requirements again', $order && 'in_progress' === $order->status, $order ? (string) $order->status : '' );
$again = $factory->create_renewal_order( $row, 'pi_qa_renewal_1' );
$check( 'the same charge delivered twice makes no second order', $again === $renewal && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE transaction_id = %s", 'pi_qa_renewal_1' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( '  and fires nothing again', 1 === $paid, (string) $paid );
$check( 'a renewal without a charge id is refused', null === $factory->create_renewal_order( $row, '' ) );
remove_action( 'wpss_order_paid', $count );

// Clean up every row this test made, including renewals an old factory may have duplicated.
$linked = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}wpss_order_meta WHERE meta_key = '_wpss_recurring_subscription_id' AND meta_value = %s", (string) $sub ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$ids    = array_unique( array_filter( array_merge( array( $first, (int) $renewal ), $linked ) ) );
foreach ( $ids as $id ) {
	$wpdb->delete( $orders, array( 'id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
}
$wpdb->delete( $wpdb->prefix . 'wpss_pro_recurring_subscriptions', array( 'id' => $sub ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_wallet_transactions WHERE reference_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ') AND %d', 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
