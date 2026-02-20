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
	const DB_VERSION = '1.3.6';

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
	 * Check if schema needs update.
	 *
	 * @return bool True if update needed.
	 */
	public function needs_update(): bool {
		$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );
		return version_compare( $installed_version, self::DB_VERSION, '<' );
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

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create all plugin tables.
	 *
	 * @return void
	 */
	private function create_tables(): void {
		$charset_collate = $this->wpdb->get_charset_collate();

		$tables = array(
			$this->get_service_addons_table( $charset_collate ),
			$this->get_orders_table( $charset_collate ),
			$this->get_order_requirements_table( $charset_collate ),
			$this->get_conversations_table( $charset_collate ),
			$this->get_messages_table( $charset_collate ),
			$this->get_deliveries_table( $charset_collate ),
			$this->get_extension_requests_table( $charset_collate ),
			$this->get_reviews_table( $charset_collate ),
			$this->get_disputes_table( $charset_collate ),
			$this->get_dispute_messages_table( $charset_collate ),
			$this->get_buyer_requests_table( $charset_collate ),
			$this->get_proposals_table( $charset_collate ),
			$this->get_vendor_profiles_table( $charset_collate ),
			$this->get_portfolio_items_table( $charset_collate ),
			$this->get_notifications_table( $charset_collate ),
			$this->get_wallet_transactions_table( $charset_collate ),
			$this->get_withdrawals_table( $charset_collate ),
		);

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
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
			subtotal decimal(10,2) NOT NULL,
			addons_total decimal(10,2) DEFAULT 0,
			total decimal(10,2) NOT NULL,
			currency varchar(10) DEFAULT 'USD',
			commission_rate decimal(5,2) DEFAULT NULL,
			platform_fee decimal(10,2) DEFAULT NULL,
			vendor_earnings decimal(10,2) DEFAULT NULL,
			status varchar(50) DEFAULT 'pending_payment',
			delivery_deadline datetime DEFAULT NULL,
			original_deadline datetime DEFAULT NULL,
			payment_method varchar(50) DEFAULT NULL,
			payment_status varchar(50) DEFAULT 'pending',
			transaction_id varchar(255) DEFAULT NULL,
			paid_at datetime DEFAULT NULL,
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
			subject varchar(255) DEFAULT NULL,
			participants longtext,
			message_count int(11) DEFAULT 0,
			unread_counts longtext,
			is_closed tinyint(1) DEFAULT 0,
			last_message_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id)
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
			reason text NOT NULL,
			status varchar(50) DEFAULT 'pending',
			responded_by bigint(20) unsigned DEFAULT NULL,
			response_message text,
			original_due_date datetime DEFAULT NULL,
			new_due_date datetime DEFAULT NULL,
			responded_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id)
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
			refund_amount decimal(10,2) DEFAULT NULL,
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
	 * Get buyer requests table SQL.
	 *
	 * @param string $charset_collate Charset collation.
	 * @return string SQL statement.
	 */
	private function get_buyer_requests_table( string $charset_collate ): string {
		$table = $this->get_table_name( 'buyer_requests' );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			buyer_id bigint(20) unsigned NOT NULL,
			category_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL,
			description text NOT NULL,
			budget_min decimal(10,2) DEFAULT NULL,
			budget_max decimal(10,2) DEFAULT NULL,
			delivery_days int(11) DEFAULT NULL,
			attachments longtext,
			status varchar(50) DEFAULT 'open',
			expires_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_post (post_id),
			KEY idx_buyer (buyer_id),
			KEY idx_status (status),
			KEY idx_category (category_id)
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
			attachments longtext,
			status varchar(50) DEFAULT 'pending',
			rejection_reason text,
			withdrawal_reason text,
			order_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_request (request_id),
			KEY idx_vendor (vendor_id)
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
			verification_tier varchar(50) DEFAULT 'basic',
			verified_at datetime DEFAULT NULL,
			country varchar(100) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			timezone varchar(100) DEFAULT NULL,
			website varchar(255) DEFAULT NULL,
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
			amount decimal(10,2) NOT NULL,
			balance_after decimal(10,2) NOT NULL,
			currency varchar(10) DEFAULT 'USD',
			description text,
			reference_type varchar(50) DEFAULT NULL,
			reference_id bigint(20) unsigned DEFAULT NULL,
			status varchar(50) DEFAULT 'completed',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user (user_id),
			KEY idx_type (type),
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
			amount decimal(10,2) NOT NULL,
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
			KEY idx_vendor_status (vendor_id, status)
		) {$charset_collate};";
	}

	/**
	 * Drop all plugin tables.
	 *
	 * Used during uninstall.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$tables = array(
			'withdrawals',
			'wallet_transactions',
			'notifications',
			'portfolio_items',
			'vendor_profiles',
			'proposals',
			'buyer_requests',
			'dispute_messages',
			'disputes',
			'reviews',
			'extension_requests',
			'deliveries',
			'messages',
			'conversations',
			'order_requirements',
			'orders',
			'service_addons',
		);

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
		return array(
			'service_addons'      => $this->get_table_name( 'service_addons' ),
			'orders'              => $this->get_table_name( 'orders' ),
			'order_requirements'  => $this->get_table_name( 'order_requirements' ),
			'conversations'       => $this->get_table_name( 'conversations' ),
			'messages'            => $this->get_table_name( 'messages' ),
			'deliveries'          => $this->get_table_name( 'deliveries' ),
			'extension_requests'  => $this->get_table_name( 'extension_requests' ),
			'reviews'             => $this->get_table_name( 'reviews' ),
			'disputes'            => $this->get_table_name( 'disputes' ),
			'dispute_messages'    => $this->get_table_name( 'dispute_messages' ),
			'buyer_requests'      => $this->get_table_name( 'buyer_requests' ),
			'proposals'           => $this->get_table_name( 'proposals' ),
			'vendor_profiles'     => $this->get_table_name( 'vendor_profiles' ),
			'portfolio_items'     => $this->get_table_name( 'portfolio_items' ),
			'notifications'       => $this->get_table_name( 'notifications' ),
			'wallet_transactions' => $this->get_table_name( 'wallet_transactions' ),
			'withdrawals'         => $this->get_table_name( 'withdrawals' ),
		);
	}
}
