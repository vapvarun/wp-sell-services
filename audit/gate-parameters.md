# Gate parameters - checks that run on every flow

These were journeys 15 to 19. They are not flows: they are the conditions every flow is walked under.
A flow journey passes only if its steps hold under each parameter that applies to it.

| Parameter | Values | Applies to |
|---|---|---|
| Rail | standalone, woo (EDD and FluentCart are beta) | every flow that takes payment |
| Theme | Reign, BuddyX, BuddyX Pro, Twenty Twenty-Five | every frontend flow |
| Scheme | light, dark | every frontend flow |
| Width | 1440, 390 | every flow that renders UI |
| Direction | LTR, RTL | every frontend flow |
| Site state | fresh install, upgraded from the previous release | install flow and every flow with stored data |
| Time zone | Asia/Kolkata, America/New_York, UTC | any flow with a date or deadline |

Each section below is a set of checks. The settings heading outline is guarded by `tests/test-settings-heading-outline.php`, which renders the Payment Gateways tab on the standalone rail.


---

## Settings: heading outline and contrast

## Steps

### 1. Every Settings tab has a heading outline (card:10412306674, card:10412156185, card:10412156063)
Open each tab (General, Checkout, Pages, Payment Gateways, Commission & Tax, Payouts, Vendor, Orders, Emails, Branding, Integrations, Advanced) with the browser's heading list or a screen reader's headings key.

**Expect** each tab starts with a level-2 heading, every card title is a heading (not a paragraph), sub-sections (Add New Rule, Payout Method, Subscription Plans) are level 3 below it, and no level is skipped. Cards look the same as before (small uppercase titles).

### 2. Helper text and sidebar labels are readable (card:10412155855, card:10412155759)
Settings at 1440 and 390, browser zoom 200%.

**Expect** the grey line under the page title, field descriptions and the sidebar group labels (SETUP, MONEY, MARKETPLACE...) read clearly on the grey admin background; run an axe or Lighthouse contrast check and see none on these classes.

### 3. Add-on note on the service page (card:10384448877)
A service with add-ons, light and dark theme.

**Expect** the "N add-ons available from $X" line passes 4.5:1 in both.


---

## Dark mode and JS-inserted icons

## Steps

### 1. Remove from Favorites is visible in dark (card:10384321636)
Dashboard > Favorites with two favourites, dark mode.

**Expect** the round remove button shows a dark surface with a light X at rest (not a white disc), turns red on hover; same in light.

### 2. Remove attachment shows its X (card:10384209485)
Order conversation, attach a file.

**Expect** a visible X appears at once on the staged file, has a name for screen readers, and removing it works. A file name containing `<b>` shows as text.

### 3. Scrollbars follow the theme (card:10411858243)
Dark theme: Portfolio > add item description, Messages composer, any select in the dashboard modal.

**Expect** the textarea scrollbar and select list are dark, not the light native chrome.

### 4. Pager hover keeps its text (card:10399252183)
Notifications with 40+ rows, hover and tab to Next on each theme, light and dark.

**Expect** the label stays readable (4.5:1) on hover and focus.

### 5. Text buttons in modals are not clipped (card:10408125397)
Pro dashboard > Subscription > Cancel subscription; seller profile > Contact; a service page > Contact; Stripe payouts rail > disconnect.

**Expect** no horizontal scrollbar in the modal, "Keep Subscription" / "Cancel" are full-size text buttons, and clicking them closes the modal; the corner X still closes it too.

### Notification rows read in dark mode (found in the 1.9.0 dark walk)
Buyer with unread notifications, Reign in dark, dashboard Notifications at 1440 and 390; also My Account > Notifications and the admin Notifications page.

**Expect** message and time text on an unread row measure 4.5:1 or better, the list is styled on all three surfaces, and the page source carries no inline style block for the list. check: tests/test-notification-list-style.php

### Meaningful text clears 4.5:1 in both modes (found by the 1.9.0 computed-contrast sweep)
Vendor dashboard (Services, Proposals, Portfolio, Analytics, Earnings) on Reign, light and dark, 1440 and 390.

**Expect** Delete and Withdraw labels, analytics period captions, notification times and wallet credit and debit amounts all measure 4.5:1 or better; none uses the hint token. check: tests/test-text-token-contrast.php


---

## Layout per surface

## Steps

### 1. Checkout sidebar cards do not slide under the summary (card:10411948180)
Standalone checkout at 1440 wide, scroll to the bottom.

**Expect** "About the Seller" and "What happens next?" stay fully readable; the Order Summary never paints over them.

### 2. Buyer-request breadcrumb is one line (card:10384469084)
Open a single buyer request.

**Expect** Home / Requests / title on one row, separators between items.

### 3. Report dialog does not flash (card:10398652904)
Reload a service page and watch the first two seconds on a slow connection.

**Expect** no dialog appears until Report is clicked.

### 4. Deliver dialog clears a sticky header (card:10384248570)
Seller order view, click Deliver on a theme with a sticky header, at 1440 and 390.

**Expect** the whole dialog, including its title and close X, sits below the header.

### 5. Milestone Days input stays in its box (card:10379783717)
Propose with milestones at 390.

**Expect** Amount and Days sit side by side inside the row, no horizontal scroll.

### 6. Empty-state categories are centred (card:10380369422)
New buyer with no orders, Dashboard empty state.

**Expect** the categories block is centred under the message.

### 7. Vacation fields line up (card:10384895523)
Admin > Vendors > a vendor > Vacation Mode.

**Expect** each label sits above its control, same left edge.

