# Cloud Storage Overview **[PRO]**

**Status:** This feature is planned for a future Pro release and is not currently available.

## Planned Feature

Cloud storage integration is on the Pro roadmap to offload delivery files from your WordPress server to external cloud platforms. This documentation describes the planned functionality.

## What Cloud Storage Will Offer

When released, cloud storage will move file hosting from your WordPress server to specialized cloud providers.

### Current File Storage

**How it works now:**
- Vendors upload deliveries to WordPress server
- Files stored in `/wp-content/uploads/wpss/deliveries/`
- Buyers download directly from your server
- Files consume server disk space
- Bandwidth counted against hosting plan

### Planned Cloud Storage Solution

**How it will work:**
- Vendors upload deliveries through WordPress
- Files automatically transferred to cloud provider
- Files stored in cloud bucket/container
- Buyers download from cloud
- Unlimited scalability

**Planned Benefits:**
- Virtually unlimited storage
- No local disk space consumed
- Better global download speeds
- Reduced server load
- Easy to scale
- Professional CDN integration

## Planned Supported Providers

### AWS S3 (Amazon Web Services)

Industry-standard cloud storage with proven reliability.

**Key Features:**
- Unlimited storage
- 99.999999999% durability
- Global datacenter coverage
- CloudFront CDN integration

### Google Cloud Storage

Google's competitive storage solution with strong Asia-Pacific presence.

**Key Features:**
- Unlimited storage
- Multi-regional redundancy
- Cloud CDN integration
- Simple pricing

### DigitalOcean Spaces

S3-compatible storage with predictable pricing.

**Key Features:**
- S3-compatible API
- Included CDN
- Flat rate: $5/month for 250GB storage + 1TB transfer
- Simple setup

## Current Workaround

Until cloud storage is released, you can:

1. **Increase Server Storage**: Upgrade hosting plan
2. **Manual Offload**: Periodically move old files to external storage
3. **File Size Limits**: Set reasonable limits in **Settings → Files**
4. **Cleanup Policy**: Auto-delete old deliveries after completion

Configure in **Settings → Files → Storage Management**.

## Security in Current System

Files are currently secured through:

**Access Control:**
- Buyer must own the order
- Order must be completed
- Login required for downloads

**Storage Protection:**
- Files stored in uploads directory with restricted access
- Direct URL access prevented
- WordPress authentication required

## File Management

**Current Folder Structure:**
```
wp-content/uploads/wpss/
├── deliveries/
│   ├── order-12345/
│   │   └── delivery.zip
│   └── order-12346/
│       └── files.zip
└── temp/
    └── upload-processing/
```

## Migration Path

When cloud storage is released:

1. Existing files can remain on server
2. New uploads will use cloud storage
3. Optional migration tool will move old files
4. No downtime required

## Interest in Cloud Storage?

If you need cloud storage for your marketplace, please:

1. Contact support to express interest
2. Share your use case and storage needs
3. Help us prioritize this feature
4. Get notified when released

## Related Documentation

- [File Upload Settings](../platform-settings/advanced-settings.md) - Configure upload limits
- [Order Deliveries](../order-management/deliveries-revisions.md) - How deliveries work
- [Platform Performance](../platform-settings/advanced-settings.md) - Optimize your site

---

**Note:** This documentation describes planned functionality. Check the plugin changelog for cloud storage release updates.
