<?php
/**
 * Helper Functions
 *
 * @package WPSellServices
 * @since   1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get a plugin option value.
 *
 * Retrieves a setting from one of the plugin's option groups.
 *
 * @param string $group   Option group name (e.g., 'general', 'vendors', 'orders').
 * @param string $key     Option key within the group.
 * @param mixed  $default Default value if option doesn't exist.
 * @return mixed
 */
function wpss_get_option( string $group, string $key, $default = null ) {
	$option_name = 'wpss_' . $group;
	$options     = get_option( $option_name, array() );

	return $options[ $key ] ?? $default;
}

/**
 * Format price with currency symbol.
 *
 * @param float  $price    The price to format.
 * @param string $currency Currency code.
 * @return string
 */
function wpss_format_price( float $price, string $currency = '' ): string {
	if ( empty( $currency ) ) {
		$currency = wpss_get_currency();
	}

	$symbols = array(
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'JPY' => '¥',
		'INR' => '₹',
		'AUD' => 'A$',
		'CAD' => 'C$',
		'CHF' => 'CHF',
		'CNY' => '¥',
		'KRW' => '₩',
		'BRL' => 'R$',
		'MXN' => 'MX$',
	);

	$symbol   = $symbols[ $currency ] ?? $currency . ' ';
	$decimals = in_array( $currency, array( 'JPY', 'KRW' ), true ) ? 0 : 2;

	/**
	 * Filter the formatted price.
	 *
	 * @param string $formatted Formatted price string.
	 * @param float  $price     Original price.
	 * @param string $currency  Currency code.
	 */
	return apply_filters(
		'wpss_format_price',
		$symbol . number_format( $price, $decimals ),
		$price,
		$currency
	);
}

/**
 * Get the default currency.
 *
 * @return string
 */
function wpss_get_currency(): string {
	// Read from wpss_general settings array.
	$general_settings = get_option( 'wpss_general', array() );
	$currency         = $general_settings['currency'] ?? 'USD';

	/**
	 * Filter the default currency.
	 *
	 * @param string $currency Currency code.
	 */
	return apply_filters( 'wpss_currency', $currency );
}

/**
 * Get the platform name.
 *
 * @since 1.1.0
 *
 * @return string Platform name or site name as fallback.
 */
function wpss_get_platform_name(): string {
	// Read from wpss_general settings array.
	$general_settings = get_option( 'wpss_general', array() );
	$platform_name    = $general_settings['platform_name'] ?? '';

	// Fall back to site name if empty.
	if ( empty( $platform_name ) ) {
		$platform_name = get_bloginfo( 'name' );
	}

	/**
	 * Filter the platform name.
	 *
	 * @since 1.1.0
	 * @param string $platform_name Platform name.
	 */
	return apply_filters( 'wpss_platform_name', $platform_name );
}

/**
 * Get the plugin instance.
 *
 * @return \WPSellServices\Core\Plugin
 */
function wpss(): \WPSellServices\Core\Plugin {
	return \WPSellServices\Core\Plugin::get_instance();
}

/**
 * Get template part.
 *
 * @param string $slug Template slug.
 * @param string $name Optional template name.
 * @param array  $args Optional arguments to pass to template.
 * @return void
 */
function wpss_get_template_part( string $slug, string $name = '', array $args = array() ): void {
	$template = '';

	// Look in theme first.
	if ( $name ) {
		$template = locate_template( "wp-sell-services/{$slug}-{$name}.php" );
	}

	if ( ! $template ) {
		$template = locate_template( "wp-sell-services/{$slug}.php" );
	}

	// Fall back to plugin templates.
	if ( ! $template ) {
		if ( $name && file_exists( WPSS_PLUGIN_DIR . "templates/{$slug}-{$name}.php" ) ) {
			$template = WPSS_PLUGIN_DIR . "templates/{$slug}-{$name}.php";
		} elseif ( file_exists( WPSS_PLUGIN_DIR . "templates/{$slug}.php" ) ) {
			$template = WPSS_PLUGIN_DIR . "templates/{$slug}.php";
		}
	}

	/**
	 * Filter the template file path.
	 *
	 * @param string $template Template file path.
	 * @param string $slug     Template slug.
	 * @param string $name     Template name.
	 */
	$template = apply_filters( 'wpss_get_template_part', $template, $slug, $name );

	$template_name = $name ? "{$slug}-{$name}" : $slug;

	/** This filter is documented in src/functions.php wpss_get_template() */
	$args = apply_filters( 'wpss_template_args', $args, $template_name );

	if ( $template ) {
		// Extract args to make them available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;
	}
}

/**
 * Get template.
 *
 * @param string $template_name Template name.
 * @param array  $args          Arguments to pass to template.
 * @param string $template_path Template path in theme.
 * @param string $default_path  Default template path.
 * @return void
 */
function wpss_get_template( string $template_name, array $args = array(), string $template_path = '', string $default_path = '' ): void {
	if ( empty( $template_path ) ) {
		$template_path = 'wp-sell-services/';
	}

	if ( empty( $default_path ) ) {
		$default_path = WPSS_PLUGIN_DIR . 'templates/';
	}

	// Look within theme first.
	$template = locate_template( $template_path . $template_name );

	// Fall back to plugin.
	if ( ! $template ) {
		$template = $default_path . $template_name;
	}

	/**
	 * Filter the template file path.
	 *
	 * @param string $template      Template file path.
	 * @param string $template_name Template name.
	 * @param array  $args          Template arguments.
	 */
	$template = apply_filters( 'wpss_get_template', $template, $template_name, $args );

	/**
	 * Filter the template arguments before rendering.
	 *
	 * Allows modification or addition of variables passed to a template
	 * before extraction and rendering.
	 *
	 * @since 1.1.0
	 * @param array  $args          Template arguments.
	 * @param string $template_name Template name being loaded.
	 */
	$args = apply_filters( 'wpss_template_args', $args, $template_name );

	if ( file_exists( $template ) ) {
		// Extract args to make them available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;
	}
}

