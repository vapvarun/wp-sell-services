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
	 * @var string
	 */
	const DB_VERSION = '1.5.2';

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
	);

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

		$this->create_tables();
		$this->run_column_migrations();
		$this->run_precision_migrations();

		update_option( self::VERSION_OPTION, self::DB_VERSION );
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
		);

		foreach ( $migrations as $migration ) {
			$this->maybe_add_column(
				$migration['table'],
				$migration['column'],
				$migration['definition'],
				$migration['after']
			);
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
			KEY idx_deadline (delivery_deadline)
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
			KEY idx_vendor_status (vendor_id,status)
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

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dispute_id bigint(20) unsigned NOT NULL,
			sender_id bigint(20) unsigned NOT NULL,
			sender_role varchar(50) NOT NULL,
			message text NOT NULL,
			attachments longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_dispute (dispute_id)
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
			KEY idx_reference (reference_type,reference_id)
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

		$this->create_tables();
		$this->run_column_migrations();
		$this->run_precision_migrations();

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
