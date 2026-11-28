# Add a Payment Method

A payment method (a gateway) is a small plugin that adds one way to pay at checkout. WP Sell Services owns everything around the money: pricing, the orders, the checkout page, the Payment methods screen, Mark as paid, refunds, the abandoned-order sweep and the app routes. Your gateway only moves the money and says what it can do.

Start from the sample: **`docs/examples/wpss-sample-bank-transfer/`** in the plugin's GitHub repository. It is a complete bank transfer method in two files. Copy the folder into `wp-content/plugins/`, activate **WP Sell Services - Sample Bank Transfer**, and it appears under **Sell Services > Settings > Payment gateways**.

## 1. Register the gateway

Extend `WPSellServices\Integrations\Gateways\AbstractPaymentGateway` and add it through the `wpss_payment_gateways` filter. The array key must be the gateway's own `get_id()`; an entry under another key is refused.

```php
add_filter( 'wpss_payment_gateways', function ( array $gateways ): array {
	require_once __DIR__ . '/class-gateway.php'; // Loaded here, so WP Sell Services is always loaded first.

	$gateway = new \MyGateway\Gateway();
	$gateway->init();
	$gateways[ $gateway->get_id() ] = $gateway;

	return $gateways;
} );
```

Always register it, even when the owner has switched it off. A method that is off is only left out of new checkouts; its existing orders still need it for refunds, webhooks and receipts.

## 2. Say what it can do

Set `$supports` on your class. Everything else is asked through `supports()`, never by your gateway's name.

| Feature | Declare it when | What you get |
|---|---|---|
| `manual_confirmation` | The money arrives outside the site and the owner confirms it (bank transfer, cash, a payment link) | **Mark as paid** on the order, the buyer's "waiting for confirmation" notice and proof-of-payment upload, the **Payments to confirm** count, and no abandoned-order sweep on its unpaid orders |
| `redirect_return` | The buyer pays on the provider's page and comes back | Override `reconcile_redirect_return()`; it runs when the return URL carries `wpss_return=<id>` |
| `verified_confirmation` | Your confirm and return legs ask the provider whether the money was taken | A payment already started can still finish after the owner switches the method off |

## 3. The methods you write

| Method | Return |
|---|---|
| `get_id()` | A lowercase id. It is stored on every order as `payment_method`, so never change it. |
| `get_name()`, `get_description()` | What the buyer and the owner read. |
| `is_enabled()` | The owner's switch (`enabled` in your settings option), plus anything else it needs, such as API keys. |
| `supports_currency( $code )` | Whether the provider takes that currency. Checkout leaves the method out when it does not. |
| `init()` | Hook your settings, your checkout handler and your webhook. |
| `render_payment_form( $amount, $currency, $order_id )` | The markup under the method at checkout. Escape it. |
| `process_refund( $transaction_id, $amount, $reason )` | `success` true or false. Add `'manual' => true` when no money moved and the owner must send it. |
| `handle_webhook( $payload )` | An array with `success`. |
| `create_payment()`, `process_payment()` | Your provider's calls, used by your own checkout code. |
| `get_settings_fields()` | Field definitions, for reference. |

`get_settings_card()`, `rest_create_intent()` and `rest_confirm_payment()` have working defaults in the base class.

## 4. Settings and the on/off switch

Your gateway gets a card on the **Payment gateways** settings tab. Its settings are one option named `wpss_{id}_settings`, which is also the card's settings group: register it with `register_setting()` on `admin_init` and draw the fields on `wpss_gateway_settings_{id}`.

The **Payment methods** table turns the method on and off by writing `enabled` into that option, so your sanitizer must keep `enabled` along with your own fields. The table shows **Needs setup** when the switch is on but `is_enabled()` is false.

## 5. The checkout

**Pay later** (`manual_confirmation`): the checkout form posts `action=wpss_{id}_process_payment` with the `wpss_checkout` nonce. Your handler:

1. Checks the nonce, calls `wpss_gateway_require_enabled( $this )` and requires a logged-in buyer.
2. Saves the billing fields with `wpss_save_billing_from_request( $_POST )`.
3. Reads the request with `CheckoutIntentService::request_from_post( $_POST )`.
4. For a cart (`is_multi_checkout`), calls `$this->open_pending_cart_orders( $buyer_id )`. Otherwise it calls `$this->open_pending_order( $request )`, which prices a new purchase or puts an existing order (a tip, a milestone, an extension) on your method.
5. Answers `wp_send_json_success( array( 'redirect_url' => ... ) )`.

Never price an order or write an order row yourself. Those two methods apply the prices, add-ons, the owner's order limits and the account checks every rail shares.

**Pay now** (cards, wallets): mark your form `data-wpss-own-submit` so the shared submit handler stands down, then charge through the CheckoutIntent seam: `resolve()` gives the amount to charge, `settle()` records it. See [Custom Integrations](custom-integrations.md#the-checkoutintent-seam-gateway-agnostic). Never call `mark_as_paid()` from a gateway.

## 6. The mobile app

`POST /wpss/v1/payments/create-intent` with `gateway={id}` calls your `rest_create_intent()`. A pay-later method returns `$this->create_offline_rest_order( $request )`. The app lists your method from `GET /wpss/v1/payments/methods`, the same list the website checkout shows.

## 7. Webhooks

The provider calls `/wpss-payment/{id}/callback/`; get the URL with `wpss_get_payment_callback_url( 'your_id' )`. Hook `wpss_payment_callback_{id}`, check the provider's signature, then answer with `$this->respond_to_webhook( $this->handle_webhook( $event ), $dedup_key )`:

- A result with `retry` answers 500, so the provider sends the event again.
- Anything else answers 200 and is remembered for 48 hours, so a repeat delivery is applied once.

To mark the order paid from a webhook, use `$this->settle_webhook_payment( $order_id, $transaction_id, $amount, $currency )`. It gives back a payment for the wrong amount and refuses one already used. The route stays live on every rail, so refunds and disputes on old orders still arrive.

## 8. Check it

Run the payment-module contract test:

```bash
wp eval-file wp-content/plugins/wp-sell-services/tests/test-payment-module-contract.php
```

It checks every registered gateway, including yours: keyed by its id, a settings card, only known features, a refund and a webhook that answer, a failed provider call that is a failed refund rather than a success, and manual exactly when it declares `manual_confirmation`. It also walks the sample end to end: switched on from the Payment Methods table, a purchase, a tip and a cart through its checkout handler, the order page, the abandoned sweep, switched off, then Mark as paid.

## Related Guides

- **[Take Payments Outside the Plugin](payments-outside-the-plugin.md)** -- No gateway at all: Offline plus Mark as paid
- **[Custom Integrations](custom-integrations.md)** -- The interface and the CheckoutIntent seam
- **[Hooks & Filters](hooks-filters.md)** -- `wpss_payment_gateways`, `wpss_checkout_gateways` and the rest
