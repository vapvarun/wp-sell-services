<?php
/**
 * The app can edit a service's FAQs over REST (Basecamp 10545978302).
 *
 * POST/PUT /wpss/v1/services ignored `faqs`, so FAQs written in the app were
 * dropped and GET /services/{id} did not return them. save_service_meta() now
 * caps the list with wpss_enforce_service_limits() and calls the existing
 * ServiceManager::save_faqs() writer: `faqs: []` clears, an absent key leaves
 * the stored FAQs alone.
 *
 * Run: wp eval-file tests/test-rest-service-faqs-saved.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f       = wpss_test_fixtures();
$service = ( $f->service )( 'draft' );
$stored  = static fn() => (array) ( get_post_meta( $service, '_wpss_faqs', true ) ?: array() );
$put     = static function ( array $body ) use ( $service ) {
	$req = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	return rest_do_request( $req );
};

wp_set_current_user( $f->admin );

$res = $put( array( 'faqs' => array( array( 'question' => 'QA-RFT How long?', 'answer' => '<b>Three</b> days<script>x</script>' ) ) ) );
$check( 'PUT with faqs answers 200 (got ' . $res->get_status() . ')', 200 === $res->get_status() );
$faqs = $stored();
$check( 'the FAQ is stored', 1 === count( $faqs ) && 'QA-RFT How long?' === ( $faqs[0]['question'] ?? '' ), wp_json_encode( $faqs ) );
$check( 'the answer goes through the writer\'s sanitizer', false === strpos( (string) ( $faqs[0]['answer'] ?? '' ), '<script' ) );

$get = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/services/' . $service ) );
$check( 'GET /services/{id} returns the FAQs', 'QA-RFT How long?' === ( $get->get_data()['faqs'][0]['question'] ?? '' ), wp_json_encode( $get->get_data()['faqs'] ?? null ) );

$put( array( 'title' => get_the_title( $service ) ) );
$check( 'a PUT without faqs leaves them alone', 1 === count( $stored() ) );

$limit = static fn() => 2;
add_filter( 'wpss_service_max_faq', $limit, 999 );
$res = $put( array( 'faqs' => array_fill( 0, 4, array( 'question' => 'QA-RFT q', 'answer' => 'a' ) ) ) );
remove_filter( 'wpss_service_max_faq', $limit, 999 );
$check( 'the FAQ cap applies over REST', 2 === count( $stored() ), (string) count( $stored() ) );

$put( array( 'faqs' => array( array( 'question' => 'QA-RFT kept', 'answer' => 'yes' ), array( 'question' => '', 'answer' => ' <p></p> ' ) ) ) );
$check( 'a blank FAQ row is dropped, not stored', 1 === count( $stored() ) && 'QA-RFT kept' === ( $stored()[0]['question'] ?? '' ), wp_json_encode( $stored() ) );

$put( array( 'faqs' => array() ) );
$check( 'faqs: [] clears them', array() === $stored() );

wp_set_current_user( 1 );
echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
