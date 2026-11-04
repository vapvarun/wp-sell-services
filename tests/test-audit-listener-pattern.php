<?php
/**
 * Every audit row is written by a listener, never by the code that made the change (Basecamp 10432412388).
 *
 * Run: wp eval-file tests/test-audit-listener-pattern.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\AuditLogService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// 1. Static guard: no direct log() call outside the listener files, in Free or Pro.
$allowed = array( 'wp-sell-services/src/Services/AuditLogService.php', 'wp-sell-services-pro/src/Services/AuditListeners.php' );
$direct  = array();
foreach ( array( 'wp-sell-services', 'wp-sell-services-pro' ) as $plugin ) {
	$root = WP_PLUGIN_DIR . '/' . $plugin . '/src';
	if ( ! is_dir( $root ) ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$relative = $plugin . '/src/' . substr( $file->getPathname(), strlen( $root ) + 1 );
		if ( 'php' !== $file->getExtension() || in_array( $relative, $allowed, true ) ) {
			continue;
		}
		$code = (string) file_get_contents( $file->getPathname() );
		if ( preg_match( '/AuditLogService\(\s*\)\s*\)\s*->\s*log\s*\(|\$audit\w*\s*->\s*log\s*\(/', $code ) ) {
			$direct[] = $relative;
		}
	}
}
$check( 'no writer calls the audit log directly (' . ( $direct ? implode( ', ', $direct ) : 'none' ) . ')', array() === $direct );

// 2. Behaviour: each documented action writes its audit row. Only the audit listeners run, so no
// email, notification or money side effect fires for the fake ids below.
global $wpdb, $wp_filter;
$table  = $wpdb->prefix . 'wpss_audit_log';
$before = (int) $wpdb->get_var( "SELECT COALESCE( MAX( id ), 0 ) FROM {$table}" ); // phpcs:ignore WordPress.DB
$order  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}wpss_orders ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB
$fake   = 987654;
$saved  = array_map( static fn( $hook ) => clone $hook, $wp_filter );
$booted = new ReflectionProperty( AuditLogService::class, 'booted' );

try {
	$wp_filter = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$booted->setValue( null, false );
	( new AuditLogService() )->init();
	( new AuditLogService() )->init();
	$check( 'init() registers its listeners once however often it is called', 1 === count( $wp_filter['wpss_report_resolved']->callbacks[10] ?? array() ) );

	$pro = class_exists( '\WPSellServicesPro\Services\AuditListeners' );
	if ( $pro ) {
		( new \WPSellServicesPro\Services\AuditListeners() )->init();
	}

	$refund_context = array( 'gateway' => 'stripe', 'amount' => 5.0 );
	$fire           = array(
		'order.status_change'           => array( 'wpss_order_status_recorded', $fake, 'in_progress', 'delivered', 'note', 0, false ),
		'order.cancel'                  => array( 'wpss_order_status_cancelled', $order, 'in_progress' ),
		'order.earnings_reversal_failed' => array( 'wpss_order_earnings_reversal_failed', $fake, 2, 9.5 ),
		'order.refund_pending'          => array( 'wpss_order_refund_recorded', $fake, 'pending', $refund_context, (object) array( 'payment_status' => 'paid' ), false ),
		'order.refund'                  => array( 'wpss_order_refund_recorded', $fake, 'refunded', $refund_context, (object) array( 'payment_status' => 'paid' ), true ),
		'order.refund_failed'           => array( 'wpss_order_refund_recorded', $fake, 'failed', $refund_context, (object) array( 'payment_status' => 'paid' ), false ),
		'order.refund_unrecorded'       => array( 'wpss_order_refund_unrecorded', $fake, 5.0, 'refunded' ),
		'order.pending_refund_assigned' => array( 'wpss_pending_refund_assigned', $fake, 'k1', 5.0 ),
		'order.repair'                  => array( 'wpss_order_repaired', $fake, 'stripe_double_tax', '12.00', '10.00', array( 'payment' => 'pi_1' ) ),
		'ledger.insert'                 => array( 'wpss_ledger_row_inserted', $fake, 2, 9.5, array( 'type' => 'earning' ) ),
		'email.failed'                  => array( 'wpss_email_failed', new WP_Error( 'wp_mail_failed', 'boom', array( 'to' => array( 'a@example.com' ), 'subject' => 'Hi' ) ), 'order_completed', false ),
		'ledger.duplicate_removed'      => array( 'wpss_duplicate_row_removed', 'wallet_transaction', $fake, 1, array() ),
		'review.duplicate_removed'      => array( 'wpss_duplicate_row_removed', 'review', $fake, 1, array() ),
		'dispute.transition'            => array( 'wpss_dispute_status_changed', $fake, 'resolved', 'open', array( 'order_id' => 7 ) ),
		'dispute.assign'                => array( 'wpss_dispute_assigned', $fake, 1, 0, 7 ),
		'review.approved'               => array( 'wpss_review_moderated', $fake, 'approved', 'pending' ),
		'review.imported'               => array( 'wpss_review_imported', $fake, 2, 3, 5 ),
		'review.edited'                 => array( 'wpss_review_edited', $fake, 'reviewer_name', 'Old', 'New' ),
		'review.deleted'                => array( 'wpss_review_deleted', $fake, (object) array( 'vendor_id' => 2, 'customer_id' => 3, 'rating' => 4 ) ),
		'vendor.suspended'              => array( 'wpss_vendor_status_updated', $fake, 'suspended', 'active' ),
		'commission.rate_changed'       => array( 'wpss_vendor_commission_rate_changed', $fake, null, 12.5 ),
		'vendor.migrated'               => array( 'wpss_vendor_migrated', $fake ),
		'vendor.role_removed'           => array( 'wpss_vendor_role_removed', $fake, array( 'subscriber' ) ),
		'vendor.role_restored'          => array( 'wpss_vendor_role_restored', $fake, array( 'wpss_vendor' ) ),
		'member.account_status'         => array( 'wpss_account_status_changed', $fake, 'suspended', 'active' ),
		'report.resolve'                => array( 'wpss_report_resolved', $fake, 'dismissed' ),
	);
	if ( $pro ) {
		$fire['payout.batch_created']    = array( 'wpss_pro_payout_batch_created', $fake, 'PENDING', 20.0, 2, 'USD' );
		$fire['commission.rule_created'] = array( 'wpss_pro_commission_rule_changed', 'created', $fake, array( 'rate' => 10.0 ) );
	}

	foreach ( $fire as $event => $args ) {
		$hook = array_shift( $args );
		$from = (int) $wpdb->get_var( "SELECT COALESCE( MAX( id ), 0 ) FROM {$table}" ); // phpcs:ignore WordPress.DB
		do_action( $hook, ...$args );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d AND event_type = %s", $from, $event ) ); // phpcs:ignore WordPress.DB
		$check( "{$hook} records {$event}", null !== $row );
	}

	// Payloads kept their shape through the move.
	$suspended = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d AND event_type = 'vendor.suspended'", $before ) ); // phpcs:ignore WordPress.DB
	$check( 'vendor status row keeps from/to', $suspended && 'active' === $suspended->from_value && 'suspended' === $suspended->to_value );
	$mail = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d AND event_type = 'email.failed'", $before ) ); // phpcs:ignore WordPress.DB
	$check( 'email failure row keeps recipient and type', $mail && str_contains( (string) $mail->context, 'a@example.com' ) && str_contains( (string) $mail->context, 'order_completed' ) );
} finally {
	$wp_filter = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$booted->setValue( null, true );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id > %d", $before ) ); // phpcs:ignore WordPress.DB
}

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
