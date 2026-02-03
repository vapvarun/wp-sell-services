# Advanced Settings

Configure service moderation, file upload limits, rate limiting, feature toggles, and performance optimization options.

## Service Moderation

Control how new services are published on your marketplace.

![Advanced Settings Tab](../images/settings-advanced-tab.png)

## Service Approval Mode

Determine if vendor services require admin review before going live.

### Auto-Approve Services

Services are published immediately after vendor submits them.

**Enable:**
1. Go to **WP Sell Services → Settings → Advanced**
2. Set **Service Moderation** to "Auto-Approve"
3. Save changes

**Best For:**
- Established marketplaces with trusted vendors
- High-volume platforms
- Quick time-to-market for services

**Workflow:**
1. Vendor creates service
2. Vendor clicks "Publish"
3. Service goes live immediately
4. Service appears in catalog
5. Buyers can purchase instantly

### Manual Review

Admin must approve each service before it becomes visible to buyers.

**Enable:**
1. Set **Service Moderation** to "Manual Review"
2. Save changes

**Best For:**
- New marketplaces building reputation
- Quality-controlled platforms
- Niche marketplaces with specific standards
- Compliance-sensitive industries

**Workflow:**
1. Vendor creates service
2. Vendor submits for review
3. Admin receives notification
4. Admin reviews service content
5. Admin approves or rejects
6. Service goes live after approval

**Reviewing Pending Services:**
1. Go to **WP Sell Services → Services**
2. Filter by **Status: Pending Review**
3. Click service to review
4. Check content, pricing, images, policies
5. Click **Approve** or **Reject** with reason

### Rejection Reasons

When rejecting services, provide clear feedback:

**Common Rejection Reasons:**
- Inappropriate content or images
- Misleading service description
- Pricing violates platform policies
- Copyright infringement
- Poor quality images
- Incomplete service details
- Prohibited service type

Vendor receives rejection email with reason and can resubmit after corrections.

## File Upload Limits

Control file sizes and types vendors and buyers can upload.

### Maximum File Size

Set the maximum size for individual file uploads.

**Configuration:**
1. Navigate to **Settings → Advanced → File Uploads**
2. Set **Maximum File Size** in MB
3. Recommended: 10-50 MB
4. Save changes

**Applies To:**
- Service gallery images
- Service videos
- Order deliverables
- Order requirements
- Revision files
- Dispute attachments

**Note:** Server PHP limits may override this setting. If uploads fail, check:
- `upload_max_filesize` in php.ini
- `post_max_size` in php.ini
- Contact hosting provider if needed

### Allowed File Types

Control which file formats can be uploaded.

**Default Allowed Types:**

**Images:**
- JPG/JPEG
- PNG
- GIF
- WebP

**Documents:**
- PDF
- DOC/DOCX
- XLS/XLSX
- TXT

**Archives:**
- ZIP
- RAR

**Media:**
- MP4 (video)
- MP3 (audio)

**Design Files:** **[PRO]**
- PSD (Photoshop)
- AI (Illustrator)
- FIG (Figma)
- SKETCH

**Configuration:**
1. Go to **Settings → Advanced → File Types**
2. Check file types to allow
3. Uncheck file types to block
4. Save changes

**Security Note:** Never allow executable files (.exe, .php, .js, .sh) to prevent malware uploads.

### Total Upload Limit Per Order

**[PRO]** Set maximum total file size for all files in one order.

**Configuration:**
1. Set **Total Upload Limit** **[PRO]**
2. Recommended: 100-500 MB
3. Prevents single orders from consuming excessive storage
4. Save changes

## Rate Limiting

Prevent spam and abuse through action rate limits.

### Service Creation Limit

Limit how many services vendors can create per time period.

**Configuration:**
1. Navigate to **Settings → Advanced → Rate Limiting**
2. Enable **Service Creation Limit**
3. Set **Max Services Per** (hour/day/week)
4. Example: 5 services per day
5. Save changes

**Prevents:**
- Spam service creation
- Low-quality bulk submissions
- Market flooding by single vendor

### Order Request Limit

Limit buyer actions to prevent abuse.

**Configuration:**
1. Enable **Order Request Limit**
2. Set **Max Orders Per** (hour/day)
3. Example: 10 orders per hour
4. Save changes

**Prevents:**
- Fake order spam
- Payment testing attacks
- System abuse

### Message Rate Limit

