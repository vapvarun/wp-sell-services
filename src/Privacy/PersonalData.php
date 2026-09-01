<?php
/**
 * WordPress privacy tools integration.
 *
 * @package WPSellServices\Privacy
 */

declare( strict_types = 1 );

namespace WPSellServices\Privacy;

use WPSellServices\Services\AccountDeletionService;

/**
 * Puts marketplace data into Tools > Export Personal Data and Erase Personal Data.
 *
 * Registering nothing here was not a neutral gap. The plugin stores a member's
 * orders, messages, reviews, profile and money ledger, and a site owner
 * answering a subject-access request from the standard WordPress screen was
 * handed core data only - a complete-looking export that was missing everything
 * this plugin knows. Our own docs claimed otherwise until they were corrected.
 *
 * Erasure delegates to AccountDeletionService rather than deleting rows here,
 * so there is one place that decides what may go and what must stay: completed
 * orders, reviews and messages belong to the person on the other side of them
 * as well, and the owner needs them for their own accounting.
 *
 * @since 1.7.0
 */
class PersonalData {

	/**
	 * How many rows to return per page for each data group.
	 *
	 * WordPress calls exporters repeatedly with an incrementing page until one
	 * reports done, so a member with thousands of messages does not have to be
	 * assembled in a single request.
	 */
	private const PER_PAGE = 100;

	/**
	 * Fail loudly when a query errors instead of exporting nothing.
	 *
	 * A wrong column name returns an empty result set and no exception, which
	 * is indistinguishable from "this member has no messages". That is exactly
	 * the silent-omission failure this class exists to prevent, and it bit this
	 * file during development: three of the four groups queried columns that do
	 * not exist and reported a clean, empty export.
	 *
	 * @param string $group Group being queried, for the log line.
	 * @return void
	 */
	private function assert_no_db_error( string $group ): void {
		global $wpdb;

		if ( ! $wpdb->last_error ) {
			return;
		}

		wpss_log(
			sprintf( 'Privacy export query failed for %s: %s', $group, $wpdb->last_error ),
			'error'
		);
	}

