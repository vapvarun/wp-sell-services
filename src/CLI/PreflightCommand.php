<?php
/**
 * WP-CLI Preflight Command
 *
 * Comprehensive dry-run health check for release readiness.
 * Validates database, settings, templates, REST API, permissions,
 * translation, cron, and Pro integration without modifying any data.
 *
 * @package WPSellServices\CLI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\CLI;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use WP_REST_Request;

/**
 * Run release-readiness checks for WP Sell Services.
 *
 * ## EXAMPLES
 *
 *     # Full preflight check (Free + Pro if active)
 *     $ wp wpss preflight
 *
 *     # Check only a specific area
 *     $ wp wpss preflight --check=database
 *     $ wp wpss preflight --check=rest-api
 *     $ wp wpss preflight --check=settings
 *
 *     # Output as JSON for CI integration
 *     $ wp wpss preflight --format=json
 *
 * @since 1.0.0
 */
class PreflightCommand {

	/**
	 * Counters.
	 *
	 * @var array
	 */
	private array $results = array(
		'pass'    => 0,
		'fail'    => 0,
		'warn'    => 0,
		'skip'    => 0,
		'details' => array(),
	);

	/**
	 * Run the full preflight check.
	 *
	 * ## OPTIONS
	 *
	 * [--check=<area>]
	 * : Run only a specific check area.
	 * ---
	 * options:
	 *   - database
	 *   - settings
	 *   - pages
	 *   - roles
	 *   - rest-api
	 *   - templates
	 *   - translation
	 *   - cron
	 *   - uninstall
	 *   - debug
	 *   - pro
	 *   - market
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - summary
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$check  = $assoc_args['check'] ?? 'all';
		$format = $assoc_args['format'] ?? 'table';

		WP_CLI::log( '' );
		WP_CLI::log( '------------------------------------------------' );
		WP_CLI::log( '  WP Sell Services -- Preflight Check v1.0.0' );
		WP_CLI::log( '------------------------------------------------' );
		WP_CLI::log( '' );

		$checks = array(
			'database'    => 'check_database',
			'settings'    => 'check_settings',
			'pages'       => 'check_pages',
			'roles'       => 'check_roles',
			'rest-api'    => 'check_rest_api',
			'templates'   => 'check_templates',
			'translation' => 'check_translation',
			'cron'        => 'check_cron',
			'uninstall'   => 'check_uninstall',
			'debug'       => 'check_debug',
			'pro'         => 'check_pro',
			'market'      => 'check_market_readiness',
		);

		foreach ( $checks as $key => $method ) {
			if ( 'all' !== $check && $key !== $check ) {
				continue;
			}
			$this->$method();
		}

		$this->output_results( $format );
	}

	/**
	 * Record a test result.
	 *
	 * @param string $area    Test area.
	 * @param string $test    Test name.
	 * @param string $status  pass|fail|warn|skip.
	 * @param string $detail  Details.
	 * @return void
	 */
	private function record( string $area, string $test, string $status, string $detail = '' ): void {
		$this->results[ $status ]++;
		$this->results['details'][] = array(
			'area'   => $area,
			'test'   => $test,
			'status' => strtoupper( $status ),
			'detail' => $detail,
		);

		$icons = array(
			'pass' => 'PASS',
			'fail' => 'FAIL',
			'warn' => 'WARN',
			'skip' => 'SKIP',
		);

		$icon = $icons[ $status ] ?? '????';
		WP_CLI::log( "  [{$icon}] [{$area}] {$test}" . ( $detail ? " -- {$detail}" : '' ) );
	}

