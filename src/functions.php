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

	// Use the canonical symbol map (wpss_get_currency_symbol) so every
	// supported currency renders with its proper symbol out of the box - no
	// code snippet required. Falls back to the code plus a space for a
	// currency with no known symbol.
	$symbol = wpss_get_currency_symbol( $currency );
	if ( $symbol === $currency ) {
		$symbol .= ' ';
	}
	$decimals = wpss_get_currency_decimals( $currency );

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
 * Get the number of decimal places to display for a currency.
 *
 * Zero-decimal currencies (e.g. JPY, KRW, VND) are conventionally shown
 * without minor units - "¥120,000", not "¥120,000.00". This is the single
 * source of truth used by both PHP price formatting and the client-side
 * JS formatter (localized as `currencyDecimals`).
 *
 * @since 1.2.1
 *
 * @param string $currency Currency code. Defaults to site currency.
 * @return int Number of decimal places (0 or 2).
 */
function wpss_get_currency_decimals( string $currency = '' ): int {
	if ( empty( $currency ) ) {
		$currency = wpss_get_currency();
	}

	$registry = wpss_get_currency_registry();
	$decimals = isset( $registry[ $currency ] ) ? (int) $registry[ $currency ]['decimals'] : 2;

	if ( in_array( $currency, wpss_get_zero_decimal_currencies(), true ) ) {
		$decimals = 0;
	}

	/**
	 * Filter the number of decimal places for a currency.
	 *
	 * @since 1.2.1
	 *
	 * @param int    $decimals Number of decimal places.
	 * @param string $currency Currency code.
	 */
	return (int) apply_filters( 'wpss_currency_decimals', $decimals, $currency );
}

/**
 * Convert a major-unit amount to integer minor units for the given currency.
 *
 * Mirrors WooCommerce's `wc_add_number_precision()`: scale by the currency's
 * decimal places and round to an integer, so money is compared and transported
 * as integers instead of floats. Currency-aware by construction — JPY/KRW have
 * no minor unit (x1), USD/EUR have two (x100), BHD/KWD have three (x1000).
 *
 * THE canonical converter. Do not hand-roll `* 100` or a `0.01` epsilon
 * anywhere — both are wrong for zero-decimal and three-decimal currencies.
 *
 * @since 1.2.2
 *
 * @param float  $amount   Amount in major units (e.g. dollars).
 * @param string $currency Optional. Currency code. Defaults to the store currency.
 * @return int Amount in minor units (e.g. cents).
 */
function wpss_amount_to_minor_units( float $amount, string $currency = '' ): int {
	$decimals = wpss_get_currency_decimals( $currency );

	return (int) round( $amount * ( 10 ** $decimals ) );
}

/**
 * Whether two amounts are equal for the given currency.
 *
 * Compares integer minor units, so there is no float epsilon to tune and the
 * comparison is exact at the currency's real precision. Use this for every
 * "did the buyer pay what we expected?" check.
 *
 * @since 1.2.2
 *
 * @param float  $a        First amount in major units.
 * @param float  $b        Second amount in major units.
 * @param string $currency Optional. Currency code. Defaults to the store currency.
 * @return bool True when the two amounts are the same to the currency's precision.
 */
function wpss_amounts_match( float $a, float $b, string $currency = '' ): bool {
	return wpss_amount_to_minor_units( $a, $currency ) === wpss_amount_to_minor_units( $b, $currency );
}

/**
 * Get the list of zero-decimal currency codes.
 *
 * Derived from the registry (codes whose `decimals` is 0), so it stays in sync
 * automatically. Exposed as its own accessor so JS surfaces that format
 * arbitrary per-row currencies (e.g. the wallet ledger) can localize it.
 *
 * @since 1.2.1
 *
 * @return array<int, string> Currency codes rendered without minor units.
 */
function wpss_get_zero_decimal_currencies(): array {
	$codes = array();
	foreach ( wpss_get_currency_registry() as $code => $data ) {
		if ( 0 === (int) $data['decimals'] ) {
			$codes[] = $code;
		}
	}

	/**
	 * Filter the list of zero-decimal currency codes.
	 *
	 * @since 1.2.1
	 *
	 * @param array<int, string> $codes Currency codes rendered without minor units.
	 */
	return apply_filters( 'wpss_zero_decimal_currencies', $codes );
}

/**
 * Get the HTML attributes for a price <input type="number"> in a currency.
 *
 * Zero-decimal currencies (JPY, KRW, VND) get whole-number step + placeholder;
 * everything else gets cent precision. Single source so no template hardcodes
 * step="0.01" / placeholder="0.00".
 *
 * @since 1.2.1
 *
 * @param string $currency Currency code. Defaults to site currency.
 * @return array{step: string, placeholder: string} Input step + placeholder.
 */
