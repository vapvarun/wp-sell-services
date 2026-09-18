# Payment / Checkout Architecture — R&D + Target Design

**Question that triggered this:** *"Why does it matter if the cart has 1 or many
items? A gateway should just charge the total value + buyer address, like
WooCommerce. We'll add more gateways/integrations later — we don't want to keep
duplicating this per gateway."*

**Answer: you're right.** The gateway should charge ONE server-computed total +
buyer. Item count, add-ons, multi-vendor splitting, and commission are the
*platform's* concern, resolved server-side — never the gateway's, and never
passed piecemeal through gateway JS. The current code violates this, which is
the root cause of the whole P0 payments batch (#287, #358, #362046, and the
multi-cart client-amount security hole).

---

## 1. What we have today (verified in code)

### The gateway contract is already correct
`Integrations/Contracts/PaymentGatewayInterface.php`:
```php
public function create_payment( float $amount, string $currency, array $metadata = [] ): array;
```
Amount-centric. Good. The gateways themselves are fine.

### Order creation + commission is already centralized
`StandaloneOrderProvider`:
- `create_order( $order_data )` → `CommissionService::compute_breakdown()` (per-order fee/earnings).
- `create_orders_from_cart( $cart, $method, $txn, $customer )` → loops `create_order` per cart line (multi-vendor split).

Good. This is the right place and it already exists.

### The problem is the MIDDLE layer, duplicated per gateway
Each gateway re-implements "resolve what to charge + what to create":

| Concern | Stripe | PayPal | Razorpay |
|---|---|---|---|
| own `ajax_create_*` intent handler | ✅ | ✅ | ✅ |
| single service+package pricing | server | server | server |
| pay_order (proposal/milestone) pricing | ✅ `$order->total` | partial | ✅ |
| add-ons | ✅ `addon_ids` | ❌ (#358) | ❌ (#362046) |
| multi-cart | trusts **client `$_POST['amount']`** ⚠️ | ❌ | flag only |
| own JS gathering `service_id/package_id/addon_ids/pay_order/is_multi_checkout` | 5 fields | **2 fields** | 4 fields |
| own call to `create_order` / `create_orders_from_cart` on success | ✅ (L839/1083) | ✅ (L642/909) | ✅ |

Every gateway copy-pastes this and **drifts** — PayPal missing 3 fields, Razorpay
missing add-ons, multi-cart trusting the browser's total. A new gateway =
re-implement all of it and miss some = new bugs. This is exactly the dup you
want to stop.

### The cart is server-side (`CartController`)
So an authoritative total CAN be computed server-side. Today the multi path
ignores it and trusts the client amount instead.

---

## 2. Target design — one gateway-/integration-agnostic seam

Introduce a **CheckoutIntent** value object + **CheckoutIntentService** (resolve
+ settle) in the FREE core, used by every gateway (free + Pro) and every future
integration.

```
CheckoutIntent {
  amount     : float     // SERVER-computed, authoritative (never from client)
  currency   : string
  buyer_id   : int
  kind       : 'cart' | 'order'   // cart = new purchase(s); order = pay existing
  order_id   : ?int      // when kind = 'order' (proposal / milestone / tip / extension)
  cart       : ?array    // when kind = 'cart' (line items for order creation)
  metadata   : array     // passthrough for the gateway (customer id, description)
}
```

### `CheckoutIntentService::resolve( $request ): CheckoutIntent`
The **single** place that decides the amount. Reads the server-side cart, OR a
`pay_order` id, OR a single service+package+addons — and computes the total
server-side. Replaces the per-gateway single/multi/pay_order/addon branching.
No `is_multi_checkout`, no client amount. One item or fifty — same code path;
the total is just the sum.

### `CheckoutIntentService::settle( $intent, $gateway_id, $transaction_id ): array`
The **single** place that turns a successful charge into order(s):
- `kind = 'cart'` → `create_orders_from_cart(...)` (existing) → clear cart.
- `kind = 'order'` → mark the existing order paid + settle the ledger (existing).

Gateway-agnostic, runs once. Replaces each gateway's copy of the create-order
calls.

### Gateways become thin drivers
A gateway implements only:
- `create_payment( amount, currency, metadata )` — already in the interface,
- its own **UI** (mount Stripe Element / render PayPal buttons / open Razorpay),
- `confirm/webhook` → calls `CheckoutIntentService::settle()`.

It does **not** know about services, packages, add-ons, carts, or multi. Adding
a gateway = ~1 class, zero checkout logic. Adding a checkout *context* (a new
pay_order sub-type, subscriptions, etc.) = extend `resolve()` once; every gateway
gets it for free.

### JS shrinks too
One shared `wpss-checkout.js` posts to a shared `wpss_checkout_create_intent`
(no per-item fields — the server resolves from the cart/session). Gateway JS
handles only its own widget. Kills the paypal.js/razorpay.js field-drift class.

---

## 3. How this fixes the P0 batch at the ROOT (not patches)

| Card | Card's suggested patch | Root fix via this design |
|---|---|---|
| #287 Stripe multi omits `is_multi_checkout` | add the flag to JS | no flag exists — `resolve()` sums the cart server-side |
| #358 PayPal missing pay_order/addons/multi | add 3 fields to paypal.js | PayPal calls `resolve()` → gets all contexts free |
| #362046 Razorpay ignores addons/pay_order | add fields to razorpay.js | same — free via `resolve()` |
| (implicit) multi trusts client `$_POST['amount']` | — | **security fix**: amount is server-authoritative |

Four bugs + a security hole collapse into "route every gateway through
resolve()/settle()."

---

## 4. Migration plan (money-critical → phased + verified each step)

1. **Add `CheckoutIntentService` (resolve + settle)** — pure addition, no gateway
   touched. Unit-test the pricing (single, addons, multi, pay_order) against the
   existing per-gateway logic to prove parity.
2. **Refactor Stripe** to `resolve()`/`settle()`. Verify end-to-end with test
   cards: single, single+addons, 2-item multi, pay_order (milestone). This also
   fixes #287 and closes the client-amount hole. Confirm displayed = charged =
   order row(s).
3. **Refactor PayPal + Razorpay** to the same seam → #358 + #362046 fixed for
   free. Verify each.
4. *(optional)* Collapse the per-gateway `ajax_create_*` into one
   `CheckoutController` + slim `wpss-checkout.js`; gateways register a driver, not
   AJAX endpoints.

Each phase is money-verified before the next; nothing ships to the charge path
unverified.

**Not in scope of this seam** (tracked separately): the *non-payment* P0s
(#616 admin $0 packages, #650 requirements schema, #693 category seeding,
#011 first-run gateway) and the Pro subscription cluster (#361581/#361530/
#360454 — recurring-billing correctness, a different Stripe API surface).
