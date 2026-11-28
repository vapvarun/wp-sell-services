# Digital Products

Sell a file buyers download the moment they pay: a template, a preset pack, an e-book, a font. There is no brief to fill in, no work to wait for and no delivery time.

## Create a digital product

1. Open **Dashboard > Create Service**.
2. In **Basic Info**, under **How do buyers pay you?**, choose **Digital product**.
3. Fill in **Pricing**. Each package needs a title and a price. A digital product has no delivery time, revisions or express delivery, so those fields are hidden.
4. Add images in **Gallery**, as for any service.
5. In **Your File**, choose the file buyers download. One file per product, up to the site's upload size limit (10 MB by default). To sell several files, put them in one ZIP.
6. Add FAQs in **Extras & FAQs**. Add-ons are not offered on a digital product.
7. **Review** shows **File for buyers uploaded** in the checklist and **Instant download** in the pricing summary. Press **Publish Service**.

A digital product cannot be published, or bought, until it has a file. The wizard and the publish check both say **Please upload the file buyers download.**

The **Requirements** step is skipped: a file needs no brief.

## Update the file

Upload a new file in the wizard's **Your File** step, or in **Sell Services > All Services > edit > Overview > Product file** and press **Update**. Each upload is a new version.

- Buyers always get the latest version, including everyone who bought earlier.
- Earlier versions are kept, so a download already in progress is never cut off.
- The current file name, size and version number show in both places.

## What the buyer sees

- **Service page and cards:** **Instant download** instead of a delivery time. No revisions line.
- **Checkout:** no delivery time and no revisions badge. **What happens next?** reads **Pay, Download, Complete**.
- **After paying:** the order is delivered at once, whichever way they paid (card, PayPal, offline once the site owner confirms it). The order page shows **Your download** with the file and a **Download** button, and the delivery message says the download is ready.
- **Emails and notifications:** the buyer gets one receipt, **Your Download Is Ready**, with a **Download** button, and the seller gets **New Sale**. Neither is told work has started or that a delivery needs review. A digital product with no file uploaded yet waits in progress like any order, and the seller delivers it by hand.
- **Dashboard > Downloads:** every digital product they bought, newest first, each with its order and a **Download** button. The menu item appears once they have bought one.
- **Offline payment:** while the payment waits, the order says the download is ready once it is confirmed.

The buyer accepts the delivery, or the order completes automatically after the usual auto-complete period. Reviews, disputes and refunds work as for any order. A digital product has no revisions and no paid extensions.

## Who can download

- The buyer of a paid order that was not cancelled or refunded.
- The seller of the product.
- A site administrator.

Nobody else can, even with the link: the file is kept in the private file store and the link answers **File not found** to anyone else. A refunded or cancelled order loses its download, and the product leaves the buyer's **Downloads**.

A product that has orders cannot be deleted, so a buyer's download never disappears from under them. When a product with no orders is deleted for good, its files are deleted too.

## Download log

Each download is recorded in **Sell Services > Audit Log** as **Product file downloaded**, with the file name and the member who downloaded it.

There is no download limit or expiry by default. A site can add one with the `wpss_can_download_product` filter.

## For developers

| What | Where |
|------|-------|
| Upload a file (seller, admin) | `POST /wpss/v1/services/{id}/file`, multipart field `file`. Returns the file: `id`, `name`, `type`, `size`, `version`, `uploaded_at`, `url`. |
| The current file on a product | `product_file` on `GET /wpss/v1/services/{id}` (null unless you may download it) |
| A buyer's downloads | `GET /wpss/v1/downloads?page=1&per_page=20` |
| An order's kind and file | `kind` (`digital` or `service`) and `download` on `GET /wpss/v1/orders/{id}` |
| Download from the app | `GET /wpss/v1/orders/{service_id}/files/{file_id}?scope=service` |

Hooks: `wpss_product_file_added`, `wpss_instant_delivery_created`, `wpss_can_download_product`, `wpss_file_downloaded`. See [Hooks & Filters](../developer-guide/hooks-filters.md).

## Related Guides

- **[Service Creation Wizard](service-wizard.md)** -- The three ways to sell: fixed price, custom quote, digital product
- **[Pricing and Packages](pricing-packages.md)** -- Package tiers and prices
- **[Publishing and Moderation](publishing-moderation.md)** -- What happens after you hit Publish
