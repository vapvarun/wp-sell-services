<?php
/**
 * Regressions from the 1.7.1 release browser walk.
 *
 * The services archive filter must not knock a front-page services page over
 * to the blog, a private requirement file renders without a PHP warning, the
 * admin Reports page renders without one, ?vendor= names the tab after the
 * vendor, a proposal order gets a real created_at, a pending withdrawal cannot
 * be marked paid, a buyer whose offline refund is still to be sent by hand is
 * told so, and closing a dispute with a note keeps the note.
 *
 * Run: wp eval-file tests/test-walk-regressions.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ReportsPage;
use WPSellServices\Admin\Pages\WithdrawalsPage;
use WPSellServices\Frontend\ServiceArchiveView;
use WPSellServices\Frontend\TemplateLoader;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\DisputeService;
use WPSellServices\Services\DisputeWorkflowManager;
use WPSellServices\Services\EarningsService;
use WPSellServices\Services\OrderWorkflowManager;
use WPSellServices\Services\ProposalService;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// Every PHP notice/warning raised while a surface renders is a failure.
$warnings = array();
set_error_handler(
	static function ( int $no, string $str, string $file, int $line ) use ( &$warnings ): bool {
		$warnings[] = basename( $file ) . ':' . $line . ' ' . $str;
		return true;
	},
	E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE
);
$render = static function ( callable $fn ) use ( &$warnings ): array {
	$before = count( $warnings );
	ob_start();
	$fn();
	$html = (string) ob_get_clean();
	return array( $html, array_slice( $warnings, $before ) );
};

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user( 1 );

$vendor_user = null;
foreach ( get_users( array( 'role' => 'wpss_vendor', 'number' => 20 ) ) as $candidate ) {
	if ( wpss_is_vendor( $candidate->ID ) ) {
		$vendor_user = $candidate;
		break;
	}
}
$vendor_id = $vendor_user ? (int) $vendor_user->ID : 0;

// --- 1. front-page services archive filter ------------------------------------------
$services_page = wpss_get_page_id( 'services_page' );
$saved_front   = array( get_option( 'show_on_front' ), get_option( 'page_on_front' ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $services_page );
$check( 'services page is mapped', $services_page > 0 );
$check( 'front-page request drops empty public filter vars', array() === apply_filters( 'request', array( 'min_price' => '', 'max_price' => '', 'search' => '' ) ) );
$check( '  and non-empty ones, keeping paged', array( 'paged' => '2' ) === apply_filters( 'request', array( 'min_price' => '10', 'paged' => '2' ) ) );
$other = array( 'pagename' => 'about', 'min_price' => '5' );
$check( '  a request for another page is untouched', $other === apply_filters( 'request', $other ) );
list( $sidebar, $w ) = $render( static fn() => ( new ServiceArchiveView() )->render_sidebar() );
$check( 'filter form posts to the services page permalink', str_contains( $sidebar, 'action="' . esc_url( get_permalink( $services_page ) ) . '"' ) );
update_option( 'show_on_front', 'posts' );
$check( '  a request for a non-front services page is untouched', array( 'min_price' => '' ) === apply_filters( 'request', array( 'min_price' => '' ) ) );
update_option( 'show_on_front', $saved_front[0] );
update_option( 'page_on_front', $saved_front[1] );

// --- fixtures shared by the order-side checks --------------------------------------
$orders   = $wpdb->prefix . 'wpss_orders';
$disputes = $wpdb->prefix . 'wpss_disputes';
$buyer    = (int) wp_insert_user( // A real login: the order view only shows submitted requirements to a party.
	array(
		'user_login' => 'walkbuyer' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$nobody   = 999998; // Stand-in vendor for order rows, so no real member gets notifications.
$service  = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_title'  => 'Walk regression service',
		'post_author' => $vendor_id ?: 1,
	)
);
wpss_save_service_requirements(
	(int) $service,
	array(
		array(
			'id'       => 'req_brief',
			'label'    => 'Brief',
			'type'     => 'file',
			'required' => true,
		),
	)
);
$new_order = static function ( array $extra = array() ) use ( $wpdb, $orders, $buyer, $nobody, $service ): int {
	$wpdb->insert(
		$orders,
		array_merge(
			array(
				'order_number'   => 'WPSS-WALK-' . wp_rand(),
				'customer_id'    => $buyer,
				'vendor_id'      => $nobody,
				'service_id'     => (int) $service,
				'platform'       => 'standalone',
				'subtotal'       => 100.000,
				'total'          => 100.000,
				'currency'       => 'USD',
				'status'         => 'in_progress',
				'payment_status' => 'paid',
				'payment_method' => 'offline',
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			$extra
		)
	);
	return (int) $wpdb->insert_id;
};
$order_ids = array();

// --- 2. order view with a private (path, no url) requirement file -------------------
$order_ids[] = $file_order = $new_order();
$wpdb->insert(
	$wpdb->prefix . 'wpss_order_requirements',
	array(
		'order_id'     => $file_order,
		'field_data'   => wp_json_encode( array( 'req_brief' => 'brief.png' ) ),
		'attachments'  => wp_json_encode(
			array(
				array(
					'id'       => wp_generate_uuid4(),
					'name'     => 'brief.png',
					'type'     => 'image/png',
					'size'     => 10,
					'path'     => 'walk/brief.png',
					'order_id' => $file_order,
					'kind'     => 'requirement',
					'key'      => 'req_brief',
				),
			)
		),
		'submitted_at' => current_time( 'mysql' ),
	)
);
wp_set_current_user( $buyer );
list( $view, $w ) = $render(
	static function () use ( $file_order ) {
		$order_id = $file_order;
		include WPSS_PLUGIN_DIR . 'templates/order/order-view.php';
	}
);
wp_set_current_user( 1 );
$check( 'order view with a private file record renders without a PHP warning', empty( $w ) );
foreach ( $w as $line ) {
	echo "      {$line}\n";
}
$check( '  and links the file through the guarded endpoint', str_contains( $view, 'wpss_order_file' ) && str_contains( $view, 'brief.png' ) );

// --- 3. admin Reports page ----------------------------------------------------------
$wpdb->insert(
	$wpdb->prefix . 'wpss_reports',
	array(
		'target_type'      => 'service',
		'target_id'        => (int) $service,
		'reported_user_id' => $vendor_id ?: 1,
		'reporter_id'      => $buyer,
		'reason'           => 'other',
		'details'          => 'walk regression',
		'status'           => 'open',
		'created_at'       => current_time( 'mysql' ),
	)
);
$report_id = (int) $wpdb->insert_id;
$_GET['status'] = 'open';
list( $reports, $w ) = $render( static fn() => ( new ReportsPage() )->render_page() );
unset( $_GET['status'] );
$check( 'admin Reports page renders without a PHP warning', empty( $w ) );
foreach ( $w as $line ) {
	echo "      {$line}\n";
}
$check( '  and offers the member actions for a live reported user', str_contains( $reports, 'walk regression' ) && str_contains( $reports, 'Suspend member' ) );

// --- 4. ?vendor= on the directory page ---------------------------------------------
$vendors_page = wpss_get_vendors_page_id();
if ( $vendor_user && $vendors_page ) {
	$_GET['vendor']       = $vendor_user->user_nicename;
	$GLOBALS['wp_query']  = new WP_Query( array( 'page_id' => $vendors_page ) );
	$GLOBALS['wp_query']->the_post();
	$template = ( new TemplateLoader() )->template_include( 'page.php' );
	$check( '?vendor= on the directory page renders the profile route', str_ends_with( $template, 'vendor/profile.php' ) );
	$check( '  document title names the vendor', str_contains( wp_get_document_title(), wpss_get_member_display_name( $vendor_id ) ) );
	$_GET['vendor'] = 'no-such-vendor-' . wp_rand();
	set_query_var( 'wpss_vendor', '' ); // The route above set it for the template; a real request starts clean.
	$check( '  an unknown slug falls through to the directory page', 'page.php' === ( new TemplateLoader() )->template_include( 'page.php' ) );
	unset( $_GET['vendor'] );
	wp_reset_query();
} else {
	$check( '?vendor= route (no vendor or no directory page on this site to test with)', false );
}
$check( 'profile template has a single h1', 1 === substr_count( (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/vendor/profile.php' ), '<h1' ) );

// --- 5. checkout route names the tab after the checkout page ------------------------
$check( 'standalone checkout route filters the document title', str_contains( (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Integrations/Standalone/StandaloneAdapter.php' ), "'document_title_parts'" ) );

// --- 6. proposal order clock ---------------------------------------------------------
$prop_vendor = wp_insert_user(
	array(
		'user_login' => 'walkvendor' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$requests  = new BuyerRequestService();
$proposals = new ProposalService();
$req_id    = $requests->create(
	array(
		'title'         => 'Walk proposal clock',
		'description'   => 'Walk regression',
		'delivery_days' => 3,
	)
);
$prop_id   = $proposals->submit(
	$req_id,
	(int) $prop_vendor,
	array(
		'description'   => 'Proposal',
		'price'         => 50,
		'delivery_days' => 3,
	)
);
$accepted  = $requests->convert_to_order( (int) $req_id, (int) $prop_id );
$prop_row  = $wpdb->get_row( $wpdb->prepare( "SELECT id, order_id, created_at, updated_at FROM {$wpdb->prefix}wpss_proposals WHERE id = %d", $prop_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$prop_ord  = $wpdb->get_row( $wpdb->prepare( "SELECT id, created_at, updated_at FROM {$orders} WHERE platform = 'request' AND platform_order_id = %d", $req_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$now       = strtotime( current_time( 'mysql' ) );
$fresh     = static fn( ?string $stamp ): bool => $stamp && '0000-00-00 00:00:00' !== $stamp && abs( strtotime( $stamp ) - $now ) < 5 * MINUTE_IN_SECONDS;
$check( 'proposal accept creates the order', ! empty( $accepted['success'] ) && $prop_ord );
$check( '  order created_at is a real site-local timestamp', $prop_ord && $fresh( $prop_ord->created_at ) );
$check( '  order updated_at is on the same clock', $prop_ord && $fresh( $prop_ord->updated_at ) );
$check( '  proposal created_at and updated_at are on the same clock', $prop_row && $fresh( $prop_row->created_at ) && $fresh( $prop_row->updated_at ) );
if ( $prop_ord ) {
	$order_ids[] = (int) $prop_ord->id;
}

// --- 7. withdrawals: Mark paid needs Approve first -----------------------------------
$wpdb->insert(
	$wpdb->prefix . 'wpss_withdrawals',
	array(
		'vendor_id'  => $nobody,
		'amount'     => 12.000,
		'method'     => 'paypal',
		'details'    => wp_json_encode( array( 'email' => 'walk@example.test' ) ),
		'status'     => 'pending',
		'created_at' => current_time( 'mysql' ),
	)
);
$withdrawal_id = (int) $wpdb->insert_id;
$earnings      = new EarningsService();
$_GET['status'] = 'pending';
list( $wd_page, $w ) = $render( static fn() => ( new WithdrawalsPage() )->render_page() );
$check( 'withdrawals page renders without a PHP warning', empty( $w ) );
$row_html = static function ( string $html, int $id ): string {
	$start = strpos( $html, 'data-withdrawal-id="' . $id . '"' );
	return false === $start ? '' : substr( $html, $start, 2000 );
};
$check( 'pending withdrawal row offers Approve, not Mark paid', str_contains( $row_html( $wd_page, $withdrawal_id ), 'data-action="approve"' ) && ! str_contains( $row_html( $wd_page, $withdrawal_id ), 'data-action="complete"' ) );
$refused = $earnings->mark_paid( $withdrawal_id, 'walk' );
$check( 'mark_paid() refuses a pending request', empty( $refused['success'] ) && 'not_approved' === ( $refused['code'] ?? '' ) );
$earnings->process_withdrawal( $withdrawal_id, EarningsService::WITHDRAWAL_APPROVED );
$_GET['status'] = 'approved';
list( $wd_page, $w ) = $render( static fn() => ( new WithdrawalsPage() )->render_page() );
unset( $_GET['status'] );
$check( 'approved withdrawal row offers Mark paid', str_contains( $row_html( $wd_page, $withdrawal_id ), 'data-action="complete"' ) );

// --- 8 + 9. disputes: pending offline refund copy, close note ------------------------
$saved_settings = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved_settings, array( 'allow_disputes' => 1 ) ) );
$order_ids[]   = $refund_order = $new_order();
$dispute_id    = (int) ( new DisputeService() )->open( $refund_order, $buyer, 'other', 'walk' );
$wpdb->update(
	$disputes,
	array(
		'status'        => 'resolved',
		'resolution'    => DisputeService::RESOLUTION_PARTIAL_REFUND,
		'refund_amount' => 30.000,
	),
	array( 'id' => $dispute_id )
);
wpss_get_order_provider()->update_item_meta( $refund_order, OrderWorkflowManager::REFUND_PENDING_META, 30 );
$dispute_view = static function () use ( $dispute_id, $buyer ): string {
	$_GET['dispute'] = $dispute_id;
	$user_id         = $buyer;
	ob_start();
	include WPSS_PLUGIN_DIR . 'templates/dashboard/sections/disputes.php';
	unset( $_GET['dispute'] );
	return (string) ob_get_clean();
};
$html = $dispute_view();
$check( 'buyer copy says the offline refund is sent manually while it is pending', str_contains( $html, 'is being refunded to you' ) && str_contains( $html, 'sent manually' ) && ! str_contains( $html, 'was refunded to you' ) );
wpss_get_order_provider()->update_item_meta( $refund_order, OrderWorkflowManager::REFUND_PENDING_META, 0 );
$html = $dispute_view();
$check( '  and reads as refunded once the site owner has sent it', str_contains( $html, 'was refunded to you' ) );

$order_ids[]     = $close_order = $new_order();
$close_dispute   = (int) ( new DisputeService() )->open( $close_order, $buyer, 'other', 'walk' );
$closed          = ( new DisputeWorkflowManager() )->cancel( $close_dispute, 1, 'Closed after a call with both parties.' );
$closed_row      = $wpdb->get_row( $wpdb->prepare( "SELECT status, resolution_notes FROM {$disputes} WHERE id = %d", $close_dispute ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$check( 'closing a dispute with a note closes it', ! empty( $closed['success'] ) && 'closed' === ( $closed_row->status ?? '' ) );
$check( '  and stores the note as resolution_notes', 'Closed after a call with both parties.' === ( $closed_row->resolution_notes ?? '' ) );

// --- cleanup ------------------------------------------------------------------------
restore_error_handler();
update_option( 'wpss_orders', $saved_settings );
foreach ( array_unique( $order_ids ) as $oid ) {
	foreach ( array( 'wpss_orders' => 'id', 'wpss_order_requirements' => 'order_id', 'wpss_conversations' => 'order_id', 'wpss_order_meta' => 'order_id', 'wpss_disputes' => 'order_id' ) as $table => $column ) {
		$wpdb->delete( $wpdb->prefix . $table, array( $column => (int) $oid ) );
	}
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => (int) $oid ) );
}
foreach ( array( $dispute_id, $close_dispute ) as $did ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => $did ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'dispute', 'object_id' => $did ) );
}
$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_messages WHERE conversation_id NOT IN (SELECT id FROM {$wpdb->prefix}wpss_conversations)" ); // phpcs:ignore
$wpdb->delete( $wpdb->prefix . 'wpss_reports', array( 'id' => $report_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_withdrawals', array( 'id' => $withdrawal_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_type' => 'withdrawal', 'reference_id' => $withdrawal_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'request_id' => (int) $req_id ) );
foreach ( array( $buyer, $nobody, (int) $prop_vendor ) as $u ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $u ) );
}
wp_delete_post( (int) $req_id, true );
wp_delete_post( (int) $service, true );
wp_delete_user( (int) $prop_vendor );
wp_delete_user( $buyer );
wp_set_current_user( 0 );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