/**
 * Check if current request is a REST request.
 *
 * @return bool
 */
function wpss_is_rest_request(): bool {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	// Check for REST URL pattern.
	$rest_url    = wp_parse_url( get_rest_url() );
	$current_url = wp_parse_url( add_query_arg( array() ) );

	return isset( $rest_url['path'], $current_url['path'] )
		&& strpos( $current_url['path'], $rest_url['path'] ) === 0;
}

/**
 * Get service by ID.
 *
 * @param int $service_id Service post ID.
 * @return \WPSellServices\Models\Service|null
 */
function wpss_get_service( int $service_id ): ?\WPSellServices\Models\Service {
	$post = get_post( $service_id );

	if ( ! $post || \WPSellServices\PostTypes\ServicePostType::POST_TYPE !== $post->post_type ) {
		return null;
	}

	return \WPSellServices\Models\Service::from_post( $post );
}

/**
 * Get order by ID.
 *
 * @param int $order_id Order ID.
 * @return \WPSellServices\Models\ServiceOrder|null
 */
function wpss_get_order( int $order_id ): ?\WPSellServices\Models\ServiceOrder {
	global $wpdb;

	$table = $wpdb->prefix . 'wpss_orders';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$order_id
		)
	);

	return $row ? \WPSellServices\Models\ServiceOrder::from_db( $row ) : null;
}

/**
 * Get vendor profile by user ID.
 *
 * @param int $user_id WordPress user ID.
 * @return \WPSellServices\Models\VendorProfile|null
 */
function wpss_get_vendor( int $user_id ): ?\WPSellServices\Models\VendorProfile {
	return \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
}

/**
 * Check if user is a vendor.
 *
 * Checks the wpss_vendor capability first, then falls back to checking
 * the user's role and vendor meta for backward compatibility with users
 * registered before the wpss_vendor capability was added to the role.
 *
 * @param int|null $user_id User ID. Defaults to current user.
 * @return bool
 */
function wpss_is_vendor( ?int $user_id = null ): bool {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return false;
	}

	// Primary check: wpss_vendor capability.
	$is_vendor = user_can( $user_id, 'wpss_vendor' );

	// Fallback: check if user has the wpss_vendor role directly.
	if ( ! $is_vendor ) {
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'wpss_vendor', (array) $user->roles, true ) ) {
			$is_vendor = true;
		}
	}

	// Fallback: check vendor meta for legacy vendors.
	if ( ! $is_vendor ) {
		$is_vendor = (bool) get_user_meta( $user_id, '_wpss_is_vendor', true );
	}

	/**
	 * Filter whether user is a vendor.
	 *
	 * @param bool $is_vendor Whether user is a vendor.
	 * @param int  $user_id   User ID.
	 */
	return apply_filters( 'wpss_is_vendor', $is_vendor, $user_id );
}

/**
 * Get active e-commerce adapter.
 *
 * @return \WPSellServices\Integrations\Contracts\EcommerceAdapterInterface|null
 */
function wpss_get_active_adapter(): ?\WPSellServices\Integrations\Contracts\EcommerceAdapterInterface {
	return wpss()->get_integration_manager()->get_active_adapter();
}

/**
 * Sanitize HTML content.
 *
 * @param string $content HTML content.
 * @return string
 */
function wpss_sanitize_html( string $content ): string {
	return wp_kses(
		$content,
		array(
			'a'          => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'br'         => array(),
			'em'         => array(),
			'strong'     => array(),
			'p'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'h1'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'h6'         => array(),
			'blockquote' => array(),
			'code'       => array(),
			'pre'        => array(),
		)
	);
}

/**
 * Generate unique order number.
 *
 * @return string
 */
function wpss_generate_order_number(): string {
	$prefix = apply_filters( 'wpss_order_number_prefix', 'WPSS-' );
	$number = wp_rand( 100000, 999999 );

	return $prefix . $number . '-' . time();
}

/**
 * Generate unique dispute number.
 *
 * @return string
 */
function wpss_generate_dispute_number(): string {
	$prefix = apply_filters( 'wpss_dispute_number_prefix', 'DSP-' );
	$number = wp_rand( 10000, 99999 );

	return $prefix . $number . '-' . time();
}

/**
 * Log message for debugging.
 *
 * @param mixed  $message Message to log.
 * @param string $level   Log level (info, warning, error).
 * @return void
 */
function wpss_log( $message, string $level = 'info' ): void {
	$advanced_settings = get_option( 'wpss_advanced', array() );
	$plugin_debug      = ! empty( $advanced_settings['enable_debug_mode'] );

	if ( ! $plugin_debug && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
		return;
	}

	if ( ! is_string( $message ) ) {
		$message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

	$log_message = sprintf(
		'[%s] [WPSS %s] %s',
		wp_date( 'Y-m-d H:i:s' ),
		strtoupper( $level ),
		$message
	);

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $log_message );
}

/**
 * Format currency (alias for wpss_format_price).
 *
 * @param float  $amount   Amount to format.
 * @param string $currency Currency code.
 * @return string
 */
function wpss_format_currency( float $amount, string $currency = '' ): string {
	return wpss_format_price( $amount, $currency );
}

/**
 * Get currency symbol.
 *
 * @param string $currency Currency code. Defaults to site currency.
 * @return string
 */
