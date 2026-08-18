# Role matrix

Verified 2026-08-17 against a live site (`wp_roles()`), not transcribed from
code comments.

## Roles and WPSS capabilities

| Role | Slug | WPSS capabilities |
|---|---|---|
| Administrator | `administrator` | `wpss_manage_disputes`, `wpss_manage_orders`, `wpss_manage_services`, `wpss_manage_settings`, `wpss_manage_vendors`, `wpss_respond_to_requests`, `wpss_vendor`, `wpss_view_analytics` |
| Vendor | `wpss_vendor` | `wpss_manage_orders`, `wpss_manage_services`, `wpss_respond_to_requests`, `wpss_vendor`, `wpss_view_analytics` |
| Buyer | `subscriber` | **none** |

Roles are granted in `Activator::create_roles()`.

## The thing to understand about buyers

**A buyer holds no WPSS capability at all.** A buyer is a plain WordPress
subscriber. Everything a buyer is allowed to do is allowed because they are a
*participant* in the record — their order, their dispute, their request — never
because of a capability.

So any gate phrased "can the buyer do X" must be an **ownership check**:

```php
// Correct: participant check.
wpss_user_can_view_order( $order, $user_id );

// Wrong for buyers: they will never have a wpss_* cap.
current_user_can( 'wpss_manage_orders' );
```

`wpss_user_can_view_order()` is the correct shared check. There are roughly
twenty hand-rolled variants of the same logic scattered around the codebase;
they are the reason participant checks drift. Prefer the shared one, and when
you touch a hand-rolled variant, replace it.

## Vendor is additive, not exclusive

`wpss_vendor` is granted **on top of** the user's existing role — a vendor is
still a buyer. Two consequences that have caused real bugs:

- Dashboards must be role-*aware*, not role-*switched*: a vendor has both Buying
  and Selling sections. `wpss_dashboard_default_section` picks the landing tab.
- A seller can hold a request of their own. Guard against self-dealing
  explicitly — e.g. a seller proposing on their own request notifies nobody.

`wpss_is_vendor()` is the shared check. It reads the capability first, then
falls back to the role, then to legacy `_wpss_is_vendor` meta. Do not read the
meta key directly; it is the least reliable of the three and was the source of
role-granted vendors 404ing on `GET /vendors/{id}`.

## Admin holds `wpss_vendor` too

Administrators carry `wpss_vendor`, so `wpss_is_vendor( $admin_id )` is
**true**. Anywhere "is this the seller on this order?" matters, compare
`vendor_id` — do not infer it from the capability.

## Vendor account status is separate from role

Holding the role does not mean the vendor is approved. Status lives in
`wpss_vendor_profiles.status` (`active` / `pending` / `suspended`) and is read
through `wpss_get_vendor_status()`. The legacy `_wpss_vendor_status` user meta
was read in four places and written in none, so every caller fell through to its
own default — the REST API reported every vendor as approved. Use the accessor.

## REST

`wpss_rest_require_login()` and `wpss_rest_require_admin()` in
`src/functions/rest.php` answer **401 vs 403** correctly. A logged-out user gets
401; a logged-in user without rights gets 403 with the single code
`wpss_not_vendor`. Do not hand-roll this per controller.

## Where to check the current truth

```
wp eval 'foreach (wp_roles()->roles as $s => $r) { $c = array_keys(array_filter($r["capabilities"])); print_r([$s, array_values(array_filter($c, fn($x) => strpos($x, "wpss") === 0))]); }'
```
