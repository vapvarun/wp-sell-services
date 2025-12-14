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

	$symbols = [
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
	];

	$symbol   = $symbols[ $currency ] ?? $currency . ' ';
	$decimals = in_array( $currency, [ 'JPY', 'KRW' ], true ) ? 0 : 2;

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
	/**
	 * Filter the default currency.
	 *
	 * @param string $currency Currency code.
	 */
	return apply_filters( 'wpss_currency', get_option( 'wpss_currency', 'USD' ) );
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
function wpss_get_template_part( string $slug, string $name = '', array $args = [] ): void {
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

	if ( $template ) {
		// Extract args to make them available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args );
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
function wpss_get_template( string $template_name, array $args = [], string $template_path = '', string $default_path = '' ): void {
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

	if ( file_exists( $template ) ) {
		// Extract args to make them available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args );
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
	$current_url = wp_parse_url( add_query_arg( [] ) );

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

	/**
	 * Filter whether user is a vendor.
	 *
	 * @param bool $is_vendor Whether user is a vendor.
	 * @param int  $user_id   User ID.
	 */
	return apply_filters( 'wpss_is_vendor', user_can( $user_id, 'wpss_vendor' ), $user_id );
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
		[
			'a'      => [
				'href'   => [],
				'title'  => [],
				'target' => [],
				'rel'    => [],
			],
			'br'     => [],
			'em'     => [],
			'strong' => [],
			'p'      => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
			'h1'     => [],
			'h2'     => [],
			'h3'     => [],
			'h4'     => [],
			'h5'     => [],
			'h6'     => [],
			'blockquote' => [],
			'code'   => [],
			'pre'    => [],
		]
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
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
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

	$symbols = [
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
	];

	/**
	 * Filter currency symbols.
	 *
	 * @param array $symbols Currency symbols array.
	 */
	$symbols = apply_filters( 'wpss_currency_symbols', $symbols );

	return $symbols[ $currency ] ?? $currency;
}

/**
 * Get supported currencies.
 *
 * @return array
 */
function wpss_get_currencies(): array {
	$currencies = [
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
	];

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
	$statuses = [
		'pending'               => __( 'Pending', 'wp-sell-services' ),
		'accepted'              => __( 'Accepted', 'wp-sell-services' ),
		'rejected'              => __( 'Rejected', 'wp-sell-services' ),
		'requirements_submitted' => __( 'Requirements Submitted', 'wp-sell-services' ),
		'in_progress'           => __( 'In Progress', 'wp-sell-services' ),
		'delivered'             => __( 'Delivered', 'wp-sell-services' ),
		'revision_requested'    => __( 'Revision Requested', 'wp-sell-services' ),
		'completed'             => __( 'Completed', 'wp-sell-services' ),
		'cancelled'             => __( 'Cancelled', 'wp-sell-services' ),
		'disputed'              => __( 'Disputed', 'wp-sell-services' ),
		'refunded'              => __( 'Refunded', 'wp-sell-services' ),
	];

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
	return get_permalink( $service_id );
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

	$vendors_page = (int) get_option( 'wpss_vendors_page' );

	if ( $vendors_page ) {
		return add_query_arg( 'vendor', $user->user_nicename, get_permalink( $vendors_page ) );
	}

	return home_url( '/vendor/' . $user->user_nicename );
}

/**
 * Get dashboard URL.
 *
 * @param string $tab Optional tab/section.
 * @return string
 */
function wpss_get_dashboard_url( string $tab = '' ): string {
	$dashboard_page = (int) get_option( 'wpss_dashboard_page' );

	if ( ! $dashboard_page ) {
		return admin_url();
	}

	$url = get_permalink( $dashboard_page );

	if ( $tab ) {
		$url = add_query_arg( 'tab', $tab, $url );
	}

	return $url;
}
