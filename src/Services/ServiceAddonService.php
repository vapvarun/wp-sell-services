<?php
/**
 * Service Addon Service
 *
 * Handles service addons/extras with simple, frontend-friendly field types.
 * Designed for easy service creation from both frontend and backend.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Manages service addons with 4 basic field types.
 *
 * Field Types:
 * - checkbox: Simple yes/no toggle (most common)
 * - quantity: Select multiple units (e.g., "Extra revisions" x 3)
 * - dropdown: Pick one from options (e.g., "Resolution: 720p, 1080p, 4K")
 * - text: Custom text input (e.g., "Enter business name")
 *
 * @since 1.0.0
 */
class ServiceAddonService {

	/**
	 * Field types - kept simple for frontend use.
	 */
	public const TYPE_CHECKBOX = 'checkbox';
	public const TYPE_QUANTITY = 'quantity';
	public const TYPE_DROPDOWN = 'dropdown';
	public const TYPE_TEXT     = 'text';

	/**
	 * Pricing types.
	 */
	public const PRICE_FLAT       = 'flat';
	public const PRICE_PERCENTAGE = 'percentage';
	public const PRICE_QUANTITY   = 'quantity_based';

	/**
	 * Database table name (without prefix).
	 */
	private const TABLE_NAME = 'wpss_service_addons';

	/**
	 * Create a new addon for a service.
	 *
	 * @param int   $service_id Service post ID.
	 * @param array $data       Addon data.
	 * @return array{success: bool, addon_id: int|null, message: string}
	 */
	public function create( int $service_id, array $data ): array {
		global $wpdb;

		// Validate service exists.
		if ( 'wpss_service' !== get_post_type( $service_id ) ) {
			return array(
				'success'  => false,
				'addon_id' => null,
				'message'  => __( 'Invalid service ID.', 'wp-sell-services' ),
			);
		}

		// Validate required fields.
		if ( empty( $data['title'] ) ) {
			return array(
				'success'  => false,
				'addon_id' => null,
				'message'  => __( 'Addon title is required.', 'wp-sell-services' ),
			);
		}

		$field_type = $data['field_type'] ?? self::TYPE_CHECKBOX;
		if ( ! $this->is_valid_field_type( $field_type ) ) {
			return array(
				'success'  => false,
				'addon_id' => null,
				'message'  => __( 'Invalid field type.', 'wp-sell-services' ),
			);
		}

		$table = $wpdb->prefix . self::TABLE_NAME;

		// Get next sort order.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max_sort = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(sort_order) FROM {$table} WHERE service_id = %d",
				$service_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$addon_data = array(
			'service_id'          => $service_id,
			'title'               => sanitize_text_field( $data['title'] ),
			'description'         => sanitize_textarea_field( $data['description'] ?? '' ),
			'field_type'          => $field_type,
			'price'               => (float) ( $data['price'] ?? 0 ),
			'price_type'          => $this->sanitize_price_type( $data['price_type'] ?? self::PRICE_FLAT ),
			'min_quantity'        => (int) ( $data['min_quantity'] ?? 1 ),
			'max_quantity'        => (int) ( $data['max_quantity'] ?? 10 ),
			'is_required'         => ! empty( $data['is_required'] ) ? 1 : 0,
			'options'             => wp_json_encode( $data['options'] ?? array() ),
			'delivery_days_extra' => (int) ( $data['delivery_days_extra'] ?? 0 ),
			'applies_to'          => wp_json_encode( $data['applies_to'] ?? array( 'all' ) ),
			'sort_order'          => $max_sort + 1,
			'is_active'           => 1,
			'created_at'          => current_time( 'mysql' ),
			'updated_at'          => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table,
			$addon_data,
			array( '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return array(
				'success'  => false,
				'addon_id' => null,
				'message'  => __( 'Failed to create addon.', 'wp-sell-services' ),
			);
		}

		$addon_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a service addon is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $addon_id   Addon ID.
		 * @param int   $service_id Service ID.
		 * @param array $addon_data Addon data.
		 */
		do_action( 'wpss_addon_created', $addon_id, $service_id, $addon_data );

		return array(
			'success'  => true,
			'addon_id' => $addon_id,
			'message'  => __( 'Addon created successfully.', 'wp-sell-services' ),
		);
	}

