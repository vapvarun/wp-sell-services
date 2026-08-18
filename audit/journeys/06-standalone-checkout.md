# Journey 06 — Paying on the standalone rail

**Role:** logged-out visitor, then buyer, then administrator
**Status:** passing as of commits `7deda27`, `345cc0c`, `9b1acd0`, `e13921f`

Guards cards 10208392848, 10208094640, 10163575694.

## Why this one matters

Checkout is the one screen where a defect costs money directly, and the three it
has produced were each invisible from the code alone:

- **The page rendered full-bleed** on any theme that opens its content wrapper
  in a page template rather than `header.php`. That includes stock Twenty
  Twenty-Four. On BuddyX free it looked fine — by luck, not design — so reading
  the template told you nothing.
- **Offline pay-order never recorded `payment_method`**, which left an order no
  admin could confirm and no buyer could send proof for. Every other gateway
  writes that column as a side effect of `mark_as_paid()`; this was the one path
  that did not settle, so it was the one path that had to write it itself.
- **A sign-in wall stood between a buyer and paying**, on a marketplace whose
  owner wanted neither.

## Preconditions

- `wpss_general['ecommerce_platform']` is `standalone`.
- The offline gateway is enabled with instruction text set.
- Run step 1 on **two themes**: one that opens a container in `header.php`
  (BuddyX free) and one that does not (Twenty Twenty-Four). The bug only exists
  on the second kind.

## Steps

### 1. The page is contained
Open `/service-checkout/{service_id}/?package=0`.

**Expect**
- The checkout content is **not** the full width of the viewport.
- On a theme with its own container: our wrapper stands down —
  `[data-wpss-auto-container]` computes `max-width: none` and zero horizontal
  padding, and the content is as wide as the theme allows. No double gutter.
- On a theme without one: our wrapper binds at `--wpss-container-max` and
  centres.
- Both: `document.documentElement.scrollWidth === clientWidth`.

Measure it. "Looks about right" is what let this through the first time.

### 2. 390px
Same URL at 390px.

**Expect**
- No horizontal scroll.
- The guarantees bar has **wrapped** onto more than one row rather than forcing
  the page wider than the screen. A buyer must never have to pan sideways to
  read the total.

### 3. A logged-out buyer can reach payment
Log out. Open the same URL.

**Expect** — with `checkout_account_creation` **on** — the form, not a sign-in
wall. With it **off**, the wall is correct; that is the default.

### 4. The account is created from what was already typed
Fill the billing fields and pay.

**Expect**
- The order is created and attributed to a real user — never `customer_id = 0`.
- The buyer is signed in afterwards, and the **payment succeeds in the same
  request**. A "Security check failed." here means the session token was not
  established before the nonce was minted.

### 5. Offline records which method was chosen
Choose offline payment on an order reached through `?pay_order={id}` — an
accepted proposal is the realistic route.

**Expect**
- `wpss_orders.payment_method` is `offline`. **Not NULL.**
- The order view shows the payment instructions.
- The buyer sees the proof-of-payment upload.
- The admin order screen shows "Awaiting Confirmation" with **Mark as Paid**.

All four read the same column. If it is NULL the order is a dead end for
everyone, which is what made this a fatal-class defect rather than a cosmetic
one.

### 6. Admin confirms, vendor is credited once
Mark the order paid.

**Expect** the vendor credited exactly once, through
`StandaloneOrderProvider::mark_as_paid()`. Repeat the action: no second credit.

### 7. The other rail is untouched
Open a checkout reached through the mapped `service-checkout` **page** rather
than the `/service-checkout/{id}/` rewrite.

**Expect** it renders contained on both themes. It goes through the theme's
normal page flow and never touched the renderer this journey exercises — which
is worth confirming precisely because it is easy to assume one fix covered both.

## Notes for whoever runs this

- The container behaviour is decided in JS on load. If you disable JavaScript,
  the wrapper stays constrained; that is the safe direction and not a failure.
- `?pay_order={id}` and `/service-checkout/{id}/` are **different routes** with
  different renderers. A pass on one says nothing about the other.
