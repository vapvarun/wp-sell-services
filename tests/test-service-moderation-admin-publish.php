<?php
/**
 * An administrator's Publish approves, Quick Edit no longer rewrites moderation,
 * and Skip to full editor saves a draft (Basecamp 10398957993, 10398958911,
 * 10398959570, 10398686546).
 *
 * Run: wp eval-file tests/test-service-moderation-admin-publish.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// Moderation on for this script only, and every fixture counts as complete.
add_filter( 'wpss_require_service_moderation', '__return_true' );
add_filter( 'wpss_service_publish_errors', '__return_empty_array' );
$page            = new \WPSellServices\Admin\Pages\ServiceModerationPage();
$state           = static fn( $id ) => wpss_get_service_moderation_state( $id );

$page->register_guards();
wp_set_current_user( 1 );

$made = array();
$mk   = static function ( string $status, string $meta ) use ( &$made ) {
	$id = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_title' => 'QA-RFT moderation ' . wp_rand(), 'post_status' => 'draft', 'post_author' => 1 ) );
	update_post_meta( $id, '_wpss_moderation_status', $meta );
	$made[] = $id;
	return $id;
};

// Admin publishes a pending service: the meta must follow.
$id = $mk( 'pending', 'pending' );
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
$check( 'admin publish of a pending service leaves it live', 'publish' === get_post_status( $id ) );
$check( 'admin publish of a pending service approves it (not live-but-pending)', 'approved' === $state( $id ) );

// Quick Edit of an approved live service (title only, no moderation field) leaves it live and approved.
$id2 = $mk( 'publish', 'approved' );
wp_update_post( array( 'ID' => $id2, 'post_status' => 'publish' ) );
$before = $state( $id2 );
wp_update_post( array( 'ID' => $id2, 'post_title' => 'QA-RFT renamed' ) );
$check( 'a title edit does not change the moderation state', $before === $state( $id2 ) );
$check( 'a title edit keeps an approved service live', 'publish' === get_post_status( $id2 ) );

// UI surface: no shared moderation select in Quick Edit, no blanket CSS hide.
$check( 'ServiceModerationPage has no quick_edit_fields', ! method_exists( $page, 'quick_edit_fields' ) );
$check( 'admin.css no longer hides every Quick Edit Status', false === strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/admin.css' ), '.inline-edit-row .inline-edit-status' ) );
$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/service-edit.js' );
$check( 'Skip to full editor saves a draft, not a publish', (bool) preg_match( '/skipBtn\.on\(.click.*?savePost\(true\)/s', $js ) && false !== strpos( $js, "#save-post" ) );

foreach ( $made as $m ) {
	wp_delete_post( $m, true );
}
remove_filter( 'wpss_require_service_moderation', '__return_true' );
remove_filter( 'wpss_service_publish_errors', '__return_empty_array' );
wp_set_current_user( 0 );
echo $fails ? "{$fails} FAILED\n" : "All passed\n";
