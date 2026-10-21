# Journey 05 - Vendor creates and publishes a service; standing limits what they can start

**Role:** vendor, then administrator
**Rail:** standalone, woo
**Status:** rewritten 2026-10-18; walk pending (see .qa-results/walks.json)

Guards cards 10208080926, 10208086929, 10212521285, 10212520711.

## Why this one matters

Four separate defects lived in this one screen, and **three of them were
invisible on a default install** because the demo seeder built only flat
categories. The wizard's subcategory dropdown, `getSubcategories()`, the archive
optgroup rendering and every caller of `wpss_group_category_terms()` only
execute when a child term exists — so a local QA pass walked straight past all
of them and the bug reached a buyer.

The fourth was worse in a different way: the site's own administrator could not
open the wizard at all, because two independent service-limit gates both ran
without a capability check.

The lesson worth keeping: **demo data is test coverage.** A code path that
seeded data never reaches is a path nobody has run.

## Preconditions

A category hierarchy must exist. `wp wpss demo marketplace` now seeds one
(Logo Design / Banner Design under Graphics & Design; WordPress Development /
Mobile Apps under Programming & Tech). Confirm before starting:

```
wp term list wpss_service_category --fields=term_id,name,parent
```

At least one row must have a non-zero `parent`. If none do, the seeder did not
run and steps 2 and 6 cannot fail — which is exactly how this shipped.

## Steps

### 1. The wizard opens for a vendor
Visit `/dashboard/create/` as a vendor.

**Expect**
- The wizard renders: `#wpss-service-wizard` exists, step `basic` is visible.
- No "You have reached your service limit" anywhere on the page.

### 2. Category offers parents only
Open the **Category** dropdown.

**Expect**
- Only top-level categories. **Zero** child terms, and no `—`-prefixed options.
- `window.wpssCategories` still contains the children —
  `wpssCategories.filter(c => c.parent > 0).length > 0`.

Both halves matter. The dropdown must not offer children, and the JS data must
still carry them: filtering the query to `parent => 0` satisfies the first and
silently breaks the second, leaving the Subcategory dropdown permanently empty.

### 3. Subcategory follows the parent
Select a parent that has children.

**Expect** the Subcategory dropdown enables and lists exactly that parent's
children. Select a parent with none: it offers nothing, and does not error.

### 4. The Review checklist tracks state live
Fill title (10+ chars), category, description (120+ chars). Go to **Review**.

**Expect** those three rows show a check-circle icon, not an empty circle.

### 5. The two rows that used to lie
Still on Review, go back and set a valid Basic price and delivery time, then a
main image. Return to Review.

**Expect**
- "Basic package pricing complete" — check-circle.
- "Main image uploaded" — check-circle.

Then clear the price. **Expect** that row reverts to an empty circle without a
page reload; re-enter it and it checks again.

Read the rendered `<svg>` class, not the `data-lucide` attribute. The original
defect was that the attribute flipped correctly and the drawn icon never
changed — the DOM looked right while the screen was wrong.

### 6. Publish, then check where it landed
Publish the service. Visit `/services/` and open the category filter.

**Expect** the child term appears **nested under its parent** in the sidebar category list (indented, smaller type, link inside `.wpss-subcategory-list`), not flat between top-level categories. In the Service Grid block filter and the request forms it sits inside an `<optgroup>` labelled with the parent. Selecting it filters to services in that child.

### 7. The administrator is not a subscriber
Log in as the site administrator — one who already owns more services than any
configured limit allows — and visit `/dashboard/create/`.

**Expect** the wizard renders. Not "You have reached your service limit. Please
upgrade your subscription plan."

Then `POST /wpss/v1/services` as that administrator.

**Expect** anything other than `403 wpss_service_limit_reached`. A `400` for a
missing required field is a pass — it means the limit gate was cleared and
ordinary validation took over.

### 8. A real vendor is still metered
With a service limit configured, take a **non-admin** vendor who is at their
limit and repeat step 7.

**Expect** the block. The exemption is for site owners; if this step passes, the
limits are off for everybody and that is a worse bug than the one being guarded.

## Notes for whoever runs this

- Test the **filter**, not the class. `wpss_vendor_can_create_service` has two
  gates on it — this plugin's per-profile maximum at priority 10 and Pro's plan
  enforcer at priority 20. `PlanEnforcer::can_create_service()` can answer
  `true` while the filter still answers `false`.
- 390px on every step that renders UI.

## Admin moderation doors (added 2026-10-18)

### M1. Quick Edit never changes moderation (card:10398957993, card:10398959570)
With service moderation on, Services > Quick Edit on an approved, live service: change only the title, Update.

**Expect** the service stays published and Approved; the Moderation column is unchanged; there is no Moderation dropdown in Quick Edit and core's Status dropdown is visible. On Buyer Requests Quick Edit the Status dropdown is visible too.

### M2. An admin Publish is the approval (card:10398958911)
Open a Pending service in the editor and click Publish (also: Quick Edit > Status Published).

**Expect** the Moderation column says Approved, the Pending count drops, the service shows on /services/, and the vendor gets the approved email.

### M3. Skip to full editor saves a draft (card:10398686546)
New Service wizard (admin), fill step 1, click Skip to full editor.

**Expect** the service is a draft in the normal tabbed editor, nothing appears on /services/ until Publish is clicked.


# Part 2 - account standing (was journey 12)

## Steps

### 1. Vendor cannot pause their own order (card:10399370449)
As the vendor, open an in-progress order, then `POST /orders/{id}/hold`.

**Expect** no Hold button anywhere, the route refuses with "Only an administrator can put an order on hold", the status is unchanged. As admin both hold and resume work.

### 2. Suspend a vendor with an open paid order (card:10384354109)
Admin sets the vendor to Suspended. As the vendor open the dashboard.

**Expect** a Selling > Sales Orders item and nothing else under Selling; the open order can be delivered and messaged inside; My Services, Proposals, Portfolio and Earnings are not reachable by URL.

### 3. Suspended vendor starts nothing new (card:10384354109)
As the suspended vendor submit a proposal (form and `POST /requests/{id}/proposals`), use Contact vendor on another vendor's service, send a message in a pre-sale thread.

**Expect** each is refused with a plain message ("Your vendor account is suspended" or "Your account is suspended"), nothing is created, no notification reaches the other person.

### 4. Plugin menu is not offered to vendors (card:10379867128)
As a vendor who also has `edit_posts`, open wp-admin.

**Expect** no WP Sell Services menu item and no 403 page reachable from the admin bar or menu.


**Doors to assert together** (the REST twin and the AJAX door must agree): dashboard publish, bulk publish, wizard draft and publish, portfolio add and update, REST services and proposals. Guard: `tests/test-ajax-standing-gate.php`.

### 5. Each tier has its own description (card:10244588840)
In the wizard Pricing step, switch between Basic, Standard and Premium.

**Expect** every tier has a Tier description field that saves and shows under the tier on the service page; leaving it empty is allowed.

### 6. A refused publish says why (card:10399161587)
Publish an incomplete service from the editor and from the list.

**Expect** the service stays a draft and an explicit notice names what is missing, visible only to someone who may edit that service.

### 7. My Services status tiles (card:10403854262)
Open Dashboard > My Services.

**Expect** the All, Active, Drafts and Pending Review tiles are links; selecting one lists only that status and the count matches the list; at 390px they wrap without horizontal scroll.

### A refused file stops a delivery (vendor sweep 1.9.0)
As the vendor deliver an order with an .exe attached.

**Expect** an error naming the file and no delivery sent; in a message, a refused attachment shows a notice. Guard: `static:delivery-refuses-bad-file`.