	/**
	 * Register with the WordPress privacy tools.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Add the marketplace exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['wp-sell-services'] = array(
			'exporter_friendly_name' => __( 'Marketplace activity', 'wp-sell-services' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Add the marketplace eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['wp-sell-services'] = array(
			'eraser_friendly_name' => __( 'Marketplace activity', 'wp-sell-services' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export everything the marketplace knows about one member.
	 *
	 * @param string $email Email address being exported.
	 * @param int    $page  1-indexed page number.
	 * @return array{data:array<int,array<string,mixed>>,done:bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$page   = max( 1, $page );
		$offset = ( $page - 1 ) * self::PER_PAGE;
		$export = array();

		// The profile is a single row, so it only belongs on the first page.
		if ( 1 === $page ) {
			$profile = $this->export_vendor_profile( $user->ID );
			if ( $profile ) {
				$export[] = $profile;
			}
		}

		$groups = array(
			'orders'   => 'export_orders',
			'messages' => 'export_messages',
			'reviews'  => 'export_reviews',
			'ledger'   => 'export_wallet',
		);

		$more = false;

		foreach ( $groups as $method ) {
			$rows = $this->{$method}( $user->ID, $offset );

			if ( count( $rows ) >= self::PER_PAGE ) {
				$more = true;
			}

			foreach ( $rows as $row ) {
				$export[] = $row;
			}
		}

		return array(
			'data' => $export,
			'done' => ! $more,
		);
	}

	/**
	 * Erase what can be erased, and say plainly what was kept.
	 *
	 * @param string $email Email address being erased.
	 * @param int    $page  1-indexed page number.
	 * @return array{items_removed:bool,items_retained:bool,messages:array<int,string>,done:bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$service     = new AccountDeletionService();
		$obligations = $service->get_obligations( $user->ID );

		// An account with work or money in flight is refused, exactly as it is
		// on the member's own delete button. Erasing here would strand the
		// person on the other side of those orders.
		if ( ! empty( $obligations['blocked'] ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => true,
				'messages'       => array(
					sprintf(
						/* translators: %d: number of orders still in progress */
						_n(
							'Marketplace data was kept: %d order is still in progress or awaiting payout. Settle or cancel it, then run the erasure again.',
							'Marketplace data was kept: %d orders are still in progress or awaiting payout. Settle or cancel them, then run the erasure again.',
							(int) ( $obligations['order_count'] ?? 0 ),
							'wp-sell-services'
						),
						(int) ( $obligations['order_count'] ?? 0 )
					),
				),
				'done'           => true,
			);
		}

		$result = $service->delete( $user->ID );

		if ( is_wp_error( $result ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => true,
				'messages'       => array( $result->get_error_message() ),
				'done'           => true,
			);
		}

		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array(
				__( 'Marketplace personal data was removed: profile, seller profile, portfolio, listings, saved items, notifications and devices.', 'wp-sell-services' ),
				__( 'Completed orders, reviews and messages were kept and attributed to a deleted member. They belong to the other party as well, and the marketplace owner needs them for accounting and tax records.', 'wp-sell-services' ),
			),
			'done'           => true,
		);
	}

	/**
	 * The member's orders, as buyer and as vendor.
	 *
	 * @param int $user_id User ID.
	 * @param int $offset  Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	private function export_orders( int $user_id, int $offset ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_number, status, total, currency, created_at, completed_at, customer_id, vendor_id
				 FROM {$table}
				 WHERE customer_id = %d OR vendor_id = %d
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);

		$this->assert_no_db_error( __FUNCTION__ );

		$items = array();

		foreach ( $rows ?: array() as $row ) {
			$items[] = array(
				'group_id'    => 'wpss-orders',
				'group_label' => __( 'Marketplace orders', 'wp-sell-services' ),
				'item_id'     => 'wpss-order-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Order number', 'wp-sell-services' ),
						'value' => $row['order_number'],
					),
					array(
						'name'  => __( 'Role', 'wp-sell-services' ),
						'value' => (int) $row['customer_id'] === $user_id
							? __( 'Buyer', 'wp-sell-services' )
							: __( 'Vendor', 'wp-sell-services' ),
					),
					array(
						'name'  => __( 'Status', 'wp-sell-services' ),
						'value' => $row['status'],
					),
					array(
						'name'  => __( 'Total', 'wp-sell-services' ),
						'value' => $row['total'] . ' ' . ( $row['currency'] ?: '' ),
					),
					array(
						'name'  => __( 'Placed', 'wp-sell-services' ),
						'value' => $row['created_at'],
					),
					array(
						'name'  => __( 'Completed', 'wp-sell-services' ),
						'value' => $row['completed_at'] ?: __( 'Not completed', 'wp-sell-services' ),
					),
				),
			);
		}

		return $items;
	}

	/**
	 * Messages the member sent.
	 *
	 * Only their own. A conversation is two people, and the other side's words
	 * are not this member's personal data to export.
	 *
	 * @param int $user_id User ID.
	 * @param int $offset  Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	private function export_messages( int $user_id, int $offset ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, conversation_id, content, created_at
				 FROM {$table}
				 WHERE sender_id = %d
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$user_id,
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);

		$this->assert_no_db_error( __FUNCTION__ );

		$items = array();

		foreach ( $rows ?: array() as $row ) {
			$items[] = array(
				'group_id'    => 'wpss-messages',
				'group_label' => __( 'Marketplace messages', 'wp-sell-services' ),
				'item_id'     => 'wpss-message-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Sent', 'wp-sell-services' ),
						'value' => $row['created_at'],
					),
					array(
						'name'  => __( 'Message', 'wp-sell-services' ),
						'value' => $row['content'],
					),
				),
			);
		}

		return $items;
	}

	/**
	 * Reviews the member wrote.
	 *
	 * @param int $user_id User ID.
	 * @param int $offset  Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	private function export_reviews( int $user_id, int $offset ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, rating, review, status, created_at
				 FROM {$table}
				 WHERE reviewer_id = %d
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$user_id,
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);

		$this->assert_no_db_error( __FUNCTION__ );

		$items = array();

		foreach ( $rows ?: array() as $row ) {
			$items[] = array(
				'group_id'    => 'wpss-reviews',
				'group_label' => __( 'Marketplace reviews', 'wp-sell-services' ),
				'item_id'     => 'wpss-review-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Rating', 'wp-sell-services' ),
						'value' => $row['rating'],
					),
					array(
						'name'  => __( 'Review', 'wp-sell-services' ),
						'value' => $row['review'],
					),
					array(
						'name'  => __( 'Written', 'wp-sell-services' ),
						'value' => $row['created_at'],
					),
				),
			);
		}

		return $items;
	}

	/**
	 * The member's wallet ledger.
	 *
	 * Money in and out is personal data, and it is the part a member is most
	 * likely to actually want when they ask.
	 *
	 * @param int $user_id User ID.
	 * @param int $offset  Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	private function export_wallet( int $user_id, int $offset ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// The ledger keys on user_id, not vendor_id. Getting that wrong
				// returns an empty result with no error - which is the exact
				// silent-empty-export failure this class exists to prevent.
				"SELECT id, type, amount, description, created_at
				 FROM {$table}
				 WHERE user_id = %d
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$user_id,
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);

		$this->assert_no_db_error( __FUNCTION__ );

		$items = array();

		foreach ( $rows ?: array() as $row ) {
			$items[] = array(
				'group_id'    => 'wpss-wallet',
				'group_label' => __( 'Marketplace earnings ledger', 'wp-sell-services' ),
				'item_id'     => 'wpss-wallet-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Date', 'wp-sell-services' ),
						'value' => $row['created_at'],
					),
					array(
						'name'  => __( 'Type', 'wp-sell-services' ),
						'value' => $row['type'],
					),
					array(
						'name'  => __( 'Amount', 'wp-sell-services' ),
						'value' => $row['amount'],
					),
					array(
						'name'  => __( 'Description', 'wp-sell-services' ),
						'value' => $row['description'],
					),
				),
			);
		}

		return $items;
	}

	/**
	 * The seller profile, if the member has one.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null
	 */
	private function export_vendor_profile( int $user_id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$fields = array(
			'tagline'         => __( 'Tagline', 'wp-sell-services' ),
			'bio'             => __( 'Bio', 'wp-sell-services' ),
			'country'         => __( 'Country', 'wp-sell-services' ),
			'city'            => __( 'City', 'wp-sell-services' ),
			'website'         => __( 'Website', 'wp-sell-services' ),
			'social_links'    => __( 'Social links', 'wp-sell-services' ),
			'created_at'      => __( 'Seller since', 'wp-sell-services' ),
		);

		$data = array();

		foreach ( $fields as $key => $label ) {
			if ( ! isset( $row[ $key ] ) || '' === $row[ $key ] ) {
				continue;
			}

			$data[] = array(
				'name'  => $label,
				'value' => $row[ $key ],
			);
		}

		if ( ! $data ) {
			return null;
		}

		return array(
			'group_id'    => 'wpss-vendor-profile',
			'group_label' => __( 'Marketplace seller profile', 'wp-sell-services' ),
			'item_id'     => 'wpss-vendor-profile-' . $user_id,
			'data'        => $data,
		);
	}
}
