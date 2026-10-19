<?php
/**
 * Server-clock columns convert per row in a named zone (daylight saving), not with one offset
 * snapshot (Basecamp 10415811057).
 *
 * Run: wp eval-file tests/test-utc-migration-server-zone.php
 *
 * @package WPSellServices
 */

use WPSellServices\Database\UtcMigration;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// A New York server read in July: the snapshot is -4h, winter rows are -5h.
$named = array( 'server_zone' => 'America/New_York', 'server_offset' => -14400 );
$check( 'winter row uses EST', '2026-01-15 17:00:00' === UtcMigration::server_to_utc( '2026-01-15 12:00:00', $named ) );
$check( 'summer row uses EDT', '2026-07-15 16:00:00' === UtcMigration::server_to_utc( '2026-07-15 12:00:00', $named ) );

$fixed = array( 'server_zone' => '', 'server_offset' => 7200 );
$check( 'a fixed-offset server keeps the offset', '2026-01-15 10:00:00' === UtcMigration::server_to_utc( '2026-01-15 12:00:00', $fixed ) );

$check( 'server_zone is empty or a real zone', in_array( UtcMigration::server_zone(), array_merge( array( '' ), timezone_identifiers_list() ), true ) );

exit( $fails ? 1 : 0 );
