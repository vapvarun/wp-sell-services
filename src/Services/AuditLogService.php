<?php
/**
 * Audit Log Service
 *
 * Cross-cutting audit trail for sensitive state changes. Callers hand over
 * an event type, object reference, and payload; the service captures the
 * current actor (user, role, IP, user agent) and persists everything to
 * the {@see wpss_audit_log} table for forensic and compliance review.
 *
 * Write failures are logged but never throw — audit logging must not break
 * the user-facing flow that triggered it.
 *
 * @package WPSellServices\Services
 * @since   1.1.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AuditLogService class.
 *
 * @since 1.1.0
 */
class AuditLogService {

	/**
	 * Option name holding the retention period in days.
	 *
	 * A value of 0 (default) means "never delete". Any positive integer
	 * activates the daily cleanup cron which deletes rows older than N days.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const RETENTION_OPTION = 'wpss_audit_log_retention_days';

	/**
	 * Daily cron hook name used for the retention cleanup job.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const CLEANUP_HOOK = 'wpss_audit_log_cleanup';

	/**
	 * Fully qualified database table name (with site prefix).
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wpss_audit_log';
	}

	/**
	 * Record an audit event.
	 *
	 * Captures `actor_id` / `actor_role` from the current WordPress user and
	 * request metadata (IP, user agent) from `$_SERVER`. The caller only
	 * needs to describe the event and object; everything else is derived.
	 *
	 * Soft-coupled to the caller: a DB failure is logged via `wpss_log()`
	 * but never thrown or returned as an error, so the triggering business
	 * operation (order status change, refund, etc.) is never blocked by an
	 * audit write failure.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $event_type  Dot-separated identifier, e.g. `order.status_change`.
	 * @param string               $object_type Object slug, e.g. `order`, `proposal`, `dispute`.
	 * @param int                  $object_id   Primary key of the subject object.
	 * @param array<string, mixed> $data        {
	 *     Optional. Event-specific payload.
	 *
	 *     @type string              $action     Verb describing the event, e.g. `update`, `force`, `cancel`.
	 *     @type string|int|float    $from_value Previous value (cast to string on persist).
	 *     @type string|int|float    $to_value   New value (cast to string on persist).
	 *     @type bool                $is_forced  Whether the change bypassed the natural rules (e.g. admin override).
	 *     @type array<string, mixed> $context    Additional structured context persisted as JSON.
	 * }
	 * @return int The new audit row ID, or 0 on failure.
	 */
	public function log( string $event_type, string $object_type, int $object_id, array $data = array() ): int {
		global $wpdb;

		$actor_id   = (int) get_current_user_id();
		$actor_role = '';

		if ( $actor_id > 0 ) {
			$user = get_userdata( $actor_id );
			if ( $user instanceof \WP_User && ! empty( $user->roles ) ) {
				$actor_role = (string) reset( $user->roles );
			}
		}

		$context = isset( $data['context'] ) && is_array( $data['context'] )
			? $data['context']
			: array();

		// Merge in request metadata unless the caller supplied their own.
		if ( ! isset( $context['ip'] ) ) {
			$context['ip'] = $this->get_request_ip();
		}
		if ( ! isset( $context['user_agent'] ) ) {
			$context['user_agent'] = $this->get_request_user_agent();
		}

		$row = array(
			'actor_id'    => $actor_id,
			'actor_role'  => $actor_role ?: null,
			'event_type'  => $event_type,
			'object_type' => $object_type,
			'object_id'   => $object_id,
			'action'      => isset( $data['action'] ) ? (string) $data['action'] : null,
			'from_value'  => isset( $data['from_value'] ) ? (string) $data['from_value'] : null,
			'to_value'    => isset( $data['to_value'] ) ? (string) $data['to_value'] : null,
			'is_forced'   => ! empty( $data['is_forced'] ) ? 1 : 0,
			'context'     => wp_json_encode( $context ),
			'created_at'  => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$this->table,
			$row,
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			if ( function_exists( 'wpss_log' ) ) {
				wpss_log(
					sprintf(
						'AuditLogService::log failed for %s/%d (%s): %s',
						$object_type,
						$object_id,
						$event_type,
						$wpdb->last_error
					),
					'error'
				);
			}
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Query audit log rows with optional filters and pagination.
	 *
	 * Used by the REST controller and administrative views. All filter
	 * arguments are optional and prepared against the allowlist, never
	 * interpolated raw.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $args {
	 *     Optional. Query filters.
	 *
	 *     @type string $object_type Filter by object type.
	 *     @type int    $object_id   Filter by object ID (requires object_type).
	 *     @type int    $actor_id    Filter by actor ID.
	 *     @type string $event_type  Filter by event type.
	 *     @type bool   $is_forced   Return only forced (bypass) events if true.
	 *     @type string $from_date   ISO date/datetime lower bound (inclusive).
	 *     @type string $to_date     ISO date/datetime upper bound (inclusive).
	 *     @type int    $page        1-based page number (default 1).
	 *     @type int    $per_page    Rows per page, max 100 (default 20).
	 * }
	 * @return array{rows: array<int, object>, total: int, page: int, per_page: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['object_type'] ) ) {
			$where[]  = 'object_type = %s';
			$values[] = (string) $args['object_type'];

			if ( ! empty( $args['object_id'] ) ) {
				$where[]  = 'object_id = %d';
				$values[] = (int) $args['object_id'];
			}
		}

		if ( ! empty( $args['actor_id'] ) ) {
			$where[]  = 'actor_id = %d';
			$values[] = (int) $args['actor_id'];
		}

		if ( ! empty( $args['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = (string) $args['event_type'];
		}

		if ( ! empty( $args['is_forced'] ) ) {
			$where[] = 'is_forced = 1';
		}

		if ( ! empty( $args['from_date'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = (string) $args['from_date'];
		}

		if ( ! empty( $args['to_date'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = (string) $args['to_date'];
		}

		$where_sql = implode( ' AND ', $where );

		// Total count — separate query so pagination is accurate.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$values
				? $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}", $values )
				: "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}"
		);

		$query_values   = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				$query_values
			)
		);

		return array(
			'rows'     => is_array( $rows ) ? $rows : array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Delete audit rows older than the configured retention period.
	 *
	 * Invoked by the `wpss_audit_log_cleanup` daily cron. A retention value
	 * of 0 (the default) means "never delete" and is a no-op.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of rows deleted.
	 */
	public function cleanup_expired(): int {
		global $wpdb;

		$retention_days = (int) get_option( self::RETENTION_OPTION, 0 );

		if ( $retention_days <= 0 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE created_at < DATE_SUB( NOW(), INTERVAL %d DAY )",
				$retention_days
			)
		);

		return (int) $deleted;
	}

	/**
	 * Best-effort request IP extraction.
	 *
	 * Prefers trusted proxy headers when the site is behind a reverse proxy,
	 * falls back to REMOTE_ADDR. Returns an empty string when no address is
	 * available (CLI, cron, unit tests).
	 *
	 * @return string
	 */
	private function get_request_ip(): string {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $candidates as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

			// X-Forwarded-For may contain a comma-separated chain — take the first.
			if ( false !== strpos( $value, ',' ) ) {
				$value = trim( explode( ',', $value )[0] );
			}

			$ip = filter_var( $value, FILTER_VALIDATE_IP );
			if ( $ip ) {
				return (string) $ip;
			}
		}

		return '';
	}

	/**
	 * Best-effort request user-agent extraction.
	 *
	 * Truncated to 255 characters so a pathological user agent cannot bloat
	 * the audit row.
	 *
	 * @return string
	 */
	private function get_request_user_agent(): string {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		return substr( $ua, 0, 255 );
	}
}