	/**
	 * Update an existing addon.
	 *
	 * @param int   $addon_id Addon ID.
	 * @param array $data     Data to update.
	 * @return array{success: bool, message: string}
	 */
	public function update( int $addon_id, array $data ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$addon = $this->get( $addon_id );
		if ( ! $addon ) {
			return array(
				'success' => false,
				'message' => __( 'Addon not found.', 'wp-sell-services' ),
			);
		}

		$update_data = array( 'updated_at' => current_time( 'mysql' ) );
		$formats     = array( '%s' );

		// Allowed fields to update.
		$allowed = array(
			'title'               => '%s',
			'description'         => '%s',
			'field_type'          => '%s',
			'price_type'          => '%s',
			'price'               => '%f',
			'delivery_days_extra' => '%d',
			'min_quantity'        => '%d',
			'max_quantity'        => '%d',
			'is_required'         => '%d',
			'is_active'           => '%d',
			'sort_order'          => '%d',
		);

		foreach ( $allowed as $field => $format ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$formats[]             = $format;
			}
		}

		// JSON fields.
		$json_fields = array( 'options', 'applies_to' );
		foreach ( $json_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = wp_json_encode( $data[ $field ] );
				$formats[]             = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $addon_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to update addon.', 'wp-sell-services' ),
			);
		}

		/**
		 * Fires after a service addon is updated.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $addon_id Addon ID.
		 * @param array $data     Updated data.
		 */
		do_action( 'wpss_addon_updated', $addon_id, $update_data );

		return array(
			'success' => true,
			'message' => __( 'Addon updated successfully.', 'wp-sell-services' ),
		);
	}

	/**
	 * Get a single addon by ID.
	 *
	 * @param int $addon_id Addon ID.
	 * @return object|null Addon object or null.
	 */
	public function get( int $addon_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$addon = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$addon_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $addon ) {
			return null;
		}

		return $this->format_addon( $addon );
	}

	/**
	 * Get all addons for a service.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $args       Query arguments.
	 * @return array Array of addon objects.
	 */
	public function get_service_addons( int $service_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$defaults = array(
			'active_only' => true,
			'package_id'  => null,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( 'service_id = %d' );
		$params = array( $service_id );

		if ( $args['active_only'] ) {
			$where[] = 'is_active = 1';
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$addons = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY sort_order ASC, id ASC",
				...$params
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$formatted = array_map( array( $this, 'format_addon' ), $addons );

		// Filter by package if specified.
		if ( null !== $args['package_id'] ) {
			$formatted = array_filter(
				$formatted,
				function ( $addon ) use ( $args ) {
					return $this->addon_applies_to_package( $addon, $args['package_id'] );
				}
			);
		}

		return array_values( $formatted );
	}

	/**
	 * Delete an addon.
	 *
	 * @param int $addon_id Addon ID.
	 * @return array{success: bool, message: string}
	 */
	public function delete( int $addon_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$addon = $this->get( $addon_id );
		if ( ! $addon ) {
			return array(
				'success' => false,
				'message' => __( 'Addon not found.', 'wp-sell-services' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $addon_id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to delete addon.', 'wp-sell-services' ),
			);
		}

		/**
		 * Fires after a service addon is deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $addon_id Addon ID.
		 * @param object $addon    Deleted addon data.
		 */
		do_action( 'wpss_addon_deleted', $addon_id, $addon );

		return array(
			'success' => true,
			'message' => __( 'Addon deleted.', 'wp-sell-services' ),
		);
	}

	/**
	 * Calculate addon price based on pricing type and context.
	 *
	 * @param object $addon      Addon object.
	 * @param float  $base_price Base service/package price.
	 * @param int    $quantity   Quantity selected.
	 * @return float Calculated price.
	 */
	public function calculate_price( object $addon, float $base_price, int $quantity = 1 ): float {
		$unit_price = 0;

		switch ( $addon->price_type ) {
			case self::PRICE_FLAT:
				$unit_price = $addon->price;
				break;

			case self::PRICE_PERCENTAGE:
				$unit_price = ( $base_price * $addon->price ) / 100;
				break;

			case self::PRICE_QUANTITY:
			default:
				$unit_price = $addon->price;
				break;
		}

		// For quantity type, multiply by quantity.
		if ( self::TYPE_QUANTITY === $addon->field_type ) {
			return $unit_price * $quantity;
		}

		return $unit_price;
	}

	/**
	 * Calculate total delivery days added by addon.
	 *
	 * @param object $addon    Addon object.
	 * @param int    $quantity Quantity selected.
	 * @return int Extra delivery days.
	 */
	public function calculate_delivery_days( object $addon, int $quantity = 1 ): int {
		// Quantity addons may add days per unit.
		if ( self::TYPE_QUANTITY === $addon->field_type ) {
			return $addon->delivery_days_extra * $quantity;
		}

		return $addon->delivery_days_extra;
	}

	/**
	 * Validate addon selection.
	 *
	 * @param object $addon    Addon object.
	 * @param mixed  $value    Selected value.
	 * @param int    $quantity Quantity.
	 * @return array{valid: bool, message: string}
	 */
	public function validate_selection( object $addon, $value, int $quantity = 1 ): array {
		// Check required.
		if ( $addon->is_required && empty( $value ) ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					/* translators: %s: Addon title */
					__( '%s is required.', 'wp-sell-services' ),
					$addon->title
				),
			);
		}

		// Skip validation if not selected.
		if ( empty( $value ) ) {
			return array(
				'valid'   => true,
				'message' => '',
			);
		}

		// Validate based on field type.
		switch ( $addon->field_type ) {
			case self::TYPE_QUANTITY:
				if ( $addon->min_quantity > 0 && $quantity < $addon->min_quantity ) {
					return array(
						'valid'   => false,
						'message' => sprintf(
							/* translators: 1: Addon title, 2: Minimum quantity */
							__( '%1$s requires minimum quantity of %2$d.', 'wp-sell-services' ),
							$addon->title,
							$addon->min_quantity
						),
					);
				}

				if ( $addon->max_quantity > 0 && $quantity > $addon->max_quantity ) {
					return array(
						'valid'   => false,
						'message' => sprintf(
							/* translators: 1: Addon title, 2: Maximum quantity */
							__( '%1$s allows maximum quantity of %2$d.', 'wp-sell-services' ),
							$addon->title,
							$addon->max_quantity
						),
					);
				}
				break;

			case self::TYPE_DROPDOWN:
				// Validate value is in options.
				$valid_options = wp_list_pluck( $addon->options, 'value' );
				if ( ! in_array( $value, $valid_options, true ) ) {
					return array(
						'valid'   => false,
						'message' => sprintf(
							/* translators: %s: Addon title */
							__( 'Invalid selection for %s.', 'wp-sell-services' ),
							$addon->title
						),
					);
				}
				break;

			case self::TYPE_TEXT:
				// Basic text validation - max 500 chars.
				if ( mb_strlen( $value ) > 500 ) {
					return array(
						'valid'   => false,
						'message' => sprintf(
							/* translators: %s: Addon title */
							__( '%s must not exceed 500 characters.', 'wp-sell-services' ),
							$addon->title
						),
					);
				}
				break;
		}

		return array(
			'valid'   => true,
			'message' => '',
		);
	}

	/**
	 * Check if addon applies to a specific package.
	 *
	 * @param object $addon      Addon object.
	 * @param int    $package_id Package ID.
	 * @return bool True if addon applies.
	 */
	private function addon_applies_to_package( object $addon, int $package_id ): bool {
		$applies_to = $addon->applies_to;

		// If 'all', addon applies to all packages.
		if ( in_array( 'all', $applies_to, true ) ) {
			return true;
		}

		// Check if package ID is in the list.
		return in_array( $package_id, $applies_to, true );
	}

	/**
	 * Check if field type is valid.
	 *
	 * @param string $type Field type.
	 * @return bool True if valid.
	 */
	private function is_valid_field_type( string $type ): bool {
		return in_array(
			$type,
			array(
				self::TYPE_CHECKBOX,
				self::TYPE_QUANTITY,
				self::TYPE_DROPDOWN,
				self::TYPE_TEXT,
			),
			true
		);
	}

	/**
	 * Sanitize price type.
	 *
	 * @param string $type Price type.
	 * @return string Valid price type.
	 */
	private function sanitize_price_type( string $type ): string {
		$valid = array( self::PRICE_FLAT, self::PRICE_PERCENTAGE, self::PRICE_QUANTITY );
		return in_array( $type, $valid, true ) ? $type : self::PRICE_FLAT;
	}

	/**
	 * Format addon from database row.
	 *
	 * @param object $row Database row.
	 * @return object Formatted addon object.
	 */
	private function format_addon( object $row ): object {
		$decoded_options          = json_decode( $row->options ?? '[]', true );
		$row->options             = ! empty( $decoded_options ) ? $decoded_options : array();
		$decoded_applies          = json_decode( $row->applies_to ?? '["all"]', true );
		$row->applies_to          = ! empty( $decoded_applies ) ? $decoded_applies : array( 'all' );
		$row->is_required         = (bool) ( $row->is_required ?? false );
		$row->is_active           = (bool) ( $row->is_active ?? true );
		$row->price               = (float) ( $row->price ?? 0 );
		$row->delivery_days_extra = (int) ( $row->delivery_days_extra ?? 0 );
		$row->min_quantity        = (int) ( $row->min_quantity ?? 1 );
		$row->max_quantity        = (int) ( $row->max_quantity ?? 10 );

		return $row;
	}

	/**
	 * Get available field types with labels.
	 *
	 * @return array<string, string> Field types.
	 */
	public static function get_field_types(): array {
		return array(
			self::TYPE_CHECKBOX => __( 'Checkbox', 'wp-sell-services' ),
			self::TYPE_QUANTITY => __( 'Quantity', 'wp-sell-services' ),
			self::TYPE_DROPDOWN => __( 'Dropdown', 'wp-sell-services' ),
			self::TYPE_TEXT     => __( 'Text Input', 'wp-sell-services' ),
		);
	}

	/**
	 * Get available pricing types with labels.
	 *
	 * @return array<string, string> Pricing types.
	 */
	public static function get_pricing_types(): array {
		return array(
			self::PRICE_FLAT       => __( 'Flat Fee', 'wp-sell-services' ),
			self::PRICE_PERCENTAGE => __( 'Percentage of Price', 'wp-sell-services' ),
			self::PRICE_QUANTITY   => __( 'Per Quantity', 'wp-sell-services' ),
		);
	}

	/**
	 * Get common addon templates.
	 *
	 * @return array Common addon configurations.
	 */
	public static function get_addon_templates(): array {
		return array(
			'rush_delivery'    => array(
				'title'               => __( 'Rush Delivery', 'wp-sell-services' ),
				'description'         => __( 'Get your order faster', 'wp-sell-services' ),
				'field_type'          => self::TYPE_CHECKBOX,
				'price_type'          => self::PRICE_PERCENTAGE,
				'price'               => 25,
				'delivery_days_extra' => -2,
			),
			'extra_revision'   => array(
				'title'        => __( 'Extra Revision', 'wp-sell-services' ),
				'description'  => __( 'Add additional revision rounds', 'wp-sell-services' ),
				'field_type'   => self::TYPE_QUANTITY,
				'price_type'   => self::PRICE_QUANTITY,
				'price'        => 10,
				'min_quantity' => 1,
				'max_quantity' => 5,
			),
			'source_files'     => array(
				'title'       => __( 'Source Files', 'wp-sell-services' ),
				'description' => __( 'Receive editable source files', 'wp-sell-services' ),
				'field_type'  => self::TYPE_CHECKBOX,
				'price_type'  => self::PRICE_FLAT,
				'price'       => 20,
			),
			'priority_support' => array(
				'title'       => __( 'Priority Support', 'wp-sell-services' ),
				'description' => __( '24/7 priority support during project', 'wp-sell-services' ),
				'field_type'  => self::TYPE_CHECKBOX,
				'price_type'  => self::PRICE_FLAT,
				'price'       => 15,
			),
			'custom_text'      => array(
				'title'       => __( 'Custom Text/Name', 'wp-sell-services' ),
				'description' => __( 'Add personalized text', 'wp-sell-services' ),
				'field_type'  => self::TYPE_TEXT,
				'price_type'  => self::PRICE_FLAT,
				'price'       => 0,
			),
			'resolution'       => array(
				'title'       => __( 'Resolution', 'wp-sell-services' ),
				'description' => __( 'Choose output resolution', 'wp-sell-services' ),
				'field_type'  => self::TYPE_DROPDOWN,
				'price_type'  => self::PRICE_FLAT,
				'options'     => array(
					array(
						'label' => __( 'HD 720p', 'wp-sell-services' ),
						'value' => '720p',
						'price' => 0,
					),
					array(
						'label' => __( 'Full HD 1080p (+$10)', 'wp-sell-services' ),
						'value' => '1080p',
						'price' => 10,
					),
					array(
						'label' => __( '4K UHD (+$25)', 'wp-sell-services' ),
						'value' => '4k',
						'price' => 25,
					),
				),
			),
		);
	}
}
