<?php
/**
 * Database Schema Manager
 *
 * Handles database table creation and management.
 *
 * @package WPSellServices\Database
 * @since   1.0.0
 */

namespace WPSellServices\Database;

defined( 'ABSPATH' ) || exit;

/**
 * SchemaManager class.
 *
 * @since 1.0.0
 */
class SchemaManager {

	/**
	 * Database version.
	 *
	 * Moves independently of WPSS_VERSION — a schema change inside an already
	 * numbered release still has to bump this, or install() short-circuits on
	 * needs_update() and the new columns are never added. 1.6.1 adds
	 * message_type + description to wpss_dispute_messages, which makes that
	 * table the single store for a dispute conversation. 1.7.1 backfills
	 * revisions_included on standalone orders from their package snapshot.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.7.1';

	/**
	 * Option name for storing DB version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'wpss_db_version';

	/**
	 * Table prefix for plugin tables.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->prefix = $wpdb->prefix . 'wpss_';
	}

	/**
	 * Get table name with prefix.
	 *
	 * @param string $table Table name without prefix.
	 * @return string Full table name.
	 */
	public function get_table_name( string $table ): string {
		return $this->prefix . $table;
	}

	/**
	 * Canonical list of plugin tables (without prefix).
	 *
	 * Used by {@see needs_update()} to detect missing tables even when the
	 * stored DB version matches the code — protects against manual drops,
	 * DB restores from pre-activation snapshots, and environments where
	 * tables were never created in the first place.
	 *
	 * @var string[]
	 */
	private const CORE_TABLES = array(
		'service_packages',
		'service_addons',
		'orders',
		'order_requirements',
		'conversations',
		'messages',
		'deliveries',
		'extension_requests',
		'reviews',
		'disputes',
		'dispute_messages',
		'proposals',
		'vendor_profiles',
		'portfolio_items',
		'notifications',
		'wallet_transactions',
		'withdrawals',
		'audit_log',
		'reports',
		'payment_receipts',
	);

	/**
	 * Tables this plugin used to create and no longer does.
	 *
	 * They are NOT in CORE_TABLES, so no install made since each feature moved
	 * has them - but nothing ever dropped them either, so every upgraded site
	 * still carries them holding whatever rows they held on the day the feature
	 * moved.
	 *
	 * This list exists because stale tables that look authoritative actively
	 * mislead. A developer debugging buyer requests on a long-lived site reads
	 * wpss_buyer_requests, believes it, and reasons from frozen data - that
	 * produced one confidently wrong root cause on Basecamp 10236358969, where
	 * the UI was right and the table was abandoned.
	 *
	 * So: before treating any wpss_* table as truth, check it is in CORE_TABLES.
	 * If it is here instead, the value on the right says where the live data is.
	 *
	 * @since 1.7.0
	 * @var array<string, string>
	 */
	private const RETIRED_TABLES = array(
		'buyer_requests'       => 'wpss_request posts plus post meta',
		'service_faqs'         => '_wpss_faqs post meta on the service',
		'service_requirements' => '_wpss_requirements post meta on the service',
		'service_platform_map' => 'the platform_order_ref column on wpss_orders',
		'analytics_events'     => 'derived from wpss_orders at query time (Pro)',
	);

	/**
	 * Report which retired tables physically survive on this install.
	 *
	 * Deliberately reports rather than drops. Dropping is destructive and
	 * irreversible, and a plugin update runs unattended on sites whose data
	 * nobody has looked at - the rows are stale copies of what now lives in
	 * posts and meta on every install checked, but "every install checked" is
	 * not "every install". Cleanup is offered explicitly (WP-CLI, or the
	 * delete-data-on-uninstall option) rather than performed silently.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string, array{table: string, rows: int, data_now: string}>
	 */
	public function get_retired_tables(): array {
		$found = array();

		foreach ( self::RETIRED_TABLES as $name => $data_now ) {
			$full = $this->prefix . $name;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema inspection, not cacheable state.
			if ( ! $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) ) {
				continue;
			}

			$found[ $name ] = array(
				'table'    => $full,
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name comes from a private const.
				'rows'     => (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM `{$full}`" ),
				'data_now' => $data_now,
			);
		}

		return $found;
	}

	/**
	 * Drop the retired tables.
	 *
	 * Only ever called from uninstall, where dropping is what the owner asked
	 * for. Never from an upgrade routine.
	 *
	 * @since 1.7.0
	 *
	 * @return string[] Names of the tables actually dropped.
	 */
	public function drop_retired_tables(): array {
		$dropped = array();

		foreach ( array_keys( $this->get_retired_tables() ) as $name ) {
			$full = $this->prefix . $name;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name comes from a private const.
			$this->wpdb->query( "DROP TABLE IF EXISTS `{$full}`" );
			$dropped[] = $full;
		}

		return $dropped;
	}

	/**
	 * Check if schema needs update.
	 *
	 * Returns true when the stored DB version is older than the code
	 * version, OR any core table is physically missing from the database.
	 * The latter covers manual drops and partial installs where the
	 * version option survived but tables did not.
	 *
	 * @return bool True if update needed.
	 */
	public function needs_update(): bool {
		$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			return true;
		}