function wpss_get_currency_symbol( string $currency = '' ): string {
	if ( empty( $currency ) ) {
		$currency = wpss_get_currency();
	}

	$symbols = array(
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'JPY' => '¥',
		'INR' => '₹',
		'AUD' => 'A$',
		'CAD' => 'C$',
		'CHF' => 'CHF',
		'CNY' => '¥',
		'KRW' => '₩',
		'BRL' => 'R$',
		'MXN' => 'MX$',
		'SGD' => 'S$',
		'HKD' => 'HK$',
		'NOK' => 'kr',
		'SEK' => 'kr',
		'DKK' => 'kr',
		'NZD' => 'NZ$',
		'ZAR' => 'R',
		'RUB' => '₽',
		'TRY' => '₺',
		'PLN' => 'zł',
		'THB' => '฿',
		'MYR' => 'RM',
		'PHP' => '₱',
		'IDR' => 'Rp',
		'VND' => '₫',
		'AED' => 'د.إ',
		'SAR' => '﷼',
		'EGP' => 'E£',
	);

	/**
	 * Filter currency symbols.
	 *
	 * @param array $symbols Currency symbols array.
	 */
	$symbols = apply_filters( 'wpss_currency_symbols', $symbols );

	return $symbols[ $currency ] ?? $currency;
}

/**
 * Get currency format string for JavaScript price formatting.
 *
 * Returns a format string like '$%s' or '€%s' where %s is replaced
 * with the formatted price value in JavaScript.
 *
 * @since 1.1.0
 *
 * @param string $currency Currency code. Defaults to site currency.
 * @return string Format string with %s placeholder.
 */
function wpss_get_currency_format( string $currency = '' ): string {
	$symbol = wpss_get_currency_symbol( $currency );

	/**
	 * Filter the currency format string.
	 *
	 * @since 1.1.0
	 * @param string $format   Format string (e.g., '$%s').
	 * @param string $symbol   Currency symbol.
	 * @param string $currency Currency code.
	 */
	return apply_filters( 'wpss_currency_format', $symbol . '%s', $symbol, $currency );
}

/**
 * Get supported currencies.
 *
 * @return array
 */
function wpss_get_currencies(): array {
	$currencies = array(
		'USD' => __( 'US Dollar', 'wp-sell-services' ),
		'EUR' => __( 'Euro', 'wp-sell-services' ),
		'GBP' => __( 'British Pound', 'wp-sell-services' ),
		'JPY' => __( 'Japanese Yen', 'wp-sell-services' ),
		'INR' => __( 'Indian Rupee', 'wp-sell-services' ),
		'AUD' => __( 'Australian Dollar', 'wp-sell-services' ),
		'CAD' => __( 'Canadian Dollar', 'wp-sell-services' ),
		'CHF' => __( 'Swiss Franc', 'wp-sell-services' ),
		'CNY' => __( 'Chinese Yuan', 'wp-sell-services' ),
		'KRW' => __( 'South Korean Won', 'wp-sell-services' ),
		'BRL' => __( 'Brazilian Real', 'wp-sell-services' ),
		'MXN' => __( 'Mexican Peso', 'wp-sell-services' ),
		'SGD' => __( 'Singapore Dollar', 'wp-sell-services' ),
		'HKD' => __( 'Hong Kong Dollar', 'wp-sell-services' ),
		'NOK' => __( 'Norwegian Krone', 'wp-sell-services' ),
		'SEK' => __( 'Swedish Krona', 'wp-sell-services' ),
		'DKK' => __( 'Danish Krone', 'wp-sell-services' ),
		'NZD' => __( 'New Zealand Dollar', 'wp-sell-services' ),
		'ZAR' => __( 'South African Rand', 'wp-sell-services' ),
		'RUB' => __( 'Russian Ruble', 'wp-sell-services' ),
		'TRY' => __( 'Turkish Lira', 'wp-sell-services' ),
		'PLN' => __( 'Polish Zloty', 'wp-sell-services' ),
		'THB' => __( 'Thai Baht', 'wp-sell-services' ),
		'MYR' => __( 'Malaysian Ringgit', 'wp-sell-services' ),
		'PHP' => __( 'Philippine Peso', 'wp-sell-services' ),
		'IDR' => __( 'Indonesian Rupiah', 'wp-sell-services' ),
		'VND' => __( 'Vietnamese Dong', 'wp-sell-services' ),
		'AED' => __( 'UAE Dirham', 'wp-sell-services' ),
		'SAR' => __( 'Saudi Riyal', 'wp-sell-services' ),
		'EGP' => __( 'Egyptian Pound', 'wp-sell-services' ),
	);

	/**
	 * Filter supported currencies.
	 *
	 * @param array $currencies Currencies array.
	 */
	return apply_filters( 'wpss_currencies', $currencies );
}

/**
 * Calculate time difference in human readable format.
 *
 * @param string $datetime MySQL datetime string.
 * @return string
 */
function wpss_time_ago( string $datetime ): string {
	$timestamp = strtotime( $datetime );

	if ( ! $timestamp ) {
		return '';
	}

	return human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-sell-services' );
}

/**
 * Get order status label.
 *
 * @param string $status Status key.
 * @return string
 */
function wpss_get_order_status_label( string $status ): string {
	$statuses = wpss_get_order_statuses();

	return $statuses[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
}

/**
 * Get all order statuses.
 *
 * @return array
 */
function wpss_get_order_statuses(): array {
	$statuses = array(
		'pending_payment'        => __( 'Pending Payment', 'wp-sell-services' ),
		'pending_requirements'   => __( 'Pending Requirements', 'wp-sell-services' ),
		'pending_approval'       => __( 'Pending Approval', 'wp-sell-services' ),
		'pending'                => __( 'Pending', 'wp-sell-services' ),
		'accepted'               => __( 'Accepted', 'wp-sell-services' ),
		'rejected'               => __( 'Rejected', 'wp-sell-services' ),
		'requirements_submitted' => __( 'Requirements Submitted', 'wp-sell-services' ),
		'in_progress'            => __( 'In Progress', 'wp-sell-services' ),
		'delivered'              => __( 'Delivered', 'wp-sell-services' ),
		'on_hold'                => __( 'On Hold', 'wp-sell-services' ),
		'late'                   => __( 'Late', 'wp-sell-services' ),
		'cancellation_requested' => __( 'Cancellation Requested', 'wp-sell-services' ),
		'revision_requested'     => __( 'Revision Requested', 'wp-sell-services' ),
		'completed'              => __( 'Completed', 'wp-sell-services' ),
		'cancelled'              => __( 'Cancelled', 'wp-sell-services' ),
		'disputed'               => __( 'Disputed', 'wp-sell-services' ),
		'refunded'               => __( 'Refunded', 'wp-sell-services' ),
		'partially_refunded'     => __( 'Partially Refunded', 'wp-sell-services' ),
	);

	/**
	 * Filter order statuses.
	 *
	 * @param array $statuses Order statuses array.
	 */
	return apply_filters( 'wpss_order_statuses', $statuses );
}

/**
 * Check if user can view order.
 *
 * @param int      $order_id Order ID.
 * @param int|null $user_id  User ID. Defaults to current user.
 * @return bool
 */
function wpss_user_can_view_order( int $order_id, ?int $user_id = null ): bool {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return false;
	}

	// Admins can view all orders.
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return false;
	}

	// Order participants can view.
	return (int) $order->customer_id === $user_id || (int) $order->vendor_id === $user_id;
}

