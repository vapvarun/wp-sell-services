<?php
/**
 * Billing prefill contract.
 *
 * A signed-in buyer should never be asked to retype what WordPress already
 * knows. The email fallback shipped first and the name fields were left out,
 * so the card's screenshot shows an empty email while the same screen today
 * shows empty first and last names - one defect, reported from whichever field
 * happened to be missing that week.
 *
 * All three now resolve in wpss_get_billing_address(), which is the single
 * accessor checkout, pay-order checkout and the account screen all read, so
 * the rule cannot drift into three copies.
 *
 * Run: wp eval-file tests/test-billing-prefill-contract.php
 *
 * @package WPSellServices
 */

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

echo "\nBilling prefill contract\n\n";

$uid = wp_insert_user(
	array(
		'user_login' => 'wpss_prefill_probe',
		'user_pass'  => wp_generate_password(),
		'user_email' => 'prefill.probe@example.test',
		'first_name' => 'Ada',
		'last_name'  => 'Lovelace',
	)
);

if ( is_wp_error( $uid ) ) {
	echo "  SKIP  could not create probe user: " . $uid->get_error_message() . "\n";
	return;
}

// 1. Nothing stored yet - every field falls back to the WP user.
$a = wpss_get_billing_address( $uid );
wpss_t( 'prefill.probe@example.test' === $a['billing_email'], 'email falls back to the account email' );
wpss_t( 'Ada' === $a['billing_first_name'], 'first name falls back to first_name meta' );
wpss_t( 'Lovelace' === $a['billing_last_name'], 'last name falls back to last_name meta' );

// 2. No name meta at all - display_name is split on the FIRST space only, so a
//    multi-word surname is not silently truncated.
delete_user_meta( $uid, 'first_name' );
delete_user_meta( $uid, 'last_name' );
wp_update_user( array( 'ID' => $uid, 'display_name' => 'Maria del Carmen Ruiz' ) );
clean_user_cache( $uid );

$b = wpss_get_billing_address( $uid );
wpss_t( 'Maria' === $b['billing_first_name'], 'display_name gives the first name' );
wpss_t( 'del Carmen Ruiz' === $b['billing_last_name'], 'everything after the first space is kept as the surname' );

// 3. A stored billing value always wins over the fallback - the buyer's own
//    correction must not be overwritten by their account name.
update_user_meta( $uid, 'billing_first_name', 'Bill' );
update_user_meta( $uid, 'billing_email', 'invoices@example.test' );
$c = wpss_get_billing_address( $uid );
wpss_t( 'Bill' === $c['billing_first_name'], 'a saved billing first name beats the account name' );
wpss_t( 'invoices@example.test' === $c['billing_email'], 'a saved billing email beats the account email' );

// 4. Logged out resolves to nothing rather than leaking anyone's details.
wpss_t( array() === wpss_get_billing_address( 0 ) && ! is_user_logged_in(), 'no user, no address' );

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
