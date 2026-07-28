<?php
/**
 * Checkout Intent — a gateway-agnostic description of "what to charge and what
 * to create" for one checkout.
 *
 * The whole point of this object is that a payment gateway never needs to know
 * about services, packages, add-ons, carts, or multi-vendor splitting. It is
 * handed an authoritative amount + currency + buyer, charges it, and on success
 * hands the intent back to CheckoutIntentService::settle() to create the
 * order(s). One item or fifty, catalog purchase or paying an existing order —
 * the gateway code is identical.
 *
 * @package WPSellServices\Checkout
 * @since   1.5.2
 */

declare(strict_types=1);

namespace WPSellServices\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object produced by CheckoutIntentService::resolve().
 *
 * @since 1.5.2
 */
final class CheckoutIntent {

	/** Pay an existing order (proposal / milestone / tip / extension). */
	public const KIND_ORDER = 'order';

	/** Multi-item cart → create one order per line. */
	public const KIND_CART = 'cart';

	/** Single service + package (+ add-ons) → create one order. */
	public const KIND_SINGLE = 'single';

	/**
	 * One of the KIND_* constants.
	 *
	 * @since 1.5.2
	 * @var   string
	 */
	public string $kind;

	/**
	 * Amount to charge, computed SERVER-SIDE. Never from the client.
	 *
	 * @since 1.5.2
	 * @var   float
	 */
	public float $amount;

	/**
	 * ISO currency code.
	 *
	 * @since 1.5.2
	 * @var   string
	 */
	public string $currency;

	/**
	 * Buyer user ID.
	 *
	 * @since 1.5.2
	 * @var   int
	 */
	public int $buyer_id;

	/**
	 * Metadata for the gateway (customer id, order id, etc.).
	 *
	 * @since 1.5.2
	 * @var   array<string,mixed>
	 */
	public array $metadata;

	/**
	 * Existing order ID (KIND_ORDER only).
	 *
	 * @since 1.5.2
	 * @var   int
	 */
	public int $order_id = 0;

	/**
	 * Cart line items (KIND_CART only).
	 *
	 * @since 1.5.2
	 * @var   array<int,array<string,mixed>>
	 */
	public array $cart = array();

	/**
	 * Service ID (KIND_SINGLE only).
	 *
	 * @since 1.5.2
	 * @var   int
	 */
	public int $service_id = 0;

	/**
	 * Package ID (KIND_SINGLE only).
	 *
	 * @since 1.5.2
	 * @var   int
	 */
	public int $package_id = 0;

	/**
	 * Resolved add-ons (KIND_SINGLE only).
	 *
	 * @since 1.5.2
	 * @var   array<int,array<string,mixed>>
	 */
	public array $addons = array();

	/**
	 * Add-ons total (KIND_SINGLE only).
	 *
	 * @since 1.5.2
	 * @var   float
	 */
	public float $addons_total = 0.0;

	/**
	 * Private constructor — use the named factories.
	 *
	 * @since 1.5.2
	 *
	 * @param string $kind     Kind.
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 * @param int    $buyer_id Buyer ID.
	 * @param array<string, mixed> $metadata Gateway metadata.
	 */
	private function __construct( string $kind, float $amount, string $currency, int $buyer_id, array $metadata ) {
		$this->kind     = $kind;
		$this->amount   = $amount;
		$this->currency = $currency;
		$this->buyer_id = $buyer_id;
		$this->metadata = $metadata;
	}

	/**
	 * Intent to pay an existing order.
	 *
	 * @since 1.5.2
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Order total.
	 * @param string $currency Currency.
	 * @param int    $buyer_id Buyer ID.
	 * @param array<string, mixed> $metadata Gateway metadata.
	 * @return self
	 */
	public static function order( int $order_id, float $amount, string $currency, int $buyer_id, array $metadata ): self {
		$intent           = new self( self::KIND_ORDER, $amount, $currency, $buyer_id, $metadata );
		$intent->order_id = $order_id;

		return $intent;
	}

	/**
	 * Intent to buy a multi-item cart.
	 *
	 * @since 1.5.2
	 *
	 * @param array<int, mixed> $cart Cart line items.
	 * @param float  $amount   Server-computed cart total.
	 * @param string $currency Currency.
	 * @param int    $buyer_id Buyer ID.
	 * @param array<string, mixed> $metadata Gateway metadata.
	 * @return self
	 */
	public static function cart( array $cart, float $amount, string $currency, int $buyer_id, array $metadata ): self {
		$intent       = new self( self::KIND_CART, $amount, $currency, $buyer_id, $metadata );
		$intent->cart = $cart;

		return $intent;
	}

	/**
	 * Intent to buy a single service + package (+ add-ons).
	 *
	 * @since 1.5.2
	 *
	 * @param int    $service_id   Service ID.
	 * @param int    $package_id   Package ID.
	 * @param array<int, mixed> $addons       Resolved add-ons.
	 * @param float  $addons_total Add-ons total.
	 * @param float  $amount       Package price + add-ons.
	 * @param string $currency     Currency.
	 * @param int    $buyer_id     Buyer ID.
	 * @param array<string, mixed> $metadata     Gateway metadata.
	 * @return self
	 */
	public static function single( int $service_id, int $package_id, array $addons, float $addons_total, float $amount, string $currency, int $buyer_id, array $metadata ): self {
		$intent               = new self( self::KIND_SINGLE, $amount, $currency, $buyer_id, $metadata );
		$intent->service_id   = $service_id;
		$intent->package_id   = $package_id;
		$intent->addons       = $addons;
		$intent->addons_total = $addons_total;

		return $intent;
	}
}
