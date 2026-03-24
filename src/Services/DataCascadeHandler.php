<?php
/**
 * Data Cascade Handler
 *
 * Handles cascade deletion of plugin data when services or users are deleted.
 *
 * @package WPSellServices\Services
 * @since   1.5.0
 */

namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DataCascadeHandler class.
 *
 * Hooks into WordPress post and user deletion to clean up related plugin data
 * from custom tables, preventing orphaned records.
 *
 * @since 1.5.0
 */
class DataCascadeHandler {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Table prefix for plugin tables.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->prefix = $wpdb->prefix . 'wpss_';
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'before_delete_post', array( $this, 'on_post_deleted' ), 10, 1 );
		add_action( 'delete_user', array( $this, 'on_user_deleted' ), 10, 1 );
	}

	/**
	 * Handle post deletion cascade.
	 *
	 * Cleans up custom table data when a service or buyer request is permanently deleted.
	 *
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public function on_post_deleted( int $post_id ): void {
		$post_type = get_post_type( $post_id );

		if ( 'wpss_service' === $post_type ) {
			$this->delete_service_data( $post_id );
		} elseif ( 'wpss_request' === $post_type ) {
			$this->delete_buyer_request_data( $post_id );
		}
	}

	/**
	 * Handle user deletion cascade.
	 *
	 * Cleans up all plugin data associated with a deleted user.
	 *
	 * @param int $user_id User ID being deleted.
	 * @return void
	 */
	public function on_user_deleted( int $user_id ): void {
		$this->delete_user_data( $user_id );
	}

	/**
	 * Delete all data related to a service.
	 *
	 * @param int $service_id Service post ID.
	 * @return void
	 */
	private function delete_service_data( int $service_id ): void {
		/**
		 * Fires before service cascade data is deleted.
		 *
		 * @since 1.5.0
		 * @param int $service_id Service post ID.
		 */
		do_action( 'wpss_before_cascade_delete_service', $service_id );

		// Delete service packages.
		$this->delete_where( 'service_packages', 'service_id', $service_id );

		// Delete service addons.
		$this->delete_where( 'service_addons', 'service_id', $service_id );

		// Delete reviews for this service.
		$this->delete_where( 'reviews', 'service_id', $service_id );

		// Delete portfolio items linked to this service.
		$this->delete_where( 'portfolio_items', 'service_id', $service_id );

		// Get order IDs for this service before deleting them.
		$order_ids = $this->get_column( 'orders', 'id', 'service_id', $service_id );

		// Delete order-related data.
		foreach ( $order_ids as $order_id ) {
			$this->delete_order_data( (int) $order_id );
		}

		// Delete orders for this service.
		$this->delete_where( 'orders', 'service_id', $service_id );

		// Delete conversations linked to this service.
		$conversation_ids = $this->get_column( 'conversations', 'id', 'service_id', $service_id );
		foreach ( $conversation_ids as $conversation_id ) {
			$this->delete_where( 'messages', 'conversation_id', (int) $conversation_id );
		}
		$this->delete_where( 'conversations', 'service_id', $service_id );

		/**
		 * Fires after service cascade data is deleted.
		 *
		 * @since 1.5.0
		 * @param int $service_id Service post ID.
		 */
		do_action( 'wpss_after_cascade_delete_service', $service_id );
	}

	/**
	 * Delete all data related to a buyer request.
	 *
	 * @param int $request_id Buyer request post ID.
	 * @return void
	 */
	private function delete_buyer_request_data( int $request_id ): void {
		/**
		 * Fires before buyer request cascade data is deleted.
		 *
		 * @since 1.5.0
		 * @param int $request_id Buyer request post ID.
		 */
		do_action( 'wpss_before_cascade_delete_request', $request_id );

		// Delete proposals for this buyer request.
		$this->delete_where( 'proposals', 'request_id', $request_id );

		/**
		 * Fires after buyer request cascade data is deleted.
		 *
		 * @since 1.5.0
		 * @param int $request_id Buyer request post ID.
		 */
		do_action( 'wpss_after_cascade_delete_request', $request_id );
	}

	/**
	 * Delete all data related to a user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function delete_user_data( int $user_id ): void {
		/**
		 * Fires before user cascade data is deleted.
		 *
		 * @since 1.5.0
		 * @param int $user_id User ID.
		 */
		do_action( 'wpss_before_cascade_delete_user', $user_id );

		// Delete vendor profile.
		$this->delete_where( 'vendor_profiles', 'user_id', $user_id );

		// Delete portfolio items.
		$this->delete_where( 'portfolio_items', 'vendor_id', $user_id );

		// Delete notifications.
		$this->delete_where( 'notifications', 'user_id', $user_id );

		// Delete wallet transactions.
		$this->delete_where( 'wallet_transactions', 'user_id', $user_id );

		// Delete withdrawals.
		$this->delete_where( 'withdrawals', 'vendor_id', $user_id );

		// Delete proposals by this vendor.
		$this->delete_where( 'proposals', 'vendor_id', $user_id );

		// Get order IDs where user is customer or vendor.
		$customer_order_ids = $this->get_column( 'orders', 'id', 'customer_id', $user_id );
		$vendor_order_ids   = $this->get_column( 'orders', 'id', 'vendor_id', $user_id );
		$order_ids          = array_unique( array_merge( $customer_order_ids, $vendor_order_ids ) );

		// Delete order-related data.
		foreach ( $order_ids as $order_id ) {
			$this->delete_order_data( (int) $order_id );
		}

		// Delete orders where user is customer or vendor.
		$this->delete_where( 'orders', 'customer_id', $user_id );
		$this->delete_where( 'orders', 'vendor_id', $user_id );

		// Delete reviews written by or about this user.
		$this->delete_where( 'reviews', 'reviewer_id', $user_id );
		$this->delete_where( 'reviews', 'reviewee_id', $user_id );

		/**
		 * Fires after user cascade data is deleted.
		 *
		 * @since 1.5.0
		 * @param int $user_id User ID.
		 */
		do_action( 'wpss_after_cascade_delete_user', $user_id );
	}

	/**
	 * Delete all data related to an order.
	 *
	 * This is called as part of service or user cascade deletion.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function delete_order_data( int $order_id ): void {
		// Delete order requirements.
		$this->delete_where( 'order_requirements', 'order_id', $order_id );

		// Delete conversations and their messages.
		$conversation_ids = $this->get_column( 'conversations', 'id', 'order_id', $order_id );
		foreach ( $conversation_ids as $conversation_id ) {
			$this->delete_where( 'messages', 'conversation_id', (int) $conversation_id );
		}
		$this->delete_where( 'conversations', 'order_id', $order_id );

		// Delete deliveries.
		$this->delete_where( 'deliveries', 'order_id', $order_id );

		// Delete extension requests.
		$this->delete_where( 'extension_requests', 'order_id', $order_id );

		// Delete disputes and their messages.
		$dispute_ids = $this->get_column( 'disputes', 'id', 'order_id', $order_id );
		foreach ( $dispute_ids as $dispute_id ) {
			$this->delete_where( 'dispute_messages', 'dispute_id', (int) $dispute_id );
		}
		$this->delete_where( 'disputes', 'order_id', $order_id );

		// Delete reviews for this order.
		$this->delete_where( 'reviews', 'order_id', $order_id );
	}

	/**
	 * Delete rows from a plugin table where a column matches a value.
	 *
	 * @param string $table  Table name without prefix (e.g., 'orders').
	 * @param string $column Column name.
	 * @param int    $value  Value to match.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	private function delete_where( string $table, string $column, int $value ) {
		$table_name = $this->prefix . $table;

		return $this->wpdb->delete(
			$table_name,
			array( $column => $value ),
			array( '%d' )
		);
	}

	/**
	 * Get column values from a plugin table where a column matches a value.
	 *
	 * @param string $table        Table name without prefix.
	 * @param string $select_col   Column to select.
	 * @param string $where_col    Column to filter by.
	 * @param int    $where_value  Value to match.
	 * @return array<int|string> Array of column values.
	 */
	private function get_column( string $table, string $select_col, string $where_col, int $where_value ): array {
		$table_name = $this->prefix . $table;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column names are hardcoded internal strings.
		$results = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT {$select_col} FROM {$table_name} WHERE {$where_col} = %d",
				$where_value
			)
		);

		return $results ?: array();
	}
}
