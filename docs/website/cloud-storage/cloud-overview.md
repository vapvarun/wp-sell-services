# Cloud Storage **[PRO]**

> **Not yet connected to deliveries.** The S3, Google Cloud and DigitalOcean
> drivers work and the connection test really does reach your bucket, but nothing
> routes a delivery file through them -- deliveries are still stored in the
> WordPress media library. The bucket is currently reachable only through the
> REST API, for custom integrations.
>
> Set this up now if you are building against the API. If you want delivery
> storage offloaded, wait for that rather than configuring it here. This page
> describes the intended behaviour once it is connected.

Cloud storage is intended to move delivery files off your WordPress server onto a dedicated provider, for better performance and faster downloads worldwide.

---

## Why Use Cloud Storage?

By default, all delivery files (the work vendors upload for buyers) are stored on your WordPress server. This works fine for smaller marketplaces, but as your platform grows, file storage can become a bottleneck:

- **Disk space fills up** -- Large or frequent deliveries eat into your hosting storage
- **Downloads slow down** -- Your web server handles both page requests and file downloads
- **Bandwidth costs rise** -- Every file download counts against your hosting plan

Cloud storage solves all of these by moving files to a specialized service designed for exactly this purpose.

---

## What Changes With Cloud Storage

| Without Cloud | With Cloud Storage |
|--------------|-------------------|
| Files stored on your WordPress server | Files stored in a cloud bucket (S3, GCS, or DO Spaces) |
| Downloads served by your web server | Downloads served by a global CDN |
| Limited by your hosting storage plan | Virtually unlimited storage |
| Bandwidth counts against hosting | Separate, affordable bandwidth |

The upload experience stays the same for vendors either way -- they upload through the familiar WordPress interface.

---

## Supported Providers

### Amazon S3

The industry standard for cloud storage. Proven reliability (99.999999999% durability), global datacenter coverage, and integration with Amazon CloudFront CDN for fast worldwide downloads.

### Google Cloud Storage

Google's cloud storage with strong performance, multi-regional redundancy, and Google Cloud CDN integration. Particularly strong for marketplaces with significant Asia-Pacific traffic.

### DigitalOcean Spaces

S3-compatible storage with simple, predictable pricing: $5/month for 250GB storage and 1TB transfer. Includes a built-in CDN. The easiest option for small to medium marketplaces.

---

## How File Delivery Works

With a provider active, the flow is:

1. A vendor uploads their delivery files through the order page (same as usual)
2. The plugin transfers the files to your cloud storage bucket
3. When the buyer downloads, the file is served from the cloud provider
4. Access is controlled through signed, time-limited download links

Each stored file records which provider holds it, so files uploaded before you switch providers keep downloading from their original bucket.

---

## Current File Storage (Without Cloud)

Until you enable cloud storage, files are stored locally:

- Delivery files go to `wp-content/uploads/wpss/deliveries/`
- Files are protected so only authorized buyers can download them
- Storage is limited by your hosting plan

**Tips for managing local storage:**
- Set reasonable file size limits in **Settings > Advanced**
- Monitor your disk usage through your hosting panel
- Consider upgrading your hosting storage if it fills up

---

## Related Guides

- [Setting Up Cloud Storage](cloud-setup.md) -- Step-by-step provider configuration
- [Advanced Settings](../platform-settings/advanced-settings.md) -- File upload limits and settings
