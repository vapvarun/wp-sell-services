# Cloud Storage Integration

**[PRO]** Store service files and order deliverables in the cloud for scalability, performance, and reliability.

## Cloud Storage Overview

Move file storage from your WordPress server to cloud storage providers for better performance and unlimited capacity.

### Why Use Cloud Storage?

**Benefits:**
- Unlimited storage capacity
- Faster file downloads (CDN)
- Reduced server load
- Automatic backups
- Better security
- Geographic distribution

**Without Cloud Storage:**
- Files stored on WordPress server
- Limited by hosting disk space
- Slower downloads for distant users
- Server bandwidth costs
- Manual backups needed

**With Cloud Storage:**
- Files stored in cloud (S3, GCS, etc.)
- Virtually unlimited space
- Fast CDN delivery worldwide
- Pay only for usage
- Automatic redundancy

## Default Local Storage (Free)

Files stored in WordPress uploads directory.

### Local Storage Location

**Directory Structure:**
```
wp-content/uploads/wpss-files/
├── services/
│   ├── 123/
│   │   ├── gallery/
│   │   │   ├── image1.jpg
│   │   │   └── image2.png
│   │   └── videos/
│   │       └── demo.mp4
├── orders/
│   ├── 5678/
│   │   ├── requirements/
│   │   │   ├── brief.pdf
│   │   │   └── logo.png
│   │   ├── deliveries/
│   │   │   ├── final-design.zip
│   │   │   └── source-files.zip
│   │   └── revisions/
│   │       └── revision-1.zip
└── temp/
    └── uploads/
```

### Local Storage Limitations

**Constraints:**
- Server disk space limits
- Hosting bandwidth costs
- No CDN delivery
- Single point of failure
- Manual backup management

**When Local is Fine:**
- New marketplaces (< 100 services)
- Low file volume
- Generous hosting plan
- Budget constraints

---

## Amazon S3 Integration (Pro)

**[PRO]** Industry-standard cloud storage with global infrastructure.

### Why Amazon S3?

**Advantages:**
- 99.999999999% durability (11 nines)
- Global CDN (CloudFront)
- Pay-as-you-go pricing
- Unlimited scalability
- Industry standard
- Strong security features

**Best For:**
- Growing marketplaces
- International audiences
- High file volume
- Professional platforms

### S3 Setup

**Requirements:**
- AWS account
- WP Sell Services Pro
- S3 bucket created

**Step 1: Create AWS Account**

1. Sign up at aws.amazon.com
2. Complete verification
3. Add payment method
4. Note your region preference

**Step 2: Create S3 Bucket**

