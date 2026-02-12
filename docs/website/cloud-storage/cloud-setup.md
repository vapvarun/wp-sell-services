# Cloud Storage Setup **[PRO]**

**Status:** This feature is planned for a future Pro release and is not currently available.

## Feature Not Yet Available

Cloud storage integration with AWS S3, Google Cloud Storage, and DigitalOcean Spaces is planned for WP Sell Services Pro but not yet implemented.

This documentation will be updated when the feature is released.

## What to Do Now

### Current File Storage Options

**1. Use WordPress Uploads Directory**

Files are currently stored in:
```
wp-content/uploads/wpss/deliveries/
```

**2. Increase Server Storage**

Contact your hosting provider to:
- Upgrade storage capacity
- Increase bandwidth allocation
- Enable server-level file compression

**3. Configure File Limits**

Set appropriate limits to manage storage:

1. Go to **Settings → Files**
2. Configure:
   - Max file size per upload (default: 50MB)
   - Max files per delivery (default: 10)
   - Allowed file types
   - Auto-delete after X days (optional)

### File Size Recommendations

| Marketplace Size | Recommended Limits |
|------------------|-------------------|
| Small (< 100 orders/month) | 50MB per file, 90-day retention |
| Medium (100-1000 orders/month) | 100MB per file, 60-day retention |
| Large (1000+ orders/month) | Consider dedicated file storage solution |

## Preparing for Cloud Storage

When the feature is released, these steps will help you migrate:

### 1. Document Current Usage

Track your storage needs:
- Average delivery file size
- Total storage used
- Monthly growth rate
- Geographic distribution of buyers

### 2. Choose Provider

Research which provider will suit your needs:

**AWS S3:** Best for large marketplaces with global reach
**Google Cloud Storage:** Good for Asia-Pacific focus
**DigitalOcean Spaces:** Simplest for small-medium marketplaces

### 3. Set Budget

Estimate monthly costs:
- Storage: $0.02-$0.023 per GB/month
- Transfer: $0.01-$0.12 per GB downloaded
- Calculate based on your order volume

## Notification List

Want to be notified when cloud storage is released?

1. Go to **Settings → Updates**
2. Enable **Feature Release Notifications**
3. Enter your email address
4. You'll be notified when cloud storage launches

## Alternative Solutions

### Third-Party Plugins

Consider these WordPress plugins for file management:

- **WP Offload Media**: Offload to S3/GCS/DigitalOcean
- **Media Cloud**: Cloud media management
- **Enable Media Replace**: Manage and replace files

**Note:** These work with WordPress media library but may require custom integration with WP Sell Services.

### Manual Offload Process

For very large marketplaces:

1. Regularly archive completed orders to external storage
2. Move old delivery files via FTP/SSH
3. Update database references (custom development required)
4. Document archive locations

**Warning:** This requires technical expertise and custom code.

## Technical Considerations

When cloud storage is implemented, it will use:

**AWS S3 SDK for PHP** - For S3 and S3-compatible providers
**Google Cloud Storage Client** - For GCS integration
**Signed URLs** - For secure temporary download links
**Background Uploads** - Non-blocking file transfers
**Automatic Retry** - Handle upload failures gracefully

## Related Documentation

- [File Upload Settings](../platform-settings/advanced-settings.md) - Current file management
- [Order Deliveries](../order-management/deliveries-revisions.md) - Delivery workflow
- [Platform Settings](../platform-settings/advanced-settings.md) - System configuration

---

**Last Updated:** February 2026
**Feature Status:** Planned for future release
**Current Version:** 1.0.0 (does not include cloud storage)