/**
 * Get service URL.
 *
 * @param int $service_id Service ID.
 * @return string
 */
function wpss_get_service_url( int $service_id ): string {
	return get_permalink( $service_id ) ?: '';
}

/**
 * Get vendor profile URL.
 *
 * @param int $user_id Vendor user ID.
 * @return string
 */
function wpss_get_vendor_url( int $user_id ): string {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return '';
	}

	$pages        = get_option( 'wpss_pages', array() );
	$vendors_page = (int) ( $pages['vendors_page'] ?? 0 );

	// Fallback to legacy option.
	if ( ! $vendors_page ) {
		$vendors_page = (int) get_option( 'wpss_vendors_page' );
	}

	if ( $vendors_page ) {
		return add_query_arg( 'vendor', $user->user_nicename, get_permalink( $vendors_page ) );
	}

	$vendor_slug = apply_filters( 'wpss_vendor_slug', 'provider' );
	return home_url( '/' . $vendor_slug . '/' . $user->user_nicename );
}

/**
 * Get dashboard URL.
 *
 * @param string $tab Optional tab/section.
 * @return string
 */
function wpss_get_dashboard_url( string $section = '' ): string {
	// First check wpss_pages option (newer, preferred).
	$pages          = get_option( 'wpss_pages', array() );
	$dashboard_page = (int) ( $pages['dashboard'] ?? 0 );

	// Fallback to legacy option for backward compatibility.
	if ( ! $dashboard_page ) {
		$dashboard_page = (int) get_option( 'wpss_dashboard_page' );
	}

	if ( ! $dashboard_page ) {
		return '';
	}

	$url = get_permalink( $dashboard_page );

	if ( ! $url ) {
		return '';
	}

	if ( $section ) {
		$url = add_query_arg( 'section', $section, $url );
	}

	return $url;
}

/**
 * Get order view URL.
 *
 * @param int    $order_id Order ID.
 * @param string $section  Dashboard section (e.g. 'sales' for vendor orders).
 * @return string
 */
function wpss_get_order_url( int $order_id, string $section = '' ): string {
	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return '';
	}

	$dashboard_url = wpss_get_dashboard_url( $section );

	if ( $dashboard_url ) {
		return add_query_arg( 'order_id', $order_id, $dashboard_url );
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order->order_number . '/' );
}

/**
 * Get order requirements URL.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function wpss_get_order_requirements_url( int $order_id ): string {
	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return '';
	}

	// Orders is the default section, so no section parameter needed.
	$dashboard_url = wpss_get_dashboard_url();

	if ( $dashboard_url ) {
		return add_query_arg(
			array(
				'order_id' => $order_id,
				'action'   => 'requirements',
			),
			$dashboard_url
		);
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order->order_number . '/requirements/' );
}

/**
 * Get service requirements (questions buyer must answer).
 *
 * @param int $service_id Service ID.
 * @return array
 */
function wpss_get_service_requirements( int $service_id ): array {
	$requirements = get_post_meta( $service_id, '_wpss_requirements', true );
	return is_array( $requirements ) ? $requirements : array();
}

/**
 * Get submitted order requirements.
 *
 * @param int $order_id Order ID.
 * @return array
 */
function wpss_get_order_requirements( int $order_id ): array {
	global $wpdb;

	$table = $wpdb->prefix . 'wpss_order_requirements';

	// Check if table exists.
	$table_exists = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
	);

	if ( ! $table_exists ) {
		// Fall back to order meta.
		$requirements = get_metadata( 'wpss_order', $order_id, '_requirements', true );
		return is_array( $requirements ) ? $requirements : array();
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT field_data FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1",
			$order_id
		),
		ARRAY_A
	);

	if ( ! $row || empty( $row['field_data'] ) ) {
		return array();
	}

	$decoded = json_decode( $row['field_data'], true );

	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Get max upload size in bytes.
 *
 * @return int
 */
function wpss_get_max_upload_size(): int {
	$upload_max = wp_max_upload_size();

	/**
	 * Filter the max upload size for requirements files.
	 *
	 * @param int $max_size Max size in bytes.
	 */
	return (int) apply_filters( 'wpss_max_upload_size', $upload_max );
}

/**
 * Get service packages.
 *
 * @param int $service_id Service ID.
 * @return array
 */
function wpss_get_service_packages( int $service_id ): array {
	$packages = get_post_meta( $service_id, '_wpss_packages', true );
	return is_array( $packages ) ? $packages : array();
}

/**
 * Normalize gallery meta into a flat array of attachment IDs.
 *
 * Handles all gallery storage formats:
 * - ServiceWizard format: ['images' => [id, ...], 'video' => '...']
 * - Legacy flat array: [id, id, ...]
 * - GalleryService format: [['type' => 'image', 'attachment_id' => id], ...]
 *
 * @since 1.1.0
 *
 * @param mixed $raw Raw gallery meta value (from get_post_meta).
 * @return int[] Flat array of attachment IDs.
 */
