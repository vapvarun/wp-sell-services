# Packet H-1 — Dashicons / inline-SVG → Lucide mapping

Reference for the language-audit + QA packets. Every dashicon class that
appeared in the 1.1.0 codebase is listed with the Lucide name we chose, plus
the reasoning for any substitution that wasn't a 1:1 match.

## Packet-plan canonical mappings (must not change)

These are the icons the 1.1.0 vocabulary depends on — every receipt, timeline,
modal, and ledger is wired to these names.

| Surface meaning             | Lucide name        | Notes                                    |
|-----------------------------|--------------------|------------------------------------------|
| Tip heart                   | `heart`            | Tip receipts, tip-view header            |
| Extension clock             | `clock-alert`      | Extension receipts, timeline extension row |
| Milestone flag              | `flag`             | Milestone receipts, timeline milestone row |
| Lock (locked phase)         | `lock`             | Locked milestone phase card              |
| Approve / check             | `check-circle-2`   | Approve button, completed badge          |
| Project-complete banner     | `party-popper`     | Order-complete celebration banner        |
| Send / deliver              | `send`             | Deliver / send-message actions           |
| Decline / x                 | `x-circle`         | Decline button, error close              |
| View                        | `eye`              | View / preview action                    |
| Edit                        | `pencil`           | Edit action                              |
| Delete                      | `trash-2`          | Delete action                            |
| Download CSV                | `download`         | CSV export, file download                |
| Upload / withdraw           | `upload`           | Request withdrawal, upload file          |
| Chat / message              | `message-square`   | Conversation, message thread             |
| Video intro                 | `video`            | Vendor intro video                       |
| Wallet                      | `wallet`           | Wallet section heading                   |
| Commission / money          | `banknote`         | Earnings, commission, money              |
| Analytics                   | `chart-column`     | Analytics page heading                   |
| Calendar / due date         | `calendar-clock`   | Deadline reminder, calendar-alt          |
| Info                        | `info`             | Help, info badges                        |
| Warning                     | `triangle-alert`   | Warnings, alerts                         |

## Dashicon → Lucide substitutions

Dashicon classes that didn't have a direct entry in the canonical list above.

