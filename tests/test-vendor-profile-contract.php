<?php
/**
 * Vendor profile resolution contract.
 *
 * A member can hold the vendor role with no row in wpss_vendor_profiles at all -
 * granted by an admin, promoted by a filter, or created by the demo seeder. The
 * profile page asked "is there a profile row" and rendered "Vendor not found."
 * when the answer was no, telling visitors a seller did not exist while
 * wpss_is_vendor() said they did.
 *
 * Same lesson as Basecamp 10208142467, where the Become a Vendor page offered
 * "Register as Vendor" to people who already were vendors: wpss_is_vendor() is
 * the canonical answer, and a missing row is an empty profile, not a missing
 * person.
 *
 * Run: wp eval-file tests/test-vendor-profile-contract.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

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

echo "\nVendor profile resolution contract\n\n";

global $wpdb;
$table = $wpdb->prefix . 'wpss_vendor_profiles';

require_once __DIR__ . '/fixtures.php';
$f = wpss_test_fixtures();

// A real vendor WITH a row.
$with_row = $f->vendor;

// A vendor by ROLE with no row - the case that broke.
$role_only = (int) wp_insert_user(
	array(
		'user_login' => $f->tag . '_roleonly',
		'user_pass'  => wp_generate_password(),
		'user_email' => $f->tag . '_roleonly@example.test',
		'role'       => 'wpss_vendor',
	)
);
$f->users[] = $role_only;
$wpdb->delete( $table, array( 'user_id' => $role_only ) );
// "Promoted by a filter" is the only way to be a vendor with no row.
add_filter( 'wpss_is_vendor', static fn( $is, $id ) => (int) $id === $role_only ? true : $is, 10, 2 );

// Somebody who is not a vendor at all.
$not_vendor = $f->buyer;

wpss_t( function_exists( 'wpss_get_vendor_profile_or_default' ), 'the defaulting accessor exists' );

if ( $with_row ) {
	$p = wpss_get_vendor_profile_or_default( $with_row );
	wpss_t( $p && $p->id > 0, "a vendor with a row still returns their real profile (user {$with_row})" );
	wpss_t( $p && '' !== $p->display_name, 'the real profile keeps its display name' );
} else {
	echo "  SKIP  no vendor profile rows on this install.\n";
}

if ( $role_only ) {
	$p = wpss_get_vendor_profile_or_default( $role_only );
	wpss_t( null !== $p, "a role-granted vendor with no row gets a profile, not null (user {$role_only})" );
	wpss_t( $p && 0 === $p->id, 'the defaulted profile is marked as having no row (id 0)' );
	wpss_t( $p && '' !== $p->display_name, 'the defaulted profile still names the person' );
	wpss_t( $p && $role_only === $p->user_id, 'the defaulted profile belongs to the right user' );

	// The bug was the profile PAGE, so assert the surface, not only the accessor.
	$html = do_shortcode( '[wpss_vendor_profile id="' . $role_only . '"]' );
	wpss_t(
		false === stripos( $html, 'Vendor not found' ),
		'the profile page no longer says the vendor does not exist'
	);
} else {
	echo "  SKIP  no role-granted vendor without a profile row on this install.\n";
}

if ( $not_vendor ) {
	wpss_t(
		null === wpss_get_vendor_profile_or_default( $not_vendor ),
		"somebody who is not a vendor still resolves to null (user {$not_vendor})"
	);
	$html = do_shortcode( '[wpss_vendor_profile id="' . $not_vendor . '"]' );
	wpss_t(
		false !== stripos( $html, 'Vendor not found' ),
		'and their profile page still says so, rather than inventing a seller'
	);
} else {
	echo "  SKIP  every user on this install is a vendor.\n";
}

wpss_t( null === wpss_get_vendor_profile_or_default( 0 ), 'user id 0 resolves to null rather than a phantom profile' );
wpss_t( null === wpss_get_vendor_profile_or_default( 99999999 ), 'a user id that does not exist resolves to null' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
