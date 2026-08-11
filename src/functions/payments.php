<?php
/**
 * Payments: the active adapter, order provider and gateway/checkout configuration.
 *
 * Split out of src/functions.php, which had grown to 6,187 lines and 148
 * global functions in a single file. This is a positional move only - no
 * function was renamed, resignatured or changed, so every call site is
 * untouched. src/functions.php now just requires these files.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get active e-commerce adapter.
 *
 * @return \WPSellServices\Integrations\Contracts\EcommerceAdapterInterface|null
 */
function wpss_get_active_adapter(): ?\WPSellServices\Integrations\Contracts\EcommerceAdapterInterface {
	return wpss()->get_integration_manager()->get_active_adapter();
}

/**
 * Whether NEW payments are taken through the plugin's own gateways.
 *
 * ONE rule, decided in one place: our gateways take payment only on the
 * standalone rail. The cart integrations are optional - a site turns one on
 * when it wants that plugin to take the money. The moment it does, WooCommerce
 * (or EDD, FluentCart, SureCart) processes ALL new payment and ours stop
 * offering a second way to pay.
 *
 * Not filterable, on purpose. Giving a buyer the choice between "pay with
 * WooCommerce" and "pay with our Stripe" on one site is confusing for them and
 * leaves the two systems disagreeing about whether the order was paid: our
 * gateway would charge on our keys with no order in the store, so no store
 * receipt, refund or report would ever know about it.
 *
 * This governs STARTING a payment, not history. Switching rails never rewrites
 * past orders: an order paid through our Stripe keeps its reference and its
 * webhooks keep being handled, and an order that went through Woo keeps its WC
 * order link, whichever rail the site later runs. Only the next order is
 * affected by the switch.
 *
 * Sub-order payments (tips, milestones, extensions) do not consult this: they
 * resolve through wpss_get_pay_order_url(), which hands off to whichever rail
 * is active.
 *
 * @since 1.4.0
 *
 * @return bool True when new payments are taken through our own gateways.
 */
function wpss_uses_standalone_payments(): bool {
	$adapter = function_exists( 'wpss_get_ecommerce_adapter' ) ? wpss_get_ecommerce_adapter() : null;

	return ! $adapter || 'standalone' === $adapter->get_id();
}

/**
 * Default checkout reassurance badges.
 *
 * ONE definition, used by the settings screen (as placeholders) and by the
 * checkout (as fallbacks), so what an owner sees while editing is exactly what
 * a buyer gets when a row is left blank.
 *
 * Every default is a statement of fact the plugin can back up. Nothing here
 * promises a refund, a guarantee or an outcome - the checkout used to say
 * "On-time Delivery / Or your money back", which no code in this plugin
 * honours and no owner agreed to. An owner who genuinely offers that can type
 * it in; we will not say it on their behalf.
 *
 * `delivery` and `revisions` carry no default sub-text because theirs comes
 * from the package being bought - see wpss_get_checkout_badges().
 *
 * @since 1.4.0
 *
 * @return array<string, array<string, string>> Keyed by badge id.
 */
function wpss_get_checkout_badge_defaults(): array {
	return array(
		'delivery'      => array(
			'label' => __( 'Delivery time', 'wp-sell-services' ),
			'title' => __( 'Delivery time', 'wp-sell-services' ),
			'note'  => '',
		),
		'communication' => array(
			'label' => __( 'Communication', 'wp-sell-services' ),
			'title' => __( 'Direct communication', 'wp-sell-services' ),
			'note'  => __( 'Message your seller on the order', 'wp-sell-services' ),
		),
		'revisions'     => array(
			'label' => __( 'Revisions', 'wp-sell-services' ),
			'title' => __( 'Revisions', 'wp-sell-services' ),
			'note'  => '',
		),
	);
}

/**
 * Build the checkout reassurance badges for a purchase.
 *
 * Owner text wins; where they have left a row blank we fall back to the real
 * numbers on the package being bought, so the badge can never contradict the
 * order it sits next to.
 *
 * @since 1.4.0
 *
 * @param array<string, mixed> $package Package being purchased.
 * @return array<int, array<string, string>> Renderable badges.
 */
