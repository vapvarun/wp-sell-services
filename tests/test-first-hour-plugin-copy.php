<?php
/**
 * First-hour owner walk fixes: closed sign-up copy, honest notices, one category list.
 *
 * Run: wp eval-file tests/test-first-hour-plugin-copy.php
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\Shortcodes;
use WPSellServices\PostTypes\ServicePostType;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

wp_set_current_user( 0 );
$was_open = get_option( 'users_can_register' );
$checkout = wpss_get_option( 'general', 'checkout_account_creation' );
$sc       = new Shortcodes();

// 1. Sign-up closed: both pages give a next step and name the site.$ah = file_get_contents( $root . '/src/Frontend/AjaxHandlers.php' );
$check( 'AJAX buyer cancel is limited to pre-delivery statuses', false !== strpos( $ah, '$buyer_immediate' ) );

update_option( 'users_can_register', 0 );
add_filter( 'wpss_checkout_creates_accounts', '__return_false', 99 );

$message = wpss_signup_closed_message();
$check( 'closed message names the site', false !== strpos( $message, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) );
$check( 'closed message says who to ask', false !== stripos( $message, 'contact' ) );

$register = $sc->register_form();
$check( 'Create account explains and links to Sign in', false !== strpos( $register, esc_html( $message ) ) && false !== strpos( $register, esc_url( wp_login_url() ) ) );
$check( 'Create account no longer ends on the bare disabled sentence', false === strpos( $register, 'Registration is currently disabled' ) );

$login = $sc->login_form();
$check( 'login page carries the closed message', false !== strpos( $login, esc_html( $message ) ) );
$check( 'login page offers no Create an account link', false === strpos( $login, 'Create an account' ) );

add_filter( 'wpss_signup_closed_message', static fn() => 'Write to help@example.test' );
$check( 'the message is filterable', false !== strpos( $sc->login_form(), 'Write to help@example.test' ) );
remove_all_filters( 'wpss_signup_closed_message' );

// 2. Guest checkout creates accounts: the login page does not claim sign-up is closed.
remove_filter( 'wpss_checkout_creates_accounts', '__return_false', 99 );
add_filter( 'wpss_checkout_creates_accounts', '__return_true', 99 );
$check( 'no closed notice on login while checkout makes accounts', false === strpos( $sc->login_form(), esc_html( $message ) ) );
remove_filter( 'wpss_checkout_creates_accounts', '__return_true', 99 );

// 3. Sign-up open: unchanged, shows Create an account and no closed notice.
update_option( 'users_can_register', 1 );
add_filter( 'wpss_checkout_creates_accounts', '__return_false', 99 );
$open_login = $sc->login_form();
$check( 'open sign-up still offers Create an account', false !== strpos( $open_login, 'Create an account' ) );
$check( 'open sign-up shows no closed notice', false === strpos( $open_login, esc_html( $message ) ) );
remove_filter( 'wpss_checkout_creates_accounts', '__return_false', 99 );

// 4. One category list for the activation seed and the wizard.
$names = ( new ServicePostType() )->get_default_category_names();
$check( 'default category list has the eight starter names', 8 === count( $names ) && in_array( 'Business', $names, true ) );
add_filter( 'wpss_default_service_categories', static fn() => array( 'Only One' ), 99 );
$check( 'the list stays filterable', array( 'Only One' ) === ( new ServicePostType() )->get_default_category_names() );
remove_all_filters( 'wpss_default_service_categories' );

$wizard = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Pages/SetupWizardPage.php' );
$check( 'wizard reads the shared list, not its own near-duplicates', false !== strpos( $wizard, 'get_default_category_names()' ) && false === strpos( $wizard, "'Graphic Design'" ) );

// 5. Wording that promised more than the plugin knows.
$admin = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Admin.php' );
$check( 'terms notice does not claim buyers are paying', false === strpos( $admin, 'Buyers are paying' ) );
$settings = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Settings.php' ) . $wizard;
$check( 'vendor registration label does not say anyone can register', false === stripos( $settings, 'anyone can register' ) );

// 6. One sign-up name field is split, not stored whole as the first name.
$made = array();
foreach ( array(
	'Maria del Carmen Ruiz' => array( 'Maria', 'del Carmen Ruiz' ),
	'Casey Buyer'           => array( 'Casey', 'Buyer' ),
	'Prince'                => array( 'Prince', '' ),
) as $typed => $expect ) {
	$uid = \WPSellServices\Frontend\PublicSignup::create_account(
		array(
			'email'        => 'split-' . wp_generate_password( 6, false, false ) . '@example.test',
			'password'     => wp_generate_password(),
			'display_name' => $typed,
		)
	);
	if ( is_wp_error( $uid ) ) {
		$check( 'sign-up with "' . $typed . '"', false, $uid->get_error_message() );
		continue;
	}
	$made[] = $uid;
	$check( '"' . $typed . '" is stored as first/last', array( get_user_meta( $uid, 'first_name', true ), get_user_meta( $uid, 'last_name', true ) ) === $expect );
	$check( 'and checkout prefill shows the same split', $expect === array( wpss_get_billing_address( $uid )['billing_first_name'], wpss_get_billing_address( $uid )['billing_last_name'] ) );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $made as $uid ) {
	wp_delete_user( $uid );
}

// 7. Owner-walk fixes: request caps, generated transaction id, bad order notice, vendor confirm.
$root = dirname( __DIR__ );
$admin_role = get_role( 'administrator' );
$check( 'staff hold the request caps', $admin_role && $admin_role->has_cap( 'edit_others_wpss_requests' ) );
foreach ( array( 'editor', 'author' ) as $r ) {
	$role = get_role( $r );
	$check( $r . ' cannot edit others\' buyer requests', ! $role || ! $role->has_cap( 'edit_others_wpss_requests' ) );
}
$check( 'generated transaction ids share one prefix', 'offline_manual_' === \WPSellServices\Integrations\Gateways\OfflineGateway::GENERATED_TRANSACTION_PREFIX );
$check( 'payment template hides generated ids', false !== strpos( file_get_contents( $root . '/templates/order/payment.php' ), 'GENERATED_TRANSACTION_PREFIX' ) );
foreach ( array( 'orders', 'sales' ) as $sec ) {
	$check( $sec . ' section tells the user about a bad order URL', false !== strpos( file_get_contents( $root . '/templates/dashboard/sections/' . $sec . '.php' ), 'That order was not found, or it is not yours.' ) );
}
$vp = file_get_contents( $root . '/src/Admin/Pages/VendorsPage.php' );
$check( 'Approve and Activate name their action', false !== strpos( $vp, 'Approve this vendor?' ) && false !== strpos( $vp, 'Reactivate this vendor?' ) );

update_option( 'users_can_register', $was_open );
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
