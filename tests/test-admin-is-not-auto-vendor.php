<?php
/**
 * An administrator is a vendor only once they sell (Basecamp 10475419083).
 *
 * Run: wp eval-file tests/test-admin-is-not-auto-vendor.php
 *
 * Every administrator used to get an ACTIVE vendor profile on their first
 * wp-admin page load, so the owner was listed in Sell Services > Vendors, the
 * public directory and every vendor count. Now the profile is made the first
 * time an administrator authors a service, or at once when they click Start
 * Selling, even with vendor approval on.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\VendorService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$has_row  = static fn( int $id ): bool => (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$profiles} WHERE user_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$admin    = static fn(): int => (int) wp_insert_user( array( 'user_login' => 'qa_adm_' . wp_generate_password( 6, false, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-adm-' . wp_generate_password( 6, false, false ) . '@example.test', 'role' => 'administrator' ) );
$made     = array();

// 1. Opening wp-admin makes nobody a vendor.
$made[] = $owner = $admin();
wp_set_current_user( $owner );
do_action( 'admin_init' );
$check( 'a fresh administrator opening wp-admin gets no vendor profile', ! $has_row( $owner ) );
$check( 'and is not a vendor', ! wpss_is_vendor( $owner ) );

// 2. Authoring a service makes them one.
$service = (int) wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'QA admin service', 'post_author' => $owner ) );
$check( 'authoring a service gives the administrator an active profile', $has_row( $owner ) && wpss_is_vendor( $owner ) );
wp_delete_post( $service, true );

// 3. Start Selling is instant for an administrator, even when approval is required.
$made[] = $second = $admin();
add_filter( 'wpss_auto_approve_vendors', '__return_false', 99 );
$check( 'Start Selling registers the administrator', ( new VendorService() )->register( $second ) );
remove_filter( 'wpss_auto_approve_vendors', '__return_false', 99 );
$check( 'and approves them at once, approval mode or not', wpss_is_vendor( $second ) );

// 4. A member who is not an administrator gains nothing by authoring.
$made[] = $member = (int) wp_insert_user( array( 'user_login' => 'qa_mem_' . wp_generate_password( 6, false, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$service = (int) wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'QA member service', 'post_author' => $member ) );
$check( 'a non-administrator author gets no profile from the hook', ! $has_row( $member ) );
wp_delete_post( $service, true );

// 5. Closed registration shuts the door on applicants, never on the site owner.
$settings = get_option( 'wpss_vendor', array() );
update_option( 'wpss_vendor', array_merge( (array) $settings, array( 'vendor_registration' => 'closed' ) ) );
$made[] = $closed_owner = $admin();
$made[] = $closed_member = (int) wp_insert_user( array( 'user_login' => 'qa_cl_' . wp_generate_password( 6, false, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$check( 'closed: a member cannot apply', ! ( new VendorService() )->register( $closed_member ) && ! wpss_is_vendor( $closed_member ) );
$check( 'closed: the administrator can still start selling', ( new VendorService() )->register( $closed_owner ) && wpss_is_vendor( $closed_owner ) );
$made[] = $wizard_owner = $admin();
wp_set_current_user( $wizard_owner );
$check( 'closed: the wizard opens for the administrator instead of a not-a-vendor notice', false === stripos( ( new \WPSellServices\Frontend\ServiceWizard() )->render_wizard(), 'must be a registered vendor' ) && wpss_is_vendor( $wizard_owner ) );
update_option( 'wpss_vendor', (array) $settings );

// 6. A suspended administrator stays suspended: the owner bypass does not reinstate.
$made[] = $suspended = $admin();
$vs = new VendorService();
$vs->ensure_admin_profile( $suspended );
$wpdb->update( $profiles, array( 'status' => 'suspended' ), array( 'user_id' => $suspended ) );
$check( 'a suspended administrator is not reinstated by ensure_admin_profile()', $vs->ensure_admin_profile( $suspended ) && 'suspended' === $vs->get_vendor_status( $suspended ) );
// Not asserted for register(): wpss_vendor_status_block() exempts administrators from every standing gate by design.

$check( 'the admin_init self-heal is gone from Plugin.php', false === strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Core/Plugin.php' ), 'maybe_auto_vendor_admin' ) );

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $made as $id ) {
	$wpdb->delete( $profiles, array( 'user_id' => $id ) );
	wp_delete_user( $id );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
