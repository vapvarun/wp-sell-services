<?php
/**
 * Notification Repository
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Database\Repositories;

use WPSellServices\Models\Notification;

/**
 * Repository for notification data access.
 *
 * @since 1.0.0
 */
class NotificationRepository extends AbstractRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	protected function get_table_name(): string {
		return 'wpss_notifications';
	}

	/**
	 * Find notification by ID.
	 *
	 * @param int $id Notification ID.
	 * @return Notification|null
	 */
	public function find( int $id ): ?Notification {
		$row = $this->find_by_id( $id );

		return $row ? Notification::from_row( $row ) : null;
	}

	/**
	 * Find notifications for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param bool   $unread_only Only unread notifications.
	 * @param int    $limit   Limit.
	 * @param int    $offset  Offset.
	 * @return Notification[]
	 */
	public function find_by_user( int $user_id, bool $unread_only = false, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		$where = $wpdb->prepare( 'user_id = %d', $user_id );

		if ( $unread_only ) {
			$where .= ' AND is_read = 0';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		return array_map( [ Notification::class, 'from_row' ], $results );
	}

	/**
	 * Find notifications by type.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Notification type.
	 * @param int    $limit   Limit.
	 * @return Notification[]
	 */
	public function find_by_type( int $user_id, string $type, int $limit = 10 ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE user_id = %d AND type = %s
				ORDER BY created_at DESC
				LIMIT %d",
				$user_id,
				$type,
				$limit
			)
		);

		return array_map( [ Notification::class, 'from_row' ], $results );
	}

	/**
	 * Create a notification.
	 *
	 * @param array $data Notification data.
	 * @return int|false Notification ID or false on failure.
	 */
	public function create( array $data ) {
		$defaults = [
			'is_read'    => 0,
			'created_at' => current_time( 'mysql' ),
		];

		$data = wp_parse_args( $data, $defaults );

		// Encode data array if present.
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$data['data'] = wp_json_encode( $data['data'] );
		}

		return $this->insert( $data );
	}

	/**
	 * Mark notification as read.
	 *
	 * @param int $id Notification ID.
	 * @return bool
	 */
	public function mark_as_read( int $id ): bool {
		return $this->update(
			$id,
			[
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Mark all notifications as read for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int Number of notifications marked.
	 */
	public function mark_all_as_read( int $user_id ): int {
		global $wpdb;

		return (int) $wpdb->update(
			$this->table,
			[
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			],
			[
				'user_id' => $user_id,
				'is_read' => 0,
			],
			[ '%d', '%s' ],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Count unread notifications for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_unread( int $user_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND is_read = 0",
				$user_id
			)
		);
	}

	/**
	 * Count notifications by type for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Type counts.
	 */
	public function count_by_type( int $user_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT type, COUNT(*) as count FROM {$this->table}
				WHERE user_id = %d
				GROUP BY type",
				$user_id
			)
		);

		$counts = [];
		foreach ( $results as $row ) {
			$counts[ $row->type ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Delete old notifications.
	 *
	 * @param int $days Days to keep.
	 * @return int Number deleted.
	 */
	public function delete_old( int $days = 90 ): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE created_at < %s AND is_read = 1",
				$cutoff
			)
		);
	}

	/**
	 * Delete all notifications for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int Number deleted.
	 */
	public function delete_for_user( int $user_id ): int {
		global $wpdb;

		return (int) $wpdb->delete(
			$this->table,
			[ 'user_id' => $user_id ],
			[ '%d' ]
		);
	}

	/**
	 * Check if notification exists.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Notification type.
	 * @param array  $data    Data to match.
	 * @return bool
	 */
	public function exists( int $user_id, string $type, array $data = [] ): bool {
		global $wpdb;

		$where = $wpdb->prepare(
			'user_id = %d AND type = %s',
			$user_id,
			$type
		);

		if ( ! empty( $data ) ) {
			$json_data = wp_json_encode( $data );
			$where    .= $wpdb->prepare( ' AND data = %s', $json_data );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table} WHERE {$where}"
		);

		return (int) $count > 0;
	}

	/**
	 * Get recent notifications grouped by date.
	 *
	 * @param int $user_id User ID.
	 * @param int $days    Number of days.
	 * @return array Grouped notifications.
	 */
	public function get_grouped_by_date( int $user_id, int $days = 7 ): array {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE user_id = %d AND created_at >= %s
				ORDER BY created_at DESC",
				$user_id,
				$cutoff
			)
		);

		$grouped = [];
		foreach ( $results as $row ) {
			$date = gmdate( 'Y-m-d', strtotime( $row->created_at ) );

			if ( ! isset( $grouped[ $date ] ) ) {
				$grouped[ $date ] = [];
			}

			$grouped[ $date ][] = Notification::from_row( $row );
		}

		return $grouped;
	}
}
