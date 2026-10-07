<?php
/**
 * One-time conversion of stored datetimes to UTC.
 *
 * Until 1.8.0 most writers stored site time, some stored UTC, and columns
 * MySQL filled itself stored the database server's clock (Basecamp
 * 10351460106). Writers and readers are UTC now; this converts the rows that
 * were written before.
 *
 * - Runs once, gated by its own option rather than the DB version, so a site
 *   already on a 1.8.0 build converts too.
 * - Only rows that existed at cutover convert: the highest id per table is
 *   recorded before anything can write, and newer rows are already UTC.
 * - Site time converts per value with get_gmt_from_date(), so a row keeps the
 *   offset that applied on its own date (DST).
 * - updated_at on the ON UPDATE tables is decided per row: PHP wrote site
 *   time, MySQL wrote the server's clock (see updated_at_utc()).
 * - A chunk and its cursor commit together, so a chunk that dies is redone
 *   from where it started, not from halfway.
 * - Every UPDATE assigns updated_at explicitly, so ON UPDATE CURRENT_TIMESTAMP
 *   does not overwrite it.
 * - Chunked through Action Scheduler; `wp wpss utc-migrate` runs it in the
 *   foreground and `--dry-run` prints what would change.
 *
 * @package WPSellServices\Database
 * @since   1.8.0
 */

namespace WPSellServices\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Converts pre-1.8.0 datetimes to UTC.
 */
class UtcMigration {

	/**
	 * Progress option: status, cutover ids, per-table cursor, offsets.
	 */
	const OPTION = 'wpss_utc_migration';

	/**
	 * Action Scheduler hook for one chunk.
	 */
	const HOOK = 'wpss_utc_migrate_batch';

	/**
	 * Rows per table per chunk.
	 */
	const CHUNK = 500;

	/**
	 * Site-time columns per table (without prefix).
	 *
	 * Columns already written in UTC are absent on purpose: delivery and
	 * original deadlines, dispute response deadlines, extension due dates
	 * (copied from the order deadline). Pro's tables were UTC from the start.
	 *
	 * @var array<string, string[]>
	 */
	const LOCAL_COLUMNS = array(
		'reports'             => array( 'resolved_at', 'created_at' ),
		'payment_receipts'    => array( 'verified_at', 'created_at' ),
		'audit_log'           => array( 'created_at' ),
		'orders'              => array( 'created_at', 'updated_at', 'paid_at', 'started_at', 'completed_at' ),
		'order_requirements'  => array( 'submitted_at' ),
		'conversations'       => array( 'last_message_at', 'created_at', 'updated_at' ),
		'messages'            => array( 'created_at', 'updated_at' ),
		'deliveries'          => array( 'responded_at', 'created_at' ),
		'extension_requests'  => array( 'responded_at', 'created_at' ),
		'reviews'             => array( 'vendor_reply_at', 'created_at', 'updated_at' ),
		'disputes'            => array( 'resolved_at', 'created_at', 'updated_at' ),
		'dispute_messages'    => array( 'created_at' ),
		'proposals'           => array( 'created_at', 'updated_at' ),
		'vendor_profiles'     => array( 'verified_at', 'created_at', 'updated_at' ),
		'portfolio_items'     => array( 'created_at' ),
		'notifications'       => array( 'read_at', 'created_at' ),
		'wallet_transactions' => array( 'created_at' ),
		'withdrawals'         => array( 'processed_at', 'created_at' ),
	);

	/**
	 * Columns only MySQL ever filled: the database server's clock.
	 *
	 * @var array<string, string[]>
	 */
	const SERVER_COLUMNS = array(
		'service_packages' => array( 'created_at', 'updated_at' ),
	);

	/**
	 * Tables carrying ON UPDATE CURRENT_TIMESTAMP on updated_at.
	 *
	 * @var string[]
	 */
	const ON_UPDATE_TABLES = array( 'service_packages', 'orders', 'conversations', 'messages', 'reviews', 'disputes', 'proposals', 'vendor_profiles' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( self::HOOK, array( self::class, 'run_chunk' ) );
		// Called from wpss_init() before Plugin::init(), so the cutover is
		// recorded before anything in this request can write a row.
		self::maybe_start();
	}

	/**
	 * Record the cutover and queue the work, once.
	 *
	 * @return void
	 */
	public static function maybe_start(): void {
		$state = get_option( self::OPTION );

		if ( false === $state ) {
			$state = self::plan();
			update_option( self::OPTION, $state, false );
		} elseif ( ! is_admin() || wp_doing_ajax() || 'pending' !== ( $state['status'] ?? '' ) ) {
			return;
		}

		// Also reached on a later admin page load while still pending: a chunk
		// that failed queued no successor, and nothing else would start it
		// again. schedule_single() does nothing when one is already waiting.
		if ( 'pending' === $state['status'] ) {
			\WPSellServices\Services\Scheduler::schedule_single( self::HOOK, time() + 30 );
		}
	}

