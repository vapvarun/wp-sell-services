<?php
/**
 * The request card offers Send Proposal only where the request page does (Basecamp 10549300372).
 *
 * content-request-card.php (archive, block, shortcode, load-more) showed Send Proposal to every
 * signed-in member: the request's own buyer and non-vendors too. It now follows single-request.php:
 * a vendor who is not the buyer, on an open request; a suspended vendor gets View Request.
 *
 * Run: wp eval-file tests/test-request-card-proposal-cta.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

use WPSellServices\Database\Repositories\VendorProfileRepository;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f          = wpss_test_fixtures();
$member     = wp_insert_user( array( 'user_login' => $f->tag . '_member', 'user_pass' => wp_generate_password(), 'user_email' => $f->tag . '_member@example.test', 'role' => 'subscriber' ) );
$f->users[] = (int) $member;
$request    = wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA-RFT request card CTA', 'post_author' => $f->buyer ) );
$f->posts[] = (int) $request;
update_post_meta( $request, '_wpss_status', 'open' );

// The card's call to action, as a viewer sees it.
$cta = static function ( int $viewer ) use ( $request ): string {
	global $post;
	wp_set_current_user( $viewer );
	clean_post_cache( $request );
	$post = get_post( $request ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	ob_start();
	wpss_get_template_part( 'content', 'request-card' );
	$html = (string) ob_get_clean();
	wp_reset_postdata();
	return preg_match( '/wpss-request-card__actions.*?<a[^>]*>\s*([^<]+?)\s*<\/a>/s', $html, $m ) ? $m[1] : '(none)';
};

try {
	$check( 'A vendor sees Send Proposal on an open request', 'Send Proposal' === $cta( $f->vendor ), $cta( $f->vendor ) );
	$check( 'The request\'s own buyer does not', 'View Request' === $cta( $f->buyer ), $cta( $f->buyer ) );
	$check( 'A member who is not a vendor does not', 'View Request' === $cta( (int) $member ), $cta( (int) $member ) );

	wp_update_post( array( 'ID' => $request, 'post_author' => $f->vendor ) );
	$check( 'A vendor viewing their own request does not', 'View Request' === $cta( $f->vendor ), $cta( $f->vendor ) );
	wp_update_post( array( 'ID' => $request, 'post_author' => $f->buyer ) );

	update_post_meta( $request, '_wpss_status', 'closed' );
	$check( 'Nobody is offered it on a closed request', 'View Request' === $cta( $f->vendor ), $cta( $f->vendor ) );
	update_post_meta( $request, '_wpss_status', 'open' );

	( new VendorProfileRepository() )->upsert( $f->vendor, array( 'status' => 'suspended' ) );
	wp_cache_flush();
	$check( 'A suspended vendor gets View Request', 'View Request' === $cta( $f->vendor ), $cta( $f->vendor ) );
} finally {
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
