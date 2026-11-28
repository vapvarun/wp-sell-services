<?php
/**
 * The WPSS Dashboard lists what the owner still has to do, once (Basecamp 10550887795).
 *
 * After the wizard, the remaining setup work was scattered: a Terms notice, a sign-up notice, nothing
 * about an empty catalog. The Dashboard now shows one Finish setting up block, derived on every load
 * (nothing stored) from the setup-health problems, the missing Terms page and an empty catalog,
 * worded as each notice is. On the Dashboard those notices stand down, so nothing shows twice; on
 * other plugin screens they still show. The block disappears when nothing is left.
 *
 * Run: wp eval-file tests/test-finish-setting-up-block.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$admin    = new \WPSellServices\Admin\Admin();
$steps    = static fn() => method_exists( $admin, 'get_setup_steps' ) ? array_keys( $admin->get_setup_steps() ) : array( 'missing' );
$capture  = static function ( callable $fn ): string {
	ob_start();
	$fn();
	return (string) ob_get_clean();
};
$on       = static function ( string $screen_id ) {
	set_current_screen( $screen_id );
};
$dash     = 'toplevel_page_wp-sell-services';
$other    = 'sell-services_page_wpss-orders';
$notices  = static fn() => $capture(
	static function () use ( $admin ) {
		$admin->setup_health_notice();
	}
);
$page     = static fn() => $capture( array( $admin, 'render_dashboard_page' ) );
$no_svc   = static function ( $counts, $type ) {
	if ( 'wpss_service' === $type ) {
		$counts         = clone $counts;
		$counts->publish = 0;
	}
	return $counts;
};
$ucr_was   = get_option( 'users_can_register' );
$terms_was = get_option( 'wpss_terms_page' );
$dism_was  = get_user_meta( 1, '_wpss_terms_notice_dismissed', true );

try {
	wp_set_current_user( 1 );
	delete_user_meta( 1, '_wpss_terms_notice_dismissed' );

	// Start from a ready marketplace: sign-up on, a Terms page, services published.
	update_option( 'users_can_register', 1 );
	$check( 'A ready marketplace has no setup steps', array() === $steps(), wp_json_encode( $steps() ) );
	$on( $dash );
	$check( '  and the Dashboard shows no Finish setting up block', false === strpos( $page(), 'wpss-setup-steps' ) );

	// Break three things a fresh install has broken.
	update_option( 'users_can_register', 0 );
	update_option( 'wpss_terms_page', 0 );
	add_filter( 'wp_count_posts', $no_svc, 10, 2 );

	$s = $steps();
	$check( 'Sign-up off, no Terms page, no services: all three are steps', in_array( 'signup', $s, true ) && ( ! wpss_terms_notice_due() || in_array( 'terms', $s, true ) ) && in_array( 'services', $s, true ), wp_json_encode( $s ) );

	$html = $page();
	$check( 'The Dashboard block lists them above Action items', false !== strpos( $html, 'Finish setting up' ) && strpos( $html, 'wpss-setup-steps' ) < strpos( $html, 'Action items' ) );
	$check( '  with the notices\' own wording', false !== strpos( $html, esc_html__( 'Visitors cannot create accounts', 'wp-sell-services' ) ) && false !== strpos( $html, 'post-new.php?post_type=wpss_service' ) );
	$check( 'On the Dashboard the same items are not repeated as notices', '' === trim( $notices() ) );
	$on( $other );
	$check( 'On another plugin screen the sign-up notice still shows', false !== strpos( $notices(), 'Visitors cannot create accounts' ) );
	$on( $dash );

	if ( wpss_terms_notice_due() ) {
		$check( 'The Terms row can be dismissed like its notice', false !== strpos( $html, 'data-notice="terms"' ) );
		update_user_meta( 1, '_wpss_terms_notice_dismissed', ( new ReflectionMethod( $admin, 'live_gateway_signature' ) )->invoke( $admin ) );
		$check( '  and once dismissed it leaves the block', ! in_array( 'terms', $steps(), true ) );
		delete_user_meta( 1, '_wpss_terms_notice_dismissed' );
	}

	// Fix each one: its row goes.
	update_option( 'users_can_register', 1 );
	$check( 'Sign-up turned on: its row goes', ! in_array( 'signup', $steps(), true ) );
	remove_filter( 'wp_count_posts', $no_svc, 10 );
	$check( 'A service published: its row goes', ! in_array( 'services', $steps(), true ) );
	update_option( 'wpss_terms_page', $terms_was );
	$check( 'Terms page mapped: nothing left, no block', array() === $steps() && false === strpos( $page(), 'wpss-setup-steps' ), wp_json_encode( $steps() ) );
} finally {
	remove_filter( 'wp_count_posts', $no_svc, 10 );
	update_option( 'users_can_register', $ucr_was );
	update_option( 'wpss_terms_page', $terms_was );
	'' === $dism_was ? delete_user_meta( 1, '_wpss_terms_notice_dismissed' ) : update_user_meta( 1, '_wpss_terms_notice_dismissed', $dism_was );
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
