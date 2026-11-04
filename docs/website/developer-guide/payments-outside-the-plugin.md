# Take Payments Outside the Plugin

Some sites collect money with something the plugin does not ship, such as Flutterwave, Mobile Money or a payment link sent by hand. Adding a full payment gateway is custom development. This page shows the lighter route: let the buyer place an order with the **Offline** method, collect the money your own way, then tell the plugin the order is paid.

You need two things: a signal when an order is placed, and a way to say it is paid.

## 1. Know when an order is placed

| Hook | Arguments | Use it for |
|---|---|---|
| `wpss_offline_order_created` | `int $order_id, object $order` | An order was placed with the Offline method and is waiting for payment. |
| `wpss_order_created` | `int $order_id, array $order_data` | Any order, on any checkout. |

Send the order to Make, Zapier or your own service:

```php
add_action( 'wpss_offline_order_created', function ( int $order_id, $order ) {
	wp_remote_post( 'https://hook.eu1.make.com/your-webhook-id', array(
		'timeout' => 10,
		'body'    => array(
			'order_id' => $order_id,
			'number'   => $order->order_number,
			'total'    => $order->total,
			'currency' => $order->currency,
			'buyer_id' => $order->customer_id,
		),
	) );
}, 10, 2 );
```

To read orders instead of pushing them, call `GET /wp-json/wpss/v1/orders` with an administrator's application password.

## 2. Tell the plugin the order is paid

```
POST /wp-json/wpss/v1/orders/{id}/mark-paid
```

| Field | Required | Meaning |
|---|---|---|
| `transaction_id` | No | The provider's reference. Stored on the order. Generated when empty. |
| `payment_method` | No | A slug such as `flutterwave`. Stored on the order. Defaults to `offline`. A gateway the plugin runs itself (`stripe`, `paypal`) is refused, because that gateway records its own payments. |

Only an administrator can call it. Use an [application password](rest-api-overview.md) and HTTP Basic authentication, which Make and Zapier both support.

```bash
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -X POST "https://example.com/wp-json/wpss/v1/orders/123/mark-paid" \
  -d "transaction_id=FLW-98765" -d "payment_method=flutterwave"
```

```json
{ "order_id": 123, "status": "pending_requirements", "payment_status": "paid", "already_paid": false }
```

This does exactly what the **Mark as Paid** button on the admin order screen does. The vendor is credited, the order moves on, and the usual emails and hooks fire.

| Answer | When |
|---|---|
| `200` | The order is now paid. |
| `200` with `already_paid: true` | It was paid already. A retry changes nothing, so your automation can repeat the call safely. |
| `400` | `payment_method` names a gateway the plugin runs itself. |
| `401` / `403` | Not signed in, or not an administrator. |
| `404` | No such order. |
| `409` | The order is not waiting for payment, for example it was cancelled. |

A phase, tip or extension waiting for payment can be settled the same way, using its own order ID.

## 3. React to the payment

| Hook | Arguments |
|---|---|
| `wpss_order_paid` | `int $order_id, string $transaction_id` |
| `wpss_order_status_changed` | `int $order_id, string $new_status, string $old_status` |
| `wpss_payment_for_closed_order` | `int $order_id, string $transaction_id, string $payment_method` |

`wpss_payment_for_closed_order` fires when a payment arrives for an order that was already cancelled or refunded. The plugin refuses to reopen it, so the owner can refund the money.

## What this is not

It does not check the payment itself. Whoever calls the route is saying the money arrived, so keep the application password to the automation that verified it.
