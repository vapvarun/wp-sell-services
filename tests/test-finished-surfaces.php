<?php
/**
 * Finished surfaces read as finished (2.0.0 desktop unification, batch 6).
 *
 *   - Emails use the brand colour, else WooCommerce's on the Woo rail, else the plugin indigo; never #7f54b3.
 *   - A rejected applicant sees why, and "contact us", where the Start selling form was; the email no
 *     longer invites a re-application the server refuses.
 *   - The Messages list previews the last thing a person wrote, not a status line.
 *   - All Services shows and filters by how each service is sold.
 *   - A buyer who owns a digital product gets its download first and "Buy again" second.
 *   - A finished order nobody wrote in has no conversation panel; the Delivered step carries a date.
 *   - A finished order page renders to the end: the tip presets read the order's currency.
 *
 * Run: wp eval-file tests/test-finished-surfaces.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ): void {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$capture = static function ( callable $fn ): string {
	ob_start();
	$fn();
	return (string) ob_get_clean();
};

$root = WP_PLUGIN_DIR . '/wp-sell-services';
$f    = wpss_test_fixtures();

// Email colour.
$es       = new \WPSellServices\Services\EmailService();
$settings = ( new ReflectionMethod( $es, 'settings' ) )->invoke( $es );
$adapter  = wpss_get_active_adapter();
if ( ! $adapter || 'woocommerce' !== $adapter->get_id() ) {
	$check( 'off the Woo rail the email colour is the plugin indigo', '#4f46e5' === $settings['base_color'] );
}
$stale = array_filter( glob( $root . '/templates/emails/*.php' ), static fn( $file ) => str_contains( (string) file_get_contents( $file ), '7f54b3' ) );
$check( 'no email template falls back to #7f54b3', ! $stale && ! str_contains( (string) file_get_contents( $root . '/src/Services/EmailService.php' ), '7f54b3' ) );
if ( class_exists( 'WPSellServicesPro\\WhiteLabel\\EmailBrandingService' ) ) {
	$default = (string) \WPSellServicesPro\WhiteLabel\WhiteLabelManager::DEFAULTS['primary_color'];
	$stub    = new class( $default ) implements \WPSellServicesPro\WhiteLabel\Contracts\BrandingProviderInterface {
		public function __construct( public string $color ) {}
		public function get_brand_name(): string { return ''; }
		public function get_brand_logo_url(): string { return ''; }
		public function get_brand_color(): string { return $this->color; }
		public function is_enabled(): bool { return true; }
	};
	$brand = new \WPSellServicesPro\WhiteLabel\EmailBrandingService( $stub );
	$check( 'an unchosen white-label colour keeps the store email colour', '#4f46e5' === $brand->filter_header_vars( array( 'base_color' => '#4f46e5' ), 'x' )['base_color'] );
	$stub->color = '#0e7490';
	$check( 'a chosen white-label colour wins', '#0e7490' === $brand->filter_header_vars( array( 'base_color' => '#4f46e5' ), 'x' )['base_color'] );
} else {
	echo "SKIP  Pro is not active, white-label colour not checked\n";
}

// Rejected applicant.
$vs = new \WPSellServices\Services\VendorService();
$check( 'an active vendor gets no closed notice', '' === wpss_vendor_closed_notice( $f->vendor ) );
$vs->set_status( $f->vendor, 'rejected', 'Needs three finished samples.' );
$notice = wpss_vendor_closed_notice( $f->vendor );
$check( 'a rejected applicant is told it was not approved', str_contains( $notice, 'was not approved' ) );
$check( 'the notice gives the admin reason', str_contains( $notice, 'Needs three finished samples.' ) );
$check( 'the notice says contact us', str_contains( $notice, 'contact us' ) );
$check( 'the rejection email no longer invites a re-application', ! str_contains( (string) file_get_contents( $root . '/templates/emails/vendor-rejected.php' ), 'apply again' ) );
$vs->set_status( $f->vendor, 'active' );

// Messages preview.
$order_id = ( $f->order )();
$cs       = new \WPSellServices\Services\ConversationService();
$conv     = $cs->create_for_order( $order_id );
$cs->send_message( (int) $conv->id, $f->buyer, 'Thanks, the logo looks great.' );
$cs->add_system_message( (int) $conv->id, 'Order marked as complete.' );
$rows = ( new \WPSellServices\Database\Repositories\ConversationRepository() )->get_conversation_summary( $f->buyer, 10 );
$row  = current( array_filter( $rows, static fn( $r ) => (int) $r->id === (int) $conv->id ) );
$check( 'the list previews the last human message, not the status line', $row && 'Thanks, the logo looks great.' === $row->last_message );
$check( 'an empty date has no "time ago"', '' === wpss_time_ago( '' ) );

// Sold as.
$sid = ( $f->service )( 'publish' );
wpss_set_service_type( $sid, 'digital' );
$screen = new \WPSellServices\Admin\ServiceListScreen();
$check( 'All Services has a Sold as column', isset( $screen->columns( array( 'title' => 'Title' ) )['wpss_type'] ) );
$check( 'the column names the type', 'Digital product' === trim( $capture( static fn() => $screen->render_column( 'wpss_type', $sid ) ) ) );
$get_was           = $_GET;
$GLOBALS['pagenow'] = 'edit.php';
set_current_screen( 'edit-wpss_service' );
$_GET  = array( 'wpss_service_type' => 'fixed' );
$query = new WP_Query();
$query->set( 'post_type', 'wpss_service' );
$GLOBALS['wp_the_query'] = $query; // Main query for is_main_query().
$screen->apply_filters( $query );
$check( 'filtering by fixed also matches services saved before types', str_contains( wp_json_encode( $query->get( 'meta_query' ) ), 'NOT EXISTS' ) );
$_GET = $get_was;

// Owned digital product.
update_post_meta( $sid, '_wpss_packages', array( array( 'name' => 'Personal', 'price' => 20, 'delivery_days' => 1, 'revisions' => 0 ) ) );
update_post_meta( $sid, '_wpss_product_files', array( array( 'id' => 'qa', 'name' => 'kit.zip', 'type' => 'application/zip', 'size' => 10, 'uploaded_at' => gmdate( 'Y-m-d H:i:s' ) ) ) );
$render = static function () use ( $sid, $capture ): string {
	$GLOBALS['post'] = get_post( $sid );
	setup_postdata( $GLOBALS['post'] );
	return $capture( static fn() => wpss_get_template( 'partials/service-packages.php', array( 'wpss_vacation' => null ) ) );
};
wp_set_current_user( $f->buyer );
$before = $render();
$check( 'a buyer who does not own it sees no download', ! str_contains( $before, 'wpss-package-owned' ) );
( $f->order )(
	array(
		'service_id' => $sid,
		'status'     => 'completed',
		'meta'       => wp_json_encode( array( 'service_type' => 'digital' ) ),
	)
);
$owned = $render();
$check( 'an owner sees "You own this" and the download first', str_contains( $owned, 'wpss-package-owned' ) && strpos( $owned, 'wpss-package-owned' ) < strpos( $owned, 'wpss-order-btn' ) );
$check( 'buying again is the secondary button', (bool) preg_match( '/wpss-btn--secondary wpss-btn--block wpss-order-btn"[^>]*>\s*Buy again/', $owned ) );
wp_set_current_user( 0 );

// Finished order with no words: no panel; with words: panel.
$silent = ( $f->order )( array( 'status' => 'completed' ) );
$cs->create_for_order( $silent );
$cs->add_system_message( (int) $cs->get_by_order( $silent )->id, 'Order marked as complete.' );
wp_set_current_user( $f->buyer );
$conv_html = static fn( int $id ) => $capture( static fn() => wpss_get_template( 'order/conversation.php', array( 'order_id' => $id, 'order' => wpss_get_order( $id ) ) ) );
$check( 'a finished order nobody wrote in renders no conversation', '' === trim( $conv_html( $silent ) ) );
$wpdb->update( $f->orders_t, array( 'status' => 'completed' ), array( 'id' => $order_id ) );
$check( 'a finished order with words keeps its conversation', str_contains( $conv_html( $order_id ), 'wpss-messaging' ) );
$page = $capture( static fn() => wpss_get_template( 'order/order-view.php', array( 'order_id' => $silent ) ) );
$check( 'the finished order page renders to the end (tip modal included)', str_contains( $page, 'wpss-tip-form' ) && str_contains( $page, '</form>' ) && ! str_contains( $page, 'wpss-order-section--conversation' ) );
wp_set_current_user( 0 );
$check( 'the order view drops the frame when the thread is empty', str_contains( (string) file_get_contents( $root . '/templates/order/order-view.php' ), "'' !== trim( \$wpss_conversation_html )" ) );

// Delivered step dated.
$wpdb->insert( $wpdb->prefix . 'wpss_deliveries', array( 'order_id' => $order_id, 'vendor_id' => $f->vendor, 'message' => 'Here it is', 'status' => 'accepted', 'created_at' => '2026-11-01 10:00:00' ) );
$delivery = (int) $wpdb->insert_id;
$timeline = $capture( static fn() => wpss_get_template( 'order/timeline.php', array( 'wpss_order' => wpss_get_order( $order_id ) ) ) );
$check( 'the Delivered step shows the first delivery date', (bool) preg_match( '/Delivered<\/span>\s*<span class="wpss-timeline__date">[^<]*\S/', $timeline ) );

// Cleanup what the fixtures do not track.
$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'id' => $delivery ) );
foreach ( array( $order_id, $silent ) as $oid ) {
	$c = $cs->get_by_order( $oid );
	if ( $c ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_messages', array( 'conversation_id' => $c->id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'id' => $c->id ) );
	}
}

echo $fails ? "{$fails} FAILED\n" : "all passed\n";