### 8. Review search shows its placeholder (card:10384766482)
Admin > Review Moderation.

**Expect** "Search by reviewer or text..." is fully visible at 1440; the field fills the row at 390.

### 9. Keyboard focus is visible on every field (UX rule 14)
Tab through the contact form, the request requirements form, checkout form groups, the block category select and the tip presets.

**Expect** each focused control shows a 3px primary ring, not only a border-colour change. check: tests/test-focus-indicator-css.php

### 10. The order options sheet fits its content on a phone
Service page at 390, tap Continue on a package.

**Expect** the Order Options sheet is as tall as its rows (about 300px), anchored at the bottom, not a full-height white panel. check: tests/test-layout-surface-css.php (modal content height at <=768px is auto)

### 11. A button looks the same on every surface (1.9.0 consolidation)
Vendor dashboard (Services, Portfolio, Proposals), the service page order sheet, checkout and My Account, 1440 and 390, light and dark.

**Expect** Primary, outline, ghost, small and link buttons share one size, weight, border and radius everywhere; `--sm` is visibly smaller than the default; a `--link` button reads as a link, not a boxed button; nothing overflows at 390. check: tests/test-primitive-single-source.php and bin/check-ux-conformance.php (duplicate-primitive)

### 12. A data table is full width and collapsed on every surface (1.9.0 consolidation)

Any `.wpss-table` (vendor earnings, My Account service orders, Pro analytics), 1440 and 390, light and dark.

**Expect** the table is 100% wide with collapsed borders whichever stylesheets the page loads; the dashboard table keeps its bordered, rounded card look. check: tests/test-primitive-single-source.php (no surface sheet redeclares the table base)


---

## Forms: state and feedback

## Steps

### 1. Inactive commission rule stays inactive when edited (card:10407616413)
Pro > Settings > Commission, save a rule with Active unticked, open Edit.

**Expect** the list says Inactive, the Active box is unticked in Edit, and saving without touching it keeps the rule inactive.

### 2. Order status change says what happened (card:10399369371)
Admin > Orders > view an order, change the status and Update; then submit the same status; then a status the order cannot move to.

**Expect** a green "updated" notice, an info "nothing changed" notice, and a red notice explaining the refusal.

### 3. Wizard names the missing delivery time (card:10399020562)
Create a service with a Basic price and no delivery time, Continue.

**Expect** "Please set a delivery time for the Basic package", not a price message.

### 4. Decision note stays on the dispute (card:10398958589)
Admin > Disputes > open one, type a decision note, move it to Pending Review, Update.

**Expect** the note appears in the dispute thread as an Admin message, and the buyer and seller see it in theirs.

### 5. Empty requirement question is caught (card:10398584633)
Wizard > Requirements, add a row and leave the question blank, Continue.

**Expect** "Requirement 1 needs a question, or remove it."

### 6. Add-on rows keep unique ids (card:10398686846)
Services > Add New, add two add-ons, remove the first, add another.

**Expect** no two elements share an id; clicking a label focuses its own field.

### 7. Edit Request has one heading (card:10403821351)
Buyer dashboard > Requests > Edit.

**Expect** one "Edit Request" title and a Back button.

### 8. A portfolio image can be removed (card:10403187415)
Seller dashboard > Portfolio > Add item, pick two images, remove one with its X, Save; then edit the item and remove the last one.

**Expect** the X has an accessible name, the saved item keeps only the remaining image, and an item with no images saves.

### 9. Earnings commission button works (card:10384898745)
Admin > Vendors > a vendor > Earnings, set a rate, Save; then Reset.

**Expect** the status line shows the saved message (not "undefined") and the rate persists; Reset returns to the global rate.

### 10. Push settings save (card:10408271874)
Pro > Settings > Integrations > Push, Save.

**Expect** a success notice, the button re-enables, no console error.

### 11. Header avatar matches the photo the member set (card:10411862427)
Member sets a profile photo; open the site on Reign and BuddyX.

**Expect** the header avatar shows that photo, not the Gravatar.


---

## Time zones

## Steps

### 1. A deadline names its zone (decision 15)
Buyer order view, seller order view, admin order screen, My Account service orders, dashboard order row, a buyer request's expiry.

**Expect** "Oct 21, 2026 3:00 pm (UTC+5:30)" everywhere the same moment appears; everyday times (created, messages, timeline) stay plain.

### 2. The same moment reads the same on every screen
One order across the admin list, admin detail, buyer view, seller view, the email, the REST payload.

**Expect** the same day and time on all of them, in the site's zone.

### 3. A vacation return date does not shift
Seller sets Back on Oct 21 on a New York site.

**Expect** the buyer sees Oct 21 on the service page, the card badge and the notice, never Oct 20.

### 4. A typed date or range is read in the display zone
Admin > Orders date filter, Audit Log range, Earnings range, vendor CSV export week/month; edit a buyer request deadline and save without touching it.

**Expect** the range includes the whole local day; the saved deadline does not move after a save.

### 5. Wallet dates are not the browser's
Seller earnings > wallet ledger, browser set to a different zone than the site.

**Expect** the date matches the site, and an entry made at 11 pm local is on that day.

### 6. REST dates are ISO 8601
GET any `wpss/v1` list with dates.

**Expect** `2026-10-21T09:30:00+00:00` style values, including routes written after this release.

### 7. Cancellation emails use the reader's zone
Request an order cancellation as buyer on a site with a member-zone filter.

**Expect** the vendor and the buyer each see the response deadline in their own zone, labelled.
