# Journey 23 - Blocks, shortcodes, and Pro presentation (cloud storage, display currency, white label, push)

**Roles:** admin, visitor, buyer, vendor
**Rail:** standalone, woo
**Status:** written 2026-11-03; walk pending
**Docs:** docs/website/marketplace-display/gutenberg-blocks.md, marketplace-display/shortcodes-reference.md, cloud-storage/cloud-overview.md, payments-checkout/display-currency.md, platform-settings/white-label.md, notifications-emails/in-app-notifications.md

## Steps

### 1. Every block on a page
In the editor add `wpss/service-grid`, `service-search`, `service-categories`, `featured-services`, `seller-card`, `buyer-requests`; publish; view logged out at 1280 and 390, on a block theme too.

**Expect** each renders real data and its key attributes change the output (columns, per page, category, layout); `seller-card` with no user renders nothing for a visitor.

### 2. Member shortcodes refuse a visitor
Logged out, open pages carrying `[wpss_dashboard]`, `[wpss_my_orders]`, `[wpss_order_details]`, `[wpss_post_request]`, `[wpss_service_wizard]`, `[wpss_account]`.

**Expect** each shows a log-in prompt, never data; `[wpss_order_details]` for a logged-in stranger says they may not view the order. Guard: `RenderSurfaceAuthorityTest`.

### 3. Cloud storage (Pro)
Settings > Integrations: choose S3 (or GCS / Spaces), save, Test Connection. Deliver a file on an order.

**Expect** the test writes, reads back and deletes a file; the delivery reaches the bucket and the local copy is removed; the buyer's download is a 5-minute signed URL and a stranger is refused; with the provider unreachable the upload keeps the local file and a download answers 503, not a broken link.

### 4. Display currency (Pro)
Settings > Integrations: enable with an Open Exchange Rates App ID; add `[wpss_currency_switcher]`; pick another currency.

**Expect** prices carry an approximate figure beside the base price, checkout states the base charge, earnings/admin/emails stay in base; with no fresh rates (over 7 days) the hint disappears; rates are never fetched on a page view.

### 5. White label (Pro)
Settings > Branding: brand name, logo, colour, footer text, hide branding.

**Expect** the admin menu, the member dashboard header and every email header/footer carry the brand; no "Powered by" anywhere.

### 6. Push (Pro)
Settings > Integrations: add Firebase credentials; register a device via `POST /auth/devices`; trigger a notification for that member.

**Expect** one push queued and sent; an invalid token is removed from the member's devices. **Known gap:** no automated test covers device routes or dispatch.

Guards: `tests/test-services-grid-contract.php`, `tests/test-service-grid-block-editor.php`, `tests/test-order-files-contract.php`, `tests/test-display-currency-surfaces-contract.php`, `tests/test-exchange-rates-never-fetched-on-page-view.php`, Pro `tests/Unit/WhiteLabel/*`.