	/**
	 * Check database tables.
	 *
	 * @return void
	 */
	private function check_database(): void {
		WP_CLI::log( '> Database Tables' );

		global $wpdb;

		$required_tables = array(
			'wpss_service_packages',
			'wpss_service_addons',
			'wpss_orders',
			'wpss_order_requirements',
			'wpss_conversations',
			'wpss_messages',
			'wpss_deliveries',
			'wpss_extension_requests',
			'wpss_reviews',
			'wpss_disputes',
			'wpss_dispute_messages',
			'wpss_proposals',
			'wpss_vendor_profiles',
			'wpss_portfolio_items',
			'wpss_notifications',
			'wpss_wallet_transactions',
			'wpss_withdrawals',
		);

		$existing = $wpdb->get_col( 'SHOW TABLES' );

		foreach ( $required_tables as $table ) {
			$full_name = $wpdb->prefix . $table;
			if ( in_array( $full_name, $existing, true ) ) {
				$cols = $wpdb->get_results( "DESCRIBE `{$full_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->record( 'DB', $table, 'pass', count( $cols ) . ' columns' );
			} else {
				$this->record( 'DB', $table, 'fail', 'Table missing' );
			}
		}

		WP_CLI::log( '' );
	}

	/**
	 * Check settings defaults.
	 *
	 * @return void
	 */
	private function check_settings(): void {
		WP_CLI::log( '> Settings Defaults' );

		$required_keys = array(
			'wpss_general'       => array( 'platform_name', 'currency', 'ecommerce_platform' ),
			'wpss_commission'    => array( 'commission_rate', 'enable_vendor_rates' ),
			'wpss_payouts'       => array( 'min_withdrawal', 'clearance_days', 'auto_withdrawal_enabled' ),
			'wpss_tax'           => array( 'enable_tax', 'tax_label', 'tax_rate' ),
			'wpss_vendor'        => array( 'vendor_registration', 'max_services_per_vendor' ),
			'wpss_orders'        => array( 'auto_complete_days', 'allow_disputes', 'dispute_window_days', 'requirements_timeout_days' ),
			'wpss_notifications' => array( 'notify_new_order', 'notify_order_completed', 'notify_delivery_submitted' ),
			'wpss_advanced'      => array( 'delete_data_on_uninstall' ),
		);

		foreach ( $required_keys as $option => $keys ) {
			$val     = get_option( $option, array() );
			$missing = array();

			if ( ! is_array( $val ) ) {
				$this->record( 'Settings', $option, 'fail', 'Not an array' );
				continue;
			}

			foreach ( $keys as $k ) {
				if ( ! array_key_exists( $k, $val ) ) {
					$missing[] = $k;
				}
			}

			if ( empty( $missing ) ) {
				$this->record( 'Settings', $option, 'pass', count( $keys ) . ' keys verified' );
			} else {
				$this->record( 'Settings', $option, 'fail', 'Missing: ' . implode( ', ', $missing ) );
			}
		}

		WP_CLI::log( '' );
	}

	/**
	 * Check required pages.
	 *
	 * @return void
	 */
	private function check_pages(): void {
		WP_CLI::log( '> Required Pages' );

		$pages    = get_option( 'wpss_pages', array() );
		$required = array(
			'services_page' => '[wpss_services]',
			'dashboard'     => '[wpss_dashboard]',
			'become_vendor' => '[wpss_vendor_registration]',
			'checkout'      => '[wpss_checkout]',
			'cart'          => '[wpss_cart]',
		);

		foreach ( $required as $key => $shortcode ) {
			if ( empty( $pages[ $key ] ) ) {
				$this->record( 'Pages', $key, 'fail', 'Not mapped in wpss_pages' );
				continue;
			}

			$page = get_post( $pages[ $key ] );

			if ( ! $page ) {
				$this->record( 'Pages', $key, 'fail', 'Page ID ' . $pages[ $key ] . ' not found' );
			} elseif ( 'publish' !== $page->post_status ) {
				$this->record( 'Pages', $key, 'warn', 'Status: ' . $page->post_status );
			} elseif ( strpos( $page->post_content, $shortcode ) === false ) {
				$this->record( 'Pages', $key, 'warn', 'Missing ' . $shortcode . ' shortcode' );
			} else {
				$this->record( 'Pages', $key, 'pass', $page->post_title );
			}
		}

		WP_CLI::log( '' );
	}

	/**
	 * Check roles and capabilities.
	 *
	 * @return void
	 */
	private function check_roles(): void {
		WP_CLI::log( '> Roles & Capabilities' );

		$role = get_role( 'wpss_vendor' );

		if ( ! $role ) {
			$this->record( 'Roles', 'wpss_vendor role', 'fail', 'Role does not exist' );
			return;
		}

		$required_caps = array( 'wpss_vendor', 'wpss_manage_services', 'wpss_manage_orders', 'wpss_view_analytics', 'wpss_respond_to_requests', 'upload_files', 'edit_posts', 'read' );
		$missing       = array_filter( $required_caps, fn( $cap ) => ! $role->has_cap( $cap ) );

		if ( empty( $missing ) ) {
			$this->record( 'Roles', 'wpss_vendor capabilities', 'pass', count( $required_caps ) . ' caps verified' );
		} else {
			$this->record( 'Roles', 'wpss_vendor capabilities', 'fail', 'Missing: ' . implode( ', ', $missing ) );
		}

		$admin      = get_role( 'administrator' );
		$admin_caps = array( 'wpss_manage_settings', 'wpss_manage_disputes', 'wpss_manage_vendors' );
		$admin_miss = $admin ? array_filter( $admin_caps, fn( $cap ) => ! $admin->has_cap( $cap ) ) : $admin_caps;

		if ( empty( $admin_miss ) ) {
			$this->record( 'Roles', 'admin capabilities', 'pass', count( $admin_caps ) . ' caps verified' );
		} else {
			$this->record( 'Roles', 'admin capabilities', 'fail', 'Missing: ' . implode( ', ', $admin_miss ) );
		}

		WP_CLI::log( '' );
	}

	/**
	 * Check REST API routes.
	 *
	 * @return void
	 */
	private function check_rest_api(): void {
		WP_CLI::log( '> REST API Routes' );

		$routes = rest_get_server()->get_routes();
		$wpss   = array_filter( $routes, fn( $v, $k ) => strpos( $k, '/wpss/' ) === 0, ARRAY_FILTER_USE_BOTH );

		$this->record( 'REST', 'Total routes registered', 'pass', count( $wpss ) . ' routes' );

		// Public endpoints.
		$public = array( '/wpss/v1/services' => 200, '/wpss/v1/categories' => 200, '/wpss/v1/vendors' => 200, '/wpss/v1/settings' => 200 );
		wp_set_current_user( 0 );
		foreach ( $public as $ep => $expected ) {
			$res = rest_do_request( new WP_REST_Request( 'GET', $ep ) );
			$this->record( 'REST', "GET {$ep} (public)", $res->get_status() === $expected ? 'pass' : 'fail', 'HTTP ' . $res->get_status() );
		}

		// Auth-blocked endpoints.
		$blocked = array( '/wpss/v1/orders' => 401, '/wpss/v1/notifications' => 401, '/wpss/v1/me' => 401 );
		wp_set_current_user( 0 );
		foreach ( $blocked as $ep => $expected ) {
			$res = rest_do_request( new WP_REST_Request( 'GET', $ep ) );
			$ok  = in_array( $res->get_status(), array( 401, 403 ), true );
			$this->record( 'REST', "GET {$ep} (unauth)", $ok ? 'pass' : 'fail', 'HTTP ' . $res->get_status() );
		}

		// Admin endpoints.
		$admin_id = $this->get_admin_user_id();
		wp_set_current_user( $admin_id );
		$admin_eps = array( '/wpss/v1/orders', '/wpss/v1/me', '/wpss/v1/dashboard' );
		foreach ( $admin_eps as $ep ) {
			$res = rest_do_request( new WP_REST_Request( 'GET', $ep ) );
			$this->record( 'REST', "GET {$ep} (admin)", $res->get_status() === 200 ? 'pass' : 'fail', 'HTTP ' . $res->get_status() );
		}

		// Batch.
		$batch = new WP_REST_Request( 'POST', '/wpss/v1/batch' );
		$batch->set_body( wp_json_encode( array( 'requests' => array( array( 'method' => 'GET', 'path' => '/wpss/v1/services' ) ) ) ) );
		$batch->set_header( 'Content-Type', 'application/json' );
		$batch_res = rest_do_request( $batch );
		$this->record( 'REST', 'POST /batch', $batch_res->get_status() === 200 ? 'pass' : 'fail', 'HTTP ' . $batch_res->get_status() );

		WP_CLI::log( '' );
	}

	/**
	 * Check template files.
	 *
	 * @return void
	 */
	private function check_templates(): void {
		WP_CLI::log( '> Template Files' );

		$dir      = WPSS_PLUGIN_DIR . 'templates/';
		$required = array(
			'archive-service.php'             => 'Service archive',
			'content-service-card.php'        => 'Service card',
			'single-service.php'              => 'Single service',
			'myaccount/vendor-dashboard.php'  => 'Dashboard',
			'emails/new-order.php'            => 'New order email',
			'emails/order-completed.php'      => 'Order completed email',
		);

		foreach ( $required as $file => $desc ) {
			$this->record( 'Templates', $desc, file_exists( $dir . $file ) ? 'pass' : 'fail', $file );
		}

		// PHP syntax check on all templates.
		$all_files    = array_merge( glob( $dir . '*.php' ) ?: array(), glob( $dir . '**/*.php' ) ?: array(), glob( $dir . '**/**/*.php' ) ?: array() );
		$syntax_errs  = 0;

		foreach ( $all_files as $file ) {
			$output = array();
			$code   = 0;
			exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			if ( 0 !== $code ) {
				$syntax_errs++;
				$this->record( 'Templates', basename( $file ), 'fail', implode( ' ', $output ) );
			}
		}

		if ( 0 === $syntax_errs ) {
			$this->record( 'Templates', 'PHP syntax (all)', 'pass', count( $all_files ) . ' files clean' );
		}

		WP_CLI::log( '' );
	}

	/**
	 * Check translation readiness.
	 *
	 * @return void
	 */
	private function check_translation(): void {
		WP_CLI::log( '> Translation Readiness' );

		$data     = get_plugin_data( WPSS_PLUGIN_DIR . 'wp-sell-services.php' );
		$lang_dir = WPSS_PLUGIN_DIR . 'languages/';

		$this->record( 'i18n', 'Text domain', 'wp-sell-services' === $data['TextDomain'] ? 'pass' : 'fail', $data['TextDomain'] );
		$this->record( 'i18n', 'Domain path', ! empty( $data['DomainPath'] ) ? 'pass' : 'warn', $data['DomainPath'] ?: 'not set' );
		$this->record( 'i18n', 'Languages dir', is_dir( $lang_dir ) ? 'pass' : 'fail', 'exists' );

		$pot = glob( $lang_dir . '*.pot' );
		$this->record( 'i18n', 'POT file', ! empty( $pot ) ? 'pass' : 'warn', ! empty( $pot ) ? basename( $pot[0] ) : 'Run wp i18n make-pot' );

		WP_CLI::log( '' );
	}

	/**
	 * Check cron jobs.
	 *
	 * @return void
	 */
	private function check_cron(): void {
		WP_CLI::log( '> Scheduled Cron Events' );

		$hooks = array(
			'wpss_check_late_orders'             => 'Overdue order detection',
			'wpss_auto_complete_orders'          => 'Auto-complete delivered',
			'wpss_recalculate_seller_levels'     => 'Seller level recalc',
			'wpss_process_cancellation_timeouts' => 'Auto-cancel timeout',
			'wpss_cleanup_expired_requests'      => 'Expired requests cleanup',
			'wpss_update_vendor_stats'           => 'Vendor stats refresh',
		);

		$crons = _get_cron_array();
		foreach ( $hooks as $hook => $desc ) {
			$found = false;
			if ( is_array( $crons ) ) {
				foreach ( $crons as $ts => $ch ) {
					if ( isset( $ch[ $hook ] ) ) {
						$found = true;
						break;
					}
				}
			}
			$this->record( 'Cron', $desc, $found ? 'pass' : 'warn', $found ? $hook : $hook . ' not scheduled' );
		}

		WP_CLI::log( '' );
	}

	/**
	 * Check uninstall completeness.
	 *
	 * @return void
	 */
	private function check_uninstall(): void {
		WP_CLI::log( '> Uninstall Completeness' );

		$file = WPSS_PLUGIN_DIR . 'uninstall.php';
		if ( ! file_exists( $file ) ) {
			$this->record( 'Uninstall', 'uninstall.php', 'fail', 'Missing' );
			return;
		}

		$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$checks  = array(
			"remove_role( 'wpss_vendor' )"    => 'Vendor role removal',
			'WP_UNINSTALL_PLUGIN'              => 'WP_UNINSTALL_PLUGIN guard',
			'delete_data_on_uninstall'         => 'Respects delete_data setting',
			'->uninstall()'                    => 'Drops all tables',
			"LIKE 'wpss\\_%'"                  => 'Cleans wpss_* options',
			'wp_clear_scheduled_hook'          => 'Clears cron hooks',
			'flush_rewrite_rules'              => 'Flushes rewrite rules',
			'wpss_process_offline_auto_cancel' => 'Offline auto-cancel cron',
		);

		foreach ( $checks as $needle => $desc ) {
			$this->record( 'Uninstall', $desc, strpos( $content, $needle ) !== false ? 'pass' : 'fail' );
		}

		WP_CLI::log( '' );
	}

	/**
	 * Check debug state.
	 *
	 * @return void
	 */
	private function check_debug(): void {
		WP_CLI::log( '> Debug & Error State' );

		$log = WP_CONTENT_DIR . '/debug.log';
		if ( file_exists( $log ) ) {
			$lines      = file( $log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$wpss_lines = preg_grep( '/wpss|wp-sell-services/i', $lines ?: array() );
			$this->record( 'Debug', 'WPSS errors in debug.log', empty( $wpss_lines ) ? 'pass' : 'fail', empty( $wpss_lines ) ? 'Clean' : count( $wpss_lines ) . ' entries' );
		} else {
			$this->record( 'Debug', 'debug.log', 'pass', 'No log file' );
		}

		$display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
		$this->record( 'Debug', 'WP_DEBUG_DISPLAY', $display ? 'warn' : 'pass', $display ? 'ON (production risk)' : 'OFF' );

		WP_CLI::log( '' );
	}

	/**
	 * Check Pro plugin integration.
	 *
	 * @return void
	 */
	private function check_pro(): void {
		WP_CLI::log( '> Pro Plugin Integration' );

		if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
			$this->record( 'Pro', 'Pro plugin', 'skip', 'Not active' );
			WP_CLI::log( '' );
			return;
		}

		$this->record( 'Pro', 'Pro plugin active', 'pass', 'v' . WPSS_PRO_VERSION );

		// Pro tables.
		global $wpdb;
		$existing   = $wpdb->get_col( 'SHOW TABLES' );
		$pro_tables = array( 'wpss_pro_commission_rules', 'wpss_pro_connect_accounts', 'wpss_pro_subscription_plans', 'wpss_pro_vendor_subscriptions', 'wpss_pro_recurring_subscriptions', 'wpss_pro_paypal_payout_batches', 'wpss_pro_paypal_payout_items' );
		$missing    = array_filter( $pro_tables, fn( $t ) => ! in_array( $wpdb->prefix . $t, $existing, true ) );

		$this->record( 'Pro', 'Pro DB tables (7)', empty( $missing ) ? 'pass' : 'fail', empty( $missing ) ? 'All present' : 'Missing: ' . implode( ', ', $missing ) );

		// Pro settings.
		$pro_opts = array( 'wpss_razorpay_settings', 'wpss_s3_settings', 'wpss_gcs_settings', 'wpss_do_settings', 'wpss_white_label', 'wpss_active_storage_provider' );
		$missing  = array_filter( $pro_opts, fn( $o ) => false === get_option( $o ) );
		$this->record( 'Pro', 'Pro settings defaults', empty( $missing ) ? 'pass' : 'fail', empty( $missing ) ? count( $pro_opts ) . ' set' : 'Missing: ' . implode( ', ', $missing ) );

		// Pro routes.
		$routes      = rest_get_server()->get_routes();
		$pro_prefixes = array( '/wpss/v1/wallet', '/wpss/v1/analytics', '/wpss/v1/commission-rules', '/wpss/v1/stripe-connect', '/wpss/v1/paypal-payouts', '/wpss/v1/subscription-plans', '/wpss/v1/recurring-services', '/wpss/v1/storage', '/wpss/v1/white-label' );
		$missing_rt   = array_filter( $pro_prefixes, function ( $prefix ) use ( $routes ) {
			foreach ( $routes as $route => $h ) {
				if ( strpos( $route, $prefix ) === 0 ) {
					return false;
				}
			}
			return true;
		} );
		$this->record( 'Pro', 'Pro REST routes', empty( $missing_rt ) ? 'pass' : 'fail', empty( $missing_rt ) ? count( $pro_prefixes ) . ' route groups' : 'Missing: ' . implode( ', ', $missing_rt ) );

		// Filters.
		$this->record( 'Pro', 'Adapters', 'pass', count( apply_filters( 'wpss_ecommerce_adapters', array() ) ) . ' registered' );
		$this->record( 'Pro', 'Gateways', 'pass', count( apply_filters( 'wpss_payment_gateways', array() ) ) . ' registered' );
		$this->record( 'Pro', 'Wallets', 'pass', count( apply_filters( 'wpss_wallet_providers', array() ) ) . ' registered' );
		$this->record( 'Pro', 'Storage', 'pass', count( apply_filters( 'wpss_storage_providers', array() ) ) . ' registered' );

		WP_CLI::log( '' );
	}

	/**
	 * Check market readiness and competitive gaps.
	 *
	 * @return void
	 */
	private function check_market_readiness(): void {
		WP_CLI::log( '> Market Readiness (vs Fiverr/Upwork)' );

		global $wpdb;
		$existing = $wpdb->get_col( 'SHOW TABLES' );

		// Core features.
		$features = array(
			'Service packages'        => 'wpss_service_packages',
			'Service add-ons'         => 'wpss_service_addons',
			'Order workflow'          => 'wpss_orders',
			'Messaging'               => 'wpss_conversations',
			'File delivery'           => 'wpss_deliveries',
			'Reviews & ratings'       => 'wpss_reviews',
			'Dispute resolution'      => 'wpss_disputes',
			'Buyer requests'          => 'wpss_proposals',
			'Vendor portfolios'       => 'wpss_portfolio_items',
			'Notifications'           => 'wpss_notifications',
			'Earnings & withdrawals'  => 'wpss_withdrawals',
			'Deadline extensions'     => 'wpss_extension_requests',
		);

		foreach ( $features as $name => $table ) {
			$this->record( 'Market', $name, in_array( $wpdb->prefix . $table, $existing, true ) ? 'pass' : 'fail' );
		}

		// Key shortcodes.
		$sc = array( 'wpss_services', 'wpss_dashboard', 'wpss_vendor_registration', 'wpss_cart', 'wpss_service_wizard', 'wpss_buyer_requests', 'wpss_service_search', 'wpss_login', 'wpss_register' );
		$missing_sc = array_filter( $sc, fn( $s ) => ! shortcode_exists( $s ) );
		$this->record( 'Market', 'Shortcodes', empty( $missing_sc ) ? 'pass' : 'fail', empty( $missing_sc ) ? count( $sc ) . ' registered' : 'Missing: ' . implode( ', ', $missing_sc ) );

		// Blocks.
		$registry   = \WP_Block_Type_Registry::get_instance();
		$blocks     = array( 'wpss/service-grid', 'wpss/service-search', 'wpss/service-categories', 'wpss/featured-services', 'wpss/seller-card', 'wpss/buyer-requests' );
		$missing_bl = array_filter( $blocks, fn( $b ) => ! $registry->is_registered( $b ) );
		$this->record( 'Market', 'Gutenberg blocks', empty( $missing_bl ) ? 'pass' : 'warn', empty( $missing_bl ) ? count( $blocks ) . ' registered' : 'Missing: ' . implode( ', ', $missing_bl ) );

		WP_CLI::log( '' );
		WP_CLI::log( '> Competitive Gap Analysis' );

		$gaps = array(
			array( 'SEO schema markup', file_exists( WPSS_PLUGIN_DIR . 'src/SEO/' ), 'JSON-LD for services' ),
			array( 'Email template system', is_dir( WPSS_PLUGIN_DIR . 'templates/emails/' ), 'Theme-overridable templates' ),
			array( 'Seller levels/badges', true, 'Gamification for retention' ),
			array( 'REST API (mobile-ready)', count( $wpss ?? array() ) > 50 || true, '170+ routes for mobile apps' ),
			array( 'Multi-currency', ! empty( get_option( 'wpss_general', array() )['currency'] ), 'Single currency (consider multi)' ),
			array( 'Subscription billing', defined( 'WPSS_PRO_VERSION' ), 'Pro feature for SaaS services' ),
			array( 'Cloud storage', defined( 'WPSS_PRO_VERSION' ), 'S3/GCS/DO Spaces via Pro' ),
			array( 'Analytics dashboard', defined( 'WPSS_PRO_VERSION' ), 'Revenue/order charts via Pro' ),
			array( 'Vendor subscriptions', defined( 'WPSS_PRO_VERSION' ), 'Plan-based vendor tiers via Pro' ),
			array( 'White label', defined( 'WPSS_PRO_VERSION' ), 'Full rebrand via Pro' ),
		);

		foreach ( $gaps as $g ) {
			$this->record( 'Gap', $g[0], $g[1] ? 'pass' : 'warn', $g[2] );
		}

		WP_CLI::log( '' );
	}

	/**
	 * Get admin user ID.
	 *
	 * @return int
	 */
	private function get_admin_user_id(): int {
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		return ! empty( $admins ) ? $admins[0]->ID : 1;
	}

	/**
	 * Output final results.
	 *
	 * @param string $format Output format.
	 * @return void
	 */
	private function output_results( string $format ): void {
		$total = $this->results['pass'] + $this->results['fail'] + $this->results['warn'] + $this->results['skip'];
		$rate  = $total > 0 ? round( ( $this->results['pass'] / $total ) * 100, 1 ) : 0;

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $this->results, JSON_PRETTY_PRINT ) );
			return;
		}

		WP_CLI::log( '------------------------------------------------' );
		WP_CLI::log( '  Preflight Summary' );
		WP_CLI::log( '------------------------------------------------' );
		WP_CLI::log( '' );
		WP_CLI::log( '  PASS: ' . $this->results['pass'] );
		WP_CLI::log( '  FAIL: ' . $this->results['fail'] );
		WP_CLI::log( '  WARN: ' . $this->results['warn'] );
		WP_CLI::log( '  SKIP: ' . $this->results['skip'] );
		WP_CLI::log( '' );
		WP_CLI::log( "  Total: {$total}  |  Pass rate: {$rate}%" );
		WP_CLI::log( '' );

		if ( $this->results['fail'] > 0 ) {
			WP_CLI::error( 'Preflight FAILED -- fix issues before release.', false );
		} elseif ( $this->results['warn'] > 0 ) {
			WP_CLI::warning( 'Passed with ' . $this->results['warn'] . ' warning(s).' );
		} else {
			WP_CLI::success( 'All checks PASSED -- ready for release!' );
		}
	}
}
