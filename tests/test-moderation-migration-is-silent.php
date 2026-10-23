<?php
/**
 * The upgrade migration that returns published-but-unapproved services to the
 * review queue is the system acting, not a member submitting. It can fire on
 * any member's request, so even with a vendor logged in it must not announce
 * a submit or erase the rejection reason. A member's pending save with
 * moderation switched off is not a submit either.
 *
 * Run: wp eval-file tests/test-moderation-migration-is-silent.php
 *
 * Uses one live QA-owned service; it is put back on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ServiceModerationPage;
use WPSellServices\Core\Plugin;
use WPSellServices\Services\ModerationService;

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

$vendor_id = wpss_test_fixture_user( 'vendor' );
if ( ! $vendor_id ) {
	echo "SKIP  no seeded vendor on this site\n";
	return;
}

$service_id = (int) ( get_posts(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'author'      => $vendor_id,
		'numberposts' => 1,
		'fields'      => 'ids',
	)
)[0] ?? 0 );
if ( ! $service_id ) {
	echo "SKIP  no live service by the QA vendor\n";
	return;
}

// The migration sweeps the whole site; never let it move someone else's service.
$others = get_posts(
	array(
		'post_type'    => 'wpss_service',
		'post_status'  => 'publish',
		'fields'       => 'ids',
		'numberposts'  => 1,
		'post__not_in' => array( $service_id ),
		'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => ModerationService::META_MODERATION_STATUS,
				'value'   => array( 'pending', 'rejected' ),
				'compare' => 'IN',
			),
		),
	)
);
if ( $others ) {
	echo "SKIP  another published service is awaiting review; the migration would move it\n";
	return;
}

$post_before = get_post( $service_id );
$meta_before = array(
	ModerationService::META_MODERATION_STATUS => get_post_meta( $service_id, ModerationService::META_MODERATION_STATUS, true ),
	ModerationService::META_REJECTION_REASON  => get_post_meta( $service_id, ModerationService::META_REJECTION_REASON, true ),
);

$guards    = ModerationService::is_enabled() ? null : new ServiceModerationPage();
$announced = 0;
$count     = static function () use ( &$announced ) {
	++$announced;
};

$restore = static function () use ( $service_id, $post_before, $meta_before, $guards, $count ) {
	wp_set_current_user( 0 );
	remove_action( 'wpss_service_pending_moderation', $count );
	remove_filter( 'wpss_require_service_moderation', '__return_true' );
	remove_filter( 'wpss_require_service_moderation', '__return_false' );
	foreach ( $meta_before as $key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $service_id, $key );
		} else {
			update_post_meta( $service_id, $key, $value );
		}
	}
	wp_update_post(
		array(
			'ID'          => $service_id,
			'post_status' => $post_before->post_status,
		)
	);
	if ( $guards ) {
		remove_action( 'save_post_wpss_service', array( $guards, 'set_default_moderation_status' ), 10 );
		remove_action( 'pre_get_posts', array( $guards, 'filter_frontend_queries' ) );
		remove_action( 'save_post_wpss_service', array( $guards, 'approve_admin_publish' ), 20 );
		remove_filter( 'wp_insert_post_data', array( $guards, 'intercept_publish' ), 10 );
	}
};

add_filter( 'wpss_require_service_moderation', '__return_true' );
if ( $guards ) {
	$guards->register_guards();
}
add_action( 'wpss_service_pending_moderation', $count );

// A live service an admin rejected, still published (the stranded shape the migration repairs).
wp_set_current_user( 0 );
update_post_meta( $service_id, ModerationService::META_MODERATION_STATUS, ModerationService::STATUS_REJECTED );
update_post_meta( $service_id, ModerationService::META_REJECTION_REASON, 'QA-RFT reason' );

// The upgrade fires on a vendor's request.
wp_set_current_user( $vendor_id );
$migrate = new ReflectionMethod( Plugin::class, 'migrate_unapproved_published_services' );
$migrate->invoke( null );
clean_post_cache( $service_id );

$check( 'migration moved the stranded service to pending', 'pending' === get_post_status( $service_id ), (string) get_post_status( $service_id ) );
$check( 'migration announced no submit', 0 === $announced, "fired {$announced}x" );
$check( 'rejection is kept', ModerationService::STATUS_REJECTED === get_post_meta( $service_id, ModerationService::META_MODERATION_STATUS, true ) );
$check( 'rejection reason is kept', 'QA-RFT reason' === get_post_meta( $service_id, ModerationService::META_REJECTION_REASON, true ) );
$check( 'the request user is put back', $vendor_id === get_current_user_id() );

// A member's pending save while moderation is off is not a submit.
remove_filter( 'wpss_require_service_moderation', '__return_true' );
add_filter( 'wpss_require_service_moderation', '__return_false' );
delete_post_meta( $service_id, ModerationService::META_MODERATION_STATUS );
$announced = 0;
( new ServiceModerationPage() )->set_default_moderation_status( $service_id, get_post( $service_id ), true );
$check( 'pending save with moderation off announces nothing', 0 === $announced, "fired {$announced}x" );
