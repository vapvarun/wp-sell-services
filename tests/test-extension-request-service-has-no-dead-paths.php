<?php
/**
 * ExtensionRequestService holds the status constants only: no create/approve/reject, no second reader.
 *
 * Those three called NotificationService::send() with the old 5-argument shape and threw a TypeError. Nothing
 * called them (extensions run through ExtensionOrderService), so they are removed rather than repaired.
 * Its readers were a second, partial copy of ExtensionRequestsController's queries (no amount or
 * payment fields) that nothing called, so they went too.
 *
 * Run: wp eval-file tests/test-extension-request-service-has-no-dead-paths.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$class = \WPSellServices\Services\ExtensionRequestService::class;

foreach ( array( 'create', 'approve', 'reject' ) as $method ) {
	$check( "no broken {$method}() left to throw", ! method_exists( $class, $method ) );
}
$check( 'status constants are still there for the REST controller and ExtensionOrderService', 'pending' === $class::STATUS_PENDING && 'approved' === $class::STATUS_APPROVED && 'rejected' === $class::STATUS_REJECTED );

foreach ( array( 'get', 'get_by_order', 'get_pending', 'format_request', 'get_statuses' ) as $method ) {
	$check( "no second reader {$method}() beside the REST controller", ! method_exists( $class, $method ) );
}

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
