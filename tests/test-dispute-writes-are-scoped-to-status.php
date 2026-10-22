<?php
/**
 * Dispute writes name the status they read: a decision made on a stale read is
 * refused instead of overwriting the other admin's, and a resolved or closed
 * dispute takes no more evidence.
 *
 * Run: wp eval-file tests/test-dispute-writes-are-scoped-to-status.php
 *
 * Reads one escalated and one resolved dispute; the escalated row is restored
 * on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\Dispute;
use WPSellServices\Services\DisputeService;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_disputes';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$escalated = $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'escalated' ORDER BY id DESC LIMIT 1", ARRAY_A );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$resolved = $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'resolved' ORDER BY id DESC LIMIT 1", ARRAY_A );

$restore = static function () use ( $wpdb, $table, $escalated ) {
	if ( $escalated ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, $escalated, array( 'id' => $escalated['id'] ) );
	}
};

$check( 'an escalated dispute exists to test against', (bool) $escalated );
$check( 'a resolved dispute exists to test against', (bool) $resolved );

if ( $escalated ) {
	// A service whose first read is stale: it believes the dispute is still open.
	$stale = new class() extends DisputeService {
		public function get( int $dispute_id ): ?Dispute {
			$dispute = parent::get( $dispute_id );
			if ( $dispute ) {
				$dispute->status = Dispute::STATUS_OPEN;
			}
			return $dispute;
		}
	};

	wp_set_current_user( 1 );
	$moved = $stale->transition( (int) $escalated['id'], DisputeService::STATUS_CLOSED );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$now = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $escalated['id'] ) );

	$check( 'a transition decided on a stale read is refused', false === $moved, $stale->last_error() );
	$check( 'the other admin\'s status is left alone', 'escalated' === $now, $now );
}

if ( $resolved ) {
	$service = new DisputeService();
	$dispute = $service->get( (int) $resolved['id'] );

	$check( 'a resolved dispute does not accept input', $dispute && ! DisputeService::accepts_input( $dispute ) );
	$check( 'evidence on a resolved dispute is refused', false === $service->add_evidence( (int) $resolved['id'], 1, 'text', 'late note' ) );
}