| Dashicon                     | Lucide             | Reason                                   |
|------------------------------|--------------------|------------------------------------------|
| `dashicons-yes`              | `check`            | Simpler check glyph (non-circled)        |
| `dashicons-yes-alt`          | `check-circle-2`   | Matches approve flow                     |
| `dashicons-no`               | `x`                | Plain close / no                         |
| `dashicons-no-alt`           | `x-circle`         | Close button in chrome                   |
| `dashicons-dismiss`          | `x-circle`         | Cancellation                             |
| `dashicons-search`           | `search`           | 1:1                                      |
| `dashicons-megaphone`        | `megaphone`        | 1:1                                      |
| `dashicons-money-alt`        | `banknote`         | Matches canonical money mapping          |
| `dashicons-calendar-alt`     | `calendar-clock`   | Matches canonical due-date mapping       |
| `dashicons-calendar`         | `calendar`         | Plain calendar (non-due-date)            |
| `dashicons-clock`            | `clock-alert`      | Canonical extension-clock mapping        |
| `dashicons-format-chat`      | `message-square`   | Matches canonical chat mapping           |
| `dashicons-format-image`     | `image`            | 1:1                                      |
| `dashicons-format-gallery`   | `images`           | Plural (Lucide's multi-image variant)    |
| `dashicons-arrow-left-alt2`  | `chevron-left`     | Small chevron, not a full arrow          |
| `dashicons-arrow-right-alt2` | `chevron-right`    | Small chevron, not a full arrow          |
| `dashicons-arrow-down-alt2`  | `chevron-down`     | Small chevron                            |
| `dashicons-cart`             | `shopping-cart`    | Full cart name                           |
| `dashicons-cloud`            | `upload-cloud`     | Save / publish button in wizard          |
| `dashicons-update`           | `refresh-cw`       | Update / refresh                         |
| `dashicons-backup`           | `undo`             | Revision request (undo semantics)        |
| `dashicons-awards`           | `party-popper`     | Order-completed celebration              |
| `dashicons-email`            | `mail`             | New-message icon                         |
| `dashicons-star-filled`      | `star`             | Lucide star is filled when used alone    |
| `dashicons-marker`           | `circle`           | Checklist "not yet done" state           |
| `dashicons-businessman`      | `briefcase`        | Proposal-received (business role)        |
| `dashicons-thumbs-up`        | `thumbs-up`        | 1:1                                      |
| `dashicons-thumbs-down`      | `thumbs-down`      | 1:1                                      |
| `dashicons-bank`             | `landmark`         | Bank/withdrawal — Lucide's bank icon     |
| `dashicons-menu`             | `grip-vertical`    | Drag handle (sortable list)              |
| `dashicons-performance`      | `gauge`            | Number-field icon                        |
| `dashicons-editor-textcolor` | `type`             | Text-field icon                          |
| `dashicons-editor-paragraph` | `align-left`       | Textarea-field icon                      |
| `dashicons-editor-help`      | `help-circle`      | FAQ icon                                 |
| `dashicons-info`             | `info`             | 1:1                                      |
| `dashicons-warning`          | `triangle-alert`   | Matches canonical warning mapping        |
| `dashicons-plus-alt`         | `plus-circle`      | Extras / add-on button                   |
| `dashicons-plus-alt2`        | `plus`             | Inline "add one more" (non-circled)      |
| `dashicons-list-view`        | `list`             | 1:1                                      |
| `dashicons-grid-view`        | `layout-grid`      | 1:1                                      |
| `dashicons-visibility`       | `eye`              | Matches canonical view mapping           |
| `dashicons-hidden`           | `eye-off`          | 1:1                                      |
| `dashicons-category`         | `folder`           | Category / taxonomy                      |
| `dashicons-tag`              | `tag`              | 1:1                                      |
| `dashicons-admin-tools`      | `wrench`           | Integrations / tools                     |
| `dashicons-admin-generic`    | `settings`         | Settings cog                             |
| `dashicons-admin-links`      | `link`             | 1:1                                      |
| `dashicons-admin-page`       | `file-text`        | Document icon                            |
| `dashicons-admin-plugins`    | `plug`             | 1:1                                      |
| `dashicons-dashboard`        | `layout-dashboard` | 1:1                                      |
| `dashicons-store`            | `store`            | 1:1                                      |
| `dashicons-bell`             | `bell`             | 1:1                                      |
| `dashicons-lightbulb`        | `lightbulb`        | 1:1                                      |
| `dashicons-edit`             | `pencil`           | Matches canonical edit mapping           |
| `dashicons-trash`            | `trash-2`          | Matches canonical delete mapping         |
| `dashicons-upload`           | `upload`           | 1:1                                      |
| `dashicons-download`         | `download`         | 1:1                                      |
| `dashicons-heart`            | `heart`            | 1:1                                      |
| `dashicons-flag`             | `flag`             | 1:1                                      |
| `dashicons-saved`            | `check`            | Resolved / saved                         |
| `dashicons-superhero`        | `shield`           | Admin/vendor-protection icon             |
| `dashicons-chart-line`       | `chart-line`       | 1:1                                      |
| `dashicons-chart-area`       | `chart-area`       | 1:1                                      |
| `dashicons-chart-bar`        | `chart-column`     | Matches canonical analytics mapping      |
| `dashicons-chart-pie`        | `chart-pie`        | 1:1                                      |
| `dashicons-groups`           | `users`            | 1:1                                      |
| `dashicons-paperclip`        | `paperclip`        | 1:1                                      |
| `dashicons-media-default`    | `file`             | Generic attachment                       |
| `dashicons-move`             | `move`             | 1:1                                      |

## Inline-SVG → Lucide substitutions

SVG paths we were inlining (1.1.0 receipts, order-view, dashboards, etc.).

| Source location                        | Lucide name      |
|----------------------------------------|------------------|
| `tip-view.php` header heart SVG        | `heart`          |
| `extension-view.php` header clock SVG  | `clock-alert`    |
| `milestone-view.php` header flag SVG   | `flag`           |
| `order-view.php` back arrow            | `arrow-left`     |
| `order-view.php` close (X)             | `x`              |
| `order-view.php` download              | `download`       |
| `order-view.php` upload                | `upload`         |
| `order-view.php` send                  | `send`           |
| `order-view.php` message square        | `message-square` |
| `order-view.php` attach paperclip      | `paperclip`      |
| `order-view.php` info / warning        | `info` / `triangle-alert` |
| `order-view.php` file                  | `file-text`      |
| `order-view.php` party banner          | `party-popper`   |
| `order-view.php` check-circle          | `check-circle-2` |
| `order-view.php` lock                  | `lock`           |
| `order-view.php` trash                 | `trash-2`        |
| `order-view.php` external link         | `external-link`  |
| `order-view.php` eye                   | `eye`            |
| `order-view.php` loader spinner        | `loader-2`       |
| `order-view.php` heart (tip CTA)       | `heart`          |
| `order-view.php` clock (timeline row)  | `clock`          |
| `order-view.php` star (filled)         | `star`           |
| `order-view.php` chevron-down          | `chevron-down`   |
| `order-view.php` clipboard-check       | `clipboard-check`|
| `order-view.php` copy                  | `copy`           |
| `sales.php` empty-state receipt / book | `banknote`       |
| `orders.php` empty-state bag           | `shopping-bag`   |
| `requests.php` empty megaphone         | `megaphone`      |
| `messages.php` two-bubble chat         | `messages-square`|
| `messages.php` grid-app                | `layout-grid`    |
| `create-request.php` megaphone         | `megaphone`      |
| `edit-request.php` save (floppy)       | `save`           |
| `services.php` save                    | `save`           |
| `vendor/profile.php` verified badge    | `badge-check`    |
| `Frontend.php` mini-cart               | `shopping-cart`  |
| `UnifiedDashboard.php` login-prompt    | `user`           |
| `UnifiedDashboard::render_icon('chat')`     | `message-square` (alias) |
| `UnifiedDashboard::render_icon('receipt')`  | `banknote` (alias)       |
| `UnifiedDashboard::render_icon('awards')`   | `award` (alias)          |
| `UnifiedDashboard::render_icon('chart-bar')`| `chart-column` (alias)   |
| `SureCartAccountProvider.php` empty    | `package`        |
| `SureCartAccountProvider.php` back-link| `arrow-left`     |

## Left untouched (intentionally)

These SVGs are NOT icons; they are product illustrations / logomarks.
Removing them would break the visual.

| File                                                              | Reason                         |
|-------------------------------------------------------------------|--------------------------------|
| `wp-sell-services/src/PostTypes/ServicePostType.php` (menu_icon) | WP `add_menu_page` accepts a data-URL SVG. Uses Lucide `shopping-cart` glyph packed in base64. |
| `wp-sell-services/src/Admin/Admin.php` (menu_icon)               | Same — Lucide `store` glyph as base64 data URL. |
| `wp-sell-services-pro/templates/dashboard/sections/stripe-connect.php` | Stripe logomark illustration (not an icon). Comment-tagged `keep: illustration, not icon`. |

## Fallback for dashicons the dashboard cannot render

`src/Models/Notification.php::get_icon_class()` now returns a plain Lucide
name (e.g. `shopping-cart`) — not a `dashicons-…` class. Any existing REST
consumer that stores the old `dashicons-…` string on a row will still render
(lucide createIcons tolerates unknown names silently), but the UI layer
should migrate to rendering the new name as `<i data-lucide="…">`.

## Theme-override surface

To rescale or retint every icon at once:

```css
:root {
    --wpss-icon-size: 28px;
    --wpss-icon-stroke: 1.5;
    color: #d946ef;
}
```

The tokens live in `assets/css/design-system.css`. A test override page
is at `docs/qa/token-preview.html`.
