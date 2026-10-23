<?php
/**
 * The service wizard has one rule for who may edit: the author, or an admin who
 * keeps the seller as author. Image upload takes sellers in good standing only,
 * and the publish refusal gives a service that already holds a slot no limit error.
 *
 * Run: wp eval-file tests/test-wizard-owner-or-admin-and-upload-gate.php
 *
 * Creates one QA draft service owned by a QA seller and sets `wpss_account_status`
 * on that seller; both are put back on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\ServiceWizard;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$seller = wpss_test_fixture_user( 'vendor' );
if ( ! $seller ) {
	echo "SKIP  no seeded vendor on this site\n";
	return;
}
$prior  = get_user_meta( $seller, 'wpss_account_status', true );

wp_set_current_user( 0 );
$service_id = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'draft',
		'post_title'  => 'QA wizard owner ' . wp_generate_password( 5, false ),
		'post_author' => $seller,
	)
);

$restore = static function () use ( $service_id, $seller, $prior ) {
	if ( '' === $prior ) {
		delete_user_meta( $seller, 'wpss_account_status' );
	} else {
		update_user_meta( $seller, 'wpss_account_status', $prior );
	}
	if ( $service_id ) {
		wp_delete_post( $service_id, true );
	}
};

/** Run an AJAX handler and return its decoded JSON answer. */
$ajax = static function ( callable $handler, array $post ) {
	$_POST    = $post + array( 'nonce' => wp_create_nonce( 'wpss_service_wizard' ) );
	$_REQUEST = $_POST;
	$die      = static function () {
		return static function () {
			throw new RuntimeException( 'ajax-done' );
		};
	};
	add_filter( 'wp_die_ajax_handler', $die, 99 );
	add_filter( 'wp_doing_ajax', '__return_true' );
	ob_start();
	try {
		$handler();
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$out = (string) ob_get_clean();
	remove_filter( 'wp_die_ajax_handler', $die, 99 );
	remove_filter( 'wp_doing_ajax', '__return_true' );

	return json_decode( $out, true ) ?: array();
};

$wizard = new ServiceWizard();
$data   = wp_json_encode( array( 'title' => 'QA wizard owner edited', 'description' => 'QA body', 'packages' => array() ) );

// An admin saves a draft of the seller's service: allowed, and the seller stays the author.
wp_set_current_user( 1 );
$res = $ajax( array( $wizard, 'ajax_save_draft' ), array( 'service_id' => $service_id, 'data' => $data ) );
clean_post_cache( $service_id );
$check( 'an admin can save a seller\'s service in the wizard', ! empty( $res['success'] ), wp_json_encode( $res ) );
$check( 'and the seller is still its author', $seller === (int) get_post_field( 'post_author', $service_id ), (string) get_post_field( 'post_author', $service_id ) );

// Another member cannot.
$other = (int) get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID', 'exclude' => array( $seller, 1 ) ) )[0];
wp_set_current_user( $other );
$res = $ajax( array( $wizard, 'ajax_save_draft' ), array( 'service_id' => $service_id, 'data' => $data ) );
$check( 'another member cannot save it', empty( $res['success'] ), wp_json_encode( $res ) );

// Upload: a plain member and a suspended seller are refused before any file is read.
$_FILES = array( 'file' => array( 'name' => 'x.png', 'tmp_name' => '/nonexistent', 'type' => 'image/png', 'error' => 0, 'size' => 1 ) );
$res    = $ajax( array( $wizard, 'ajax_upload_gallery' ), array() );
$check( 'a member who is not a seller cannot upload service images', empty( $res['success'] ), wp_json_encode( $res ) );
update_user_meta( $seller, 'wpss_account_status', 'suspended' );
wp_set_current_user( $seller );
$res = $ajax( array( $wizard, 'ajax_upload_gallery' ), array() );
$check( 'a suspended seller cannot upload', empty( $res['success'] ) && false !== stripos( (string) ( $res['data']['message'] ?? '' ), 'suspended' ), wp_json_encode( $res ) );
delete_user_meta( $seller, 'wpss_account_status' );
$_FILES = array();

// Dead handler gone.
$check( 'the no-op remove-gallery handler is gone', ! method_exists( $wizard, 'ajax_remove_gallery' ) && ! has_action( 'wp_ajax_wpss_wizard_remove_gallery' ) );

// Publish refusal and the limit rule agree: a service already pending takes no new slot.
// Pending is set before the limit is lowered: going draft to pending at the limit is itself refused.
wp_update_post( array( 'ID' => $service_id, 'post_status' => 'pending' ) );
add_filter( 'wpss_vendor_can_create_service', '__return_false' );
$limit_text = wpss_service_limit_error( 0, $seller );
$check( 'a new service at the limit gets a limit error', '' !== $limit_text );
$check( 'a pending service at the limit takes no new slot', '' === wpss_service_limit_error( $service_id, $seller ) );
$check( 'pressing Publish on it is not refused for the limit', $limit_text !== wpss_service_publish_refusal( $service_id, $seller ), wpss_service_publish_refusal( $service_id, $seller ) );
remove_filter( 'wpss_vendor_can_create_service', '__return_false' );
wp_update_post( array( 'ID' => $service_id, 'post_status' => 'draft' ) );
add_filter( 'wpss_vendor_can_create_service', '__return_false' );
$check( 'a draft at the limit is refused for it', $limit_text === wpss_service_publish_refusal( $service_id, $seller ) );
remove_filter( 'wpss_vendor_can_create_service', '__return_false' );

// The dashboard page is a door too: it must ask the same rule as the wizard.
$render_create = static function ( int $as ) use ( $service_id ): string {
	wp_set_current_user( $as );
	$_GET['id'] = $service_id;
	$user_id    = $as;
	ob_start();
	include WP_PLUGIN_DIR . '/wp-sell-services/templates/dashboard/sections/create.php';
	$html = (string) ob_get_clean();
	unset( $_GET['id'] );

	return $html;
};
$denied = 'You do not have permission to edit this service.';
$check( 'the dashboard editor refuses a stranger', false !== strpos( $render_create( $other ), $denied ) );
$check( 'the dashboard editor opens for an admin on a seller\'s service', false === strpos( $render_create( 1 ), $denied ) );
wp_set_current_user( 1 );
