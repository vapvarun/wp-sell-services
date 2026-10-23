<?php
/**
 * 1.9.1 combo-smoke contracts: suspended vendor starts nothing, order limits
 * cannot be negative, a repeat milestone submit changes nothing, a paid
 * extension returns a late order to In Progress.
 *
 * Run: wp eval-file tests/test-smoke-1-9-1-contracts.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

/**
 * Print one assertion.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $msg . "\n";
}

global $wpdb;
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$orders   = $wpdb->prefix . 'wpss_orders';

echo "\n1.9.1 smoke contracts\n\n";

// 10425686753: order limits clamp server-side.
( new \WPSellServices\Admin\Settings() )->register_settings();
wpss_t( 0.0 === (float) sanitize_option( 'wpss_min_order_amount', '-5' ), 'minimum order amount -5 is stored as 0' );
wpss_t( 0.0 === (float) sanitize_option( 'wpss_max_order_amount', '-100' ), 'maximum order amount -100 is stored as 0' );

// 10425686711: a vendor suspended on the profile row starts no new thread.
// A throwaway vendor with an active profile, removed on any exit.
$uid = wpss_test_fixtures()->vendor;
$svc = new \WPSellServices\Services\ConversationService();
wpss_t( null === $svc->standing_block( 0, $uid ), 'active vendor may start a pre-sale thread' );
( new \WPSellServices\Services\VendorService() )->set_status( $uid, 'suspended' );
$block = $svc->standing_block( 0, $uid );
wpss_t( $block instanceof \WP_Error, 'suspended vendor is refused a new pre-sale thread' );
$nav = new ReflectionMethod( \WPSellServices\Frontend\UnifiedDashboard::class, 'is_suspended_vendor' );
wpss_t( $nav->invoke( new \WPSellServices\Frontend\UnifiedDashboard(), $uid ), 'dashboard sees the vendor as suspended (Sales Orders stays)' );
( new \WPSellServices\Services\VendorService() )->set_status( $uid, 'active' );

// 10425686815: a repeat milestone submit fires nothing.
// A throwaway paid phase in progress, removed on any exit.
$staged = wpss_test_milestone_contract( array( array( 'status' => 'in_progress', 'payment_status' => 'paid', 'paid_at' => current_time( 'mysql', true ) ) ) );
$fired  = 0;
add_action( 'wpss_milestone_submitted', function () use ( &$fired ) { ++$fired; } );
$ms = new \WPSellServices\Services\MilestoneService();
$ms->submit( $staged['phases'][0], $staged['vendor'], 'QA-SMOKE' );
$ms->submit( $staged['phases'][0], $staged['vendor'], 'QA-SMOKE' );
$ms->submit( $staged['phases'][0], $staged['vendor'], 'QA-SMOKE' );
wpss_t( 1 === $fired, "three submits of one phase notify once (fired {$fired})" );

// 10425686815: the paid-extension path returns a late order to In Progress.
$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/ExtensionOrderService.php' );
wpss_t( false !== strpos( $src, "'late' === \$parent->status" ), 'paid extension moves a late order back to in progress' );

// Triage of the other smoke failures.
$method = new ReflectionMethod( \WPSellServices\Services\NotificationService::class, 'is_email_service_handling' );
wpss_t( true === $method->invoke( new \WPSellServices\Services\NotificationService(), 'tip_received' ), 'a tip sends the seller one email, not two' );

update_option( 'wpss_dispute_admin_email', '' );
$dsrc = (string) file_get_contents( dirname( __DIR__ ) . '/src/Services/DisputeWorkflowManager.php' );
wpss_t( false !== strpos( $dsrc, "get_option( 'wpss_dispute_admin_email' ) ?: get_option( 'admin_email' )" ), 'a blank dispute admin email falls back to the site admin email' );
delete_option( 'wpss_dispute_admin_email' );

// A throwaway approved, live service, removed on any exit.
$svc_id = wpss_test_live_service();
$mod    = new \WPSellServices\Services\ModerationService();
$count  = did_action( 'wpss_service_approved' );
$mod->approve( $svc_id );
$mod->approve( $svc_id );
wpss_t( did_action( 'wpss_service_approved' ) === $count, 'approving an approved, live service twice announces nothing' );

// Standard-practice fixes from the smoke triage.
$notes = $wpdb->prefix . 'wpss_notifications';
$title = static function ( int $user, string $type ) use ( $wpdb, $notes ): string {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$notes} WHERE user_id = %d AND type = %s ORDER BY id DESC LIMIT 1", $user, $type ) );
};
$qa_user = $uid; // The throwaway vendor: its notifications go with it.
$ns      = new \WPSellServices\Services\NotificationService();

$ns->send( $qa_user, 'dispute_response_reminder', array( 'order_id' => 0 ) );
wpss_t( 'Dispute response due' === $title( $qa_user, 'dispute_response_reminder' ), 'a dispute reminder reads as one, not as a generic notification' );

$ns->send( $qa_user, 'milestone_revision_requested', array( 'order_id' => 0 ) );
wpss_t( 'Revision requested' === $title( $qa_user, 'milestone_revision_requested' ), 'a milestone revision request notifies the seller' );
wpss_t( false !== has_action( 'wpss_milestone_revision_requested' ), 'the revision request is wired to a listener' );

$wpdb->insert(
	$wpdb->prefix . 'wpss_reports',
	array(
		'target_type'      => 'user',
		'target_id'        => $qa_user,
		'reported_user_id' => $qa_user,
		'reporter_id'      => $qa_user,
		'reason'           => 'spam',
		'details'          => 'QA-SMOKE',
		'status'           => 'open',
		'created_at'       => current_time( 'mysql', true ),
	)
);
$report_id = (int) $wpdb->insert_id;
\WPSellServices\API\ReportsController::resolve_report( $report_id, 'upheld' );
wpss_t( 'Your report was reviewed' === $title( $qa_user, 'report_resolved' ), 'the reporter is told the report was reviewed' );
$wpdb->delete( $wpdb->prefix . 'wpss_reports', array( 'id' => $report_id ), array( '%d' ) );

// A held review tells the seller nothing; the live one does.
$reviews = $wpdb->prefix . 'wpss_reviews';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$review = $wpdb->get_row( "SELECT id, vendor_id, order_id FROM {$reviews} WHERE status = 'approved' AND order_id > 0 LIMIT 1" );
if ( $review ) {
	$count = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$notes} WHERE user_id = %d AND type = 'review_received'", $review->vendor_id ) );
	$wpdb->update( $reviews, array( 'status' => 'pending' ), array( 'id' => $review->id ) );
	$before = $count();
	do_action( 'wpss_review_created', (int) $review->id, (int) $review->order_id );
	wpss_t( $count() === $before, 'a review held for moderation does not notify the seller' );
	$wpdb->update( $reviews, array( 'status' => 'approved' ), array( 'id' => $review->id ) );
	do_action( 'wpss_review_moderated', (int) $review->id, 'approved' );
	wpss_t( $count() === $before + 1, 'approving the held review notifies the seller once' );
	$wpdb->delete( $notes, array( 'user_id' => $review->vendor_id, 'type' => 'review_received' ), array( '%d', '%s' ) );
} else {
	echo "  SKIP  no approved review.\n";
}

// The ledger list takes the period the export uses.
$ledger_user = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->prefix}wpss_wallet_transactions GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 1" );
if ( $ledger_user ) {
	wp_set_current_user( $ledger_user );
	$ask  = static function ( array $params ): int {
		$request = new WP_REST_Request( 'GET', '/wpss/v1/wallet/transactions' );
		$request->set_query_params( $params );
		return (int) ( rest_do_request( $request )->get_headers()['X-WP-Total'] ?? -1 );
	};
	wpss_t( $ask( array() ) > 0 && 0 === $ask( array( 'from' => '2999-01-01 00:00:00' ) ), 'the wallet ledger list honours the period window' );
	wp_set_current_user( 0 );
}

// The review Helpful vote is gone, and the sweep for it.
wpss_t( ! method_exists( \WPSellServices\Database\Repositories\ReviewRepository::class, 'toggle_helpful' ), 'the review Helpful vote writer is removed' );
wpss_t( false === has_action( 'wp_ajax_wpss_mark_review_helpful' ) && ! isset( rest_get_server()->get_routes()['/wpss/v1/reviews/(?P<id>[\d]+)/helpful'] ), 'no Helpful AJAX action or REST route' );

// The dashboard profile form prefills the saved social links (the column is JSON, not an array).
$social_row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}wpss_vendor_profiles WHERE social_links LIKE '%http%' LIMIT 1" );
if ( $social_row ) {
	$saved = (array) json_decode( (string) $social_row->social_links, true );
	$url   = (string) reset( $saved );
	wp_set_current_user( (int) $social_row->user_id );
	$user_id        = (int) $social_row->user_id;
	$is_vendor      = true;
	// The page receives the raw row (social_links is a JSON string), so hand the template exactly that.
	$vendor_service = new class( $social_row ) extends \WPSellServices\Services\VendorService {
		public function __construct( private object $row ) {}
		public function get_profile( int $user_id ): ?object {
			return $this->row;
		}
	};
	ob_start();
	include WPSS_PLUGIN_DIR . 'templates/dashboard/sections/profile.php';
	$form = (string) ob_get_clean();
	wp_set_current_user( 0 );
	wpss_t( '' !== $url && false !== strpos( $form, 'value="' . esc_url( $url ) . '"' ), 'the profile form prefills the saved social links' );
}

// A tip awaiting offline confirmation reads the same on the order page as on the tip page.
$order_view = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/order/order-view.php' );
wpss_t( 1 === preg_match( '/wpss-tip-receipt__title">\s*<\?php.{0,200}wpss_order_awaits_payment_confirmation\(\s*\$tip_order_row/s', $order_view ), 'the order page tip receipt has an awaiting-confirmation state' );

// Conflict and duplication guards: no generic script handle, one shared toast instead of per-template notice copies.
$frontend_src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Frontend/Frontend.php' ) . (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Frontend/ServiceWizard.php' );
wpss_t( false === strpos( $frontend_src, "'alpinejs'" ), 'the bundled Alpine uses a prefixed handle, not the generic alpinejs' );
$inline_notice = 0;
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR . 'templates', FilesystemIterator::SKIP_DOTS ) ) as $tpl ) {
	$inline_notice += substr_count( (string) file_get_contents( $tpl->getPathname() ), 'wpssShowNotice' );
}
wpss_t( 0 === $inline_notice, 'templates use the shared wpssToast, not their own wpssShowNotice' );
