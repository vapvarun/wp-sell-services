<?php
/**
 * The demo importer builds a believable catalog (Basecamp 10478804193).
 *
 * Run: wp eval-file tests/test-demo-import-shape.php
 *
 * It imported 20 services from 14 templates, so six were reworded copies ("I will
 * expertly ... that converts"), and the reassignment step left several on the
 * administrator. Now each template is imported once under the demo vendor for
 * its category, and Delete Demo Content takes the site back to none.
 *
 * Runs the real Settings > Import Demo Content handler, so it SKIPs on a site
 * that already has demo content (the importer refuses a second import).
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

if ( wpss_has_demo_content() ) {
	echo "SKIP  demo content is already installed; run on a site without it\n";
	return;
}

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// Call the AJAX handler in this process: wp_send_json() ends in wp_die(), which throws here.
$ajax = static function ( string $action ): array {
	wp_set_current_user( 1 );
	$_REQUEST['nonce'] = wp_create_nonce( 'wpss_demo_content' );
	add_filter( 'wp_doing_ajax', '__return_true' );
	$stop = static fn() => static function () {
		throw new RuntimeException( 'wp_die' );
	};
	add_filter( 'wp_die_ajax_handler', $stop );
	ob_start();
	try {
		( new \WPSellServices\Admin\Admin() )->{$action}();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- wp_die() ends the handler.
	}
	$out = (string) ob_get_clean();
	remove_filter( 'wp_die_ajax_handler', $stop );
	remove_filter( 'wp_doing_ajax', '__return_true' );

	return (array) json_decode( $out, true );
};
add_filter( 'pre_wp_mail', '__return_true' );

$demo_services = static fn(): array => get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => '_wpss_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);

$result   = $ajax( 'ajax_import_demo_content' );
$services = $demo_services();
$titles   = wp_list_pluck( $services, 'post_title' );
$check( 'the import succeeds', ! empty( $result['success'] ), wp_json_encode( $result ) );
$check( 'every demo title is unique (' . count( $titles ) . ')', count( $titles ) > 0 && count( $titles ) === count( array_unique( $titles ) ) );
$check( 'every demo service went live (or to review)', $services && ! array_filter( $services, static fn( $p ) => ! in_array( $p->post_status, array( 'publish', 'pending' ), true ) ), wp_json_encode( array_count_values( wp_list_pluck( $services, 'post_status' ) ) ) );
$check( 'every demo service has a main image', ! array_filter( $services, static fn( $p ) => ! has_post_thumbnail( $p ) ) );
$check( 'no reworded copy ("expertly", "that converts")', ! array_filter( $titles, static fn( $t ) => false !== stripos( $t, 'expertly' ) || false !== stripos( $t, 'that converts' ) ) );

$admins  = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
$authors = array_map( 'intval', wp_list_pluck( $services, 'post_author' ) );
$check( 'no demo service is authored by an administrator', ! array_intersect( $authors, array_map( 'intval', $admins ) ) );

$vendors = array_map( 'intval', get_users( array( 'meta_key' => '_wpss_demo_content', 'meta_value' => '1', 'fields' => 'ID' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$check( 'every demo vendor owns at least one service', $vendors && ! array_diff( $vendors, $authors ), wp_json_encode( array_count_values( $authors ) ) );

$again = $ajax( 'ajax_import_demo_content' );
$check( 'a second import is refused', 'already_imported' === ( $again['data']['code'] ?? '' ), wp_json_encode( $again ) );

wpss_delete_demo_content();
$check( 'deleting demo content leaves no demo service', ! $demo_services() && ! wpss_has_demo_content() );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