	/**
	 * Build the starting state: offsets and per-table cutover ids.
	 *
	 * @return array<string, mixed>
	 */
	public static function plan(): array {
		global $wpdb;

		$server_offset = self::server_offset();
		$site_is_utc   = 0.0 === (float) get_option( 'gmt_offset' ) && in_array( (string) get_option( 'timezone_string' ), array( '', 'UTC', 'Etc/UTC' ), true );

		$cutover = array();
		$tables  = array_keys( self::LOCAL_COLUMNS + self::SERVER_COLUMNS );
		foreach ( $tables as $table ) {
			$name = $wpdb->prefix . 'wpss_' . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) !== $name ) {
				continue;
			}
			$cutover[ $table ] = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$nothing_to_do = $site_is_utc && 0 === $server_offset;

		return array(
			'status'        => $nothing_to_do ? 'done' : 'pending',
			'site_is_utc'   => $site_is_utc,
			'server_offset' => $server_offset,
			'cutover'       => $cutover,
			'cursor'        => array(),
			'meta_done'     => $site_is_utc,
			'started'       => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Seconds the database server's own clock runs ahead of UTC.
	 *
	 * The plugin set this connection to UTC; the global zone is what columns
	 * filled by MySQL used before.
	 *
	 * @return int
	 */
	public static function server_offset(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$offset = $wpdb->get_var( "SELECT TIMESTAMPDIFF( SECOND, UTC_TIMESTAMP(), CONVERT_TZ( UTC_TIMESTAMP(), '+00:00', @@global.time_zone ) )" );

		// NULL when the global zone is a name and MySQL has no zone tables.
		return null === $offset ? 0 : (int) $offset;
	}

	/**
	 * Action Scheduler entry: one chunk, then queue the next.
	 *
	 * The next chunk carries its own number: Scheduler::schedule_single()
	 * skips a hook that is already pending, and the running action counts as
	 * pending, so a bare reschedule from inside it was dropped after one chunk.
	 *
	 * @param int $chunk Chunk number.
	 * @return void
	 */
	public static function run_chunk( int $chunk = 0 ): void {
		if ( ! self::step() ) {
			\WPSellServices\Services\Scheduler::schedule_single( self::HOOK, time() + 5, array( $chunk + 1 ) );
		}
	}

	/**
	 * Convert the next chunk and save progress.
	 *
	 * @return bool True when everything is converted.
	 */
	public static function step(): bool {
		global $wpdb;

		// From the database, not a persistent object cache: a chunk that died
		// was rolled back there, and the cache may still hold its cursor.
		wp_cache_delete( self::OPTION, 'options' );
		$state = get_option( self::OPTION );

		if ( ! is_array( $state ) ) {
			return true;
		}

		// Rows and cursor commit together. Saved once per chunk with no
		// transaction, a chunk that died midway converted its first rows twice.
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$samples = array();
		$done    = self::advance( $state, false, $samples );
		update_option( self::OPTION, $state, false );
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $done;
	}

	/**
	 * Convert the next chunk of the given state.
	 *
	 * @param array<string, mixed> $state   State, advanced in place.
	 * @param bool                 $dry_run Collect samples instead of writing.
	 * @param array<string, mixed> $samples Up to 3 "old -> new" per column, plus row counts.
	 * @return bool True when everything is converted.
	 */
	public static function advance( array &$state, bool $dry_run, array &$samples ): bool {
		if ( 'done' === ( $state['status'] ?? '' ) ) {
			return true;
		}

		// On a UTC site these values do not move, except updated_at where
		// MySQL stamped it with a server clock that is not UTC.
		foreach ( self::LOCAL_COLUMNS as $table => $columns ) {
			if ( ! self::table_done( $state, $table ) ) {
				self::convert_table( $state, $table, $columns, 'site', $dry_run, $samples );
				return false;
			}
		}

		if ( empty( $state['meta_done'] ) ) {
			self::convert_meta( $dry_run, $samples );
			$state['meta_done'] = true;
			return false;
		}

		if ( 0 !== (int) $state['server_offset'] ) {
			foreach ( self::SERVER_COLUMNS as $table => $columns ) {
				if ( ! self::table_done( $state, $table ) ) {
					self::convert_table( $state, $table, $columns, 'server', $dry_run, $samples );
					return false;
				}
			}
		}

		$state['status']   = 'done';
		$state['finished'] = gmdate( 'Y-m-d H:i:s' );

		return true;
	}

	/**
	 * Whether a table has no rows left at or below its cutover.
	 *
	 * @param array<string, mixed> $state State.
	 * @param string               $table Table without prefix.
	 * @return bool
	 */
	private static function table_done( array $state, string $table ): bool {
		if ( ! isset( $state['cutover'][ $table ] ) ) {
			return true;
		}

		return (int) ( $state['cursor'][ $table ] ?? 0 ) >= (int) $state['cutover'][ $table ];
	}

	/**
	 * Convert one chunk of one table.
	 *
	 * @param array<string, mixed> $state   State, cursor advanced in place.
	 * @param string               $table   Table without prefix.
	 * @param string[]             $columns Columns to convert.
	 * @param string               $from    'site' or 'server'.
	 * @param bool                 $dry_run Collect samples only.
	 * @param array<string, mixed> $samples Samples, by table.column.
	 * @return void
	 */
	private static function convert_table( array &$state, string $table, array $columns, string $from, bool $dry_run, array &$samples ): void {
		global $wpdb;

		$name    = $wpdb->prefix . 'wpss_' . $table;
		$cursor  = (int) ( $state['cursor'][ $table ] ?? 0 );
		$cutover = (int) $state['cutover'][ $table ];
		$extra   = array();

		if ( 'orders' === $table ) {
			$extra = array( 'platform', 'meta' );
		} elseif ( 'disputes' === $table ) {
			$extra = array( 'meta', 'evidence' );
		} elseif ( 'messages' === $table ) {
			$extra = array( 'read_by' );
		}

		$keep   = in_array( $table, self::ON_UPDATE_TABLES, true ) && ! in_array( 'updated_at', $columns, true ) ? array( 'updated_at' ) : array();
		$select = implode( ', ', array_merge( array( 'id' ), $columns, $extra, $keep ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT {$select} FROM {$name} WHERE id > %d AND id <= %d ORDER BY id ASC LIMIT %d", $cursor, $cutover, self::CHUNK ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $rows as $row ) {
			$skip = self::utc_already( $table, (string) ( $row['platform'] ?? '' ) );
			$data = array();

			foreach ( $columns as $column ) {
				if ( in_array( $column, $skip, true ) || empty( $row[ $column ] ) || '0000-00-00 00:00:00' === $row[ $column ] ) {
					continue;
				}
				if ( 'server' === $from ) {
					$value = gmdate( 'Y-m-d H:i:s', strtotime( $row[ $column ] . ' UTC' ) - (int) $state['server_offset'] );
				} elseif ( 'updated_at' === $column && in_array( $table, self::ON_UPDATE_TABLES, true ) ) {
					$value = self::updated_at_utc( $row, $columns, $state );
				} else {
					$value = get_gmt_from_date( (string) $row[ $column ] );
				}

				if ( $value !== $row[ $column ] ) {
					$data[ $column ] = $value;
				}
			}

			foreach ( $extra as $blob ) {
				if ( 'platform' === $blob || empty( $row[ $blob ] ) ) {
					continue;
				}
				$converted = self::convert_json( (string) $row[ $blob ], $blob );
				if ( null !== $converted ) {
					$data[ $blob ] = $converted;
				}
			}

			if ( $data ) {
				if ( $dry_run ) {
					foreach ( $data as $column => $value ) {
						$key = $table . '.' . $column;
						if ( count( $samples[ $key ] ?? array() ) < 3 && ! in_array( $column, $extra, true ) ) {
							$samples[ $key ][] = $row[ $column ] . ' -> ' . $value;
						}
						$samples[ $key . '#rows' ] = ( $samples[ $key . '#rows' ] ?? 0 ) + 1;
					}
				} else {
					// Assigning updated_at keeps ON UPDATE CURRENT_TIMESTAMP from
					// stamping the conversion time over it.
					if ( in_array( $table, self::ON_UPDATE_TABLES, true ) && ! isset( $data['updated_at'] ) ) {
						$data['updated_at'] = $row['updated_at'];
					}
					$wpdb->update( $name, $data, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

			$cursor = (int) $row['id'];
		}

		// An empty chunk means nothing at or below the cutover is left.
		$state['cursor'][ $table ] = $rows ? $cursor : $cutover;
	}

	/**
	 * UTC for an updated_at on a table with ON UPDATE CURRENT_TIMESTAMP.
	 *
	 * Two writers filled this column before 1.8.0. A writer that set it wrote
	 * site time; one that left it out got the database server's clock from
	 * MySQL. Nothing on the row says which, so:
	 *
	 * - the same second as another site-time column of the row (created,
	 *   paid, completed...) is one PHP write, so site time;
	 * - otherwise the server's clock, unless that reading puts the update
	 *   before the row existed or after the conversion began and the site
	 *   reading does not.
	 *
	 * ponytail: a PHP write that touched no other date column and passes both
	 * bounds is read as the server's clock, off by the gap between the two
	 * zones (nothing when they match). Only a per-write log could tell them apart.
	 *
	 * @param array<string, mixed> $row     Row, with every column in $columns.
	 * @param string[]             $columns The table's site-time columns.
	 * @param array<string, mixed> $state   State: server_offset, started.
	 * @return string
	 */
	private static function updated_at_utc( array $row, array $columns, array $state ): string {
		$stored = (string) $row['updated_at'];
		$site   = get_gmt_from_date( $stored );
		$server = gmdate( 'Y-m-d H:i:s', strtotime( $stored . ' UTC' ) - (int) $state['server_offset'] );

		if ( $site === $server ) {
			return $site;
		}

		foreach ( $columns as $column ) {
			if ( 'updated_at' !== $column && ! empty( $row[ $column ] ) && abs( strtotime( $row[ $column ] . ' UTC' ) - strtotime( $stored . ' UTC' ) ) <= 2 ) {
				return $site;
			}
		}

		$created  = empty( $row['created_at'] ) ? '' : get_gmt_from_date( (string) $row['created_at'] );
		$started  = (string) ( $state['started'] ?? gmdate( 'Y-m-d H:i:s' ) );
		$possible = static fn( string $utc ): bool => $utc >= $created && $utc <= $started;

		return ! $possible( $server ) && $possible( $site ) ? $site : $server;
	}

	/**
	 * Columns a row already holds in UTC, by who wrote it.
	 *
	 * @param string $table    Table without prefix.
	 * @param string $platform Order platform.
	 * @return string[]
	 */
	private static function utc_already( string $table, string $platform ): array {
		if ( 'orders' !== $table ) {
			return array();
		}

		if ( 'woocommerce' === $platform ) {
			return array( 'created_at', 'updated_at' );
		}

		if ( 'manual' === $platform ) {
			return array( 'started_at', 'paid_at', 'completed_at' );
		}

		return array();
	}

	/**
	 * Convert the site-time datetimes inside a JSON column.
	 *
	 * @param string $json JSON text.
	 * @param string $blob Column name.
	 * @return string|null New JSON, or null when nothing changed.
	 */
	private static function convert_json( string $json, string $blob ): ?string {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		$keys = array( 'timestamp', 'requested_at', 'created_at', 'submitted_at', 'revision_requested_at', 'escalated_at', 'assigned_at', 'cancelled_at', 'reminder_sent', 'first_at', 'last_at' );

		if ( 'read_by' === $blob ) {
			// { user_id: datetime }.
			foreach ( $data as $user => $when ) {
				if ( is_string( $when ) && self::is_datetime( $when ) ) {
					$data[ $user ] = get_gmt_from_date( $when );
				}
			}
		} else {
			array_walk_recursive(
				$data,
				static function ( &$value, $key ) use ( $keys ) {
					if ( in_array( $key, $keys, true ) && is_string( $value ) && self::is_datetime( $value ) ) {
						$value = get_gmt_from_date( $value );
					}
				}
			);
		}

		$out = wp_json_encode( $data );

		return false === $out || $out === $json ? null : $out;
	}

	/**
	 * Whether a string is a MySQL datetime.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_datetime( string $value ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value );
	}

	/**
	 * Convert plugin meta written in site time.
	 *
	 * _wpss_last_active is left alone: it is rewritten within minutes of a
	 * member's next request.
	 *
	 * @param bool                 $dry_run Collect samples only.
	 * @param array<string, mixed> $samples Samples.
	 * @return void
	 */
	private static function convert_meta( bool $dry_run, array &$samples ): void {
		global $wpdb;

		$sets = array(
			array( $wpdb->usermeta, 'umeta_id', '_wpss_vendor_since' ),
			array( $wpdb->postmeta, 'meta_id', '_wpss_moderated_at' ),
		);

		foreach ( $sets as list( $table, $id_column, $key ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$id_column} AS id, meta_value FROM {$table} WHERE meta_key = %s", $key ), ARRAY_A );

			foreach ( $rows as $row ) {
				if ( ! self::is_datetime( (string) $row['meta_value'] ) ) {
					continue;
				}
				$new = get_gmt_from_date( (string) $row['meta_value'] );
				if ( $dry_run ) {
					if ( count( $samples[ $key ] ?? array() ) < 3 ) {
						$samples[ $key ][] = $row['meta_value'] . ' -> ' . $new;
					}
					$samples[ $key . '#rows' ] = ( $samples[ $key . '#rows' ] ?? 0 ) + 1;
				} else {
					$wpdb->update( $table, array( 'meta_value' => $new ), array( $id_column => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_query_meta_value
				}
			}
		}

		if ( ! $dry_run ) {
			wp_cache_flush_group( 'user_meta' );
			wp_cache_flush_group( 'post_meta' );
		}
	}
}