function wpss_get_gallery_ids( $raw ): array {
	if ( ! is_array( $raw ) || empty( $raw ) ) {
		return array();
	}

	// ServiceWizard format: ['images' => [...], 'video' => '...'].
	if ( isset( $raw['images'] ) && is_array( $raw['images'] ) ) {
		return array_values( array_filter( array_map( 'absint', $raw['images'] ) ) );
	}

	// GalleryService format: [['type' => 'image', 'attachment_id' => 123], ...].
	if ( isset( $raw[0] ) && is_array( $raw[0] ) && isset( $raw[0]['type'] ) ) {
		$ids = array();
		foreach ( $raw as $item ) {
			if ( 'image' === ( $item['type'] ?? '' ) && ! empty( $item['attachment_id'] ) ) {
				$ids[] = absint( $item['attachment_id'] );
			}
		}
		return $ids;
	}

	// Legacy flat array of IDs: [123, 456, ...].
	return array_values( array_filter( array_map( 'absint', $raw ) ) );
}

/**
 * Get the video URL from gallery meta.
 *
 * @since 1.1.0
 *
 * @param mixed $raw Raw gallery meta value (from get_post_meta).
 * @return string Video URL or empty string.
 */
function wpss_get_gallery_video_url( $raw ): string {
	if ( ! is_array( $raw ) ) {
		return '';
	}

	// ServiceWizard format: ['images' => [...], 'video' => '...'].
	if ( isset( $raw['video'] ) && is_string( $raw['video'] ) ) {
		return $raw['video'];
	}

	// GalleryService format: [['type' => 'video', 'url' => '...'], ...].
	if ( isset( $raw[0] ) && is_array( $raw[0] ) ) {
		foreach ( $raw as $item ) {
			if ( 'video' === ( $item['type'] ?? '' ) && ! empty( $item['url'] ) ) {
				return $item['url'];
			}
		}
	}

	return '';
}

/**
 * Get order confirmation URL (thank you page).
 *
 * @param int $order_id Order ID.
 * @return string
 */
function wpss_get_order_confirmation_url( int $order_id ): string {
	$order = wpss_get_order( $order_id );

	if ( ! $order ) {
		return '';
	}

	$confirmation_page = (int) get_option( 'wpss_order_confirmation_page' );

	if ( $confirmation_page ) {
		return add_query_arg( 'order_id', $order_id, get_permalink( $confirmation_page ) );
	}

	// Fall back to dashboard order view.
	$dashboard_url = wpss_get_dashboard_url();
	if ( $dashboard_url ) {
		return add_query_arg( 'order_id', $order_id, $dashboard_url );
	}

	$order_slug = apply_filters( 'wpss_service_order_slug', 'service-order' );
	return home_url( '/' . $order_slug . '/' . $order->order_number . '/confirmation/' );
}

/**
 * Check if late requirements submission is allowed.
 *
 * @since 1.0.0
 *
 * @return bool Whether late requirements submission is enabled.
 */
function wpss_allow_late_requirements_submission(): bool {
	$order_settings = get_option( 'wpss_orders', array() );
	$allow_late     = ! empty( $order_settings['allow_late_requirements'] );

	/**
	 * Filter whether late requirements submission is allowed.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $allow_late Whether late submission is allowed.
	 */
	return (bool) apply_filters( 'wpss_allow_late_requirements_submission', $allow_late );
}

/**
 * Add a notification for a user.
 *
 * Helper function to simplify adding notifications via NotificationService.
 *
 * @since 1.0.0
 *
 * @param int    $user_id User ID to notify.
 * @param string $type    Notification type.
 * @param string $message Notification message.
 * @param array  $data    Additional data.
 * @return int|false Notification ID or false on failure.
 */
function wpss_add_notification( int $user_id, string $type, string $message, array $data = array() ) {
	$notification_service = new \WPSellServices\Services\NotificationService();

	// Generate title from type.
	$type_titles = array(
		'order_created'       => __( 'New Order', 'wp-sell-services' ),
		'order_status'        => __( 'Order Update', 'wp-sell-services' ),
		'new_message'         => __( 'New Message', 'wp-sell-services' ),
		'delivery_submitted'  => __( 'Delivery Submitted', 'wp-sell-services' ),
		'delivery_accepted'   => __( 'Delivery Accepted', 'wp-sell-services' ),
		'revision_requested'  => __( 'Revision Requested', 'wp-sell-services' ),
		'review_received'     => __( 'New Review', 'wp-sell-services' ),
		'dispute_opened'      => __( 'Dispute Opened', 'wp-sell-services' ),
		'dispute_resolved'    => __( 'Dispute Resolved', 'wp-sell-services' ),
		'deadline_warning'    => __( 'Deadline Warning', 'wp-sell-services' ),
		'service_approved'    => __( 'Service Approved', 'wp-sell-services' ),
		'service_rejected'    => __( 'Service Requires Changes', 'wp-sell-services' ),
		'withdrawal_pending'  => __( 'Withdrawal Request', 'wp-sell-services' ),
		'withdrawal_approved' => __( 'Withdrawal Approved', 'wp-sell-services' ),
		'withdrawal_rejected' => __( 'Withdrawal Rejected', 'wp-sell-services' ),
	);

	$title = $type_titles[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );

	return $notification_service->create( $user_id, $type, $title, $message, $data );
}

/**
 * Get default page slugs for standalone mode.
 *
 * These are used as fallbacks when no page is mapped in Settings → Pages.
 * Site owners can override by mapping WP pages in settings.
 *
 * @since 1.2.0
 *
 * @return array<string, string> Map of page_key => default slug.
 */
