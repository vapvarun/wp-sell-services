<?php
/**
 * One event, one email: a notification row for an order event EmailService already
 * mails must not send its own plain copy. The buyer got "Order Completed" twice
 * because delivery_accepted was missing from the covered list (combo walk, 1.9.0).
 *
 * Run: wp eval-file tests/test-notification-no-double-email.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\NotificationService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$method = new ReflectionMethod( NotificationService::class, 'is_email_service_handling' );
$method->setAccessible( true );
$service = new NotificationService();

// Every type notify_order_status() writes for an order whose lifecycle email EmailService sends.
$order_event_types = array(
	'order_started',
	'order_in_progress',
	NotificationService::TYPE_DELIVERY_SUBMITTED,
	NotificationService::TYPE_DELIVERY_ACCEPTED,
	'order_completed_vendor',
	NotificationService::TYPE_REVISION_REQUESTED,
	'order_cancelled',
);
foreach ( $order_event_types as $type ) {
	$check( "'{$type}' row leaves the email to EmailService", true === $method->invoke( $service, $type ) );
}

// A type EmailService never mails must still send its own.
$check( 'a type nobody else mails keeps its own email', false === $method->invoke( $service, 'wpss_test_unmailed_type' ) );

exit( $fails ? 1 : 0 );