		return $this->has_missing_tables();
	}

	/**
	 * Check whether any core table is missing from the database.
	 *
	 * @return bool
	 */
	private function has_missing_tables(): bool {
		foreach ( self::CORE_TABLES as $table ) {
			$full = $this->prefix . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
			$exists = $this->wpdb->get_var(
				$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $full )
			);
			if ( $full !== $exists ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Create or update all database tables.
	 *
	 * @return void
	 */
	public function install(): void {
		if ( ! $this->needs_update() ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$this->dedupe_for_unique_keys();
		$this->create_tables();
		$this->run_column_migrations();
		$this->run_precision_migrations();
		$this->add_1_7_1_indexes();

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
<<<<<<< HEAD
	 * Remove the duplicate rows that would block the 1.7.1 unique keys.
	 *
	 * Ledger idempotency and one-review-per-order were read-then-insert with
	 * no key, so a replayed webhook or a double submit could leave two rows.
	 * dbDelta() adds UNIQUE (reference_type, reference_id, type) on the ledger
	 * and UNIQUE (order_id, review_type) on reviews, and silently fails while
	 * duplicates exist - so they are removed first, lowest id kept, every
	 * removed row written to the audit log. Ledger rows are removed only when
	 * they are exact copies (same user, amount, key); a same-key row with a
	 * different amount is a bookkeeping question, not a duplicate, and is
	 * logged for a human instead. Idempotent: nothing to remove on a clean or
	 * fresh install.
=======
	 * Indexes for the surfaces that filter on unindexed columns (1.7.1).
	 *
	 * Review moderation lists by (status, created_at); the vendor directory
	 * filters on availability and country; the buyer's order list filters by
	 * (customer_id, status) and sorts by created_at. Each ADD KEY is guarded by
	 * SHOW INDEX so this is safe to run on every install()/sync().
>>>>>>> 1.7.1-F16
	 *
	 * @since 1.7.1
	 *
	 * @return void
	 */
<<<<<<< HEAD
	private function dedupe_for_unique_keys(): void {
		$ledger  = $this->get_table_name( 'wallet_transactions' );
		$reviews = $this->get_table_name( 'reviews' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- plugin-controlled table names; no user input.
		if ( $ledger === $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $ledger ) ) ) {
			$exact = $this->wpdb->get_results(
				"SELECT d.id, d.user_id, d.type, d.amount, d.reference_type, d.reference_id, k.id AS kept_id
				FROM {$ledger} d
				JOIN {$ledger} k ON k.reference_type = d.reference_type AND k.reference_id = d.reference_id AND k.type = d.type
					AND k.user_id = d.user_id AND k.amount = d.amount AND k.id < d.id
				WHERE d.reference_id IS NOT NULL"
			);
			$this->remove_duplicates( $ledger, 'ledger.duplicate_removed', 'wallet_transaction', (array) $exact );

			$conflicts = $this->wpdb->get_results(
				"SELECT reference_type, reference_id, type, COUNT(*) AS n, GROUP_CONCAT(id) AS ids
				FROM {$ledger} WHERE reference_id IS NOT NULL
				GROUP BY reference_type, reference_id, type HAVING n > 1"
			);
			foreach ( (array) $conflicts as $c ) {
				wpss_log( sprintf( 'Schema 1.7.1: ledger rows %s share %s#%d/%s with different amounts; left in place, reconcile by hand before the unique key can be added.', $c->ids, $c->reference_type, $c->reference_id, $c->type ), 'error' );
			}
		}

		if ( $reviews === $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $reviews ) ) ) {
			$dupes = $this->wpdb->get_results(
				"SELECT d.id, d.order_id, d.review_type, d.rating, d.reviewer_id, k.id AS kept_id
				FROM {$reviews} d
				JOIN {$reviews} k ON k.order_id = d.order_id AND k.review_type = d.review_type AND k.id < d.id"
			);
			$this->remove_duplicates( $reviews, 'review.duplicate_removed', 'review', (array) $dupes );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Delete duplicate rows, one audit row each.
	 *
	 * @since 1.7.1
	 *
	 * @param string        $table       Full table name.
	 * @param string        $event       Audit event type.
	 * @param string        $object_type Audit object type.
	 * @param array<object> $rows        Rows to delete; each exposes id and kept_id.
	 * @return void
	 */
	private function remove_duplicates( string $table, string $event, string $object_type, array $rows ): void {
		if ( empty( $rows ) ) {
			return;
		}

		$audit = new \WPSellServices\Services\AuditLogService();

		foreach ( $rows as $row ) {
			$audit->log(
				$event,
				$object_type,
				(int) $row->id,
				array(
					'action'  => 'delete',
					'context' => array( 'kept_id' => (int) $row->kept_id ) + array_diff_key(
						(array) $row,
						array(
							'id'      => 1,
							'kept_id' => 1,
						)
					),
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->delete( $table, array( 'id' => (int) $row->id ), array( '%d' ) );
		}

		wpss_log( sprintf( 'Schema 1.7.1: removed %d duplicate %s row(s); see the audit log for ids.', count( $rows ), $object_type ) );
=======
	private function add_1_7_1_indexes(): void {
		$this->maybe_add_index( 'reviews', 'status_created', 'status, created_at' );
		$this->maybe_add_index( 'vendor_profiles', 'availability', 'is_available, vacation_mode' );
		$this->maybe_add_index( 'vendor_profiles', 'country', 'country' );
		$this->maybe_add_index( 'orders', 'customer_status_created', 'customer_id, status, created_at' );
	}

	/**
	 * Add a secondary index when the table has no key of that name.
	 *
	 * @since 1.7.1
	 *
	 * @param string $table   Table name without prefix.
	 * @param string $name    Key name.
	 * @param string $columns Comma-separated column list (plugin-controlled).
	 * @return void
	 */
	private function maybe_add_index( string $table, string $name, string $columns ): void {
		$full_table = $this->get_table_name( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare( "SHOW INDEX FROM `{$full_table}` WHERE Key_name = %s", $name )
		);

		if ( null !== $exists ) {
			return;
		}

		// Identifiers are plugin-controlled constants; no data is interpolated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb->query( "ALTER TABLE `{$full_table}` ADD KEY `{$name}` ({$columns})" );
>>>>>>> 1.7.1-F16
	}

	/**
	 * Run additive column migrations on existing tables.
	 *
	 * The dbDelta() pass already reconciles most schema deltas from the CREATE
	 * TABLE definitions, but on some sites/collations it can silently skip an
	 * added column. This belt-and-suspenders pass explicitly checks
	 * INFORMATION_SCHEMA.COLUMNS before each ALTER TABLE so it is safe to run
	 * repeatedly and on fresh installs (no-op when the column already exists).
	 *
	 * Append a new entry to the map whenever a column is added to an existing
	 * table; keep the matching CREATE TABLE definition in sync so fresh installs
	 * and dbDelta stay aligned.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	private function run_column_migrations(): void {
		$migrations = array(
			// Vacation mode + message (Basecamp #9983528280): dbDelta can fail
			// to add columns on upgrade, leaving every vacation-mode save to
			// fail silently — list all three vacation columns so the upgrade
			// path self-heals. Order matches the CREATE TABLE definition.
			array(
				'table'      => 'vendor_profiles',
				'column'     => 'vacation_mode',
				'definition' => 'tinyint(1) DEFAULT 0',
				'after'      => 'is_available',
			),
			array(
				'table'      => 'vendor_profiles',
				'column'     => 'vacation_message',
				'definition' => 'text',
				'after'      => 'vacation_mode',
			),
			// Buyer-facing vacation return date (Basecamp #9982757528).
			array(
				'table'      => 'vendor_profiles',
				'column'     => 'vacation_return_date',
				'definition' => 'date DEFAULT NULL',
				'after'      => 'vacation_message',
			),
			// Original author name for guest/legacy reviews with no WP account
			// (reviewer_id = 0) — e.g. WooCommerce comment_author carried over by
			// the Woo->WP migration (Basecamp #10108962763 / Zoho #40660). NULL
			// for native reviews, which always come from a real logged-in user.
			// Listed here so the upgrade path self-heals if dbDelta misses it.
			array(
				'table'      => 'reviews',
				'column'     => 'reviewer_name',
				'definition' => 'varchar(255) DEFAULT NULL',
				'after'      => 'reviewer_id',
			),
			// A dispute conversation used to live in two places: the opening
			// statement and admin replies in wpss_dispute_messages, member
			// evidence in the disputes row's `evidence` JSON column. Each
			// surface read only one, so the parties saw "No messages yet" on a
			// thread the admin could read in full. These two columns let the
			// messages table hold everything the JSON did, so there is one
			// store. Listed here so upgrades self-heal if dbDelta misses them.
			array(
				'table'      => 'dispute_messages',
				'column'     => 'message_type',
				'definition' => "varchar(20) NOT NULL DEFAULT 'text'",
				'after'      => 'message',
			),
			array(
				'table'      => 'dispute_messages',
				'column'     => 'description',
				'definition' => 'text',
				'after'      => 'message_type',
			),
			// How much was actually refunded to the buyer. NULL = never
			// refunded; equal to total = full refund; less = partial.
			//
			// Needed because the amount was previously knowable only inside a
			// dispute (wpss_disputes.refund_amount), so an order refunded from
			// the admin screen had no record of how much went back, and the
			// vendor's proportional share could not be computed at all.
			array(
				'table'      => 'orders',
				'column'     => 'refunded_amount',
				'definition' => 'decimal(11,3) DEFAULT NULL',
				'after'      => 'paid_at',
			),
			// Billing address as it stood WHEN THE ORDER WAS PAID, as JSON.
			//
			// A snapshot, not a pointer to the profile: an invoice has to show
			// what was billed at the time, so editing the profile next year
			// must not rewrite last year's receipts. Same split WooCommerce
			// uses between _billing_* on the order and billing_* on the user.
			//
			// JSON rather than a dozen columns because nothing filters or sorts
			// on it — it is read whole, for display and export.
			array(
				'table'      => 'orders',
				'column'     => 'billing_address',
				'definition' => 'longtext',
				'after'      => 'refunded_amount',
			),
			// The external order id AS THE RAIL SPELLS IT.
			//
			// platform_order_id is bigint, which fits WooCommerce, EDD and
			// FluentCart because all three number their orders. It does not fit
			// SureCart, whose ids are opaque strings ('ord_a1b2c3'), and it will
			// not fit the next rail that numbers things the same way — Stripe,
			// Paddle and LemonSqueezy all use string ids. SureCart could not be
			// wired to the paid path at all until this column existed: the
			// provider is strict_types and typed int, so a real SureCart id was a
			// TypeError, not a silent zero.
			//
			// Every rail writes it, including the numeric ones (as a string), so
			// there is ONE lookup that works for all of them rather than a
			// per-rail branch. platform_order_id stays as-is and stays authoritative
			// for the numeric rails — nothing is migrated off it, and no existing
			// query changes meaning.
			array(
				'table'      => 'orders',
				'column'     => 'platform_order_ref',
				'definition' => 'varchar(64) DEFAULT NULL',
				'after'      => 'platform_order_id',
			),
		);

		foreach ( $migrations as $migration ) {
			$this->maybe_add_column(
				$migration['table'],
				$migration['column'],
				$migration['definition'],
				$migration['after']
			);
		}

		$this->backfill_platform_order_ref();
		$this->backfill_package_snapshots();
		$this->backfill_package_ids();
		$this->backfill_revisions_included();
	}

	/**
	 * Give standalone orders the revision count of the package they bought.
	 *
	 * Checkout never passed revisions to create_order(), so every standalone
	 * order stored 0 while its package snapshot said 2 (or -1, unlimited) and
	 * the buyer could not request one (Basecamp 10264292240). Only rows still
	 * at 0 whose snapshot disagrees are touched, so it is idempotent; orders
	 * whose package really includes no revisions stay at 0.
	 *
	 * @since 1.7.1
	 *
	 * @return void
	 */
	private function backfill_revisions_included(): void {
		$table = $this->get_table_name( 'orders' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			"SELECT id, meta FROM `{$table}`
			WHERE platform = 'standalone'
			  AND revisions_included = 0
			  AND meta LIKE '%package_snapshot%'
			ORDER BY id ASC
			LIMIT 500"
		);

		$fixed = 0;
		foreach ( (array) $rows as $row ) {
			$meta      = json_decode( (string) $row->meta, true );
			$revisions = (int) ( $meta['package_snapshot']['revisions'] ?? 0 );
			if ( 0 === $revisions ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->update( $table, array( 'revisions_included' => $revisions ), array( 'id' => (int) $row->id ), array( '%d' ), array( '%d' ) );
			++$fixed;
		}

		if ( $fixed > 0 && function_exists( 'wpss_log' ) ) {
			wpss_log( "Schema 1.7.1: set revisions_included from the package snapshot on {$fixed} standalone order(s)." );
		}
	}

	/**
	 * Give existing orders a platform_order_ref matching their platform_order_id.
	 *
	 * Without this, every order placed before the column existed would look
	 * unmatched to any code that resolves by ref, and a webhook replay on an old
	 * WooCommerce order could create a duplicate WPSS order instead of finding
	 * the one already there.
	 *
	 * Only fills rows where the ref is missing and the numeric id is present, so
	 * it is idempotent and never overwrites a rail-supplied value (SureCart's
	 * 'ord_...' has no numeric id to be rewritten from). Batched, because a
	 * marketplace of any age can hold hundreds of thousands of orders and an
	 * unbounded UPDATE would lock the money table for the duration.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	private function backfill_platform_order_ref(): void {
		$table = $this->get_table_name( 'orders' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$table,
				'platform_order_ref'
			)
		);

		if ( (int) $column_exists < 1 ) {
			return;
		}

		// A hard cap rather than while(true): if something about the UPDATE stops
		// making progress, an activation hook must not spin forever. 200 batches
		// of 2000 covers 400k orders; anything beyond that finishes on the next
		// upgrade pass, and nothing is broken in the meantime because the rows
		// left behind are simply not yet resolvable by ref.
		for ( $batch = 0; $batch < 200; $batch++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$updated = $this->wpdb->query(
				"UPDATE `{$table}`
				SET platform_order_ref = CAST(platform_order_id AS CHAR)
				WHERE platform_order_ref IS NULL
				  AND platform_order_id IS NOT NULL
				LIMIT 2000"
			);

			if ( ! $updated ) {
				return;
			}
		}
	}

	/**
	 * Freeze what was bought on orders placed before snapshots were taken.
	 *
	 * `package_id` is a POSITIONAL index into the service's `_wpss_packages`
	 * meta, not a stable key. Reorder or delete a tier and every order holding
	 * that index silently re-resolves to a different package - an order placed
	 * for "Premium" begins reading as "Basic" (Basecamp #10154919857).
	 *
	 * From 1.6.0 every paid order snapshots itself, because every rail now
	 * routes through `wpss_order_paid`. Orders placed before that have nothing
	 * frozen and stay exposed, so they are backfilled here. On the sandbox this
	 * was 14 of 17 orders carrying a package - the mitigation existed but
	 * covered 18% of them.
	 *
	 * Bounded on purpose. Each capture reads the service's meta, so this is one
	 * query per order, and an upgrade hook must not walk a marketplace with
	 * 200k orders in a single request. It takes a fixed slice per run and the
	 * rest are picked up on the next upgrade pass; nothing is broken meanwhile,
	 * because an order with no snapshot behaves exactly as it does today.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	private function backfill_package_snapshots(): void {
		if ( ! function_exists( 'wpss_capture_order_package_snapshot' ) ) {
			return;
		}

		$table = $this->get_table_name( 'orders' );

		// Candidates: a real package, and no snapshot recorded yet. The JSON is
		// matched with LIKE rather than a JSON function so this keeps working on
		// MySQL 5.6 / MariaDB builds without JSON support.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order_ids = $this->wpdb->get_col(
			"SELECT id FROM `{$table}`
			WHERE package_id IS NOT NULL
			  AND ( meta IS NULL OR meta NOT LIKE '%package_snapshot%' )
			ORDER BY id ASC
			LIMIT 500"
		);

		if ( empty( $order_ids ) ) {
			return;
		}

		foreach ( $order_ids as $order_id ) {
			// Idempotent, and it skips sub-orders (tips, milestone phases,
			// extensions) itself - those carry no package of their own.
			wpss_capture_order_package_snapshot( (int) $order_id );
		}
	}

	/**
	 * Give existing services' packages a stable id.
	 *
	 * So a client can name a package by something that survives reordering,
	 * rather than by its position (Basecamp #10154919857). New and edited
	 * services get ids on write; this covers everything already in the database.
	 *
	 * Bounded like the other backfills - one meta read and at most one write per
	 * service, taken in slices so an upgrade hook never walks a whole catalogue
	 * in a single request. Services left for the next pass keep answering
	 * without an `id`, exactly as they do today, and clients fall back to the
	 * index.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	private function backfill_package_ids(): void {
		if ( ! function_exists( 'wpss_assign_package_ids' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$service_ids = $this->wpdb->get_col(
			"SELECT p.ID FROM `{$this->wpdb->posts}` p
			 INNER JOIN `{$this->wpdb->postmeta}` m ON m.post_id = p.ID AND m.meta_key = '_wpss_packages'
			 LEFT JOIN `{$this->wpdb->postmeta}` n ON n.post_id = p.ID AND n.meta_key = '_wpss_package_next_id'
			 WHERE p.post_type = 'wpss_service'
			   AND n.post_id IS NULL
			 LIMIT 200"
		);

		foreach ( (array) $service_ids as $service_id ) {
			wpss_assign_package_ids( (int) $service_id );
		}
	}

	/**
	 * Add a column to a table only if it does not already exist.
	 *
	 * @since 1.2.0
	 *
	 * @param string $table      Table name without prefix.
	 * @param string $column     Column name to add.
	 * @param string $definition Column definition (type + default).
	 * @param string $after      Optional column to place the new one after.
	 * @return void
	 */
	private function maybe_add_column( string $table, string $column, string $definition, string $after = '' ): void {
		$full_table = $this->get_table_name( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$full_table,
				$column
			)
		);

		if ( (int) $exists > 0 ) {
			return;
		}

		$position = '' !== $after ? ' AFTER `' . esc_sql( $after ) . '`' : '';

		// Identifiers are plugin-controlled constants (column names, not user input);
		// no data is interpolated here. Safe to build the DDL string directly.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb->query(
			"ALTER TABLE `{$full_table}` ADD COLUMN `{$column}` {$definition}{$position}"
		);
	}

	/**
	 * Widen the settled-money columns to hold 3 decimal places (Basecamp #10132235432).
	 *
	 * Every money column was decimal(10,2), so the third minor digit of a
	 * 3-decimal currency (KWD/BHD — which wpss_get_currency_decimals() explicitly
	 * supports) was silently rounded on write: KWD 12.535 stored as 12.54, a real
	 * ~0.005 fund discrepancy per row. decimal(11,3) keeps the original 8 integer
	 * digits (a bare (10,3) would shrink the integer range) and adds the third
	 * decimal. Scope is the columns where money SETTLES — orders, the wallet
	 * ledger, withdrawals (payouts) and dispute refunds. Catalog/quote inputs
	 * (service_packages/addons price, proposals.proposed_price) are recomputed
	 * into these on order creation and are a separate follow-up.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function run_precision_migrations(): void {
		$money = array(
			array( 'orders', 'subtotal', 'decimal(11,3) NOT NULL' ),
			array( 'orders', 'addons_total', 'decimal(11,3) DEFAULT 0' ),
			array( 'orders', 'total', 'decimal(11,3) NOT NULL' ),
			array( 'orders', 'platform_fee', 'decimal(11,3) DEFAULT NULL' ),
			array( 'orders', 'vendor_earnings', 'decimal(11,3) DEFAULT NULL' ),
			array( 'orders', 'refunded_amount', 'decimal(11,3) DEFAULT NULL' ),
			array( 'wallet_transactions', 'amount', 'decimal(11,3) NOT NULL' ),
			array( 'wallet_transactions', 'balance_after', 'decimal(11,3) NOT NULL' ),
			array( 'withdrawals', 'amount', 'decimal(11,3) NOT NULL' ),
			array( 'disputes', 'refund_amount', 'decimal(11,3) DEFAULT NULL' ),
		);

		foreach ( $money as $col ) {
			$this->maybe_modify_column( $col[0], $col[1], $col[2], 'decimal(11,3)' );
		}
	}

	/**
	 * Change a column's type only if it does not already match the target.
	 *
	 * Reads INFORMATION_SCHEMA.COLUMNS.COLUMN_TYPE first so the ALTER is skipped
	 * when the column is already at the target type — safe to run repeatedly and
	 * on fresh installs (where the CREATE TABLE already used the target type).
	 *
	 * @since 1.3.0
	 *
	 * @param string $table       Logical table name (without prefix).
	 * @param string $column      Column name.
	 * @param string $definition  Full target column definition (type + null/default).
	 * @param string $target_type Bare target COLUMN_TYPE to compare against, e.g. 'decimal(11,3)'.
	 * @return void
	 */
	private function maybe_modify_column( string $table, string $column, string $definition, string $target_type ): void {
		$full_table = $this->get_table_name( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current_type = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$full_table,
				$column
			)
		);

		// Column absent (older/newer schema) or already the target — nothing to do.
		if ( null === $current_type || 0 === strcasecmp( (string) $current_type, $target_type ) ) {
			return;
		}

		// Identifiers + definition are plugin-controlled constants; no user input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb->query(
			"ALTER TABLE `{$full_table}` MODIFY COLUMN `{$column}` {$definition}"
		);
	}

	/**
	 * Create all plugin tables.
	 *
	 * Iterates {@see self::CORE_TABLES} so the install path and the missing-table
	 * health check share a single source of truth — adding a new table requires
	 * one edit (append name to CORE_TABLES + add a get_{name}_table method) and
	 * no risk of the two lists drifting out of sync.
	 *
	 * @return void
	 */
	private function create_tables(): void {
		$charset_collate = $this->wpdb->get_charset_collate();

		foreach ( self::CORE_TABLES as $name ) {
			$method = 'get_' . $name . '_table';
			$sql    = $this->{$method}( $charset_collate );
			dbDelta( $sql );
		}
	}

	/**
	 * Get reports table SQL.
	 *
	 * Member-filed reports on a person, a service, a review or a message. One
	 * table for all four, because the owner works ONE queue — a queue per target
	 * type is four screens to check and three to forget.
	 *
	 * `reported_user_id` is denormalised on purpose: it is resolved once at write
	 * time from whatever was reported, so "show me everything filed against this
	 * member" is an index hit rather than four joins. It is the question a site
	 * owner actually asks before suspending someone.
	 *
	 * The UNIQUE key is the anti-brigading rule, enforced by the database rather
	 * than by a read-then-write in PHP: one member may file one report per
	 * target, so a second submission updates nothing and cannot be used to stack
	 * the queue against someone.
	 *
	 * Indexes cover the four real queries: the open queue by age, everything
	 * about one target, everything against one member, and counts by reason.
	 *
	 * @since 1.5.1
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_reports_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'reports' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			target_type varchar(32) NOT NULL,
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reported_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reporter_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reason varchar(32) NOT NULL,
			details text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			resolution varchar(32) DEFAULT NULL,
			resolved_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_reporter_target (reporter_id, target_type, target_id),
			KEY idx_queue (status, created_at),
			KEY idx_target (target_type, target_id, status),
			KEY idx_reported_user (reported_user_id, status),
			KEY idx_reason (reason, status)
		) {$charset_collate};";
	}

	/**
	 * Get payment receipts table SQL.
	 *
	 * Proof-of-payment a buyer uploads against an offline order, and the record
	 * of who verified it (Basecamp #10194890682).
	 *
	 * A TABLE, not order meta, because the admin screen queries it BY STATUS
	 * across orders - "show me everything awaiting verification" is the whole
	 * job, and that is a query post meta cannot serve without scanning.
	 *
	 * It is also an audit record. Verifying a receipt credits a vendor, so who
	 * approved what, when, and on what evidence has to survive independently of
	 * the order row - `verified_by` and `verified_at` are the answer to "who
	 * released this money", which the order's status history alone cannot give.
	 *
	 * @since 1.6.0
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_payment_receipts_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'payment_receipts' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'submitted',
			verified_by bigint(20) unsigned NOT NULL DEFAULT 0,
			verified_at datetime DEFAULT NULL,
			admin_note text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id, status),
			KEY idx_queue (status, created_at),
			KEY idx_uploader (uploaded_by)
		) {$charset_collate};";
	}

	/**
	 * Get audit log table SQL.
	 *
	 * Cross-cutting audit trail for sensitive state changes (order status
	 * transitions, cancellations, refunds, future: proposal/dispute/commission
	 * actions). Writes are initiated by the {@see \WPSellServices\Services\AuditLogService}
	 * at every touch point. Indexes support both "show me everything that
	 * happened to object X" and "show me everything actor Y did" queries.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_audit_log_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'audit_log' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_role varchar(50) DEFAULT NULL,
			event_type varchar(64) NOT NULL,
			object_type varchar(32) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(32) DEFAULT NULL,
			from_value text DEFAULT NULL,
			to_value text DEFAULT NULL,
			is_forced tinyint(1) NOT NULL DEFAULT 0,
			context longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_object (object_type, object_id, created_at),
			KEY idx_actor (actor_id, created_at),
			KEY idx_event (event_type, created_at),
			KEY idx_forced (is_forced, created_at)
		) {$charset_collate};";
	}

	/**
	 * Get service packages table SQL.
	 *
	 * Stores pricing tiers (e.g., Basic, Standard, Premium) for services.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_service_packages_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'service_packages' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			price decimal(10,2) NOT NULL DEFAULT 0,
			delivery_days int(11) NOT NULL DEFAULT 1,
			revisions int(11) DEFAULT 0,
			features longtext,
			sort_order int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_service (service_id),
			KEY idx_service_order (service_id, sort_order)
		) {$charset_collate};";
	}

	/**
	 * Get service addons table SQL.
	 *
	 * Field types: checkbox, quantity, dropdown, text.
	 * Price types: flat, percentage, quantity_based.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_service_addons_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'service_addons' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			description text,
			field_type varchar(50) DEFAULT 'checkbox',
			price decimal(10,2) NOT NULL DEFAULT 0,
			price_type varchar(50) DEFAULT 'flat',
			min_quantity int(11) DEFAULT 1,
			max_quantity int(11) DEFAULT 10,
			is_required tinyint(1) DEFAULT 0,
			options longtext,
			delivery_days_extra int(11) DEFAULT 0,
			applies_to longtext,
			is_active tinyint(1) DEFAULT 1,
			sort_order int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_service (service_id),
			KEY idx_active (service_id, is_active)
		) {$charset_collate};";
	}

	/**
	 * Get orders table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_orders_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'orders' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_number varchar(50) NOT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			vendor_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			package_id bigint(20) unsigned DEFAULT NULL,
			addons longtext,
			platform varchar(50) DEFAULT 'standalone',
			platform_order_id bigint(20) unsigned DEFAULT NULL,
			platform_order_ref varchar(64) DEFAULT NULL,
			platform_item_id bigint(20) unsigned DEFAULT NULL,
			subtotal decimal(11,3) NOT NULL,
			addons_total decimal(11,3) DEFAULT 0,
			total decimal(11,3) NOT NULL,
			currency varchar(10) DEFAULT 'USD',
			commission_rate decimal(5,2) DEFAULT NULL,
			platform_fee decimal(11,3) DEFAULT NULL,
			vendor_earnings decimal(11,3) DEFAULT NULL,
			status varchar(50) DEFAULT 'pending_payment',
			delivery_deadline datetime DEFAULT NULL,
			original_deadline datetime DEFAULT NULL,
			payment_method varchar(50) DEFAULT NULL,
			payment_status varchar(50) DEFAULT 'pending',
			transaction_id varchar(255) DEFAULT NULL,
			paid_at datetime DEFAULT NULL,
			refunded_amount decimal(11,3) DEFAULT NULL,
			billing_address longtext,
			revisions_included int(11) DEFAULT 0,
			revisions_used int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			started_at datetime DEFAULT NULL,
			vendor_notes text,
			meta longtext,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_order_number (order_number),
			KEY idx_customer (customer_id),
			KEY idx_vendor (vendor_id),
			KEY idx_service (service_id),
			KEY idx_status (status),
			KEY idx_status_date (status,created_at),
			KEY idx_vendor_status (vendor_id,status),
			KEY idx_platform (platform,platform_order_id),
			KEY idx_platform_ref (platform,platform_order_ref),
			KEY idx_deadline (delivery_deadline),
			KEY idx_transaction (transaction_id(191))
		) {$charset_collate};";
	}

	/**
	 * Get order requirements table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_order_requirements_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'order_requirements' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			field_data longtext NOT NULL,
			attachments longtext,
			submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id)
		) {$charset_collate};";
	}

	/**
	 * Get conversations table SQL.
	 *
	 * Stores conversation metadata. Messages are stored in wpss_messages.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_conversations_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'conversations' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned DEFAULT 0,
			subject varchar(255) DEFAULT NULL,
			participants longtext,
			message_count int(11) DEFAULT 0,
			unread_counts longtext,
			is_closed tinyint(1) DEFAULT 0,
			last_message_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_service (service_id)
		) {$charset_collate};";
	}

	/**
	 * Get messages table SQL.
	 *
	 * Stores individual messages within conversations.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_messages_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'messages' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			sender_id bigint(20) unsigned NOT NULL,
			type varchar(50) DEFAULT 'text',
			content longtext NOT NULL,
			attachments longtext,
			metadata longtext,
			read_by longtext,
			is_edited tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_conversation (conversation_id),
			KEY idx_sender (sender_id)
		) {$charset_collate};";
	}

	/**
	 * Get deliveries table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_deliveries_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'deliveries' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			vendor_id bigint(20) unsigned NOT NULL,
			message text,
			attachments longtext,
			version int(11) DEFAULT 1,
			status varchar(50) DEFAULT 'pending',
			response_message text,
			responded_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id)
		) {$charset_collate};";
	}

	/**
	 * Get extension requests table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_extension_requests_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'extension_requests' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			requested_by bigint(20) unsigned NOT NULL,
			extra_days int(11) NOT NULL,
			amount decimal(10,2) DEFAULT 0,
			pay_order_id bigint(20) unsigned DEFAULT NULL,
			reason text NOT NULL,
			status varchar(50) DEFAULT 'pending',
			responded_by bigint(20) unsigned DEFAULT NULL,
			response_message text,
			original_due_date datetime DEFAULT NULL,
			new_due_date datetime DEFAULT NULL,
			responded_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_pay_order (pay_order_id),
			KEY idx_status (status)
		) {$charset_collate};";
	}

	/**
	 * Get reviews table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_reviews_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'reviews' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			reviewer_id bigint(20) unsigned NOT NULL,
			reviewer_name varchar(255) DEFAULT NULL,
			reviewee_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			vendor_id bigint(20) unsigned NOT NULL,
			rating tinyint(3) unsigned NOT NULL,
			review text,
			review_type varchar(50) DEFAULT 'customer_to_vendor',
			communication_rating tinyint(3) unsigned DEFAULT NULL,
			quality_rating tinyint(3) unsigned DEFAULT NULL,
			delivery_rating tinyint(3) unsigned DEFAULT NULL,
			vendor_reply text,
			vendor_reply_at datetime DEFAULT NULL,
			status varchar(50) DEFAULT 'approved',
			is_public tinyint(1) DEFAULT 1,
			helpful_count int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_reviewee (reviewee_id),
			KEY idx_service (service_id),
			KEY idx_customer (customer_id),
			KEY idx_vendor (vendor_id),
			KEY idx_vendor_status (vendor_id,status),
			UNIQUE KEY uniq_order_review (order_id,review_type)
		) {$charset_collate};";
	}

	/**
	 * Get disputes table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_disputes_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'disputes' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dispute_number varchar(50) DEFAULT NULL,
			order_id bigint(20) unsigned NOT NULL,
			initiated_by bigint(20) unsigned NOT NULL,
			respondent_id bigint(20) unsigned DEFAULT NULL,
			reason varchar(100) NOT NULL,
			description text NOT NULL,
			evidence longtext,
			status varchar(50) DEFAULT 'open',
			response_deadline datetime DEFAULT NULL,
			last_response_by bigint(20) unsigned DEFAULT NULL,
			resolution varchar(50) DEFAULT NULL,
			resolution_notes text,
			refund_amount decimal(11,3) DEFAULT NULL,
			resolved_by bigint(20) unsigned DEFAULT NULL,
			resolved_at datetime DEFAULT NULL,
			assigned_admin bigint(20) unsigned DEFAULT NULL,
			meta longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_status (status),
			KEY idx_deadline (response_deadline),
			KEY idx_assigned_admin (assigned_admin)
		) {$charset_collate};";
	}

	/**
	 * Get dispute messages table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_dispute_messages_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'dispute_messages' );

		// message_type + description carry what used to live in the disputes
		// row's `evidence` JSON column. A dispute conversation is now ONE
		// store: 'text' rows hold their text in `message`, and image/file/link
		// rows hold the URL there with the caption in `description`.
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dispute_id bigint(20) unsigned NOT NULL,
			sender_id bigint(20) unsigned NOT NULL,
			sender_role varchar(50) NOT NULL,
			message text NOT NULL,
			message_type varchar(20) NOT NULL DEFAULT 'text',
			description text,
			attachments longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_dispute (dispute_id),
			KEY idx_dispute_created (dispute_id, created_at)
		) {$charset_collate};";
	}

	/**
	 * Get proposals table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_proposals_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'proposals' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_id bigint(20) unsigned NOT NULL,
			vendor_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned DEFAULT NULL,
			cover_letter text NOT NULL,
			proposed_price decimal(10,2) NOT NULL,
			proposed_days int(11) NOT NULL,
			contract_type varchar(20) DEFAULT 'fixed',
			milestones longtext,
			attachments longtext,
			status varchar(50) DEFAULT 'pending',
			rejection_reason text,
			withdrawal_reason text,
			order_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_request (request_id),
			KEY idx_vendor (vendor_id),
			KEY idx_contract_type (contract_type)
		) {$charset_collate};";
	}

	/**
	 * Get vendor profiles table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_vendor_profiles_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'vendor_profiles' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			display_name varchar(255) DEFAULT NULL,
			tagline varchar(255) DEFAULT NULL,
			bio text,
			avatar_id bigint(20) unsigned DEFAULT NULL,
			cover_image_id bigint(20) unsigned DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			verification_tier varchar(50) DEFAULT 'new',
			verified_at datetime DEFAULT NULL,
			country varchar(100) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			timezone varchar(100) DEFAULT NULL,
			website varchar(255) DEFAULT NULL,
			intro_video_url varchar(500) DEFAULT NULL,
			social_links longtext,
			total_orders int(11) DEFAULT 0,
			completed_orders int(11) DEFAULT 0,
			total_earnings decimal(12,2) DEFAULT 0,
			net_earnings decimal(12,2) DEFAULT 0,
			total_commission decimal(12,2) DEFAULT 0,
			custom_commission_rate decimal(5,2) DEFAULT NULL,
			avg_rating decimal(3,2) DEFAULT 0,
			total_reviews int(11) DEFAULT 0,
			response_time_hours int(11) DEFAULT NULL,
			on_time_delivery_rate decimal(5,2) DEFAULT NULL,
			is_available tinyint(1) DEFAULT 1,
			vacation_mode tinyint(1) DEFAULT 0,
			vacation_message text,
			vacation_return_date date DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_user (user_id),
			KEY idx_tier (verification_tier),
			KEY idx_rating (avg_rating),
			KEY idx_status (status)
		) {$charset_collate};";
	}

	/**
	 * Get portfolio items table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_portfolio_items_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'portfolio_items' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vendor_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL,
			description text,
			media longtext,
			external_url varchar(255) DEFAULT NULL,
			tags longtext,
			is_featured tinyint(1) DEFAULT 0,
			sort_order int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_vendor (vendor_id),
			KEY idx_service (service_id)
		) {$charset_collate};";
	}

	/**
	 * Get notifications table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_notifications_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'notifications' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			message text,
			data longtext,
			action_url varchar(255) DEFAULT NULL,
			is_read tinyint(1) DEFAULT 0,
			read_at datetime DEFAULT NULL,
			is_email_sent tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user_unread (user_id,is_read),
			KEY idx_type (type)
		) {$charset_collate};";
	}

	/**
	 * Get wallet transactions table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_wallet_transactions_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'wallet_transactions' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(50) NOT NULL,
			amount decimal(11,3) NOT NULL,
			balance_after decimal(11,3) NOT NULL,
			currency varchar(10) DEFAULT 'USD',
			description text,
			reference_type varchar(50) DEFAULT NULL,
			reference_id bigint(20) unsigned DEFAULT NULL,
			status varchar(50) DEFAULT 'completed',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user (user_id),
			KEY idx_type (type),
			KEY idx_user_created (user_id,created_at,id),
			KEY idx_user_type_created (user_id,type,created_at),
			KEY idx_reference (reference_type,reference_id),
			UNIQUE KEY uniq_reference (reference_type,reference_id,type)
		) {$charset_collate};";
	}

	/**
	 * Get withdrawals table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_withdrawals_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'withdrawals' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vendor_id bigint(20) unsigned NOT NULL,
			amount decimal(11,3) NOT NULL,
			method varchar(50) NOT NULL,
			details longtext,
			status varchar(50) DEFAULT 'pending',
			is_auto tinyint(1) DEFAULT 0,
			admin_note text,
			processed_at datetime DEFAULT NULL,
			processed_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_vendor (vendor_id),
			KEY idx_status (status),
			KEY idx_vendor_status (vendor_id, status),
			KEY idx_status_created (status, created_at),
			KEY idx_method (method)
		) {$charset_collate};";
	}

	/**
	 * Re-run dbDelta for every core table to apply additive schema changes.
	 *
	 * Unlike {@see install()} this ignores the stored version gate and always
	 * runs dbDelta, which is the only safe way to add new KEYs to existing
	 * tables idempotently (dbDelta is a no-op for columns/indexes that already
	 * exist). The scale benchmark calls this so the per-query budgets are
	 * measured against the indexes the release ships, not whatever happens to
	 * be installed on the bench host. Stamps the stored DB version on success.
	 *
	 * @return void
	 */
	public function sync(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$this->dedupe_for_unique_keys();
		$this->create_tables();
		$this->run_column_migrations();
		$this->run_precision_migrations();
		$this->add_1_7_1_indexes();

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Drop all plugin tables.
	 *
	 * Used during uninstall.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// Drop order mirrors CORE_TABLES reverse so FK-free drops succeed on
		// engines that care, and so adding a table requires a single edit in
		// CORE_TABLES rather than two drifting lists.
		$tables = array_reverse( self::CORE_TABLES );

		foreach ( $tables as $table ) {
			$table_name = $this->get_table_name( $table );
			$this->wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Tables we stopped creating but never removed. Uninstall is the one
		// place dropping them is unambiguously right: the owner has ticked
		// delete-data-on-uninstall, so leaving our old tables behind would be
		// the surprise, not removing them.
		$this->drop_retired_tables();

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Get all table names.
	 *
	 * @return array<string, string> Table names keyed by short name.
	 */
	public function get_tables(): array {
		$out = array();
		foreach ( self::CORE_TABLES as $name ) {
			$out[ $name ] = $this->get_table_name( $name );
		}
		return $out;
	}
}