function wpss_get_price_input_attrs( string $currency = '' ): array {
	$decimals = wpss_get_currency_decimals( $currency );

	return array(
		'step'        => $decimals > 0 ? '0.01' : '1',
		'placeholder' => $decimals > 0 ? '0.00' : '0',
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
 * Get the datetime of a vendor's most recent completed delivery.
 *
 * Shared accessor for every "Last Delivery" display (single-service page,
 * vendor card partial). Resolves from the orders table
 * (MAX(completed_at), tip sub-orders excluded) — the legacy
 * _wpss_last_delivery user-meta key was never written.
 *
 * @since 1.2.0
 *
 * @param int $vendor_id Vendor user ID.
 * @return string|null MySQL datetime, or null when nothing was delivered yet.
 */
function wpss_get_vendor_last_delivery( int $vendor_id ): ?string {
	return ( new \WPSellServices\Services\VendorService() )->get_last_delivery_date( $vendor_id );
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

	$symbols = array();
	foreach ( wpss_get_currency_registry() as $code => $data ) {
		$symbols[ $code ] = $data['symbol'];
	}

	/**
	 * Filter currency symbols.
	 *
	 * @param array $symbols Currency symbols array (code => symbol).
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
 * Get the canonical currency registry.
 *
 * Single source of truth for every supported currency - its display name,
 * symbol, and number of decimal places. Modeled on WooCommerce's currency
 * list: all currency surfaces (settings dropdowns, price formatting, symbols,
 * decimals, JS localization) derive from this one array, so the name, symbol,
 * and decimals lists can never drift apart.
 *
 * Free owns this registry; Pro (and any add-on) reuses it through the
 * accessors below rather than maintaining its own currency list.
 *
 * @since 1.2.1
 *
 * @return array<string, array{name: string, symbol: string, decimals: int}>
 */
function wpss_get_currency_registry(): array {
	$registry = array(
		'USD' => array(
			'name'     => __( 'US Dollar', 'wp-sell-services' ),
			'symbol'   => '$',
			'decimals' => 2,
		),
		'EUR' => array(
			'name'     => __( 'Euro', 'wp-sell-services' ),
			'symbol'   => '€',
			'decimals' => 2,
		),
		'GBP' => array(
			'name'     => __( 'British Pound', 'wp-sell-services' ),
			'symbol'   => '£',
			'decimals' => 2,
		),
		'JPY' => array(
			'name'     => __( 'Japanese Yen', 'wp-sell-services' ),
			'symbol'   => '¥',
			'decimals' => 0,
		),
		'INR' => array(
			'name'     => __( 'Indian Rupee', 'wp-sell-services' ),
			'symbol'   => '₹',
			'decimals' => 2,
		),
		'AUD' => array(
			'name'     => __( 'Australian Dollar', 'wp-sell-services' ),
			'symbol'   => 'A$',
			'decimals' => 2,
		),
		'CAD' => array(
			'name'     => __( 'Canadian Dollar', 'wp-sell-services' ),
			'symbol'   => 'C$',
			'decimals' => 2,
		),
		'CHF' => array(
			'name'     => __( 'Swiss Franc', 'wp-sell-services' ),
			'symbol'   => 'CHF',
			'decimals' => 2,
		),
		'CNY' => array(
			'name'     => __( 'Chinese Yuan', 'wp-sell-services' ),
			'symbol'   => '¥',
			'decimals' => 2,
		),
		'KRW' => array(
			'name'     => __( 'South Korean Won', 'wp-sell-services' ),
			'symbol'   => '₩',
			'decimals' => 0,
		),
		'BRL' => array(
			'name'     => __( 'Brazilian Real', 'wp-sell-services' ),
			'symbol'   => 'R$',
			'decimals' => 2,
		),
		'MXN' => array(
			'name'     => __( 'Mexican Peso', 'wp-sell-services' ),
			'symbol'   => 'MX$',
			'decimals' => 2,
		),
		'SGD' => array(
			'name'     => __( 'Singapore Dollar', 'wp-sell-services' ),
			'symbol'   => 'S$',
			'decimals' => 2,
		),
		'HKD' => array(
			'name'     => __( 'Hong Kong Dollar', 'wp-sell-services' ),
			'symbol'   => 'HK$',
			'decimals' => 2,
		),
		'NOK' => array(
			'name'     => __( 'Norwegian Krone', 'wp-sell-services' ),
			'symbol'   => 'kr',
			'decimals' => 2,
		),
		'SEK' => array(
			'name'     => __( 'Swedish Krona', 'wp-sell-services' ),
			'symbol'   => 'kr',
			'decimals' => 2,
		),
		'DKK' => array(
			'name'     => __( 'Danish Krone', 'wp-sell-services' ),
			'symbol'   => 'kr',
			'decimals' => 2,
		),
		'NZD' => array(
			'name'     => __( 'New Zealand Dollar', 'wp-sell-services' ),
			'symbol'   => 'NZ$',
			'decimals' => 2,
		),
		'ZAR' => array(
			'name'     => __( 'South African Rand', 'wp-sell-services' ),
			'symbol'   => 'R',
			'decimals' => 2,
		),
		'RUB' => array(
			'name'     => __( 'Russian Ruble', 'wp-sell-services' ),
			'symbol'   => '₽',
			'decimals' => 2,
		),
		'TRY' => array(
			'name'     => __( 'Turkish Lira', 'wp-sell-services' ),
			'symbol'   => '₺',
			'decimals' => 2,
		),
		'PLN' => array(
			'name'     => __( 'Polish Zloty', 'wp-sell-services' ),
			'symbol'   => 'zł',
			'decimals' => 2,
		),
		'THB' => array(
			'name'     => __( 'Thai Baht', 'wp-sell-services' ),
			'symbol'   => '฿',
			'decimals' => 2,
		),
		'MYR' => array(
			'name'     => __( 'Malaysian Ringgit', 'wp-sell-services' ),
			'symbol'   => 'RM',
			'decimals' => 2,
		),
		'PHP' => array(
			'name'     => __( 'Philippine Peso', 'wp-sell-services' ),
			'symbol'   => '₱',
			'decimals' => 2,
		),
		'IDR' => array(
			'name'     => __( 'Indonesian Rupiah', 'wp-sell-services' ),
			'symbol'   => 'Rp',
			'decimals' => 2,
		),
		'VND' => array(
			'name'     => __( 'Vietnamese Dong', 'wp-sell-services' ),
			'symbol'   => '₫',
			'decimals' => 0,
		),
		'AED' => array(
			'name'     => __( 'UAE Dirham', 'wp-sell-services' ),
			'symbol'   => 'د.إ',
			'decimals' => 2,
		),
		'SAR' => array(
			'name'     => __( 'Saudi Riyal', 'wp-sell-services' ),
			'symbol'   => '﷼',
			'decimals' => 2,
		),
		'EGP' => array(
			'name'     => __( 'Egyptian Pound', 'wp-sell-services' ),
			'symbol'   => 'E£',
			'decimals' => 2,
		),
		'NGN' => array(
			'name'     => __( 'Nigerian Naira', 'wp-sell-services' ),
			'symbol'   => '₦',
			'decimals' => 2,
		),
	);

	/**
	 * Filter the canonical currency registry.
	 *
	 * Add, remove, or adjust a currency (name / symbol / decimals) in ONE
	 * place and every currency surface updates. Preferred over the
	 * per-surface currency filters.
	 *
	 * @since 1.2.1
	 *
	 * @param array<string, array{name: string, symbol: string, decimals: int}> $registry Currency registry.
	 */
	return apply_filters( 'wpss_currency_registry', $registry );
}

/**
 * Get supported currencies (code => name).
 *
 * Derived from {@see wpss_get_currency_registry()}.
 *
 * @return array<string, string>
 */
function wpss_get_currencies(): array {
	$currencies = array();
	foreach ( wpss_get_currency_registry() as $code => $data ) {
		$currencies[ $code ] = $data['name'];
	}

	/**
	 * Filter supported currencies.
	 *
	 * @param array $currencies Currencies array (code => name).
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
 * Resolve a reviewer's display name for templates.
 *
 * Thin template-facing wrapper over Review::resolve_reviewer_name() so raw
 * review rows in templates (which expose reviewer_name / customer_id) render
 * migrated guest authors instead of "Anonymous". Precedence: registered user
 * display_name -> stored guest name -> "Anonymous".
 *
 * @param int         $reviewer_id   Reviewer user ID (0 for guest/legacy).
 * @param string|null $reviewer_name Stored guest author name, if any.
 * @return string
 */
function wpss_get_reviewer_name( int $reviewer_id, ?string $reviewer_name = null ): string {
	return \WPSellServices\Models\Review::resolve_reviewer_name( $reviewer_id, $reviewer_name );
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
 * @param string $section Optional dashboard section slug.
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

	if ( '' !== $section ) {
		$url = wpss_append_dashboard_section( $url, $section );
	}

	return $url;
}

/**
 * Append a dashboard section to a base dashboard URL.
 *
 * Emits a pretty endpoint path (e.g. /dashboard/services/) when permalinks
 * are pretty, falling back to the `?section=` query arg on plain permalinks.
 * Centralizing this keeps every internal link, breadcrumb, and redirect on
 * the same URL shape, and means a single behavior change toggles them all.
 *
 * The default `orders` section maps to the bare dashboard URL (no segment)
 * to keep the canonical dashboard landing URL clean.
 *
 * @since 1.2.0
 *
 * @param string $base_url Base dashboard page permalink (may already carry query args).
 * @param string $section  Section slug (e.g. 'services', 'earnings').
 * @return string Section URL.
 */
function wpss_append_dashboard_section( string $base_url, string $section ): string {
	$section = sanitize_key( $section );

	if ( '' === $section || 'orders' === $section ) {
		return $base_url;
	}

	// Plain permalinks: keep the query-arg form.
	if ( ! get_option( 'permalink_structure' ) ) {
		return add_query_arg( 'section', $section, $base_url );
	}

	// Split off any query string / fragment so the endpoint segment is
	// inserted into the path, not appended after the query args.
	$query    = '';
	$fragment = '';

	$hash_pos = strpos( $base_url, '#' );
	if ( false !== $hash_pos ) {
		$fragment = substr( $base_url, $hash_pos );
		$base_url = substr( $base_url, 0, $hash_pos );
	}

	$query_pos = strpos( $base_url, '?' );
	if ( false !== $query_pos ) {
		$query    = substr( $base_url, $query_pos );
		$base_url = substr( $base_url, 0, $query_pos );
	}

	$path = trailingslashit( $base_url ) . $section . '/';

	return $path . $query . $fragment;
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
	return wpss_append_dashboard_section( $dashboard_url, 'create' );
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
		return wpss_append_dashboard_section( $dashboard_url, 'become-vendor' );
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
 * Get a service's add-ons with the legacy meta-key fallback.
 *
 * Add-ons live under `_wpss_extras` (the frontend Service Wizard's key) but
 * the admin metabox and CLI commands write to `_wpss_addons`. Every place
 * that resolves add-on indices MUST use this helper so admin / CLI-seeded
 * services surface their add-ons in the order modal, cart, checkout, and
 * orders alike. A full meta-key consolidation is parked for 1.2 — see
 * plans/future-features/from-1.1.0-audit.md.
 *
 * @since 1.2.0
 *
 * @param int $service_id Service post ID.
 * @return array<int, array<string, mixed>> Add-on rows (title, price, delivery_time), keyed by index.
 */
function wpss_get_service_extras( int $service_id ): array {
	$extras = get_post_meta( $service_id, '_wpss_extras', true ) ?: array();

	if ( empty( $extras ) ) {
		$extras = get_post_meta( $service_id, '_wpss_addons', true ) ?: array();
	}

	return is_array( $extras ) ? $extras : array();
}

/**
 * Get a service's minimum delivery days with the dual meta-key fallback.
 *
 * Delivery days live under two historical keys: `_wpss_delivery_days`
 * (written by the frontend Service Wizard and the save_post sync) and
 * `_wpss_fastest_delivery` (written by the admin metabox and the REST API).
 * Every PHP read site MUST use this helper so services created via either
 * path surface their delivery time in SEO schema, REST responses, and
 * package fallbacks alike. Meta-query filters cannot use this helper —
 * for those, every write site syncs BOTH keys instead. A full meta-key
 * consolidation is parked for 1.2 — see
 * plans/future-features/from-1.1.0-audit.md.
 *
 * @since 1.2.0
 *
 * @param int $service_id Service post ID.
 * @return int Delivery days, 0 when neither key is set.
 */
function wpss_get_service_delivery_days( int $service_id ): int {
	$delivery_days = (int) get_post_meta( $service_id, '_wpss_delivery_days', true );

	if ( $delivery_days <= 0 ) {
		$delivery_days = (int) get_post_meta( $service_id, '_wpss_fastest_delivery', true );
	}

	return max( 0, $delivery_days );
}

/**
 * Get a service's revision count with the dual meta-key fallback.
 *
 * Revision counts live under two historical keys: `_wpss_revisions`
 * (written by the frontend Service Wizard) and `_wpss_max_revisions`
 * (written by the admin metabox, the REST API, and CLI). Every PHP read
 * site MUST use this helper so services created via either path surface
 * their revision count in REST responses and package fallbacks alike.
 * Unlike the delivery-days helper, 0 ("No revisions") and -1 ("Unlimited")
 * are both valid stored values, so the fallback only triggers when the
 * primary key is truly absent. A full meta-key consolidation is parked
 * for 1.2 - see plans/future-features/from-1.1.0-audit.md.
 *
 * @since 1.2.0
 *
 * @param int $service_id Service post ID.
 * @return int Revision count. -1 means unlimited, 0 means none (or unset).
 */
function wpss_get_service_revisions( int $service_id ): int {
	$revisions = get_post_meta( $service_id, '_wpss_revisions', true );

	if ( '' === $revisions ) {
		$revisions = get_post_meta( $service_id, '_wpss_max_revisions', true );
	}

	return (int) $revisions;
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
	$all_extras    = wpss_get_service_extras( $service_id );

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

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
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

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
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

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is hardcoded fragments with %d/%s placeholders; values come via prepare().
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

	// get_pagenum_link() resolves the current request URL. Outside the main
	// query (e.g. a REST render) it can return a non-string, which would make
	// str_replace() fatal. Guard it so the default base is always a string; an
	// off-main-query caller (wpss_render_services_grid) supplies an explicit
	// 'base' + 'format' anyway.
	$pagenum_link = get_pagenum_link( 999999999 );
	$default_base = is_string( $pagenum_link )
		? str_replace( '999999999', '%#%', esc_url( $pagenum_link ) )
		: '';

	$defaults = array(
		'base'      => $default_base,
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
	// Packet H: load Lucide + bootstrap alongside the frontend bundle so every
	// <i data-lucide="…"> rendered by templates on the current surface hydrates
	// on DOMContentLoaded and on the wpss:icons:refresh CustomEvent.
	wp_enqueue_script( 'lucide' );
	wp_enqueue_script( 'wpss-icons' );
	wp_enqueue_script( 'wpss-frontend' );
}

/**
 * Check whether a URL is a recognized YouTube or Vimeo video.
 *
 * Used as the whitelist for vendor intro videos — anything that is not a
 * parseable YouTube / Vimeo link is rejected on save so the profile
 * never tries to embed an arbitrary third-party iframe.
 *
 * @since 1.1.0
 *
 * @param string $url Raw URL.
 * @return bool True if the URL is a parseable YouTube or Vimeo video link.
 */
function wpss_is_supported_video_url( string $url ): bool {
	if ( '' === $url ) {
		return false;
	}

	return null !== wpss_parse_video_embed( $url );
}

/**
 * Parse a YouTube or Vimeo URL into embed pieces.
 *
 * Returns null when the URL is not a supported provider or when the video
 * ID cannot be extracted. On success, returns:
 *   [
 *     'provider' => 'youtube'|'vimeo',
 *     'id'       => string,
 *     'embed'    => fully-qualified embed URL for an <iframe src>
 *   ]
 *
 * Supported URL shapes:
 *   - YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID,
 *     youtube.com/shorts/ID, m.youtube.com/* variants
 *   - Vimeo: vimeo.com/ID, player.vimeo.com/video/ID, channel paths with
 *     a numeric ID
 *
 * @since 1.1.0
 *
 * @param string $url Raw URL.
 * @return array{provider: string, id: string, embed: string}|null
 */
function wpss_parse_video_embed( string $url ): ?array {
	$url = trim( $url );
	if ( '' === $url ) {
		return null;
	}

	$parts = wp_parse_url( $url );
	$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	$host  = preg_replace( '/^www\./', '', $host );

	if ( 'youtu.be' === $host ) {
		$id = ltrim( (string) ( $parts['path'] ?? '' ), '/' );
		if ( preg_match( '/^[A-Za-z0-9_-]{6,}$/', $id ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $id,
				'embed'    => 'https://www.youtube.com/embed/' . rawurlencode( $id ),
			);
		}
		return null;
	}

	if ( 'youtube.com' === $host || 'm.youtube.com' === $host ) {
		$path = (string) ( $parts['path'] ?? '' );
		parse_str( (string) ( $parts['query'] ?? '' ), $query );

		$id = '';
		if ( '/watch' === $path && ! empty( $query['v'] ) ) {
			$id = (string) $query['v'];
		} elseif ( preg_match( '#^/(embed|shorts|v)/([A-Za-z0-9_-]{6,})#', $path, $m ) ) {
			$id = $m[2];
		}

		if ( preg_match( '/^[A-Za-z0-9_-]{6,}$/', $id ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $id,
				'embed'    => 'https://www.youtube.com/embed/' . rawurlencode( $id ),
			);
		}
		return null;
	}

	if ( 'vimeo.com' === $host || 'player.vimeo.com' === $host ) {
		$path = (string) ( $parts['path'] ?? '' );
		if ( preg_match( '#/(\d{5,})#', $path, $m ) ) {
			return array(
				'provider' => 'vimeo',
				'id'       => $m[1],
				'embed'    => 'https://player.vimeo.com/video/' . rawurlencode( $m[1] ),
			);
		}
		return null;
	}

	return null;
}

/**
 * Render a vendor intro video as a responsive 16:9 embed.
 *
 * Returns '' (not an iframe fallback) when the URL is empty or not a
 * supported provider so callers can echo unconditionally and the page
 * simply drops the video section when there isn't one.
 *
 * @since 1.1.0
 *
 * @param string $url   Raw video URL.
 * @param string $title Optional accessible title for the iframe.
 * @return string HTML wrapper + iframe, or empty string.
 */
function wpss_render_video_embed( string $url, string $title = '' ): string {
	$parsed = wpss_parse_video_embed( $url );
	if ( null === $parsed ) {
		return '';
	}

	$title = '' === $title ? __( 'Vendor intro video', 'wp-sell-services' ) : $title;

	return sprintf(
		'<div class="wpss-video-embed"><iframe src="%s" title="%s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe></div>',
		esc_url( $parsed['embed'] ),
		esc_attr( $title )
	);
}

/**
 * Render a paginated services grid (cards + pagination markup).
 *
 * Single source of truth for the services-block grid: both the REST
 * grid endpoint (ServicesController::get_grid) and the legacy
 * admin-ajax delegate (AjaxHandlers::load_services) call this so the
 * card template + every `wpss_*_service_card` extension hook + theme
 * override stay identical across both transports. Server-side rendering
 * is intentional — the card fires extension hooks a client-side JSON
 * renderer could not reproduce.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $attributes Block attributes (postsPerPage, orderBy, order, category).
 * @param int                  $page       Page number (1-based).
 * @param string               $base_url   Optional page URL the grid lives on, used as the
 *                                         pagination base. Required when rendering outside
 *                                         the main query (e.g. a REST request) where
 *                                         get_pagenum_link() cannot resolve the request URL.
 * @return array{html: string, pagination: string, total: int, pages: int} Rendered grid parts.
 */
function wpss_render_services_grid( array $attributes, int $page = 1, string $base_url = '' ): array {
	$args = array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => absint( $attributes['postsPerPage'] ?? 12 ),
		'paged'          => max( 1, $page ),
		'orderby'        => sanitize_key( $attributes['orderBy'] ?? 'date' ),
		'order'          => in_array( ( $attributes['order'] ?? 'DESC' ), array( 'ASC', 'DESC' ), true ) ? $attributes['order'] : 'DESC',
	);

	// Category filter.
	if ( ! empty( $attributes['category'] ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'wpss_service_category',
				'field'    => 'term_id',
				'terms'    => absint( $attributes['category'] ),
			),
		);
	}

	$query = new \WP_Query( $args );

	ob_start();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			wpss_get_template_part( 'content', 'service-card' );
		}
	} else {
		echo '<p class="wpss-no-services">' . esc_html__( 'No services found.', 'wp-sell-services' ) . '</p>';
	}
	wp_reset_postdata();
	$html = ob_get_clean();

	// Pagination. When a base URL is supplied (REST/off-main-query render),
	// build an explicit base + format so paginate_links does not depend on
	// get_pagenum_link() resolving the ambient request URL.
	$pagination_args = array();
	if ( '' !== $base_url ) {
		$clean = remove_query_arg( 'paged', $base_url );
		$sep   = ( false === strpos( $clean, '?' ) ) ? '?' : '&';

		$pagination_args['base']    = $clean . '%_%';
		$pagination_args['format']  = $sep . 'paged=%#%';
		$pagination_args['current'] = max( 1, $page );
	}

	ob_start();
	wpss_pagination( $query, $pagination_args );
	$pagination = ob_get_clean();

	return array(
		'html'       => $html,
		'pagination' => $pagination,
		'total'      => (int) $query->found_posts,
		'pages'      => (int) $query->max_num_pages,
	);
}

/**
 * Validate + upload conversation message file attachments.
 *
 * Single source of truth for message attachment handling. Used by both the
 * REST ConversationsController::send_message and the legacy admin-ajax
 * AjaxHandlers::send_message so the allow-list, MIME re-check, size cap, and
 * upload behaviour are identical across transports.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $files A single $_FILES['attachments'] entry (PHP's grouped
 *                     multi-file shape: name[], type[], tmp_name[], etc.).
 * @return array{attachments: array<int, array{id:int,url:string,name:string,type:string}>, skipped: array<int,string>}
 */
function wpss_handle_message_attachments( array $files ): array {
	$attachments = array();
	$skipped     = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return array(
			'attachments' => $attachments,
			'skipped'     => $skipped,
		);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt' );
	$allowed_mimes = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'application/pdf',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/zip',
		'text/plain',
	);
	$max_size      = 10 * 1024 * 1024; // 10MB per file.

	$file_count = count( $files['name'] );
	for ( $i = 0; $i < $file_count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}

		$file = array(
			'name'     => $files['name'][ $i ],
			'type'     => $files['type'][ $i ],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);

		$file_name = sanitize_file_name( $file['name'] );

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_types, true ) ) {
			$skipped[] = $file_name . ': ' . __( 'unsupported file type', 'wp-sell-services' );
			continue;
		}

		$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$mime_type = $file_info['type'] ?? '';
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			$skipped[] = $file_name . ': ' . __( 'invalid MIME type', 'wp-sell-services' );
			continue;
		}

		if ( $file['size'] > $max_size ) {
			$skipped[] = $file_name . ': ' . __( 'file too large (max 10MB)', 'wp-sell-services' );
			continue;
		}

		$_FILES['upload_file'] = $file;
		$attachment_id         = media_handle_upload( 'upload_file', 0 );

		if ( ! is_wp_error( $attachment_id ) ) {
			$attachments[] = array(
				'id'   => $attachment_id,
				'url'  => wp_get_attachment_url( $attachment_id ),
				'name' => $files['name'][ $i ],
				'type' => $mime_type, // Server-verified MIME, not client-provided.
			);
		} else {
			$skipped[] = $file_name . ': ' . $attachment_id->get_error_message();
		}
	}

	return array(
		'attachments' => $attachments,
		'skipped'     => $skipped,
	);
}

