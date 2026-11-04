<?php
/**
 * The notification list pages by number, not only Previous and Next.
 *
 * An inbox of 108 pages offered "Page 1 of 108 Next", so page 80 was 79 clicks
 * away. The shared list (dashboard, My Account, admin) now prints the first and
 * last page and the neighbours of the current one.
 *
 * Run: wp eval-file tests/test-notifications-numbered-pages.php
 *
 * Seeds 45 notifications on a QA-RFT user (3 pages at 20); removes them after.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$user  = (int) wp_insert_user( array( 'user_login' => 'qa_rft_pager' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-rft-pager' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$table = $wpdb->prefix . 'wpss_notifications';
for ( $i = 0; $i < 45; $i++ ) {
	$wpdb->insert( $table, array( 'user_id' => $user, 'type' => 'new_message', 'title' => "QA-RFT {$i}", 'message' => 'QA-RFT', 'is_read' => 0, 'created_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB
}

$render = static function ( int $page ) use ( $user ): string {
	wp_set_current_user( $user );
	$_GET['notifications_page'] = $page;
	ob_start();
	require wpss_locate_template( 'partials/notifications-list.php' );
	unset( $_GET['notifications_page'] );
	return (string) ob_get_clean();
};

try {
	$html = $render( 2 );
	$check( 'page 2 is marked current', (bool) preg_match( '/aria-current="page"[^>]*>2</', $html ) );
	$check( 'page 1 and page 3 are links', (bool) preg_match( '/notifications_page=3[^>]*>3</', $html ) && false !== strpos( $html, '>1</a>' ) );
	$check( 'Previous and Next stay', false !== strpos( $html, 'wpss-pagination__link--prev' ) && false !== strpos( $html, 'wpss-pagination__link--next' ) );
} finally {
	$wpdb->delete( $table, array( 'user_id' => $user ) );
	wp_set_current_user( 0 );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user );
}

echo $fails ? "FAILED {$fails}\n" : "OK\n";