1. Log in to AWS Console
2. Navigate to **S3**
3. Click **Create Bucket**
4. **Bucket Name:** `your-marketplace-files` (globally unique)
5. **Region:** Choose closest to your audience
6. **Block Public Access:** Keep enabled (we'll use signed URLs)
7. Click **Create Bucket**

**Step 3: Create IAM User**

For security, create dedicated user with limited permissions:

1. Go to **IAM** in AWS Console
2. Click **Users → Add User**
3. **Username:** `wpss-storage`
4. **Access Type:** Programmatic access (API)
5. **Permissions:** Attach policy (create custom or use `AmazonS3FullAccess`)
6. Create user
7. **Save Access Key ID and Secret Access Key** (shown once)

**Step 4: Configure in WP Sell Services**

1. Go to **WP Sell Services → Settings → Storage**
2. Select **Storage Provider:** Amazon S3
3. Enter **Access Key ID**
4. Enter **Secret Access Key**
5. Enter **Bucket Name**
6. Select **Region**
7. Optional: **CloudFront Domain** (for CDN)
8. Click **Test Connection**
9. Save changes

### S3 Configuration Options

**Bucket Settings:**

**Public Access:**
- Keep bucket private
- Files accessed via signed URLs
- Temporary access links
- Secure file downloads

**Storage Class:**
- Standard (frequent access)
- Intelligent-Tiering (auto-optimize)
- Standard-IA (infrequent access, cheaper)
- Glacier (archive, very cheap)

**CloudFront CDN:**
- Enable CloudFront distribution
- Faster worldwide delivery
- Cached at edge locations
- HTTPS by default

**Configuration Example:**
```
Access Key ID: AKIAIOSFODNN7EXAMPLE
Secret Access Key: wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
Bucket Name: mymarketplace-files
Region: us-east-1
CloudFront: d111111abcdef8.cloudfront.net
Storage Class: Intelligent-Tiering
Signed URL Expiry: 24 hours
```

### S3 Pricing

**Cost Structure:**

**Storage:**
- First 50 TB: $0.023/GB/month
- 50-500 TB: $0.022/GB/month

**Data Transfer:**
- Upload: Free
- Download (first 10 TB): $0.09/GB
- CloudFront (first 10 TB): $0.085/GB

**Requests:**
- PUT/POST: $0.005 per 1,000 requests
- GET: $0.0004 per 1,000 requests

**Example Monthly Cost:**
```
100 GB storage: $2.30
10,000 downloads (500 GB): $45.00
Total: ~$50/month
```

**Cost Optimization:**
- Use Intelligent-Tiering (auto-optimize)
- Enable CloudFront caching
- Compress files before upload
- Set lifecycle policies (archive old files)

---

## Google Cloud Storage (Pro)

**[PRO]** Google's cloud storage solution with strong performance.

### Why Google Cloud Storage?

**Advantages:**
- Fast global network
- Competitive pricing
- Strong in Asia-Pacific
- Integration with Google services
- Simpler pricing than S3

**Best For:**
- Google Cloud users
- Asia-Pacific audiences
- Platforms using other Google services

### GCS Setup

**Requirements:**
- Google Cloud account
- GCS bucket created
- Service account credentials

**Step 1: Create Google Cloud Project**

1. Go to console.cloud.google.com
2. Create new project
3. Enable billing
4. Enable Cloud Storage API

**Step 2: Create Storage Bucket**

1. Navigate to **Cloud Storage → Buckets**
2. Click **Create Bucket**
3. **Name:** `your-marketplace-files`
4. **Location Type:** Multi-region or region
5. **Storage Class:** Standard
6. **Access Control:** Uniform
7. Create bucket

**Step 3: Create Service Account**

1. Go to **IAM & Admin → Service Accounts**
2. Click **Create Service Account**
3. **Name:** `wpss-storage`
4. **Role:** Storage Object Admin
5. Create account
6. Click **Add Key → Create New Key**
7. **Type:** JSON
8. Download JSON key file

**Step 4: Configure in WP Sell Services**

1. Go to **WP Sell Services → Settings → Storage**
2. Select **Storage Provider:** Google Cloud Storage
3. Upload **Service Account JSON Key**
4. Enter **Bucket Name**
5. Select **Region**
6. Click **Test Connection**
7. Save changes

### GCS Features

**Storage Classes:**
- Standard (frequent access)
- Nearline (monthly access, cheaper)
- Coldline (quarterly access, very cheap)
- Archive (annual access, extremely cheap)

**Access Control:**
- Private buckets
- Signed URLs
- Temporary access links
- Fine-grained permissions

**CDN:**
- Google Cloud CDN
- Global edge caching
- Faster delivery
- Lower bandwidth costs

### GCS Pricing

**Storage:**
- Multi-region: $0.026/GB/month
- Regional: $0.020/GB/month

**Network:**
- Upload: Free
- Download (worldwide): $0.12/GB
- CDN (first 10 TB): $0.08/GB

**Operations:**
- Class A (writes): $0.05 per 10,000
- Class B (reads): $0.004 per 10,000

---

## DigitalOcean Spaces (Pro)

**[PRO]** Simple, affordable S3-compatible storage.

### Why DigitalOcean Spaces?

**Advantages:**
- S3-compatible API
- Simple flat pricing ($5/month)
- 250 GB storage included
- 1 TB outbound transfer included
- Built-in CDN
- No egress fees (within limits)

**Best For:**
- Budget-conscious platforms
- Simple pricing preference
- DigitalOcean users
- Predictable costs

### Spaces Setup

**Requirements:**
- DigitalOcean account
- Space created
- API keys

**Step 1: Create DigitalOcean Space**

1. Log in to DigitalOcean
2. Navigate to **Spaces**
3. Click **Create Space**
4. **Region:** Choose closest to audience
5. **Name:** `marketplace-files`
6. **CDN:** Enable (included free)
7. Create Space

**Step 2: Generate API Keys**

1. Go to **API → Spaces Keys**
2. Click **Generate New Key**
3. **Name:** `WP Sell Services`
4. Save **Access Key** and **Secret Key**

**Step 3: Configure in WP Sell Services**

1. Go to **WP Sell Services → Settings → Storage**
2. Select **Storage Provider:** DigitalOcean Spaces
3. Enter **Access Key**
4. Enter **Secret Key**
5. Enter **Space Name**
6. Select **Region** (e.g., nyc3, sfo3)
7. CDN endpoint auto-detected
8. Click **Test Connection**
9. Save changes

### Spaces Pricing

**Flat Pricing:**
```
$5/month includes:
- 250 GB storage
- 1 TB outbound transfer
- CDN included
- Unlimited inbound transfer

Overage:
- Storage: $0.02/GB/month
- Transfer: $0.01/GB
```

**Example:**
```
500 GB storage + 2 TB transfer:
Base: $5
Extra storage (250 GB): $5
Extra transfer (1 TB): $10
Total: $20/month
```

**Cost Predictability:**
Much simpler than AWS S3 pricing.

---

## File Upload Flow

How files are uploaded and stored in cloud.

### Service Creation Upload

**Vendor Creates Service:**

1. Vendor uploads gallery images
2. Plugin processes images (resize, optimize)
3. Files uploaded to cloud storage
4. Cloud URLs stored in database
5. Thumbnails generated and cached
6. Service published with cloud URLs

**Behind the Scenes:**
```php
// Upload to cloud
$file_url = wpss_upload_to_cloud( $file_path, 'services/123/gallery/' );

// Store URL in database
update_post_meta( $service_id, '_gallery_images', $file_url );

// Delete local temp file
unlink( $file_path );
```

### Order Delivery Upload

**Vendor Delivers Files:**

1. Vendor uploads delivery files
2. Files uploaded to cloud
3. Temporary local copy deleted
4. Signed download URL generated
5. Buyer receives download link
6. Link expires after 24 hours (configurable)

**Signed URL Example:**
```
https://mymarketplace-files.s3.amazonaws.com/orders/5678/delivery.zip
?AWSAccessKeyId=XXX
&Expires=1704067200
&Signature=YYY
```

Link valid until expiry timestamp.

---

## Migration to Cloud Storage

Move existing files from local storage to cloud.

### Migration Process

**Built-In Migration Tool:**

1. Go to **WP Sell Services → Storage → Migration**
2. Select **Target Storage:** (S3, GCS, or Spaces)
3. Configure cloud storage credentials
4. Click **Scan Files**
5. Review file count and estimated size
6. Click **Start Migration**
7. Monitor progress
8. Verify files after migration

**What Gets Migrated:**
- Service gallery images
- Service videos
- Order requirement files
- Order delivery files
- Revision files
- Vendor profile images

**Migration Options:**

**Copy:**
- Copy files to cloud
- Keep local copies (backup)
- More storage used temporarily

**Move:**
- Upload to cloud
- Delete local files after successful upload
- Frees local disk space

**Recommended:** Copy first, verify, then delete local.

### Migration Best Practices

**Before Migration:**
1. Backup database
2. Backup files
3. Test cloud storage connection
4. Estimate costs
5. Plan for downtime (if large dataset)

**During Migration:**
1. Monitor error logs
2. Check failed uploads
3. Verify file accessibility
4. Don't interrupt process

**After Migration:**
1. Verify random file samples
2. Test service pages
3. Test order downloads
4. Monitor for broken links
5. Delete local files (if moved)

---

## Cloud Storage Settings

### File Access Control

**Private Files:**
- Service delivery files
- Order requirement files
- Vendor documents

**Access Method:**
- Signed URLs (temporary access)
- Expire after 24 hours (default)
- Regenerate on demand

**Public Files:**
- Service gallery images (optional)
- Service thumbnails
- Public vendor avatars

**Access Method:**
- Direct URLs (no expiry)
- Cached by CDN
- Faster delivery

### File Retention

**Automatic Cleanup:**

Configure file retention policies:

**Order Files:**
- Keep delivery files: 90 days post-completion
- Keep requirement files: 30 days post-completion
- Archive to cheaper storage class
- Permanent deletion after retention

**Service Files:**
- Keep while service is active
- Archive 30 days after service deleted
- Permanent deletion after 90 days

**Configuration:**
```
Settings → Storage → Retention
Order Deliveries: 90 days
Requirements: 30 days
Deleted Service Files: 90 days
Archive Storage Class: Glacier / Coldline
```

### CDN Configuration

**Content Delivery Network:**

Speed up file delivery worldwide.

**AWS CloudFront:**
1. Create CloudFront distribution
2. Set S3 bucket as origin
3. Configure cache behaviors
4. Get distribution domain
5. Enter in WP Sell Services settings

**Google Cloud CDN:**
1. Enable Cloud CDN on bucket
2. Configure cache settings
3. URL automatically uses CDN

**DigitalOcean Spaces:**
- CDN included automatically
- No configuration needed
- Edge caching enabled

**Cache Settings:**
- Cache static files: 1 year
- Cache service images: 30 days
- No cache for private files
- Invalidate on file update

---

## Troubleshooting

### Files Not Uploading to Cloud

**Check:**
1. API credentials correct
2. Bucket/Space exists
3. Permissions set correctly (write access)
4. Bucket region matches configuration
5. No firewall blocking AWS/GCP/DO

**Debug Mode:**
Enable in settings to see detailed upload logs.

### Download Links Not Working

**Verify:**
1. Signed URL not expired
2. File still exists in cloud
3. Bucket permissions correct
4. No CORS errors (check browser console)
5. Regenerate download link

### Migration Stuck

**Troubleshoot:**
1. Check PHP execution time limits
2. Monitor server memory usage
3. Pause and resume migration
4. Migrate in smaller batches
5. Check error log for specific failures

### High Cloud Storage Costs

**Optimize:**
1. Enable intelligent tiering
2. Use cheaper storage class for old files
3. Compress files before upload
4. Implement lifecycle policies
5. Enable CDN caching to reduce egress

---

## Performance Optimization

### File Compression

Automatically compress files before upload:

**Image Compression:**
- JPEG: 80% quality
- PNG: Lossless compression
- WebP: Convert to WebP format

**Archive Compression:**
- ZIP: Maximum compression
- Reduces upload time
- Reduces storage costs
- Reduces download time

### Lazy Loading

Load cloud files only when needed:

**Service Pages:**
- Lazy load gallery images
- Defer video loading
- Progressive image loading

**Order Pages:**
- Load delivery files on click
- Don't pre-fetch large files
- Stream videos instead of download

### Caching Strategy

**Browser Cache:**
- Service images: 30 days
- Static assets: 1 year
- Profile images: 7 days

**CDN Cache:**
- Public files: Long TTL (1 month)
- Private files: No cache
- Purge cache on update

---

## Security Considerations

### Access Control

**Best Practices:**
- Never make buckets fully public
- Use signed URLs for private files
- Rotate API keys periodically
- Limit IAM user permissions
- Enable MFA for cloud accounts

### Data Encryption

**At Rest:**
- Enable bucket encryption (AES-256)
- All files encrypted by default
- No performance impact

**In Transit:**
- Always use HTTPS
- TLS 1.2 or higher
- Signed URLs use HTTPS

### Compliance

**Data Residency:**
- Choose bucket region based on regulations
- EU data in EU regions (GDPR)
- Certain industries require specific regions

**Data Retention:**
- Comply with local laws
- Automatic deletion policies
- Audit logs for compliance

---

## Related Documentation

- [Advanced Settings](../admin-settings/advanced-settings.md) - File upload limits
- [Payment Gateways](payment-gateways.md) - Payment processing
- [WooCommerce Setup](woocommerce-setup.md) - E-commerce integration

---

## Next Steps

1. Choose cloud storage provider
2. Create cloud account and bucket
3. Configure in WP Sell Services
4. Test file upload and download
5. Migrate existing files
6. Monitor storage costs
7. Optimize based on usage patterns
