<?php
/**
 * A buyer sees the whole portfolio project; an admin reads the whole review before approving.
 *
 * Run: wp eval-file tests/test-portfolio-and-review-full-content.php
 *
 * Basecamp 10513127686: the public portfolio showed the first image and a 20-word
 * description, with no way to reach the rest. Basecamp 10513123185: review
 * moderation cut a review to 30 words, so an admin approved text they had not read.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// Portfolio: three images and a 60-word story must all reach the page.
$words = implode( ' ', array_map( static fn( $i ) => "story{$i}", range( 1, 60 ) ) );
$items = array(
	array(
		'id'          => 1,
		'title'       => 'Long project',
		'description' => $words,
		'media'       => array(
			array( 'url' => 'http://x.test/a.jpg', 'large' => 'http://x.test/a-large.jpg' ),
			array( 'url' => 'http://x.test/b.jpg', 'large' => 'http://x.test/b-large.jpg' ),
			array( 'url' => 'http://x.test/c.jpg', 'large' => 'http://x.test/c-large.jpg' ),
		),
		'tags'        => array( 'logo' ),
		'is_featured' => true,
	),
);
foreach ( array( 1, 4 ) as $count ) {
	$portfolio_items = array_pad( $items, $count, $items[0] );
	$vendor_id       = 1;
	ob_start();
	include dirname( __DIR__ ) . '/templates/partials/vendor-portfolio.php';
	$html = (string) ob_get_clean();
	$check( "{$count} item(s): the third image is in the page", false !== strpos( $html, 'c-large.jpg' ) );
	$check( "{$count} item(s): the last word of the description is in the page", false !== strpos( $html, 'story60' ) );
	$check( "{$count} item(s): a control opens the project", false !== strpos( $html, 'data-wpss-portfolio-open' ) );
}

// Review moderation: the whole review is printed, not 30 words.
$page   = new \WPSellServices\Admin\Pages\ReviewModerationPage();
$method = new ReflectionMethod( $page, 'render_review_row' );
$row    = (object) array(
	'id'          => 1,
	'rating'      => 5,
	'review'      => $words,
	'status'      => 'pending',
	'reviewer_id' => 0,
	'service_id'  => 0,
	'created_at'  => '2026-11-17 00:00:00',
);
ob_start();
$method->invoke( $page, $row );
$html = (string) ob_get_clean();
$check( 'moderation row shows the last word of a 60-word review', false !== strpos( $html, 'story60' ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nAll passed\n";
exit( $fails ? 1 : 0 );