/**
 * Normalize PHP's grouped $_FILES entry into a list of per-file specs.
 *
 * Turns the `name[]/type[]/tmp_name[]/error[]/size[]` shape PHP produces for a
 * multi-file field into a flat array of single-file specs (name/type/tmp_name/
 * error/size), skipping empty slots and sanitizing the client-supplied name +
 * mime. tmp_name/error/size are not user-controlled. Shared by the REST
 * deliverables endpoint and the legacy admin-ajax delivery handler so both
 * feed DeliveryService::submit() the same shape.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $files A single grouped $_FILES['field'] entry.
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function wpss_normalize_uploaded_files( array $files ): array {
	$out = array();

	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return $out;
	}

	$count = count( $files['name'] );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}
		$out[] = array(
			'name'     => sanitize_file_name( $files['name'][ $i ] ),
			'type'     => sanitize_mime_type( $files['type'][ $i ] ),
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => (int) $files['error'][ $i ],
			'size'     => (int) $files['size'][ $i ],
		);
	}

	return $out;
}

/**
 * Sanitize a date string to a strict Y-m-d value or null.
 *
 * Accepts only a calendar-valid Y-m-d date that round-trips exactly; anything
 * else (empty string, partial, or impossible date like 2026-13-40) returns
 * null so the DATE column stores SQL NULL.
 *
 * @since 1.2.0
 *
 * @param string $value Raw date string.
 * @return string|null Valid Y-m-d date, or null.
 */