Control messaging frequency to prevent harassment.

**Configuration:**
1. Enable **Message Rate Limit**
2. Set **Max Messages Per Hour**
3. Recommended: 20-50 messages
4. Save changes

**Prevents:**
- Message spam
- Harassment
- System overload

### Buyer Request Limit

Limit how many project requests buyers can post.

**Configuration:**
1. Enable **Buyer Request Limit**
2. Set **Max Requests Per** (day/week)
3. Example: 3 requests per week
4. Save changes

**Prevents:**
- Request spam
- Low-quality bulk requests
- Vendor inbox flooding

## Feature Toggles

Enable or disable specific marketplace features.

### Available Features

| Feature | Description | Default |
|---------|-------------|---------|
| Buyer Requests | Project request marketplace | Enabled |
| Service Reviews | Rating and review system | Enabled |
| Vendor Messaging | Direct buyer-vendor chat | Enabled |
| Service Favorites | Wishlist functionality | Enabled |
| Service Sharing | Social share buttons | Enabled |
| Vendor Following | Follow favorite vendors | Enabled |
| Order Tips | Buyers can tip vendors | Enabled |
| Service Bundles | Multi-service packages | **[PRO]** |
| Subscription Services | Recurring services | **[PRO]** |
| Video Consultations | Integrated video calls | **[PRO]** |

**Toggle Features:**
1. Go to **Settings → Advanced → Features**
2. Check to enable, uncheck to disable
3. Disabled features are hidden from frontend
4. Save changes

**Use Cases for Disabling:**
- Simplify marketplace for niche focus
- Reduce complexity for users
- Phase feature rollout gradually
- Comply with specific business model

## Debug Mode

Enable detailed logging for troubleshooting.

### Enable Debug Mode

**Configuration:**
1. Navigate to **Settings → Advanced → Debug**
2. Enable **Debug Mode**
3. Select **Log Level**:
   - Errors only
   - Warnings and errors
   - All activity (verbose)
4. Save changes

**What Gets Logged:**
- Order processing steps
- Payment gateway communications
- Email send attempts
- File upload activities
- API requests
- Error messages
- Performance metrics

**View Logs:**
1. Go to **WP Sell Services → Logs**
2. Filter by date, type, severity
3. Search log entries
4. Download logs for support

**Important:** Disable debug mode in production for performance. Only enable when troubleshooting issues.

## Data Retention Settings

Control how long data is stored before automatic deletion.

### Order Data Retention

**Configuration:**
1. Go to **Settings → Advanced → Data Retention**
2. Set **Delete Completed Orders After** (days)
3. Recommended: 365 days (1 year)
4. 0 = keep forever
5. Save changes

**What Gets Deleted:**
- Order records (after retention period)
- Associated files
- Order messages
- Transaction logs

**What's Preserved:**
- Vendor earnings records
- Platform commission records
- Aggregated analytics
- User profiles

### File Retention

Set how long uploaded files are stored.

**Configuration:**
1. Set **Delete Order Files After** (days)
2. Recommended: 90 days post-completion
3. Helps manage storage costs
4. Save changes

**Download Before Deletion:**
Both buyers and vendors receive email reminders 7 days before file deletion.

### Message History Retention

**[PRO]** Control chat message storage duration.

**Configuration:**
1. Set **Delete Messages After** (days) **[PRO]**
2. Recommended: 180 days
3. Save changes

## Performance Optimization

Configure caching and optimization features.

### Enable Caching

**[PRO]** Cache database queries for faster page loads.

**Configuration:**
1. Navigate to **Settings → Advanced → Performance**
2. Enable **Query Caching** **[PRO]**
3. Set cache duration (minutes)
4. Save changes

**What Gets Cached:**
- Service listings
- Vendor profiles
- Category pages
- Search results

**Cache Clearing:**
- Automatic on service update
- Manual via **Settings → Clear Cache**

### Lazy Loading

Enable lazy loading for images and iframes.

**Configuration:**
1. Enable **Lazy Load Images**
2. Enable **Lazy Load Iframes**
3. Improves page load speed
4. Save changes

**Benefits:**
- Faster initial page load
- Reduced bandwidth usage
- Better mobile performance
- Improved SEO scores

### Database Optimization

**[PRO]** Optimize database tables automatically.

**Configuration:**
1. Enable **Auto Database Optimization** **[PRO]**
2. Set frequency (daily/weekly)
3. Save changes

