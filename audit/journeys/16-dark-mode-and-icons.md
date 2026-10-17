# Journey 16 - Dark mode and JS-inserted icons

**Roles:** buyer, seller
**Rail:** any
**Themes:** Reign, BuddyX, BuddyX Pro (light and dark), Twenty Twenty-Five
**Status:** written 2026-10-18; walk pending

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
