<?php
/**
 * Data Cascade Handler
 *
 * Handles cascade deletion of plugin data when services or users are deleted.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DataCascadeHandler class.
 *
 * Hooks into WordPress post and user deletion to clean up related plugin data
 * from custom tables, preventing orphaned records.
 *
 * @since 1.0.0
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
		 * @since 1.0.0
		 * @param int $service_id Service post ID.
		 */
		do_action( 'wpss_before_cascade_delete_service', $service_id );

		// Delete service packages.
		$this->delete_where( 'service_packages', 'service_id', $service_id );

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
		 * @since 1.0.0
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
		 * @since 1.0.0
		 * @param int $request_id Buyer request post ID.
		 */
		do_action( 'wpss_before_cascade_delete_request', $request_id );

		// Delete proposals for this buyer request.
		$this->delete_where( 'proposals', 'request_id', $request_id );

		/**
		 * Fires after buyer request cascade data is deleted.
		 *
		 * @since 1.0.0
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
		 * @since 1.0.0
		 * @param int $user_id User ID.
		 */
		do_action( 'wpss_before_cascade_delete_user', $user_id );

		/**
		 * Filter whether records shared with another member survive this cascade.
		 *
		 * An order, a review, a message, a dispute and the ledger line behind a
		 * sale each belong to TWO people, and the owner needs them for their own
		 * accounting. Deleting them because one of those people is going destroys
		 * the other one's history: the seller's completed jobs and earnings, the
		 * buyer's proof of what they bought, the owner's revenue record.
		 *
		 * So by default those rows are KEPT and anonymised: the departing member's
		 * id columns become 0 (rendered by wpss_get_member_display_name() as a
		 * deleted user) and the personal columns on those rows - billing address,
		 * reviewer name, seller notes, payout details - are blanked. Only rows the
		 * member owns alone are deleted.
		 *
		 * Return false to delete every row the member is party to instead. That
		 * takes the counterparty's records with it, so it is for throwaway data
		 * (seeded demos, test flows), never for a live marketplace.
		 *
		 * @since 1.5.2
		 * @since 1.7.1 Defaults to true on every path, including an administrator
		 *              deleting a user from wp-admin and the privacy eraser.
		 *
		 * @param bool $preserve Whether to keep shared records.
		 * @param int  $user_id  User being deleted.
		 */
		$preserve_shared = (bool) apply_filters( 'wpss_cascade_preserve_shared_records', true, $user_id );

		// Rows the member owns alone. Favourites, cart, push devices and app
		// passwords are user meta, which wp_delete_user() removes itself.
		$this->delete_where( 'vendor_profiles', 'user_id', $user_id );
		$this->delete_where( 'portfolio_items', 'vendor_id', $user_id );
		$this->delete_where( 'notifications', 'user_id', $user_id );

		/*
		 * Abuse reports, both directions.
		 *
		 * Deleted whether or not shared records are being preserved, because
		 * neither direction survives the member usefully: a report THEY filed is
		 * their own words, and a report ABOUT them describes an account that no
		 * longer exists and a person no owner can action.
		 *
		 * The audit log is deliberately NOT touched here. It records what the
		 * marketplace did rather than who a member is, it carries no personal
		 * detail beyond an actor id, and it is the owner's own compliance
		 * record — not the departing member's to erase.
		 */
		$this->delete_where( 'reports', 'reporter_id', $user_id );
		$this->delete_where( 'reports', 'reported_user_id', $user_id );

		if ( $preserve_shared ) {
			$this->anonymise_user( $user_id );
		} else {
			$this->delete_where( 'wallet_transactions', 'user_id', $user_id );
			$this->delete_where( 'withdrawals', 'vendor_id', $user_id );
			$this->delete_where( 'proposals', 'vendor_id', $user_id );

			$customer_order_ids = $this->get_column( 'orders', 'id', 'customer_id', $user_id );
			$vendor_order_ids   = $this->get_column( 'orders', 'id', 'vendor_id', $user_id );
			$order_ids          = array_unique( array_merge( $customer_order_ids, $vendor_order_ids ) );

			foreach ( $order_ids as $order_id ) {
				$this->delete_order_data( (int) $order_id );
			}

			$this->delete_where( 'orders', 'customer_id', $user_id );
			$this->delete_where( 'orders', 'vendor_id', $user_id );
			$this->delete_where( 'reviews', 'reviewer_id', $user_id );
			$this->delete_where( 'reviews', 'reviewee_id', $user_id );
		}

		/**
		 * Fires after user cascade data is deleted.
		 *
		 * @since 1.0.0
		 * @param int $user_id User ID.
		 */
		do_action( 'wpss_after_cascade_delete_user', $user_id );
	}

	/**
	 * Detach a member from every shared row without deleting the row.
	 *
	 * Each entry is table => ( id column => personal columns to blank on the
	 * rows where that column named this member ). The id column becomes 0.
	 *
	 * @since 1.7.1
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function anonymise_user( int $user_id ): void {
		$shared = array(
			'orders'              => array(
				'customer_id' => array( 'billing_address' ),
				'vendor_id'   => array( 'vendor_notes' ),
			),
			'reviews'             => array(
				'reviewer_id' => array( 'reviewer_name' ),
				'reviewee_id' => array(),
				'customer_id' => array(),
				'vendor_id'   => array(),
			),
			'wallet_transactions' => array( 'user_id' => array() ),
			'withdrawals'         => array( 'vendor_id' => array( 'details' ) ),
			'proposals'           => array( 'vendor_id' => array() ),
			'deliveries'          => array( 'vendor_id' => array() ),
			'messages'            => array( 'sender_id' => array() ),
			'dispute_messages'    => array( 'sender_id' => array() ),
			'disputes'            => array(
				'initiated_by'     => array(),
				'respondent_id'    => array(),
				'last_response_by' => array(),
				'resolved_by'      => array(),
				'assigned_admin'   => array(),
			),
			'extension_requests'  => array(
				'requested_by' => array(),
				'responded_by' => array(),
			),
		);

		foreach ( $shared as $table => $columns ) {
			foreach ( $columns as $id_column => $pii_columns ) {
				$data = array( $id_column => 0 );

				foreach ( $pii_columns as $pii_column ) {
					$data[ $pii_column ] = null;
				}

				$this->wpdb->update( $this->prefix . $table, $data, array( $id_column => $user_id ), null, array( '%d' ) );
			}
		}
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
		// Files first: the records that say where they are go with the rows below.
		wpss_delete_order_files( $order_id );

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
