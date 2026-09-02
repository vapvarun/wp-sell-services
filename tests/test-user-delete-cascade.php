<?php
/**
 * Deleting a member keeps the other party's records.
 *
 * An admin deleting a buyer from wp-admin used to delete every order that
 * buyer was party to - the vendor's completed, credited jobs included - and
 * the privacy eraser deleted the wallet ledger while telling the admin it had
 * been kept (Basecamp 10264285540). Both paths now run one cascade policy:
 * shared rows stay, the departing member's id columns become 0, PII columns on
 * those rows are blanked, and only rows the member owns alone are deleted.
 *
 * Run: wp eval-file tests/test-user-delete-cascade.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

global $wpdb;

require_once ABSPATH . 'wp-admin/includes/user.php';

$failures = array();
$p        = $wpdb->prefix . 'wpss_';
$stamp    = time();

$check = static function ( bool $ok, string $what ) use ( &$failures ): void {
	if ( ! $ok ) {
		$failures[] = $what;
	}
};

$row = static function ( string $table, int $id ) use ( $wpdb, $p ) {
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}{$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};

// Throwaway buyer and vendor.
$buyer  = wp_insert_user(
	array(
		'user_login' => "wpss-f9-buyer-{$stamp}",
		'user_email' => "wpss-f9-buyer-{$stamp}@example.test",
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$vendor = wp_insert_user(
	array(
		'user_login' => "wpss-f9-vendor-{$stamp}",
		'user_email' => "wpss-f9-vendor-{$stamp}@example.test",
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);

if ( is_wp_error( $buyer ) || is_wp_error( $vendor ) ) {
	echo "FAIL - could not create throwaway users\n";
	exit( 1 );
}

$wpdb->insert(
	"{$p}orders",
	array(
		'order_number'    => "F9-{$stamp}",
		'customer_id'     => $buyer,
		'vendor_id'       => $vendor,
		'service_id'      => 0,
		'subtotal'        => 50,
		'total'           => 50,
		'status'          => 'completed',
		'payment_status'  => 'paid',
		'billing_address' => wp_json_encode( array( 'billing_first_name' => 'Buyer', 'billing_city' => 'Testville' ) ),
		'vendor_notes'    => 'call me on my mobile',
		'completed_at'    => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

$wpdb->insert(
	"{$p}wallet_transactions",
	array(
		'user_id'        => $vendor,
		'type'           => 'credit',
		'amount'         => 40,
		'balance_after'  => 40,
		'description'    => 'F9 contract',
		'reference_type' => 'order',
		'reference_id'   => $order_id,
	)
);
$ledger_id = (int) $wpdb->insert_id;

$wpdb->insert(
	"{$p}reviews",
	array(
		'order_id'      => $order_id,
		'reviewer_id'   => $buyer,
		'reviewer_name' => 'Buyer Person',
		'reviewee_id'   => $vendor,
		'service_id'    => 0,
		'customer_id'   => $buyer,
		'vendor_id'     => $vendor,
		'rating'        => 5,
		'review'        => 'F9 contract review',
	)
);
$review_id = (int) $wpdb->insert_id;

$wpdb->insert(
	"{$p}withdrawals",
	array(
		'vendor_id' => $vendor,
		'amount'    => 40,
		'method'    => 'paypal',
		'details'   => wp_json_encode( array( 'email' => 'vendor@example.test' ) ),
		'status'    => 'completed',
	)
);
$withdrawal_id = (int) $wpdb->insert_id;

$wpdb->insert(
	"{$p}notifications",
	array(
		'user_id' => $buyer,
		'type'    => 'order_completed',
		'title'   => 'F9 contract',
	)
);

// One file on disk, referenced from a delivery row.
$files_dir = wpss_get_order_files_dir() . $order_id . '/';
wp_mkdir_p( $files_dir );
file_put_contents( $files_dir . 'deliverable.txt', 'F9' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$wpdb->insert(
	"{$p}deliveries",
	array(
		'order_id'    => $order_id,
		'vendor_id'   => $vendor,
		'message'     => 'F9 contract',
		'attachments' => wp_json_encode(
			array(
				array(
					'id'   => wp_generate_uuid4(),
					'name' => 'deliverable.txt',
					'type' => 'text/plain',
					'size' => 2,
					'path' => 'deliverable.txt',
				),
			)
		),
	)
);

// 1. Admin deletes the buyer from wp-admin.
wp_delete_user( $buyer );

$order = $row( 'orders', $order_id );
$check( null !== $order, "wp_delete_user(buyer): order {$order_id} was deleted - the vendor's completed order is gone." );
if ( $order ) {
	$check( 0 === (int) $order->customer_id, 'wp_delete_user(buyer): order still names the deleted buyer (customer_id not 0).' );
	$check( empty( $order->billing_address ), 'wp_delete_user(buyer): billing address was not blanked.' );
	$check( (int) $order->vendor_id === $vendor, 'wp_delete_user(buyer): vendor_id was touched.' );
}

$check( null !== $row( 'wallet_transactions', $ledger_id ), 'wp_delete_user(buyer): vendor ledger row was deleted.' );

$review = $row( 'reviews', $review_id );
$check( null !== $review, 'wp_delete_user(buyer): review was deleted.' );
if ( $review ) {
	$check( 0 === (int) $review->reviewer_id && empty( $review->reviewer_name ), 'wp_delete_user(buyer): review still names the reviewer.' );
}

$check(
	0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}notifications WHERE user_id = %d", $buyer ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	'wp_delete_user(buyer): buyer notifications survived.'
);

$check( 'Deleted user' === wpss_get_member_display_name( 0 ), 'wpss_get_member_display_name(0) is not "Deleted user".' );

// 2. Privacy eraser on the vendor: same policy.
$erased = ( new \WPSellServices\Privacy\PersonalData() )->erase( "wpss-f9-vendor-{$stamp}@example.test" );

$check( ! empty( $erased['items_removed'] ), 'eraser(vendor): reported nothing removed - ' . implode( ' | ', $erased['messages'] ?? array() ) );
$check( false !== strpos( implode( ' ', $erased['messages'] ?? array() ), 'Kept and anonymised' ), 'eraser(vendor): message does not list what was kept and anonymised.' );

$order = $row( 'orders', $order_id );
$check( null !== $order, 'eraser(vendor): order was deleted.' );
if ( $order ) {
	$check( 0 === (int) $order->vendor_id && empty( $order->vendor_notes ), 'eraser(vendor): order still names the vendor or keeps vendor notes.' );
}

$ledger = $row( 'wallet_transactions', $ledger_id );
$check( null !== $ledger, 'eraser(vendor): ledger row was deleted although the eraser says it was kept.' );
if ( $ledger ) {
	$check( 0 === (int) $ledger->user_id, 'eraser(vendor): ledger row still names the vendor.' );
}

$withdrawal = $row( 'withdrawals', $withdrawal_id );
$check( null !== $withdrawal, 'eraser(vendor): withdrawal was deleted.' );
if ( $withdrawal ) {
	$check( 0 === (int) $withdrawal->vendor_id && empty( $withdrawal->details ), 'eraser(vendor): withdrawal still carries the vendor id or payout details.' );
}

// 3. Deleting the order removes its files.
if ( function_exists( 'wpss_delete_order_files' ) ) {
	wpss_delete_order_files( $order_id );
	$check( ! is_dir( $files_dir ), "wpss_delete_order_files({$order_id}) left {$files_dir} on disk." );
} else {
	$failures[] = 'wpss_delete_order_files() does not exist.';
}

// Clean up.
$wpdb->delete( "{$p}orders", array( 'id' => $order_id ) );
$wpdb->delete( "{$p}deliveries", array( 'order_id' => $order_id ) );
$wpdb->delete( "{$p}wallet_transactions", array( 'id' => $ledger_id ) );
$wpdb->delete( "{$p}reviews", array( 'id' => $review_id ) );
$wpdb->delete( "{$p}withdrawals", array( 'id' => $withdrawal_id ) );
$wpdb->delete( "{$p}notifications", array( 'user_id' => $buyer ) );
$wpdb->delete( "{$p}notifications", array( 'user_id' => $vendor ) );

foreach ( array( $buyer, $vendor ) as $user_id ) {
	if ( get_userdata( $user_id ) ) {
		wp_delete_user( $user_id );
	}
}

if ( is_dir( $files_dir ) && function_exists( 'wpss_rmdir_recursive' ) ) {
	wpss_rmdir_recursive( $files_dir );
}

if ( $failures ) {
	echo "\nFAIL\n";
	foreach ( $failures as $f ) {
		echo "  - {$f}\n";
	}
	exit( 1 );
}

echo "PASS - deleting a member keeps and anonymises the other party's order, ledger, review and withdrawal; own rows and order files are removed.\n";
