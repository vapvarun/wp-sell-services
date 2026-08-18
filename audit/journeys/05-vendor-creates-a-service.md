# Journey 05 — A vendor creates a service

**Role:** vendor, then administrator
**Status:** passing as of commits `afbbbdf`, `c954ed0`, `f8eeb43` / Pro `8500b76`

Guards cards 10208080926, 10208086929, 10212521285, 10212520711.

## Why this one matters

Four separate defects lived in this one screen, and **three of them were
invisible on a default install** because the demo seeder built only flat
categories. The wizard's subcategory dropdown, `getSubcategories()`, the archive
optgroup rendering and every caller of `wpss_group_category_terms()` only
execute when a child term exists — so a local QA pass walked straight past all
of them and the bug reached a customer.

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

**Expect** the child term appears **inside an `<optgroup>` labelled with its
parent**, not flat between top-level categories. Selecting it filters to
services in that child.

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