function wpss_sanitize_date( string $value ): ?string {
	$value = trim( sanitize_text_field( $value ) );

	if ( '' === $value ) {
		return null;
	}

	$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

	if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $value ) {
		return null;
	}

	return $value;
}

/**
 * Build a sanitized vendor-profile update payload from a posted field set.
 *
 * Single source of truth for the vendor-profile form fields, shared by the
 * REST VendorsController::update_current_vendor and the legacy admin-ajax
 * AjaxHandlers::update_vendor_profile so both transports sanitize identically
 * and write the SAME wpss_vendor_profiles columns (via
 * VendorService::update_profile()). Only keys present in $src are included, so
 * partial updates leave untouched fields alone.
 *
 * @since 1.2.0
 *
 * @param array<string, mixed> $src Unslashed field set (tagline, bio, country, city,
 *                         website, intro_video_url, vacation_mode,
 *                         vacation_message; avatar_id/cover_id keys signal an
 *                         intent to clear when the id is 0).
 * @param int                  $avatar_id Resolved avatar attachment id (0 = none/clear).
 * @param int                  $cover_id  Resolved cover attachment id (0 = none/clear).
 * @return array<string, mixed> Sanitized data for VendorService::update_profile().
 */
function wpss_build_vendor_profile_update( array $src, int $avatar_id, int $cover_id ): array {
	$data = array();

	if ( array_key_exists( 'tagline', $src ) ) {
		$data['tagline'] = sanitize_text_field( (string) $src['tagline'] );
	}
	if ( array_key_exists( 'bio', $src ) ) {
		$data['bio'] = sanitize_textarea_field( (string) $src['bio'] );
	}
	if ( array_key_exists( 'country', $src ) ) {
		$data['country'] = sanitize_text_field( (string) $src['country'] );
	}
	if ( array_key_exists( 'city', $src ) ) {
		$data['city'] = sanitize_text_field( (string) $src['city'] );
	}
	if ( array_key_exists( 'website', $src ) ) {
		$data['website'] = esc_url_raw( (string) $src['website'] );
	}
	if ( array_key_exists( 'intro_video_url', $src ) ) {
		// Accept only YouTube/Vimeo origins - stored verbatim, rendered through
		// the safe embed helper. Anything else clears the field so the UI falls
		// back to the no-video state.
		$raw_video               = esc_url_raw( (string) $src['intro_video_url'] );
		$data['intro_video_url'] = wpss_is_supported_video_url( $raw_video ) ? $raw_video : '';
	}
	if ( array_key_exists( 'vacation_mode', $src ) ) {
		$data['vacation_mode'] = empty( $src['vacation_mode'] ) ? 0 : 1;
	}
	if ( array_key_exists( 'vacation_message', $src ) ) {
		$data['vacation_message'] = sanitize_textarea_field( (string) $src['vacation_message'] );
	}
	if ( array_key_exists( 'vacation_return_date', $src ) ) {
		// Accept only a strict Y-m-d date; anything else (incl. empty) stores NULL.
		$data['vacation_return_date'] = wpss_sanitize_date( (string) $src['vacation_return_date'] );
	}

	if ( $avatar_id > 0 ) {
		$data['avatar_id'] = $avatar_id;
	} elseif ( array_key_exists( 'avatar_id', $src ) ) {
		$data['avatar_id'] = null;
	}

	if ( $cover_id > 0 ) {
		$data['cover_image_id'] = $cover_id;
	} elseif ( array_key_exists( 'cover_id', $src ) ) {
		$data['cover_image_id'] = null;
	}

	return $data;
}