function wpss_get_default_page_slugs(): array {
	/**
	 * Filter default page slugs.
	 *
	 * Allows changing the default URL slugs for all WPSS pages.
	 * These only apply when no WP page is mapped in Settings → Pages.
	 *
	 * @since 1.2.0
	 *
	 * @param array $slugs Default slugs keyed by page key.
	 */
	return apply_filters(
		'wpss_default_page_slugs',
		array(
			'services_page'  => 'services',
			'dashboard'      => 'dashboard',
			'become_vendor'  => 'become-vendor',
			'create_service' => 'create-service',
			'checkout'       => 'service-checkout',
			'cart'           => 'service-cart',
		)
	);
}

/**
 * Get page URL by settings key.
 *
 * Checks mapped WP page first (Settings → Pages), then falls back
 * to the default slug. This ensures URLs work for translated or
 * custom-slug sites without hardcoded paths.
 *
 * @since 1.1.0
 *
 * @param string $page_key Page settings key (e.g., 'services_page', 'dashboard', 'checkout').
 * @return string Page URL or empty string.
 */
function wpss_get_page_url( string $page_key ): string {
	$pages   = get_option( 'wpss_pages', array() );
	$page_id = (int) ( $pages[ $page_key ] ?? 0 );

	if ( $page_id ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}

	// Fallback to default slug.
	$defaults = wpss_get_default_page_slugs();
	if ( isset( $defaults[ $page_key ] ) ) {
		return home_url( '/' . $defaults[ $page_key ] . '/' );
	}

	return '';
}

/**
 * Get the mapped page ID for a given page key.
 *
 * @since 1.1.0
 *
 * @param string $page_key Page settings key (e.g., 'services_page', 'dashboard').
 * @return int Page ID or 0.
 */
function wpss_get_page_id( string $page_key ): int {
	$pages = get_option( 'wpss_pages', array() );
	return (int) ( $pages[ $page_key ] ?? 0 );
}

/**
 * Check if the current page is a specific mapped page.
 *
 * Uses the global $post to check the page ID before any query modification,
 * making it safe to use in pre_get_posts and template_include.
 *
 * @since 1.1.0
 *
 * @param string $page_key Page settings key (e.g., 'services_page', 'dashboard').
 * @return bool
 */
function wpss_is_page( string $page_key ): bool {
	global $post;

	$page_id = wpss_get_page_id( $page_key );

	if ( ! $page_id ) {
		return false;
	}

	// Check global $post first (available before query modification).
	if ( $post instanceof \WP_Post && (int) $post->ID === $page_id ) {
		return true;
	}

	// Fallback: check queried object.
	$queried = get_queried_object_id();
	if ( $queried && $queried === $page_id ) {
		return true;
	}

	return false;
}

/**
 * Get the Create Service URL.
 *
 * Returns the URL to the Dashboard create section where vendors can create new services.
 *
 * @since 1.1.0
 *
 * @return string Create service URL (dashboard with create section).
 */
function wpss_get_create_service_url(): string {
	$dashboard_url = wpss_get_page_url( 'dashboard' );
	if ( ! $dashboard_url ) {
		return '';
	}
	return add_query_arg( 'section', 'create', $dashboard_url );
}

/**
 * Get the Become a Vendor URL.
 *
 * Returns the URL to the vendor registration page or dashboard with become-vendor section.
 *
 * @since 1.1.0
 *
 * @return string Become vendor URL.
 */
function wpss_get_become_vendor_url(): string {
	// First check for a dedicated vendor registration page.
	$vendor_page_url = wpss_get_page_url( 'vendor_registration' );
	if ( $vendor_page_url ) {
		return $vendor_page_url;
	}

	// Fall back to dashboard with become-vendor section.
	$dashboard_url = wpss_get_page_url( 'dashboard' );
	if ( $dashboard_url ) {
		return add_query_arg( 'section', 'become-vendor', $dashboard_url );
	}

	return wpss_get_page_url( 'become_vendor' );
}

/**
 * Get order status labels array.
 *
 * Alias for wpss_get_order_statuses() for backward compatibility.
 *
 * @since 1.1.0
 *
 * @return array<string, string> Status key => label pairs.
 */
function wpss_get_order_status_labels(): array {
	return wpss_get_order_statuses();
}

/**
 * Get wallet manager instance.
 *
 * Returns the WalletManager from WP Sell Services Pro if available.
 * Provides access to wallet balance, credit, debit operations.
 *
 * @since 1.1.0
 *
 * @return object|null WalletManager instance or null if Pro not active.
 */
function wpss_get_wallet_manager(): ?object {
	/**
	 * Filter the wallet manager instance.
	 *
	 * Pro plugin uses this to provide the WalletManager.
	 *
	 * @since 1.1.0
	 * @param object|null $wallet_manager WalletManager instance.
	 */
	return apply_filters( 'wpss_wallet_manager', null );
}

/**
 * Get wallet balance for a user.
 *
 * @since 1.1.0
 *
 * @param int|null $user_id User ID. Defaults to current user.
 * @return float Wallet balance or 0 if wallet not available.
 */
function wpss_get_wallet_balance( ?int $user_id = null ): float {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return 0.0;
	}

	$wallet = wpss_get_wallet_manager();

	if ( ! $wallet || ! method_exists( $wallet, 'get_balance' ) ) {
		return 0.0;
	}

	return (float) $wallet->get_balance( $user_id );
}

/**
 * Check if wallet feature is available.
 *
 * @since 1.1.0
 *
 * @return bool True if wallet is available (Pro active with wallet enabled).
 */
function wpss_has_wallet(): bool {
	$wallet = wpss_get_wallet_manager();

	return null !== $wallet && method_exists( $wallet, 'get_balance' );
}

/**
 * Get service checkout URL.
 *
 * Generates a URL to the checkout page with service parameters.
 *
 * @since 1.1.0
 *
 * @param int   $service_id Service CPT ID.
 * @param int   $package_id Package index (0, 1, 2).
 * @param array $addons     Optional addon IDs.
 * @return string Checkout URL with service parameters.
 */
