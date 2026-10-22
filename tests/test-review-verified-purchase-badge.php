<?php
/**
 * A review written for an order shows "Verified Purchase"; an imported review
 * (no order) shows "Imported review" and never the badge. The docs promise both.
 *
 * Run: wp eval-file tests/test-review-verified-purchase-badge.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\Review;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$render = static function ( int $order_id ): string {
	$wpss_review = Review::from_db(
		(object) array(
			'id'          => 1,
			'order_id'    => $order_id,
			'service_id'  => 0,
			'reviewer_id' => 1,
			'rating'      => 5,
			'review'      => 'QA',
		)
	);
	ob_start();
	include dirname( __DIR__ ) . '/templates/partials/review-body.php';
	return (string) ob_get_clean();
};

$bought   = $render( 123 );
$imported = $render( 0 );

$check( 'an order review shows the Verified Purchase badge', false !== strpos( $bought, 'wpss-review-verified' ) );
$check( 'an order review is not marked imported', false === strpos( $bought, 'wpss-review-imported' ) );
$check( 'an imported review shows Imported review', false !== strpos( $imported, 'wpss-review-imported' ) );
$check( 'an imported review never shows the badge', false === strpos( $imported, 'wpss-review-verified' ) );

exit( $fails ? 1 : 0 );
