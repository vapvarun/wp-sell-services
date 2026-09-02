# Role matrix

Verified 2026-08-17 against a live site (`wp_roles()`), not transcribed from
code comments. Updated 2026-09-02 for the 1.7.1 capability split.

## Roles and WPSS capabilities

| Role | Slug | WPSS capabilities |
|---|---|---|
| Administrator | `administrator` | `wpss_manage_disputes`, `wpss_manage_orders`, `wpss_manage_services`, `wpss_manage_settings`, `wpss_manage_vendors`, `wpss_respond_to_requests`, `wpss_vendor`, `wpss_vendor_orders`, `wpss_view_analytics`, plus every `*_wpss_services` cap |
| Shop manager | `shop_manager` | `wpss_manage_orders`, `wpss_manage_services`, `wpss_respond_to_requests`, `wpss_vendor`, `wpss_vendor_orders`, `wpss_view_analytics`, plus every `*_wpss_services` cap |
| Vendor | `wpss_vendor` | `wpss_manage_services`, `wpss_respond_to_requests`, `wpss_vendor`, `wpss_vendor_orders`, `wpss_view_analytics`, `edit_wpss_services`, `edit_published_wpss_services`, `publish_wpss_services`, `delete_wpss_services`, `delete_published_wpss_services` |
| Author | `author` | **none** (held the whole vendor set up to 1.7.0; stripped on upgrade) |
| Buyer | `subscriber` | **none** |

Roles are granted in `Activator::create_roles()`. Services use their own
capability type (`wpss_service`), so `edit_posts` no longer opens the service
editor and vendors no longer receive `edit_posts`.

## `wpss_manage_orders` is admin-side, `wpss_vendor_orders` is vendor-side

`OrderService::can_transition()` lets `manage_options` or `wpss_manage_orders`
force any status. Vendors hold `wpss_vendor_orders` only, so they are bound to
the natural transition map. Dashboard order verbs go through
`wpss_order_actor_role()` plus the allow map in `AjaxHandlers::order_action()`:
refund is admin-only (vendor when `wpss_orders[allow_vendor_refunds]` is on),
cancel is buyer-before-work or admin, start and the cancellation replies are
vendor-only.

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

`wpss_is_vendor()` is the shared check. Since 1.7.1 it is true only for a user
with an **active** `wpss_vendor_profiles` row. The role, the `wpss_vendor`
capability and the legacy `_wpss_is_vendor` meta are hints, not proof: a role
handed out in wp-admin with no profile behind it is not a vendor, and the
dashboard shows that user the same "Start selling" panel a subscriber gets.
`wpss_vendor_status_block()` refuses a no-row user with `wpss_not_vendor`.

## Admin holds `wpss_vendor` but is not automatically a seller

Administrators carry `wpss_vendor`, but `wpss_is_vendor( $admin_id )` is only
true once they have an active profile row. Anywhere "is this the seller on
this order?" matters, compare `vendor_id` — do not infer it from the
capability.

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