**Optimization Actions:**
- Remove orphaned data
- Optimize table structure
- Clean transients
- Rebuild indexes

## Image Optimization Settings

Configure automatic image processing.

### Image Compression

**[PRO]** Automatically compress uploaded images.

**Configuration:**
1. Navigate to **Settings → Advanced → Images**
2. Enable **Auto Image Compression** **[PRO]**
3. Set compression quality (60-100%)
4. Recommended: 80%
5. Save changes

**Benefits:**
- Smaller file sizes
- Faster page loads
- Reduced storage costs
- Maintained visual quality

### Image Resizing

Automatically resize large images to standard dimensions.

**Configuration:**
1. Enable **Auto Image Resize**
2. Set **Max Width** (pixels)
3. Set **Max Height** (pixels)
4. Recommended: 1920×1080
5. Save changes

**Applies To:**
- Service gallery images
- Vendor profile images
- Order deliverable images

### Thumbnail Generation

Configure thumbnail sizes for different contexts.

**Default Sizes:**
- **Catalog:** 400×300
- **Featured:** 600×400
- **Detail:** 800×600

**Custom Sizes:** **[PRO]**
1. Go to **Settings → Advanced → Images → Thumbnails**
2. Add custom thumbnail size
3. Set width and height
4. Enable cropping (optional)
5. Save and regenerate thumbnails

## Security Settings

Additional security features for marketplace protection.

### CAPTCHA Integration

**[PRO]** Add CAPTCHA to forms to prevent bot submissions.

**Supported Services:**
- Google reCAPTCHA v2
- Google reCAPTCHA v3
- hCaptcha

**Configuration:**
1. Navigate to **Settings → Advanced → Security** **[PRO]**
2. Select CAPTCHA provider
3. Enter API keys
4. Choose where to display:
   - Registration forms
   - Login forms
   - Buyer request forms
   - Contact forms
5. Save changes

### Two-Factor Authentication

**[PRO]** Require 2FA for vendor accounts.

**Configuration:**
1. Enable **Require 2FA for Vendors** **[PRO]**
2. Select 2FA method:
   - Email verification codes
   - SMS codes (requires SMS provider)
   - Authenticator app (Google Authenticator, Authy)
3. Save changes

**Vendor Setup:**
1. Vendor logs in
2. Prompted to set up 2FA
3. Scans QR code (authenticator app)
4. Enters verification code
5. 2FA activated

### IP Blocking

**[PRO]** Block specific IP addresses or ranges.

**Configuration:**
1. Go to **Settings → Advanced → Security → IP Blocking** **[PRO]**
2. Add IP addresses (one per line)
3. Supports ranges (e.g., 192.168.1.0/24)
4. Save changes

**Use Cases:**
- Block abusive users
- Prevent spam sources
- Geographic restrictions

## API Settings (Pro)

**[PRO]** Configure REST API access for integrations.

### Enable API

1. Navigate to **Settings → Advanced → API** **[PRO]**
2. Enable **REST API**
3. Generate API keys
4. Save changes

### API Rate Limiting

Prevent API abuse:

**Configuration:**
1. Set **API Requests Per Hour**
2. Recommended: 1000 per hour
3. Save changes

See API documentation for endpoint details.

## Automated Background Tasks

WP Sell Services runs several automated tasks in the background to keep your marketplace running smoothly. These tasks use WordPress Cron to execute on schedule.

### What Runs Automatically

The plugin performs these tasks without manual intervention:

#### Auto-Complete Orders

**What It Does:**
Automatically marks orders as completed if the buyer doesn't respond after delivery.

**Schedule:** Runs hourly

**How It Works:**
1. Order is delivered by vendor
2. Buyer has configured number of days to review (default: 7 days)
3. If buyer doesn't accept or request revisions
4. Order automatically completes after deadline
5. Payment is released to vendor
6. Both parties can leave reviews

**Configuration:**
Set auto-complete duration in **Settings → Orders → Auto-Complete After** (days).

**Benefits:**
- Prevents orders from staying "delivered" indefinitely
- Ensures vendors get paid for completed work
- Reduces admin manual order management
- Fair to both buyers (time to review) and vendors (timely payment)

#### Late Order Detection

**What It Does:**
Automatically flags orders that are past their delivery deadline.

**Schedule:** Runs every hour