function wpss_get_service_checkout_url( int $service_id, int $package_id = 0, array $addons = array() ): string {
	// Try the active e-commerce adapter's checkout URL builder first.
	$adapter = wpss_get_ecommerce_adapter();
	if ( $adapter ) {
		$checkout_provider = $adapter->get_checkout_provider();
		if ( $checkout_provider ) {
			return $checkout_provider->get_checkout_url(
				$service_id,
				array(
					'package_id' => $package_id,
					'addons'     => $addons,
				)
			);
		}
	}

	// Fallback: use mapped checkout page with query args.
	$url = wpss_get_page_url( 'checkout' );
	if ( ! $url ) {
		return '';
	}

	$url = add_query_arg( 'service_id', $service_id, $url );
	if ( $package_id > 0 ) {
		$url = add_query_arg( 'package', $package_id, $url );
	}
	if ( ! empty( $addons ) ) {
		$url = add_query_arg( 'addons', implode( ',', $addons ), $url );
	}

	return $url;
}

/**
 * Get the base checkout URL (without service ID).
 *
 * Uses the mapped checkout page URL, or builds from the adapter's checkout slug.
 *
 * @since 1.2.0
 * @return string Base checkout URL.
 */
function wpss_get_checkout_base_url(): string {
	// If a non-standalone adapter is active, use its checkout URL.
	$adapter = wpss_get_ecommerce_adapter();
	if ( $adapter && 'standalone' !== $adapter->get_id() ) {
		$checkout_provider = $adapter->get_checkout_provider();
		if ( $checkout_provider ) {
			return $checkout_provider->get_checkout_url();
		}
	}

	$url = wpss_get_page_url( 'checkout' );
	if ( $url ) {
		return $url;
	}

	// Fallback to adapter slug.
	$slug = \WPSellServices\Integrations\Standalone\StandaloneAdapter::get_checkout_slug();
	return home_url( '/' . $slug . '/' );
}

/**
 * Get the cart page URL for the active adapter.
 *
 * For WooCommerce returns the WC cart page; for standalone returns the service-checkout page.
 *
 * @since 1.2.0
 * @return string Cart URL.
 */
function wpss_get_cart_url(): string {
	$adapter = wpss_get_ecommerce_adapter();

	// WooCommerce: use WC cart page.
	if ( $adapter && 'woocommerce' === $adapter->get_id() && function_exists( 'wc_get_cart_url' ) ) {
		return wc_get_cart_url();
	}

	// Standalone: use the dedicated cart page.
	return wpss_get_page_url( 'cart' ) ?: wpss_get_checkout_base_url();
}

/**
 * Get the active e-commerce adapter or a specific adapter by ID.
 *
 * @since 1.1.0
 *
 * @param string|null $adapter_id Specific adapter ID or null for active adapter.
 * @return \WPSellServices\Integrations\Contracts\EcommerceAdapterInterface|null Adapter instance or null.
 */
function wpss_get_ecommerce_adapter( ?string $adapter_id = null ): ?\WPSellServices\Integrations\Contracts\EcommerceAdapterInterface {
	$integration_mgr = wpss()->get_integration_manager();

	if ( ! $integration_mgr ) {
		return null;
	}

	// Return specific adapter if ID provided.
	if ( null !== $adapter_id ) {
		return $integration_mgr->get_adapter( $adapter_id );
	}

	// Return active adapter.
	return $integration_mgr->get_active_adapter();
}

/**
 * Get the order provider from the active e-commerce adapter.
 *
 * @since 1.2.0
 *
 * @return \WPSellServices\Integrations\Contracts\OrderProviderInterface|null Order provider or null.
 */
function wpss_get_order_provider(): ?\WPSellServices\Integrations\Contracts\OrderProviderInterface {
	$adapter = wpss_get_ecommerce_adapter();

	if ( ! $adapter ) {
		return null;
	}

	return $adapter->get_order_provider();
}

/**
 * Resolve addon data from checkout POST data.
 *
 * Reads addon_ids from $_POST, validates each addon belongs to the service
 * and is active, then returns addon details and total for create_order().
 *
 * @since 1.1.0
 *
 * @param int $service_id Service post ID.
 * @return array{addons: array, addons_total: float, delivery_days_extra: int}
 */
function wpss_resolve_checkout_addons( int $service_id ): array {
	$result = array(
		'addons'              => array(),
		'addons_total'        => 0,
		'delivery_days_extra' => 0,
	);

	// Try pre-resolved addons_data first (sent by checkout form as JSON).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by calling gateway.
	$addons_json = isset( $_POST['addons_data'] ) ? sanitize_text_field( wp_unslash( $_POST['addons_data'] ) ) : '';
	if ( $addons_json ) {
		$addons_array = json_decode( $addons_json, true );
		if ( is_array( $addons_array ) ) {
			foreach ( $addons_array as $addon ) {
				$addon_price                    = (float) ( $addon['price'] ?? 0 );
				$extra_days                     = (int) ( $addon['delivery_days_extra'] ?? $addon['extra_days'] ?? 0 );
				$result['addons_total']        += $addon_price;
				$result['delivery_days_extra'] += $extra_days;
				$result['addons'][]             = array(
					'id'                  => (int) ( $addon['id'] ?? 0 ),
					'name'                => sanitize_text_field( $addon['name'] ?? $addon['title'] ?? '' ),
					'price'               => $addon_price,
					'delivery_days_extra' => $extra_days,
				);
			}
			return $result;
		}
	}

	// Fallback: resolve from addon_ids (indices into _wpss_extras post meta).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by calling gateway.
	$addon_ids_raw = isset( $_POST['addon_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['addon_ids'] ) ) : '';

	if ( ! $addon_ids_raw ) {
		return $result;
	}

	$addon_indices = array_map( 'intval', explode( ',', $addon_ids_raw ) );
	$all_extras    = get_post_meta( $service_id, '_wpss_extras', true ) ?: array();

	foreach ( $addon_indices as $index ) {
		if ( $index < 0 || ! isset( $all_extras[ $index ] ) ) {
			continue;
		}
		$extra                          = $all_extras[ $index ];
		$addon_price                    = (float) ( $extra['price'] ?? 0 );
		$extra_days                     = (int) ( $extra['delivery_time'] ?? $extra['delivery_days_extra'] ?? 0 );
		$result['addons_total']        += $addon_price;
		$result['delivery_days_extra'] += $extra_days;
		$result['addons'][]             = array(
			'id'                  => $index,
			'name'                => sanitize_text_field( $extra['title'] ?? '' ),
			'price'               => $addon_price,
			'delivery_days_extra' => $extra_days,
		);
	}

	return $result;
}

/**
 * Check if WooCommerce integration is enabled.
 *
 * Returns true if WooCommerce is the active e-commerce adapter (requires Pro).
 *
 * @since 1.1.0
 *
 * @return bool True if WooCommerce integration is active.
 */
function wpss_is_woocommerce_enabled(): bool {
	$adapter = wpss_get_active_adapter();
	if ( ! $adapter ) {
		return false;
	}

	return 'woocommerce' === $adapter->get_id();
}

/**
 * Get total order count for a user (as customer).
 *
 * @since 1.2.0
 *
 * @param int $user_id User ID.
 * @return int Order count.
 */
function wpss_get_user_order_count( int $user_id ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d",
			$user_id
		)
	);
}