function wpss_get_checkout_badges( array $package ): array {
	$settings = get_option( 'wpss_general', array() );

	if ( isset( $settings['checkout_badges_enabled'] ) && ! $settings['checkout_badges_enabled'] ) {
		return array();
	}

	$owner    = isset( $settings['checkout_badges'] ) && is_array( $settings['checkout_badges'] ) ? $settings['checkout_badges'] : array();
	$defaults = wpss_get_checkout_badge_defaults();

	$days      = isset( $package['delivery_days'] ) ? (int) $package['delivery_days'] : 0;
	$revisions = isset( $package['revisions'] ) ? (int) $package['revisions'] : null;

	// Facts about THIS purchase, used when the owner has not written their own.
	$fallback_notes = array(
		'delivery'  => $days > 0
			/* translators: %d: number of days. */
			? sprintf( _n( '%d day from requirements', '%d days from requirements', $days, 'wp-sell-services' ), $days )
			: '',
		'revisions' => null === $revisions
			? ''
			: ( -1 === $revisions
				? __( 'Unlimited revisions included', 'wp-sell-services' )
				/* translators: %d: number of revisions. */
				: sprintf( _n( '%d revision included', '%d revisions included', $revisions, 'wp-sell-services' ), $revisions ) ),
	);

	$icons = array(
		'delivery'      => "\xE2\x8F\xB1",
		'communication' => "\xF0\x9F\x92\xAC",
		'revisions'     => "\xE2\x9C\x85",
	);

	$badges = array();

	foreach ( $defaults as $key => $default ) {
		$title = trim( (string) ( $owner[ $key ]['title'] ?? '' ) );
		$note  = trim( (string) ( $owner[ $key ]['note'] ?? '' ) );

		$title = '' !== $title ? $title : $default['title'];
		$note  = '' !== $note ? $note : ( $fallback_notes[ $key ] ?? $default['note'] );

		// Nothing true to say about this one for this package - say nothing.
		if ( '' === $note ) {
			continue;
		}

		$badges[] = array(
			'icon'  => $icons[ $key ] ?? '',
			'title' => $title,
			'note'  => $note,
		);
	}

	/**
	 * Filter the checkout reassurance badges.
	 *
	 * @since 1.4.0
	 *
	 * @param array $badges  Each entry: icon, title, note.
	 * @param array $package Package being purchased.
	 */
	return (array) apply_filters( 'wpss_checkout_badges', $badges, $package );
}

/**
 * Whether the plugin is running payments in demo mode.
 *
 * A fresh install is meant to work end to end from the first minute. Until
 * this existed it could not: Stripe and PayPal ship without credentials and
 * the Test gateway was hidden behind WP_DEBUG, which is off on every
 * production site - so a new owner set up a marketplace, walked a buyer to
 * checkout and hit an empty gateway list, with nothing on screen to say a
 * step was missing.
 *
 * Demo mode fills that gap and then gets out of the way. It is ON only while
 * ALL of these hold:
 *
 *   - this site takes payment through our own gateways (standalone rail)
 *   - no real gateway has been configured yet
 *   - the owner has not turned it off
 *
 * So it disables itself the moment real credentials are saved. There is no
 * state to remember and no way to be silently stuck in test mode with a live
 * store - the thing that makes "enable a test gateway by default" dangerous
 * in most plugins.
 *
 * @since 1.4.0
 *
 * @return bool
 */
function wpss_demo_payments_enabled(): bool {
	if ( ! wpss_uses_standalone_payments() ) {
		return false;
	}

	// An explicit opt-out always wins, so an owner can run a live standalone
	// store with no gateway yet configured without a demo checkout appearing.
	if ( 'no' === get_option( 'wpss_demo_payments', '' ) ) {
		return false;
	}

	return ! wpss_has_live_gateway();
}

/**
 * Whether any real payment gateway is configured and usable.
 *
 * "Configured" means enabled AND carrying the credentials it needs - an
 * enabled gateway with empty keys cannot take money, so it does not count.
 *
 * @since 1.4.0
 *
 * @return bool
 */
function wpss_has_live_gateway(): bool {
	$gateways = wpss()->get_payment_gateways();

	foreach ( $gateways as $id => $gateway ) {
		if ( 'test' === $id ) {
			continue;
		}

		// is_enabled() is the interface method, and Stripe/PayPal implement it
		// as "enabled AND has the keys it needs" - which is exactly the
		// question here. An enabled gateway with blank credentials cannot take
		// money, so it must not count as live.
		if ( $gateway instanceof \WPSellServices\Integrations\Contracts\PaymentGatewayInterface && $gateway->is_enabled() ) {
			return true;
		}
	}

	return false;
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
 * @return \WPSellServices\Integrations\Contracts\OrderProviderInterface The marketplace order provider.
 */
function wpss_get_order_provider(): \WPSellServices\Integrations\Contracts\OrderProviderInterface {
	// ONE order authority, always.
	//
	// Orders live in wpss_orders and belong to the marketplace. A third-party
	// plugin (WooCommerce, EDD, FluentCart, SureCart) is a payment PROCESSOR: it
	// collects the money and reports a status back. It never owns the order.
	//
	// This used to hand back `$adapter->get_order_provider()`, so the object
	// changed with whichever adapter happened to be active. Two consequences,
	// both real:
	// 1. Only StandaloneOrderProvider implements create_order() /
	// create_orders_from_cart() / mark_as_paid(). On a licensed site with
	// WooCommerce active this resolved to WCOrderProvider, which implements
	// none of them - so a late Stripe/PayPal webhook for a legacy order hit
	// "Call to undefined method WCOrderProvider::mark_as_paid()". Gateways
	// stay registered on every rail BY DESIGN (see Plugin.php) precisely so
	// those webhooks keep working, so this was reachable from an
	// unauthenticated request.
	// 2. Each adapter's provider filtered reads to its own `platform`, so a
	// buyer saw only the orders taken by today's rail.
	//
	// One provider removes both. `platform` still records the processor per order.
	return new \WPSellServices\Integrations\Standalone\StandaloneOrderProvider();
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