/**
 * Render one conversation message row (the canonical messaging markup).
 *
 * Single source of truth for a message bubble in the order/dashboard thread.
 * Used by the initial server render (templates/order/conversation.php), the
 * REST message response (additive `html` field), the REST/AJAX send response,
 * and the AJAX poll - so every transport appends byte-identical markup.
 *
 * Accepts either a Message model (attachments/read_by as arrays, created_at as
 * DateTimeImmutable) or a raw $wpdb row (JSON strings, string created_at, with
 * an optional sender_name from a JOIN); the shape is normalised internally.
 *
 * @since 1.2.0
 *
 * @param object $message         Message model or raw DB row.
 * @param int    $current_user_id Viewer user ID (controls sent/received styling).
 * @return string Message row HTML.
 */
function wpss_render_message_row( object $message, int $current_user_id ): string {
	$sender_id = (int) ( $message->sender_id ?? 0 );
	$content   = (string) ( $message->content ?? '' );
	$type      = $message->type ?? ( $message->content_type ?? 'text' );
	$is_own    = $sender_id === $current_user_id;
	$is_system = 'system' === $type;

	// Normalise created_at to a timestamp.
	$created = $message->created_at ?? null;
	if ( $created instanceof \DateTimeInterface ) {
		$created_ts = $created->getTimestamp();
	} else {
		$created_ts = $created ? strtotime( (string) $created ) : time();
	}
	$time_text = wp_date( get_option( 'time_format' ), $created_ts );

	// Normalise attachments + read_by to arrays.
	$attachments = $message->attachments ?? array();
	if ( is_string( $attachments ) ) {
		$attachments = json_decode( $attachments, true ) ?: array();
	}
	$read_by = $message->read_by ?? array();
	if ( is_string( $read_by ) ) {
		$read_by = json_decode( $read_by, true ) ?: array();
	}

	// Sender display name (prefer a JOIN-supplied value, else look it up).
	$sender_name = $message->sender_name ?? '';
	if ( '' === $sender_name && $sender_id ) {
		$sender      = get_userdata( $sender_id );
		$sender_name = $sender ? $sender->display_name : '';
	}

	ob_start();

	if ( $is_system ) :
		?>
		<div class="wpss-messaging__system">
			<span class="wpss-messaging__system-text">
				<?php echo wp_kses_post( $content ); ?>
				<span class="wpss-messaging__message-time">
					<?php echo esc_html( $time_text ); ?>
				</span>
			</span>
		</div>
		<?php
	else :
		?>
		<div class="wpss-messaging__message <?php echo $is_own ? 'wpss-messaging__message--sent' : ''; ?>" data-message-id="<?php echo esc_attr( (string) ( $message->id ?? 0 ) ); ?>">
			<?php if ( ! $is_own ) : ?>
				<div class="wpss-messaging__message-avatar">
					<?php echo get_avatar( $sender_id, 32 ); ?>
				</div>
			<?php endif; ?>
			<div class="wpss-messaging__message-content">
				<div class="wpss-messaging__bubble">
					<?php if ( ! $is_own ) : ?>
						<span class="wpss-messaging__sender"><?php echo esc_html( $sender_name ); ?></span>
					<?php endif; ?>
					<div class="wpss-messaging__text">
						<?php echo wp_kses_post( nl2br( $content ) ); ?>
					</div>
					<?php if ( ! empty( $attachments ) ) : ?>
						<div class="wpss-messaging__attachments">
							<?php foreach ( $attachments as $attachment ) : ?>
								<?php
								$file_url  = $attachment['url'] ?? '';
								$file_name = $attachment['name'] ?? ( $attachment['filename'] ?? basename( (string) $file_url ) );
								$file_type = $attachment['type'] ?? '';
								$is_image  = 0 === strpos( (string) $file_type, 'image/' );
								?>
								<?php if ( $is_image && $file_url ) : ?>
									<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-image">
										<img src="<?php echo esc_url( $file_url ); ?>" alt="<?php echo esc_attr( $file_name ); ?>">
									</a>
								<?php else : ?>
									<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-file">
										<span class="wpss-messaging__attachment-icon">
											<i data-lucide="file" class="wpss-icon" aria-hidden="true"></i>
										</span>
										<span class="wpss-messaging__attachment-info">
											<span class="wpss-messaging__attachment-name"><?php echo esc_html( $file_name ); ?></span>
										</span>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<span class="wpss-messaging__message-time">
					<?php echo esc_html( $time_text ); ?>
					<?php $is_read = ! empty( array_diff_key( (array) $read_by, array( $current_user_id => '' ) ) ); ?>
					<?php if ( $is_own && $is_read ) : ?>
						<span class="wpss-messaging__message-status wpss-messaging__message-status--read" title="<?php esc_attr_e( 'Read', 'wp-sell-services' ); ?>">
							<i data-lucide="check" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
						</span>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	endif;

	return (string) ob_get_clean();
}
