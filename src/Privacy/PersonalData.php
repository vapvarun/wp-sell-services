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

		foreach ( $this->table_groups() as $group => $spec ) {
			$rows = $this->export_group( $group, $spec, $user->ID, $offset );

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
				__( 'Removed: the account, seller profile, portfolio, saved items, notifications, devices, app passwords and sessions, and abuse reports filed by or about this member. Listings were taken down.', 'wp-sell-services' ),
				__( 'Kept and anonymised: orders, deliveries, requirement answers, messages, disputes, reviews, proposals, the earnings ledger and withdrawals. The member is shown on them as a deleted user, and their billing address, reviewer name, seller notes and payout details on those rows were blanked. They belong to the other party as well, and the marketplace owner needs them for accounting and tax records.', 'wp-sell-services' ),
			),
			'done'           => true,
		);
	}

	/**
	 * Export groups that are one table query each.
	 *
	 * Each spec is `label`, `sql` (SELECT ... WHERE ... ORDER BY ..., with
	 * %d for the member's id), `params` (how many times the id appears), and
	 * `fields` (column => label, or column => array( label, formatter )).
	 * The four groups above predate this table and keep their own methods;
	 * anything new belongs here.
	 *
	 * @since 1.7.1
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function table_groups(): array {
		global $wpdb;

		$p       = $wpdb->prefix . 'wpss_';
		$flatten = array( $this, 'flatten' );
		$mask    = array( $this, 'mask' );

		return array(
			'disputes'         => array(
				'label'  => __( 'Marketplace disputes', 'wp-sell-services' ),
				'sql'    => "SELECT * FROM {$p}disputes WHERE initiated_by = %d OR respondent_id = %d ORDER BY id DESC",
				'params' => 2,
				'fields' => array(
					'dispute_number' => __( 'Dispute number', 'wp-sell-services' ),
					'reason'         => __( 'Reason', 'wp-sell-services' ),
					'description'    => __( 'Description', 'wp-sell-services' ),
					'status'         => __( 'Status', 'wp-sell-services' ),
					'resolution'     => __( 'Resolution', 'wp-sell-services' ),
					'created_at'     => __( 'Opened', 'wp-sell-services' ),
				),
			),
			'deliveries'       => array(
				'label'  => __( 'Marketplace deliveries', 'wp-sell-services' ),
				'sql'    => "SELECT * FROM {$p}deliveries WHERE vendor_id = %d ORDER BY id DESC",
				'params' => 1,
				'fields' => array(
					'order_id'   => __( 'Order', 'wp-sell-services' ),
					'message'    => __( 'Message', 'wp-sell-services' ),
					'version'    => __( 'Version', 'wp-sell-services' ),
					'status'     => __( 'Status', 'wp-sell-services' ),
					'created_at' => __( 'Delivered', 'wp-sell-services' ),
				),
			),
			'requirements'     => array(
				'label'  => __( 'Marketplace requirement answers', 'wp-sell-services' ),
				'sql'    => "SELECT r.* FROM {$p}order_requirements r INNER JOIN {$p}orders o ON o.id = r.order_id WHERE o.customer_id = %d ORDER BY r.id DESC",
				'params' => 1,
				'fields' => array(
					'order_id'     => __( 'Order', 'wp-sell-services' ),
					'field_data'   => array( __( 'Answers', 'wp-sell-services' ), $flatten ),
					'submitted_at' => __( 'Submitted', 'wp-sell-services' ),
				),
			),
			'withdrawals'      => array(
				'label'  => __( 'Marketplace withdrawals', 'wp-sell-services' ),
				'sql'    => "SELECT * FROM {$p}withdrawals WHERE vendor_id = %d ORDER BY id DESC",
				'params' => 1,
				'fields' => array(
					'amount'     => __( 'Amount', 'wp-sell-services' ),
					'method'     => __( 'Method', 'wp-sell-services' ),
					'details'    => array( __( 'Payout details', 'wp-sell-services' ), fn( string $v ): string => $this->mask( wpss_decrypt_secret( $v ) ) ),
					'status'     => __( 'Status', 'wp-sell-services' ),
					'created_at' => __( 'Requested', 'wp-sell-services' ),
				),
			),
			'proposals'        => array(
				'label'  => __( 'Marketplace proposals', 'wp-sell-services' ),
				'sql'    => "SELECT * FROM {$p}proposals WHERE vendor_id = %d ORDER BY id DESC",
				'params' => 1,
				'fields' => array(
					'cover_letter'   => __( 'Cover letter', 'wp-sell-services' ),
					'proposed_price' => __( 'Proposed price', 'wp-sell-services' ),
					'proposed_days'  => __( 'Proposed days', 'wp-sell-services' ),
					'status'         => __( 'Status', 'wp-sell-services' ),
					'created_at'     => __( 'Sent', 'wp-sell-services' ),
				),
			),
			'reports'          => array(
				'label'  => __( 'Marketplace reports filed', 'wp-sell-services' ),
				'sql'    => "SELECT * FROM {$p}reports WHERE reporter_id = %d ORDER BY id DESC",
				'params' => 1,
				'fields' => array(
					'target_type' => __( 'Reported', 'wp-sell-services' ),
					'reason'      => __( 'Reason', 'wp-sell-services' ),
					'details'     => __( 'Details', 'wp-sell-services' ),
					'status'      => __( 'Status', 'wp-sell-services' ),
					'created_at'  => __( 'Filed', 'wp-sell-services' ),
				),
			),
			'notifications'    => array(
				'label'  => __( 'Marketplace notifications', 'wp-sell-services' ),
				'sql'    => "SELECT * FROM {$p}notifications WHERE user_id = %d ORDER BY id DESC",
				'params' => 1,
				'fields' => array(
					'type'       => __( 'Type', 'wp-sell-services' ),
					'title'      => __( 'Title', 'wp-sell-services' ),
					'message'    => __( 'Message', 'wp-sell-services' ),
					'created_at' => __( 'Sent', 'wp-sell-services' ),
				),
			),
			'billing'          => array(
				'label'  => __( 'Marketplace billing addresses', 'wp-sell-services' ),
				'sql'    => "SELECT id, order_number, billing_address FROM {$p}orders WHERE customer_id = %d AND billing_address IS NOT NULL AND billing_address <> '' ORDER BY id DESC",
				'params' => 1,
				'fields' => array(
					'order_number'    => __( 'Order number', 'wp-sell-services' ),
					'billing_address' => array( __( 'Billing address', 'wp-sell-services' ), $flatten ),
				),
			),
			'reviews-received' => array(
				'label'  => __( 'Marketplace reviews received', 'wp-sell-services' ),
				'sql'    => "SELECT * FROM {$p}reviews WHERE reviewee_id = %d ORDER BY id DESC",
				'params' => 1,
				'fields' => array(
					'rating'     => __( 'Rating', 'wp-sell-services' ),
					'review'     => __( 'Review', 'wp-sell-services' ),
					'created_at' => __( 'Written', 'wp-sell-services' ),
				),
			),
		);
	}

	/**
	 * Run one table_groups() spec for one page.
	 *
	 * @param string               $group   Group key.
	 * @param array<string, mixed> $spec    Spec from table_groups().
	 * @param int                  $user_id User ID.
	 * @param int                  $offset  Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	private function export_group( string $group, array $spec, int $user_id, int $offset ): array {
		global $wpdb;

		$params = array_merge( array_fill( 0, (int) $spec['params'], $user_id ), array( self::PER_PAGE, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $spec['sql'] . ' LIMIT %d OFFSET %d', $params ), ARRAY_A );

		$this->assert_no_db_error( $group );

		$items = array();

		foreach ( $rows ?: array() as $row ) {
			$data = array();

			foreach ( $spec['fields'] as $column => $label ) {
				$value = (string) ( $row[ $column ] ?? '' );

				if ( is_array( $label ) ) {
					$value = (string) call_user_func( $label[1], $value );
					$label = $label[0];
				}

				if ( '' === $value ) {
					continue;
				}

				$data[] = array(
					'name'  => $label,
					'value' => $value,
				);
			}

			$items[] = array(
				'group_id'    => 'wpss-' . $group,
				'group_label' => $spec['label'],
				'item_id'     => 'wpss-' . $group . '-' . (int) $row['id'],
				'data'        => $data,
			);
		}

		return $items;
	}

	/**
	 * Render a stored JSON object as "key: value" lines.
	 *
	 * @param string $json Stored JSON, or plain text.
	 * @return string
	 */
	public function flatten( string $json ): string {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return $json;
		}

		$lines = array();

		foreach ( $decoded as $key => $value ) {
			$lines[] = $key . ': ' . ( is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ) );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Like flatten(), keeping only the last four characters of each value.
	 *
	 * Payout details are bank and wallet identifiers. The member is entitled
	 * to know which account is on file, not to have the full number written
	 * into a downloadable zip that outlives the request.
	 *
	 * @param string $json Stored JSON.
	 * @return string
	 */
	public function mask( string $json ): string {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			$decoded = array( 'value' => $json );
		}

		foreach ( $decoded as $key => $value ) {
			$value           = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
			$decoded[ $key ] = str_repeat( '*', max( 0, strlen( $value ) - 4 ) ) . substr( $value, -4 );
		}

		return $this->flatten( (string) wp_json_encode( $decoded ) );
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
			'tagline'      => __( 'Tagline', 'wp-sell-services' ),
			'bio'          => __( 'Bio', 'wp-sell-services' ),
			'country'      => __( 'Country', 'wp-sell-services' ),
			'city'         => __( 'City', 'wp-sell-services' ),
			'website'      => __( 'Website', 'wp-sell-services' ),
			'social_links' => __( 'Social links', 'wp-sell-services' ),
			'created_at'   => __( 'Seller since', 'wp-sell-services' ),
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
