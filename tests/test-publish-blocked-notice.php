<?php
/**
 * A refused publish is explained: the service is flagged, the editor and the Services list say why, and the
 * flag goes when the service qualifies (Basecamp 10399161587).
 *
 * Run: wp eval-file tests/test-publish-blocked-notice.php
 *
 * Publishes an incomplete service through the metabox's own save hook, then reads the notice for both screens.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Metaboxes\ServiceMetabox;
use WPSellServices\Admin\ServiceListScreen;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

$metabox = new ServiceMetabox();
add_action( 'save_post_wpss_service', array( $metabox, 'enforce_publish_rules' ), 99, 2 );
add_action( 'pre_post_update', array( $metabox, 'remember_status_before_save' ), 10, 1 );

$id = (int) wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'QA blocked publish ' . wp_generate_password( 5, false ), 'post_author' => 1 ) );
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );

$check( 'an incomplete service is demoted to draft', 'draft' === get_post_status( $id ) );
$check( 'the refused publish is on record', '' !== (string) get_post_meta( $id, ServiceMetabox::PUBLISH_BLOCKED_META, true ) );

$notice = static function ( string $base, ?int $post_id = null ) use ( $id ): string {
	set_current_screen( 'post' === $base ? 'wpss_service' : 'edit-wpss_service' );
	$GLOBALS['current_screen']->base = $base;
	$GLOBALS['post']                 = $post_id ? get_post( $post_id ) : null;
	ob_start();
	( new ServiceListScreen() )->publish_blocked_notice();

	return (string) ob_get_clean();
};

$editor = $notice( 'post', $id );
$check( 'the editor says the service was saved as a draft, not published', false !== strpos( $editor, 'saved as a draft' ) );
$check( 'the editor lists what is missing', substr_count( $editor, '<li>' ) >= 1 );
$list = $notice( 'edit' );
$check( 'the list says it too, with a way back to the service', false !== strpos( $list, 'saved as a draft' ) && false !== strpos( $list, 'Finish this service' ) );

delete_post_meta( $id, ServiceMetabox::PUBLISH_BLOCKED_META );
$check( 'with no refused publish on record there is no notice', '' === $notice( 'post', $id ) );

wp_delete_post( $id, true );
exit( $fails ? 1 : 0 );
