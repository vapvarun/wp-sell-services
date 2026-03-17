<?php
/**
 * Portfolio Service
 *
 * Handles vendor portfolio management.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Manages vendor portfolio items.
 *
 * @since 1.0.0
 */
class PortfolioService {

	/**
	 * Portfolio table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wpss_portfolio_items';
	}

	/**
	 * Get portfolio item by ID.
	 *
	 * @param int $item_id Item ID.
	 * @return array|null Item data or null.
	 */
	public function get( int $item_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $item_id )
		);

		return $row ? $this->format_item( $row ) : null;
	}

	/**
	 * Get vendor portfolio items.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Portfolio items.
	 */
	public function get_by_vendor( int $vendor_id, array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'limit'       => 20,
			'offset'      => 0,
			'service_id'  => 0,
			'featured'    => null,
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ 'vendor_id = %d' ];
		$params = [ $vendor_id ];

		if ( $args['service_id'] ) {
			$where[] = 'service_id = %d';
			$params[] = $args['service_id'];
		}

		if ( null !== $args['featured'] ) {
			$where[] = 'is_featured = %d';
			$params[] = $args['featured'] ? 1 : 0;
		}

		$where_clause = implode( ' AND ', $where );
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE {$where_clause}
				ORDER BY is_featured DESC, sort_order ASC, created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map( [ $this, 'format_item' ], $items );
	}

	/**
	 * Get featured portfolio items.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @param int $limit     Number of items.
	 * @return array Featured items.
	 */
	public function get_featured( int $vendor_id, int $limit = 6 ): array {
		return $this->get_by_vendor( $vendor_id, [
			'featured' => true,
			'limit'    => $limit,
		] );
	}

	/**
	 * Create portfolio item.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $data      Item data.
	 * @return array Result with success status.
	 */
	public function create( int $vendor_id, array $data ): array {
		// Validate required fields.
		if ( empty( $data['title'] ) ) {
			return [
				'success' => false,
				'message' => __( 'Title is required.', 'wp-sell-services' ),
			];
		}

		// Check item limit.
		$max_items = (int) get_option( 'wpss_max_portfolio_items', 50 );
		$current_count = $this->get_count( $vendor_id );

		if ( $current_count >= $max_items ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %d: maximum items */
					__( 'You can have a maximum of %d portfolio items.', 'wp-sell-services' ),
					$max_items
				),
			];
		}

		global $wpdb;

		// Get next sort order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$max_sort = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(sort_order) FROM {$this->table} WHERE vendor_id = %d",
				$vendor_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			[
				'vendor_id'    => $vendor_id,
				'service_id'   => ! empty( $data['service_id'] ) ? (int) $data['service_id'] : null,
				'title'        => sanitize_text_field( $data['title'] ),
				'description'  => wp_kses_post( $data['description'] ?? '' ),
				'media'        => wp_json_encode( $data['media'] ?? [] ),
				'external_url' => esc_url_raw( $data['external_url'] ?? '' ),
				'tags'         => wp_json_encode( $data['tags'] ?? [] ),
				'is_featured'  => ! empty( $data['is_featured'] ) ? 1 : 0,
				'sort_order'   => ( $max_sort ?? 0 ) + 1,
				'created_at'   => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
		);

		if ( ! $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to create portfolio item.', 'wp-sell-services' ),
			];
		}

		$item_id = (int) $wpdb->insert_id;

		/**
		 * Fires when portfolio item is created.
		 *
		 * @param int   $item_id   Item ID.
		 * @param int   $vendor_id Vendor user ID.
		 * @param array $data      Item data.
		 */
		do_action( 'wpss_portfolio_item_created', $item_id, $vendor_id, $data );

		return [
			'success' => true,
			'message' => __( 'Portfolio item created successfully.', 'wp-sell-services' ),
			'item_id' => $item_id,
		];
	}

	/**
	 * Update portfolio item.
	 *
	 * @param int   $item_id Item ID.
	 * @param array $data    Updated data.
	 * @return array Result with success status.
	 */
	public function update( int $item_id, array $data ): array {
		$item = $this->get( $item_id );

		if ( ! $item ) {
			return [
				'success' => false,
				'message' => __( 'Portfolio item not found.', 'wp-sell-services' ),
			];
		}

		$update_data = [];
		$formats = [];

		if ( isset( $data['title'] ) ) {
			$update_data['title'] = sanitize_text_field( $data['title'] );
			$formats[] = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = wp_kses_post( $data['description'] );
			$formats[] = '%s';
		}

		if ( isset( $data['media'] ) ) {
			$update_data['media'] = wp_json_encode( $data['media'] );
			$formats[] = '%s';
		}

		if ( isset( $data['external_url'] ) ) {
			$update_data['external_url'] = esc_url_raw( $data['external_url'] );
			$formats[] = '%s';
		}

		if ( isset( $data['tags'] ) ) {
			$update_data['tags'] = wp_json_encode( $data['tags'] );
			$formats[] = '%s';
		}

		if ( isset( $data['is_featured'] ) ) {
			$update_data['is_featured'] = $data['is_featured'] ? 1 : 0;
			$formats[] = '%d';
		}

		if ( isset( $data['service_id'] ) ) {
			$update_data['service_id'] = $data['service_id'] ? (int) $data['service_id'] : null;
			$formats[] = '%d';
		}

		if ( empty( $update_data ) ) {
			return [
				'success' => false,
				'message' => __( 'No data to update.', 'wp-sell-services' ),
			];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$update_data,
			[ 'id' => $item_id ],
			$formats,
			[ '%d' ]
		);

		if ( false === $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to update portfolio item.', 'wp-sell-services' ),
			];
		}

		/**
		 * Fires when portfolio item is updated.
		 *
		 * @param int   $item_id Item ID.
		 * @param array $data    Updated data.
		 */
		do_action( 'wpss_portfolio_item_updated', $item_id, $data );

		return [
			'success' => true,
			'message' => __( 'Portfolio item updated successfully.', 'wp-sell-services' ),
		];
	}

	/**
	 * Delete portfolio item.
	 *
	 * @param int $item_id   Item ID.
	 * @param int $vendor_id Vendor user ID (for permission check).
	 * @return array Result with success status.
	 */
	public function delete( int $item_id, int $vendor_id ): array {
		$item = $this->get( $item_id );

		if ( ! $item ) {
			return [
				'success' => false,
				'message' => __( 'Portfolio item not found.', 'wp-sell-services' ),
			];
		}

		if ( $item['vendor_id'] !== $vendor_id ) {
			return [
				'success' => false,
				'message' => __( 'You do not have permission to delete this item.', 'wp-sell-services' ),
			];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $this->table, [ 'id' => $item_id ], [ '%d' ] );

		if ( ! $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to delete portfolio item.', 'wp-sell-services' ),
			];
		}

		/**
		 * Fires when portfolio item is deleted.
		 *
		 * @param int   $item_id   Item ID.
		 * @param array $item      Item data.
		 */
		do_action( 'wpss_portfolio_item_deleted', $item_id, $item );

		return [
			'success' => true,
			'message' => __( 'Portfolio item deleted successfully.', 'wp-sell-services' ),
		];
	}

	/**
	 * Reorder portfolio items.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $order     Array of item IDs in order.
	 * @return bool True on success.
	 */
	public function reorder( int $vendor_id, array $order ): bool {
		global $wpdb;

		foreach ( $order as $position => $item_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				[ 'sort_order' => $position + 1 ],
				[
					'id'        => (int) $item_id,
					'vendor_id' => $vendor_id,
				],
				[ '%d' ],
				[ '%d', '%d' ]
			);
		}

		return true;
	}

	/**
	 * Toggle featured status.
	 *
	 * @param int $item_id   Item ID.
	 * @param int $vendor_id Vendor user ID.
	 * @return array Result with success status.
	 */
	public function toggle_featured( int $item_id, int $vendor_id ): array {
		$item = $this->get( $item_id );

		if ( ! $item || $item['vendor_id'] !== $vendor_id ) {
			return [
				'success' => false,
				'message' => __( 'Portfolio item not found.', 'wp-sell-services' ),
			];
		}

		// Check max featured items.
		if ( ! $item['is_featured'] ) {
			$max_featured = (int) get_option( 'wpss_max_featured_portfolio', 6 );
			$current_featured = $this->get_by_vendor( $vendor_id, [ 'featured' => true, 'limit' => 100 ] );

			if ( count( $current_featured ) >= $max_featured ) {
				return [
					'success' => false,
					'message' => sprintf(
						/* translators: %d: maximum featured items */
						__( 'You can only feature up to %d portfolio items.', 'wp-sell-services' ),
						$max_featured
					),
				];
			}
		}

		return $this->update( $item_id, [ 'is_featured' => ! $item['is_featured'] ] );
	}

	/**
	 * Get portfolio item count.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return int Item count.
	 */
	public function get_count( int $vendor_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE vendor_id = %d",
				$vendor_id
			)
		);
	}

	/**
	 * Get portfolio by service.
	 *
	 * @param int $service_id Service post ID.
	 * @param int $limit      Number of items.
	 * @return array Portfolio items.
	 */
	public function get_by_service( int $service_id, int $limit = 10 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE service_id = %d
				ORDER BY is_featured DESC, sort_order ASC
				LIMIT %d",
				$service_id,
				$limit
			)
		);

		return array_map( [ $this, 'format_item' ], $items );
	}

	/**
	 * Format database row to array.
	 *
	 * @param object $row Database row.
	 * @return array Formatted item.
	 */
	private function format_item( object $row ): array {
		$media = json_decode( $row->media, true ) ?: [];
		$tags = json_decode( $row->tags, true ) ?: [];

		// Get media URLs.
		$media_urls = [];
		foreach ( $media as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$media_urls[] = [
					'id'        => $attachment_id,
					'url'       => $url,
					'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
					'medium'    => wp_get_attachment_image_url( $attachment_id, 'medium' ),
					'large'     => wp_get_attachment_image_url( $attachment_id, 'large' ),
					'type'      => get_post_mime_type( $attachment_id ),
				];
			}
		}

		return [
			'id'           => (int) $row->id,
			'vendor_id'    => (int) $row->vendor_id,
			'service_id'   => $row->service_id ? (int) $row->service_id : null,
			'title'        => $row->title,
			'description'  => $row->description,
			'media'        => $media_urls,
			'external_url' => $row->external_url,
			'tags'         => $tags,
			'is_featured'  => (bool) $row->is_featured,
			'sort_order'   => (int) $row->sort_order,
			'created_at'   => $row->created_at,
		];
	}
}
