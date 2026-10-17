<?php
/**
 * Each withdrawal step sends its own email: approved says approved, marking it
 * paid says paid, rejected says rejected (Basecamp 10402904790).
 *
 * Run: wp eval-file tests/test-withdrawal-status-emails.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$vendor = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1 ) )[0] ?? null;
if ( ! $vendor ) {
	echo "SKIP  no user to mail\n";
	return;
}

$sent    = array();
$capture = static function ( $short, $args ) use ( &$sent ) {
	$sent[] = array( 'subject' => $args['subject'], 'body' => $args['message'] );
	return true;
};
add_filter( 'pre_wp_mail', $capture, 10, 2 );

$row = (object) array( 'vendor_id' => $vendor->ID, 'amount' => 25, 'admin_note' => '' );
$mail = new \WPSellServices\Services\EmailService();
$expect = array(
	'approved'  => array( 'Approved', 'Paid' ),
	'completed' => array( 'Paid', 'Approved' ),
	'rejected'  => array( 'Rejected', 'Paid' ),
);
foreach ( $expect as $status => list( $has, $lacks ) ) {
	$sent = array();
	$mail->send_withdrawal_status( 999, $status, $row );
	$s = $sent[0]['subject'] ?? '';
	$b = $sent[0]['body'] ?? '';
	$check( "{$status}: subject says {$has}", str_contains( $s, $has ) && ! str_contains( $s, $lacks ) );
	$check( "{$status}: body status cell says {$has}", str_contains( $b, '>' . $has . '<' ) || str_contains( $b, $has ) );
}

remove_filter( 'pre_wp_mail', $capture, 10 );
echo $fails ? "{$fails} FAILED\n" : "All passed\n";
