<?php
/**
 * Database Migration Manager
 *
 * Handles database migrations and upgrades.
 *
 * @package WPSellServices\Database
 * @since   1.0.0
 */

namespace WPSellServices\Database;

defined( 'ABSPATH' ) || exit;

/**
 * MigrationManager class.
 *
 * @since 1.0.0
 */
class MigrationManager {

	/**
	 * Schema manager instance.
	 *
	 * @var SchemaManager
	 */
	private SchemaManager $schema;

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param SchemaManager $schema Schema manager instance.
	 */
	public function __construct( SchemaManager $schema ) {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->schema = $schema;
	}

	/**
	 * Run all pending migrations.
	 *
	 * @return array<string, bool> Migration results.
	 */
	public function run_migrations(): array {
		$results = [];

		// Check if migrating from woo-sell-services.
		if ( $this->should_migrate_from_wss() ) {
			$results['wss_migration'] = $this->migrate_from_wss();
		}

		return $results;
	}

	/**
	 * Check if we should migrate from woo-sell-services.
	 *
	 * @return bool True if migration needed.
	 */
	public function should_migrate_from_wss(): bool {
		// Check if old plugin data exists.
		$old_conversations = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->wpdb->prefix}wss_conversations"
		);

		if ( null !== $old_conversations && (int) $old_conversations > 0 ) {
			// Check if already migrated.
			$migrated = get_option( 'wpss_migrated_from_wss', false );
			return ! $migrated;
		}

		return false;
	}

	/**
	 * Migrate data from woo-sell-services plugin.
	 *
	 * @return bool True on success.
	 */
	public function migrate_from_wss(): bool {
		try {
			$this->wpdb->query( 'START TRANSACTION' );

			// Migrate services (products with _wss_type).
			$this->migrate_wss_services();

			// Migrate conversations.
			$this->migrate_wss_conversations();

			// Migrate orders from WC order meta.
			$this->migrate_wss_orders();

			// Migrate vendor data.
			$this->migrate_wss_vendors();

			$this->wpdb->query( 'COMMIT' );

			update_option( 'wpss_migrated_from_wss', true );
			update_option( 'wpss_migration_date', current_time( 'mysql' ) );

			return true;
		} catch ( \Exception $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			error_log( 'WPSS Migration Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Migrate services from WooCommerce products.
	 *
	 * @return int Number of migrated services.
	 */
	private function migrate_wss_services(): int {
		$count = 0;

		// Find all products with _wss_type = 'yes'.
		$products = $this->wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_author, p.post_status
			FROM {$this->wpdb->posts} p
			INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE pm.meta_key = '_wss_type' AND pm.meta_value = 'yes'
			AND p.post_type = 'product'"
		);

		foreach ( $products as $product ) {
			// Create wpss_service post.
			$service_id = wp_insert_post(
				[
					'post_type'    => 'wpss_service',
					'post_title'   => $product->post_title,
					'post_content' => $product->post_content,
					'post_excerpt' => $product->post_excerpt,
					'post_author'  => $product->post_author,
					'post_status'  => $product->post_status,
				]
			);

			if ( ! is_wp_error( $service_id ) ) {
				// Copy featured image.
				$thumbnail_id = get_post_thumbnail_id( $product->ID );
				if ( $thumbnail_id ) {
					set_post_thumbnail( $service_id, $thumbnail_id );
				}

				// Copy relevant meta.
				$meta_keys = [
					'_wss_delivery_time',
					'_wss_revision_limit',
					'_wss_requirements',
				];

				foreach ( $meta_keys as $key ) {
					$value = get_post_meta( $product->ID, $key, true );
					if ( $value ) {
						$new_key = str_replace( '_wss_', '_wpss_', $key );
						update_post_meta( $service_id, $new_key, $value );
					}
				}

				// Store old ID reference for migration tracking.
				update_post_meta( $service_id, '_wpss_migrated_from_wss', $product->ID );

				++$count;
			}
		}

		return $count;
	}

	/**
	 * Migrate conversations from old table.
	 *
	 * @return int Number of migrated conversations.
	 */
	private function migrate_wss_conversations(): int {
		$count     = 0;
		$old_table = $this->wpdb->prefix . 'wss_conversations';
		$new_table = $this->schema->get_table_name( 'conversations' );

		// Check if old table exists.
		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$old_table
			)
		);

		if ( ! $table_exists ) {
			return 0;
		}

		$conversations = $this->wpdb->get_results(
			"SELECT * FROM {$old_table}"
		);

		foreach ( $conversations as $conv ) {
			$inserted = $this->wpdb->insert(
				$new_table,
				[
					'order_id'     => $conv->order_id ?? 0,
					'sender_id'    => $conv->sender_id ?? 0,
					'recipient_id' => $conv->recipient_id ?? 0,
					'message'      => $conv->message ?? '',
					'message_type' => 'text',
					'attachments'  => $conv->attachments ?? null,
					'is_read'      => $conv->is_read ?? 0,
					'created_at'   => $conv->created_at ?? current_time( 'mysql' ),
				],
				[ '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' ]
			);

			if ( $inserted ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Migrate orders from WooCommerce order meta.
	 *
	 * @return int Number of migrated orders.
	 */
	private function migrate_wss_orders(): int {
		$count       = 0;
		$orders_table = $this->schema->get_table_name( 'orders' );

		// Find WC orders with service items.
		$wc_orders = $this->wpdb->get_results(
			"SELECT DISTINCT om.order_id
			FROM {$this->wpdb->prefix}woocommerce_order_itemmeta oim
			INNER JOIN {$this->wpdb->prefix}woocommerce_order_items om ON oim.order_item_id = om.order_item_id
			WHERE oim.meta_key = '_wss_service' AND oim.meta_value = 'yes'"
		);

		foreach ( $wc_orders as $wc_order ) {
			$order_id = $wc_order->order_id;
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			foreach ( $order->get_items() as $item_id => $item ) {
				$is_service = wc_get_order_item_meta( $item_id, '_wss_service', true );

				if ( 'yes' !== $is_service ) {
					continue;
				}

				$product_id = $item->get_product_id();
				$vendor_id  = wc_get_order_item_meta( $item_id, '_wss_vendor_id', true );

				// Find migrated service ID.
				$service_id = $this->wpdb->get_var(
					$this->wpdb->prepare(
						"SELECT post_id FROM {$this->wpdb->postmeta}
						WHERE meta_key = '_wpss_migrated_from_wss' AND meta_value = %d",
						$product_id
					)
				);

				if ( ! $service_id ) {
					$service_id = $product_id;
				}

				// Generate order number.
				$order_number = 'WPSS-' . $order_id . '-' . $item_id;

				// Get order status mapping.
				$wss_status = wc_get_order_item_meta( $item_id, '_wss_order_status', true );
				$status     = $this->map_wss_status( $wss_status ?: 'pending' );

				$inserted = $this->wpdb->insert(
					$orders_table,
					[
						'order_number'      => $order_number,
						'customer_id'       => $order->get_customer_id(),
						'vendor_id'         => $vendor_id ?: get_post_field( 'post_author', $product_id ),
						'service_id'        => $service_id,
						'platform'          => 'woocommerce',
						'platform_order_id' => $order_id,
						'platform_item_id'  => $item_id,
						'subtotal'          => $item->get_subtotal(),
						'total'             => $item->get_total(),
						'currency'          => $order->get_currency(),
						'status'            => $status,
						'payment_status'    => $order->is_paid() ? 'paid' : 'pending',
						'paid_at'           => $order->get_date_paid() ? $order->get_date_paid()->format( 'Y-m-d H:i:s' ) : null,
						'created_at'        => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
					],
					[ '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ]
				);

				if ( $inserted ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Migrate vendor profiles.
	 *
	 * @return int Number of migrated vendors.
	 */
	private function migrate_wss_vendors(): int {
		$count         = 0;
		$vendors_table = $this->schema->get_table_name( 'vendor_profiles' );

		// Find users with vendor meta.
		$vendors = $this->wpdb->get_results(
			"SELECT DISTINCT user_id FROM {$this->wpdb->usermeta}
			WHERE meta_key LIKE '_wss_vendor_%'"
		);

		foreach ( $vendors as $vendor ) {
			$user_id = $vendor->user_id;
			$user    = get_userdata( $user_id );

			if ( ! $user ) {
				continue;
			}

			// Check if already exists.
			$exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$vendors_table} WHERE user_id = %d",
					$user_id
				)
			);

			if ( $exists ) {
				continue;
			}

			$inserted = $this->wpdb->insert(
				$vendors_table,
				[
					'user_id'           => $user_id,
					'display_name'      => $user->display_name,
					'tagline'           => get_user_meta( $user_id, '_wss_vendor_tagline', true ) ?: null,
					'bio'               => get_user_meta( $user_id, '_wss_vendor_bio', true ) ?: null,
					'verification_tier' => 'basic',
					'country'           => get_user_meta( $user_id, '_wss_vendor_country', true ) ?: null,
					'total_orders'      => (int) get_user_meta( $user_id, '_wss_total_orders', true ),
					'completed_orders'  => (int) get_user_meta( $user_id, '_wss_completed_orders', true ),
					'avg_rating'        => (float) get_user_meta( $user_id, '_wss_rating_average', true ),
					'total_reviews'     => (int) get_user_meta( $user_id, '_wss_rating_count', true ),
					'created_at'        => $user->user_registered,
				],
				[ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%d', '%s' ]
			);

			if ( $inserted ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Map old WSS status to new WPSS status.
	 *
	 * @param string $old_status Old status.
	 * @return string New status.
	 */
	private function map_wss_status( string $old_status ): string {
		$map = [
			'pending'            => 'pending_payment',
			'processing'         => 'in_progress',
			'requirements'       => 'waiting_requirements',
			'in-progress'        => 'in_progress',
			'delivered'          => 'pending_approval',
			'revision'           => 'revision_requested',
			'completed'          => 'completed',
			'cancelled'          => 'cancelled',
			'refunded'           => 'cancelled',
			'disputed'           => 'in_dispute',
		];

		return $map[ $old_status ] ?? 'pending_payment';
	}

	/**
	 * Get migration status.
	 *
	 * @return array<string, mixed> Migration status info.
	 */
	public function get_migration_status(): array {
		return [
			'migrated'       => (bool) get_option( 'wpss_migrated_from_wss', false ),
			'migration_date' => get_option( 'wpss_migration_date', '' ),
			'db_version'     => get_option( SchemaManager::VERSION_OPTION, '0.0.0' ),
			'current_version' => SchemaManager::DB_VERSION,
			'needs_update'   => $this->schema->needs_update(),
		];
	}

	/**
	 * Rollback migration.
	 *
	 * @return bool True on success.
	 */
	public function rollback_migration(): bool {
		// Delete migrated services.
		$migrated_services = get_posts(
			[
				'post_type'      => 'wpss_service',
				'posts_per_page' => -1,
				'meta_key'       => '_wpss_migrated_from_wss',
				'fields'         => 'ids',
			]
		);

		foreach ( $migrated_services as $service_id ) {
			wp_delete_post( $service_id, true );
		}

		// Clear migration flags.
		delete_option( 'wpss_migrated_from_wss' );
		delete_option( 'wpss_migration_date' );

		return true;
	}
}
