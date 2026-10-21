<?php
/**
 * Every rating reader counts the same reviews: approved buyer-to-vendor only.
 *
 * Run: wp eval-file tests/test-rating-single-source.php
 *
 * ponytail audit of Free: five readers re-ran the rating SQL and only some
 * filtered review_type. Seeds one approved vendor_to_customer 1-star row
 * (QA-RATING) and asserts none of them move. Removes it afterwards.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\ReviewRepository;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$t = $wpdb->prefix . 'wpss_reviews';

$svc = null;
foreach ( get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 50 ) ) as $p ) {
	if ( wpss_is_vendor( (int) $p->post_author ) ) {
		$svc = $p;
		break;
	}
}
$vendor = (int) $svc->post_author;
$repo   = new ReviewRepository();

$read = static function () use ( $svc, $vendor, $repo ) {
	wpss_recount_service_rating( $svc->ID );
	return array(
		'repo_service' => $repo->rating_stats( 'service_id', $svc->ID ),
		'repo_vendor'  => $repo->rating_stats( 'vendor_id', $vendor ),
		'recount'      => wpss_get_service_rating( $svc->ID ),
		'rest'         => wpss_rest_service_rating( $svc->ID ),
	);
};

$before = $read();

$wpdb->insert( $t, array( 'order_id' => null, 'reviewer_id' => 0, 'reviewer_name' => 'QA-RATING', 'reviewee_id' => 0, 'service_id' => $svc->ID, 'customer_id' => 0, 'vendor_id' => $vendor, 'rating' => 1, 'review' => 'QA-RATING', 'review_type' => 'vendor_to_customer', 'status' => 'approved' ) );
$row = (int) $wpdb->insert_id;

$after = $read();
$check( 'repository service stats ignore a vendor-to-customer review', $before['repo_service'] === $after['repo_service'] );
$check( 'repository vendor stats ignore it', $before['repo_vendor'] === $after['repo_vendor'] );
$check( 'the stored service rating ignores it', $before['recount'] === $after['recount'] );
$check( 'the REST service rating ignores it', $before['rest'] === $after['rest'] );

$check( 'seller levels read the count from the repository', ( new \WPSellServices\Services\SellerLevelService() )->get_vendor_stats( $vendor )->total_reviews === $before['repo_vendor']['count'] );

$wpdb->delete( $t, array( 'id' => $row ) );
wpss_recount_service_rating( $svc->ID );

exit( $fails ? 1 : 0 );