**How It Works:**
1. Each order has a delivery deadline
2. System checks if current time > deadline
3. Order status changes to "Late"
4. Buyer receives notification
5. Vendor's late delivery rate increases
6. Order appears in "Late Orders" admin report

**Impact on Vendors:**
- Affects vendor completion rate
- May impact vendor level/badge
- Shown on vendor profile statistics
- Too many late deliveries can affect ranking

**Buyer Actions:**
- Request refund for late orders
- Cancel order if extremely late
- Contact vendor for status update

#### Deadline Reminders

**What It Does:**
Sends reminder emails to vendors when order deadlines are approaching.

**Schedule:** Runs daily at configured time

**Reminder Schedule:**
- **3 days before deadline**: First reminder
- **1 day before deadline**: Urgent reminder
- **Deadline day**: Final reminder

**Email Content:**
- Order details
- Time remaining
- Direct link to order
- Quick delivery upload button

**Configuration:**
Set reminder schedule in **Settings → Emails → Deadline Reminders**.

**Benefits:**
- Helps vendors manage workload
- Reduces late deliveries
- Improves buyer satisfaction
- Proactive vendor support

#### Expired Buyer Requests Cleanup

**What It Does:**
Automatically closes buyer requests that have passed their expiration date.

**Schedule:** Runs daily

**How It Works:**
1. Buyer posts project request with deadline
2. Request expires after deadline date
3. System auto-closes expired requests
4. Vendors can no longer submit offers
5. Request marked as "Expired"
6. Buyer can repost if still needed

**Configuration:**
Default expiration: 30 days (configurable in **Settings → Buyer Requests**)

**What Happens:**
- Request status changes to "Expired"
- No new offers accepted
- Existing offers remain visible
- Buyer can still accept pending offers for 7 more days
- After that, request is archived

#### Vendor Statistics Update

**What It Does:**
Recalculates vendor performance metrics and updates rankings.

**Schedule:** Runs twice daily (12 AM and 12 PM server time)

**Metrics Calculated:**
- **Overall rating**: Average from all reviews
- **Response time**: Average time to respond to messages
- **Order completion rate**: Percentage of completed vs. total orders
- **On-time delivery rate**: Percentage of orders delivered on time
- **Revenue**: Total earnings (last 30 days, all time)
- **Active orders**: Current orders in progress

**Where Shown:**
- Vendor profile page
- Vendor dashboard
- Service listings
- Top vendor rankings
- Admin vendor reports

**Why Twice Daily:**
- Balance between accuracy and performance
- Prevents constant recalculation
- Reflects recent activity fairly
- Reduces database load

#### Auto-Withdrawals

**What It Does:**
**[PRO]** Automatically processes vendor withdrawal requests on a schedule.

**Schedule:** Configurable (weekly, biweekly, or monthly)

**How It Works:**
1. Admin enables auto-withdrawals in settings
2. Vendors opt-in to auto-withdrawal
3. System checks vendor balance on schedule
4. If balance > minimum threshold
5. Withdrawal is automatically created
6. Payment is processed via configured gateway (Stripe, PayPal, etc.)
7. Vendor receives payout
8. Transaction recorded in earnings history

**Configuration:**
**Settings → Payments → Auto-Withdrawals** **[PRO]**
- Enable/disable feature
- Withdrawal frequency (weekly/biweekly/monthly)
- Day of week or month
- Minimum balance threshold
- Payment method

**Requirements:**
- Stripe Connect or PayPal Payouts configured
- Vendor has valid payment details
- Vendor opted in to auto-withdrawals
- Balance meets minimum threshold

**Benefits:**
- Hands-off vendor payouts
- Predictable payment schedule
- Reduces manual admin work
- Improves vendor satisfaction

### How to Verify Background Tasks Are Running

Check if scheduled tasks are executing properly:

#### Method 1: WP Cron Events List

1. Install **WP Crontrol** plugin (free)
2. Go to **Tools → Cron Events**
3. Look for WP Sell Services events:
   - `wpss_auto_complete_orders`
   - `wpss_detect_late_orders`
   - `wpss_send_deadline_reminders`
   - `wpss_cleanup_expired_requests`
   - `wpss_update_vendor_stats`
   - `wpss_process_auto_withdrawals` **[PRO]**
4. Check "Next Run" times
5. If times are in the past, WP Cron may not be working

#### Method 2: Debug Logs

