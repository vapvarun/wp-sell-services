<?php
/**
 * ExtensionRequestService keeps its readers and constants, and no longer carries create/approve/reject.
 *
 * Those three called NotificationService::send() with the old 5-argument shape and threw a TypeError. Nothing
 * called them (extensions run through ExtensionOrderService), so they are removed rather than repaired.
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

$svc = new $class();
$check( 'readers still work', null === $svc->get( 999999999 ) && array() === $svc->get_by_order( 999999999 ) && null === $svc->get_pending( 999999999 ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
