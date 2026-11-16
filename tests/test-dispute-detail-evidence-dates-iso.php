<?php
/**
 * GET /wpss/v1/disputes/{id} returns the row's evidence entries with ISO 8601
 * dates and every other key kept, like the timeline and /evidence routes.
 *
 * Run: wp eval-file tests/test-dispute-detail-evidence-dates-iso.php
 *
 * No fixture: it calls the controller's response builder with an in-memory
 * dispute, so nothing is written.
 *
 * @package WPSellServices
 */

use WPSellServices\API\DisputesController;

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$dispute                 = new stdClass();
$dispute->id             = 1;
$dispute->dispute_number = 'D-1';
$dispute->order_id       = 1;
$dispute->reason         = 'other';
$dispute->status         = 'open';
$dispute->initiator_id   = 0;
$dispute->created_at     = null;
$dispute->updated_at     = null;
$dispute->description    = '';
$dispute->response_deadline = null;
$dispute->resolved_at       = null;
$dispute->evidence       = array(
	array(
		'id'         => 'note_1',
		'type'       => 'status_note',
		'note'       => 'Escalated',
		'status'     => 'escalated',
		'created_at' => '2026-11-02 12:10:52',
	),
	array(
		'id'            => 'refund_1',
		'type'          => 'refund_info',
		'refund_amount' => 12.5,
	),
);

$method = new ReflectionMethod( DisputesController::class, 'prepare_dispute_for_response' );
$out      = $method->invoke( new DisputesController(), $dispute, true );
$evidence = $out['evidence'] ?? array();

$check( 'evidence entry date is ISO 8601', 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', (string) ( $evidence[0]['created_at'] ?? '' ) ), (string) ( $evidence[0]['created_at'] ?? 'missing' ) );
$check( 'note and status keys are kept', 'Escalated' === ( $evidence[0]['note'] ?? null ) && 'escalated' === ( $evidence[0]['status'] ?? null ) );
$check( 'entry without a date is left alone', 12.5 === ( $evidence[1]['refund_amount'] ?? null ) && ! array_key_exists( 'created_at', $evidence[1] ?? array() ) );