1. Enable debug mode in **Settings → Advanced → Debug**
2. Go to **WP Sell Services → Logs**
3. Filter by "Cron" or "Background Task"
4. Verify tasks are executing on schedule
5. Look for any errors

#### Method 3: Recent Activity

Check if automated actions are happening:
- Look at recent order completions (should happen automatically)
- Check vendor statistics last updated timestamp
- Verify expired requests are being closed
- Review deadline reminder emails in sent log

### What to Do If Scheduled Tasks Aren't Running

WordPress Cron requires site traffic to trigger tasks. Low-traffic sites may experience delays.

#### Problem: WP Cron Not Triggering

**Symptoms:**
- Orders not auto-completing
- Late orders not being flagged
- Vendor stats not updating
- Background tasks not running

**Solutions:**

**Option 1: Disable WP Cron and Use Real Cron**

Add to `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

Then add a real cron job via hosting control panel:
```bash
*/15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

Or:
```bash
*/15 * * * * cd /path/to/wordpress && php wp-cron.php
```

This runs WP Cron every 15 minutes regardless of traffic.

**Option 2: Use a Cron Service**

Use external services to trigger WP Cron:
- **EasyCron** (easycron.com)
- **cron-job.org** (free)
- **SetCronJob** (setcronjob.com)

Set them to ping `https://yoursite.com/wp-cron.php` every 15 minutes.

**Option 3: Enable Alternative Cron** **[PRO]**

1. Go to **Settings → Advanced → Cron**
2. Enable **Alternative Cron Runner** **[PRO]**
3. Uses loopback requests instead of traffic-based triggering
4. Save changes

#### Problem: Specific Task Not Running

**Check Configuration:**
1. Verify task is enabled in settings
2. Check if feature is toggled on
3. Look for PHP errors in debug log
4. Ensure server has enough resources

**Manual Trigger:**
1. Install WP Crontrol plugin
2. Find the specific cron event
3. Click **Run Now** to test
4. Check if task completes successfully
5. Review logs for errors

### Performance Impact

These background tasks are optimized for minimal performance impact:

**Database Queries:**
- Indexed tables for fast lookups
- Batch processing for large datasets
- Query caching where possible

**Execution Time:**
- Tasks run quickly (usually < 5 seconds)
- No impact on user-facing pages
- Runs during low-traffic periods when possible

**Resource Usage:**
- Light memory footprint
- No CPU spikes
- Respects server limits

**Recommendations:**
- If you have 10,000+ orders, consider running vendor stats updates only once daily
- On shared hosting, ensure adequate resources
- Monitor server logs during scheduled task runs

### Customizing Task Schedules

**[PRO]** Adjust when background tasks run:

1. Go to **Settings → Advanced → Cron Schedule** **[PRO]**
2. Adjust frequency for each task:
   - Auto-complete orders: Hourly, Twice Daily, Daily
   - Late order detection: Hourly, Every 6 Hours, Daily
   - Deadline reminders: Daily at specific time
   - Expired requests: Daily at specific time
   - Vendor stats: Hourly, Twice Daily, Daily
3. Save changes

**Use Cases for Adjustments:**
- Small marketplace: Run less frequently to save resources
- High-volume marketplace: Run more frequently for accuracy
- Specific timezone needs: Set reminder times for vendor timezone

## Troubleshooting

### Services Not Auto-Approving

**Check:**
1. Service moderation set to "Auto-Approve"
2. No pending review status
3. Vendor account approved
4. Cache cleared

### Upload Limits Not Working

**Verify:**
1. PHP limits higher than plugin limits
2. Server allows large uploads
3. No conflicting plugin
4. Check debug log for errors

### Rate Limits Too Restrictive

**Adjust:**
1. Review rate limit numbers
2. Increase limits for legitimate use
3. Whitelist trusted users **[PRO]**
4. Monitor abuse vs usability balance

## Related Documentation

- [Vendor Settings](vendor-settings.md) - Vendor capabilities
- [Order Settings](order-settings.md) - Order policies
- [Payment Settings](payment-settings.md) - Commission configuration
- [Cloud Storage](../integrations/cloud-storage.md) - File storage options **[PRO]**

## Next Steps

After configuring advanced settings:

1. Test file uploads with different types and sizes
2. Monitor rate limit logs for false positives
3. Review moderation queue regularly
4. Set up monitoring for debug logs
5. Schedule regular database optimization