/**
 * Get active order count for a user (as customer).
 *
 * Active orders are those not in completed, cancelled, or refunded status.
 *
 * @since 1.2.0
 *
 * @param int $user_id User ID.
 * @return int Active order count.
 */
function wpss_get_user_active_order_count( int $user_id ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND status NOT IN ('completed', 'cancelled', 'refunded')",
			$user_id
		)
	);
}

/**
 * Get orders for a user (as customer).
 *
 * @since 1.2.0
 *
 * @param int   $user_id User ID.
 * @param array $args    Query arguments (limit, offset, status).
 * @return array Array of order objects.
 */
function wpss_get_user_orders( int $user_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	$defaults = array(
		'limit'  => 10,
		'offset' => 0,
		'status' => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE customer_id = %d";
	$params = array( $user_id );

	if ( ! empty( $args['status'] ) ) {
		$sql     .= ' AND status = %s';
		$params[] = $args['status'];
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Get notifications for a user.
 *
 * @since 1.2.0
 *
 * @param int   $user_id User ID.
 * @param array $args    Query arguments (limit, offset, unread_only).
 * @return array Array of notification objects.
 */
function wpss_get_user_notifications( int $user_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_notifications';

	$defaults = array(
		'limit'       => 20,
		'offset'      => 0,
		'unread_only' => false,
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE user_id = %d";
	$params = array( $user_id );

	if ( $args['unread_only'] ) {
		$sql .= ' AND is_read = 0';
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Get orders for a vendor.
 *
 * @since 1.2.0
 *
 * @param int   $vendor_id Vendor user ID.
 * @param array $args      Query arguments (limit, offset, status).
 * @return array Array of order objects.
 */
function wpss_get_vendor_orders( int $vendor_id, array $args = array() ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'wpss_orders';

	$defaults = array(
		'limit'  => 10,
		'offset' => 0,
		'status' => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	$sql    = "SELECT * FROM {$table} WHERE vendor_id = %d";
	$params = array( $vendor_id );

	if ( ! empty( $args['status'] ) ) {
		$sql     .= ' AND status = %s';
		$params[] = $args['status'];
	}

	$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
	$params[] = $args['limit'];
	$params[] = $args['offset'];

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Get services for a vendor.
 *
 * @since 1.2.0
 *
 * @param int   $vendor_id Vendor user ID.
 * @param array $args      Query arguments (limit, offset, status).
 * @return \WP_Post[] Array of service posts.
 */
function wpss_get_vendor_services( int $vendor_id, array $args = array() ): array {
	$defaults = array(
		'limit'  => 10,
		'offset' => 0,
		'status' => 'publish',
	);
	$args     = wp_parse_args( $args, $defaults );

	$query_args = array(
		'post_type'      => 'wpss_service',
		'author'         => $vendor_id,
		'posts_per_page' => $args['limit'],
		'offset'         => $args['offset'],
		'post_status'    => $args['status'],
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	return get_posts( $query_args );
}

/**
 * Render pagination for a WP_Query.
 *
 * Outputs pagination HTML using WordPress paginate_links().
 *
 * @since 1.2.0
 *
 * @param \WP_Query $query The query object to paginate.
 * @param array     $args  Optional. Arguments to customize pagination.
 * @return void
 */
function wpss_pagination( \WP_Query $query, array $args = array() ): void {
	$total_pages = $query->max_num_pages;

	if ( $total_pages <= 1 ) {
		return;
	}

	$current_page = max( 1, get_query_var( 'paged', 1 ) );

	$defaults = array(
		'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
		'format'    => '?paged=%#%',
		'current'   => $current_page,
		'total'     => $total_pages,
		'prev_text' => '&laquo;',
		'next_text' => '&raquo;',
		'type'      => 'list',
	);

	$args = wp_parse_args( $args, $defaults );

	$pagination = paginate_links( $args );

	if ( $pagination ) {
		echo '<nav class="wpss-pagination" aria-label="' . esc_attr__( 'Pagination', 'wp-sell-services' ) . '">';
		echo wp_kses_post( $pagination );
		echo '</nav>';
	}
}

/**
 * Enqueue WPSS frontend assets.
 *
 * Call this from any shortcode, block, or template that needs WPSS frontend styles and scripts.
 *
 * @since 1.0.0
 * @return void
 */
function wpss_enqueue_frontend_assets(): void {
	wp_enqueue_style( 'wpss-design-system' );
	wp_enqueue_style( 'wpss-frontend' );
	wp_enqueue_script( 'wpss-frontend' );
}
