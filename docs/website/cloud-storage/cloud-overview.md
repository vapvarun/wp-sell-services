# Cloud Storage **[PRO]**

Offload delivery files from your WordPress server to a dedicated cloud storage provider for better performance, unlimited scalability, and faster downloads worldwide.

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

The upload experience stays the same for vendors -- they upload through the familiar WordPress interface. The plugin automatically transfers files to your cloud provider behind the scenes.

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

When cloud storage is enabled:

1. A vendor uploads their delivery files through the order page (same as usual)
2. The plugin transfers the files to your cloud storage bucket
3. When the buyer downloads, the file is served from the cloud provider (with CDN acceleration)
4. Access is controlled through secure, time-limited download links -- only the buyer with the right order can download

If your cloud storage is ever misconfigured or unreachable, the plugin falls back to local storage automatically.

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
