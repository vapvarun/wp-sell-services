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

// 1. Sign-up closed: both pages give a next step and name the site.
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

$check( 'admin order AJAX is administrator-only', false !== strpos( file_get_contents( $root . '/src/Admin/OrderScreen.php' ), "! current_user_can( 'manage_options' )" ) );
$check( 'bulk order actions ask first and skip same-status orders', false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), 'wpss-orders-bulk' ) && false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), '$new_status === $order->status' ) );
$check( 'vendor registration page does not print a doomed form', false !== strpos( file_get_contents( $root . '/src/Frontend/Shortcodes.php' ), 'Sign-up is closed' ) );
$check( 'orders export reuses the list filter and is admin-only', false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), 'get_filter_sql()' ) && false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), "check_admin_referer( 'wpss_export_orders' )" ) );
$check( 'live-edit re-review is a setting, default off', false === wpss_get_option( 'vendor', 're_review_live_edits' ) && method_exists( \WPSellServices\Services\ModerationService::class, 're_reviews_live_edits' ) );
$check( 'cancel window is one setting, default 24', 24 === wpss_cancel_window_hours() && false !== strpos( wpss_cancel_window_rule(), '24 hours' ) );
$check( 'suspended money access is a setting, default off', false === wpss_get_option( 'vendor', 'suspended_money_access' ) && false === wpss_suspended_vendor_money_access( 1 ) );
$check( 'application questions are a setting, default off', false === wpss_get_option( 'vendor', 'application_fields' ) );
$check( 'sign-up verification and admin notice are settings, default off', false === wpss_get_option( 'vendor', 'verify_email_for_vendors' ) && false === wpss_get_option( 'vendor', 'signup_notify_admin' ) && ! wpss_email_verification_pending( 1 ) );
$check( 'seller cancellation request exists and statuses are limited to work in flight', method_exists( \WPSellServices\Services\OrderService::class, 'request_seller_cancellation' ) && array( 'in_progress', 'late', 'revision_requested' ) === \WPSellServices\Services\OrderService::seller_cancellation_statuses() );
$check( 'seller cancellation request has no auto-cancel timer', false !== strpos( file_get_contents( $root . '/src/Services/OrderWorkflowManager.php' ), 'A seller\'s request has no timer' ) );
$check( 'one phase-lock test feeds the shared pay refusal', function_exists( 'wpss_is_order_phase_locked' ) && false !== strpos( file_get_contents( $root . '/src/functions/orders.php' ), 'wpss_is_order_phase_locked( $order )' ) );
$check( 'disputes-off is part of the shared dispute guard', false !== strpos( file_get_contents( $root . '/src/Services/DisputeService.php' ), 'Disputes are turned off on this site.' ) );
$check( 'proposal reject only moves a pending proposal', 1 === preg_match( "/STATUS_REJECTED.*?'status' => self::STATUS_PENDING/s", file_get_contents( $root . '/src/Services/ProposalService.php' ) ) );
$check( 'order quantity ceiling is applied by the shared pricer', false !== strpos( file_get_contents( $root . '/src/Checkout/CheckoutIntentService.php' ), "apply_filters( 'wpss_max_order_quantity'" ) );
$check( 'single-service purchase removes only its cart line', method_exists( \WPSellServices\Checkout\ServiceCart::class, 'remove_line' ) );
$check( 'delivery takes a per-order lock', false !== strpos( file_get_contents( $root . '/src/Services/DeliveryService.php' ), "'wpss_delivery_' . \$order_id" ) );
$check( 'long lists are paged: thread page, withdrawal count, wallet page', method_exists( \WPSellServices\Services\ConversationService::class, 'get_thread_page' ) && method_exists( \WPSellServices\Services\EarningsService::class, 'count_withdrawals' ) && false !== strpos( file_get_contents( $root . '/src/Admin/Pages/VendorsPage.php' ), 'wallet_page' ) );
$check( 'a declined seller request restores late or revision-requested status', false !== strpos( file_get_contents( $root . '/src/Services/OrderService.php' ), "'from_status'" ) && false !== strpos( file_get_contents( $root . '/src/Services/OrderService.php' ), 'The seller may ask the buyer to cancel (request_seller_cancellation())' ) );
$check( 'orders CSV uses the row currency decimals', false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), "wpss_get_currency_decimals( (string) \$row->currency )" ) );
$check( 'bulk order and dispute results print at once, not via a late admin_notices hook', false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), 'function print_bulk_notice' ) );
$check( 'order detail status form asks first for Completed and Cancelled', false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), 'data-wpss-confirm-cancelled' ) && false !== strpos( file_get_contents( $root . '/assets/js/admin.js' ), 'wpss-order-status-form' ) );
$check( 'refused Assign and Retry refund carry the reason back; Mark refund sent asks first', 2 === substr_count( file_get_contents( $root . '/src/Admin/Admin.php' ), '$args += $this->signed_error_args(' ) && false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), 'data-wpss-order-confirm' ) );
$check( 'refusal reason in the order-page link is signed', false !== strpos( file_get_contents( $root . '/src/Admin/Admin.php' ), 'hash_equals( $this->error_signature(' ) );
$check( 'wizard validates currency and refuses a change under existing orders', false !== strpos( $wizard, "array_key_exists( \$currency, wpss_get_currencies() )" ) && false !== strpos( $wizard, 'count_by_status()' ) );
$check( 'wizard stays on a step that failed to save and confirms the demo import', false !== strpos( file_get_contents( $root . '/assets/js/admin-wizard.js' ), '!response.success' ) && false !== strpos( file_get_contents( $root . '/assets/js/admin-wizard.js' ), 'confirmDemo' ) );
$wpss_vo = wpss_get_option( 'vendor', 'vendor_registration' );
$wpss_set = get_option( 'wpss_vendor', array() );
$wpss_set['vendor_registration'] = 'closed';
update_option( 'wpss_vendor', $wpss_set );
$wpss_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
$wpss_buyer = get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) )[0];
$check( 'closed registration refuses a buyer but never the site owner, in one helper', wpss_vendor_registration_closed_for( (int) $wpss_buyer ) && ! wpss_vendor_registration_closed_for( (int) $wpss_admin ) );
$wpss_set['vendor_registration'] = $wpss_vo ?: 'open';
update_option( 'wpss_vendor', $wpss_set );
$check( 'no door re-reads the closed mode on its own', 0 === preg_match( "/'closed' [!=]==? \\$\\w*registration_mode/", file_get_contents( $root . '/src/Frontend/UnifiedDashboard.php' ) . file_get_contents( $root . '/src/Frontend/ServiceWizard.php' ) . file_get_contents( $root . '/templates/single-request.php' ) ) );
// Live-edit re-review: a title edit by a non-admin vendor on an approved, published service.
$wpss_vendors = get_users( array( 'role' => 'wpss_vendor', 'capability__not_in' => array( 'manage_options' ), 'number' => 1, 'fields' => 'ID' ) );
if ( $wpss_vendors ) {
	$wpss_prev_user = get_current_user_id();
	$wpss_mod       = get_option( 'wpss_vendor', array() );
	$wpss_was_mod   = $wpss_mod;
	$wpss_mod['require_service_moderation'] = true;
	$wpss_mod['re_review_live_edits']       = false;
	update_option( 'wpss_vendor', $wpss_mod );
	( new \WPSellServices\Admin\Pages\ServiceModerationPage() )->register_guards(); // Boot ran before this test turned moderation on.
	wp_set_current_user( (int) $wpss_vendors[0] );
	$wpss_svc = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_title' => 'Live edit probe', 'post_status' => 'draft', 'post_author' => (int) $wpss_vendors[0] ) );
	update_post_meta( $wpss_svc, \WPSellServices\Admin\Pages\ServiceModerationPage::META_KEY, 'approved' );
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $wpss_svc ) );
	clean_post_cache( $wpss_svc );
	wp_update_post( array( 'ID' => $wpss_svc, 'post_title' => 'Live edit probe renamed' ) );
	$check( 'title edit on an approved live service stays published with Re-review off', 'publish' === get_post_status( $wpss_svc ) );
	( new \WPSellServices\Services\ModerationService() )->requeue_live_edit( $wpss_svc );
	$check( 'meta-only (add-on) edit also stays published with Re-review off', 'publish' === get_post_status( $wpss_svc ) );
	$wpss_mod['re_review_live_edits'] = true;
	update_option( 'wpss_vendor', $wpss_mod );
	wp_update_post( array( 'ID' => $wpss_svc, 'post_title' => 'Live edit probe again' ) );
	$check( 'title edit goes to review with Re-review on', 'pending' === get_post_status( $wpss_svc ) );
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $wpss_svc ) );
	clean_post_cache( $wpss_svc );
	$wpss_mod['re_review_live_edits'] = false;
	update_option( 'wpss_vendor', $wpss_mod );
	wp_update_post( array( 'ID' => $wpss_svc, 'post_status' => 'publish' ) );
	$check( 'draft back to live still goes to review with Re-review off', 'pending' === get_post_status( $wpss_svc ) );
	wp_delete_post( $wpss_svc, true );
	update_option( 'wpss_vendor', $wpss_was_mod );
	wp_set_current_user( $wpss_prev_user );
}
$wpss_ov = file_get_contents( $root . '/templates/order/order-view.php' );
$check( 'cancel request modal follows the owner window, not a fixed 24 hours', false === strpos( $wpss_ov, '<= 24' ) && 2 === substr_count( $wpss_ov, '<= wpss_cancel_window_hours()' ) );
$check( 'closed cancellation is written out beside the button, not only a tooltip', false !== strpos( $wpss_ov, "'hint'  => \$closed_reason" ) && false !== strpos( $wpss_ov, 'wpss-order-view__action-hint' ) );
$ah = file_get_contents( $root . '/src/Frontend/AjaxHandlers.php' );
$check( 'AJAX buyer cancel is limited to pre-delivery statuses', false !== strpos( $ah, '$buyer_immediate' ) );

update_option( 'users_can_register', $was_open );
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
