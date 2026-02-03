# Data Export Guide

**[PRO]** WP Sell Services Pro includes powerful data export capabilities for generating reports, analyzing trends, and meeting compliance requirements. Export order data, earnings reports, vendor performance metrics, and more in CSV or PDF format.

## Overview

Export features include:

- **CSV Exports**: Spreadsheet-compatible data for Excel, Google Sheets, or custom analysis
- **PDF Reports**: Professional formatted reports with charts and summaries
- **Filtered Exports**: Export specific data ranges and categories
- **Scheduled Exports**: Automate recurring reports
- **GDPR Compliance**: Privacy-safe data exports

## Exporting Orders

### Order Export Options

Export order data for accounting, analysis, or reporting:

1. Go to **WordPress Admin → WP Sell Services → Orders**
2. Apply filters (optional):
   - Date range
   - Order status
   - Vendor
   - Service category
   - Payment method
3. Click **Export** button
4. Select format:
   - **CSV**: Detailed order data
   - **PDF**: Summary report with totals

### Order CSV Fields

CSV exports include:

| Field | Description |
|-------|-------------|
| Order ID | Unique order identifier |
| Order Date | Date order was placed |
| Service Name | Name of service ordered |
| Package | Package tier (Basic/Standard/Premium) |
| Vendor | Vendor username |
| Buyer | Buyer username |
| Status | Current order status |
| Gross Amount | Total order amount |
| Commission | Platform commission |
| Vendor Earnings | Amount payable to vendor |
| Payment Method | Payment gateway used |
| Delivery Date | Date order was delivered |
| Completion Date | Date order was completed |
| Rating | Buyer rating (if reviewed) |

### Order PDF Report

PDF order reports include:

- **Summary Section**:
  - Total orders in period
  - Total revenue
  - Total commission
  - Order status breakdown
- **Detailed Table**: All orders with key fields
- **Charts**:
  - Orders by status (pie chart)
  - Revenue over time (line chart)
  - Top services (bar chart)
- **Footer**: Report generation date and filters applied

## Exporting Earnings and Commissions

### Vendor Earnings Report

Export vendor-specific earnings:

**For Vendors:**
1. Go to **Vendor Dashboard → Analytics**
2. Select date range
3. Click **Export Earnings**
4. Choose format (CSV or PDF)

**For Admins:**
1. Go to **WordPress Admin → WP Sell Services → Vendors**
2. Click on a vendor
3. Go to **Earnings** tab
4. Click **Export**

### Earnings CSV Fields

| Field | Description |
|-------|-------------|
| Order ID | Reference order number |
| Order Date | Date of sale |
| Service | Service name |
| Order Amount | Gross sale amount |
| Commission Rate | Applied commission percentage |
| Commission Amount | Commission deducted |
| Net Earnings | Vendor's earnings |
| Payment Status | Paid/Pending/Cleared |
| Withdrawal Date | Date vendor withdrew earnings (if applicable) |

### Commission Report (Admins Only)

Export platform commission earnings:

1. Go to **WordPress Admin → WP Sell Services → Analytics**
2. Navigate to **Commission** section
3. Select date range
4. Click **Export Commission Report**
5. Select format:
   - **CSV**: Detailed commission data
   - **PDF**: Monthly statement format

### Commission CSV Fields

| Field | Description |
|-------|-------------|
| Date | Transaction date |
| Order ID | Reference order |
| Vendor | Vendor username |
| Category | Service category |
| Order Amount | Gross sale |
| Commission Rate | Rate applied |
| Commission Earned | Platform earnings |
| Custom Rate | If custom rate applied |
| Notes | Additional information |

### Monthly Commission Statement (PDF)

**[PRO]** Generate professional monthly statements:

1. Go to **Analytics → Commission**
2. Select month
3. Click **Generate Monthly Statement**
4. PDF includes:
   - Month summary
   - Daily commission breakdown
   - Commission by vendor
   - Commission by category
   - Commission rate analysis
   - Month-over-month comparison
   - Charts and visualizations

Perfect for accounting and bookkeeping.

## Exporting Vendor Performance Reports

### Vendor Performance Export

**[PRO]** Comprehensive vendor analytics:

1. Go to **WordPress Admin → WP Sell Services → Vendors**
2. Click **Export Vendor Report**
3. Select export options:
   - All vendors or specific vendor
   - Date range
   - Metrics to include
4. Choose format (CSV or PDF)

### Vendor Report CSV Fields

| Field | Description |
|-------|-------------|
| Vendor ID | Unique vendor identifier |
| Vendor Name | Display name |
| Email | Contact email |
| Join Date | Registration date |
| Vendor Tier | Current tier level |
| Active Services | Number of active services |
| Total Orders | All-time orders |
| Orders (Period) | Orders in selected period |
| Revenue (Period) | Revenue in selected period |
| Completion Rate | Percentage of completed orders |
| Average Rating | Overall star rating |
| Total Reviews | Number of reviews |
| Response Time | Average response time |
| Dispute Rate | Percentage of disputed orders |
| Vacation Mode | Currently in vacation mode |

### Vendor Performance PDF

Professional reports for:
- **Vendor Review Meetings**: Share performance with vendors
- **Top Performer Recognition**: Generate certificates/badges
- **Problem Vendor Identification**: Flag for review

PDF includes:
- Vendor profile summary
- Performance scorecard
- Revenue trends chart
- Order completion timeline
- Rating breakdown
- Top services list
- Improvement recommendations

## Exporting Service Statistics

### Service Report Export

Analyze service performance across your marketplace:

1. Go to **WordPress Admin → WP Sell Services → Services**
2. Apply filters (optional):
   - Category
   - Vendor
   - Status (active/draft)
   - Sort by (views, orders, revenue)
3. Click **Export Services**
4. Select format

### Service CSV Fields

| Field | Description |
|-------|-------------|
| Service ID | Unique identifier |
| Service Name | Title |
| Vendor | Vendor username |
| Category | Service category |
| Status | Published/Draft/Pending |
| Created Date | Publication date |
| Total Views | All-time views |
| Views (Period) | Views in date range |
| Total Orders | All-time orders |
| Orders (Period) | Orders in date range |
| Conversion Rate | (Orders ÷ Views) × 100 |
| Revenue (Period) | Total revenue |
| Average Rating | Star rating |
| Reviews | Number of reviews |
| Basic Price | Starting price |
| Standard Price | Mid-tier price |
| Premium Price | Top-tier price |

## Export Scheduling

**[PRO]** Automate recurring exports:

### Setting Up Scheduled Exports

1. Go to **WordPress Admin → WP Sell Services → Settings**
2. Navigate to **Export Schedules** tab
3. Click **Add Schedule**
4. Configure schedule:

**Schedule Settings:**

| Setting | Options |
|---------|---------|
| Report Type | Orders, Earnings, Commissions, Vendors, Services |
| Frequency | Daily, Weekly, Monthly |
| Day/Time | Specific day and time to generate |
| Date Range | Last 7 days, Last month, Custom |
| Format | CSV, PDF, or Both |
| Recipients | Email addresses (comma-separated) |
| FTP Upload | **[PRO]** Upload to FTP/SFTP server |
| Cloud Storage | **[PRO]** Upload to cloud storage |

5. Click **Save Schedule**

### Example Schedules

**Weekly Orders Report:**
- Report: Orders (CSV)
- Frequency: Weekly (Monday 9:00 AM)
- Range: Previous 7 days
- Recipients: accounting@example.com

**Monthly Commission Statement:**
- Report: Commission (PDF)
- Frequency: Monthly (1st day, 10:00 AM)
- Range: Previous month
- Recipients: owner@example.com, bookkeeper@example.com

**Daily Sales Summary:**
- Report: Orders (CSV + PDF)
- Frequency: Daily (6:00 PM)
- Range: Today
- Recipients: sales@example.com

### Managing Scheduled Exports

View and manage schedules:
- **Active**: Schedule is running
- **Paused**: Temporarily disabled
- **Failed**: Last export had errors

Actions:
- **Edit**: Modify schedule settings
- **Pause/Resume**: Temporarily disable
- **Run Now**: Trigger immediate export
- **Delete**: Remove schedule

## Privacy and GDPR Compliance

### GDPR-Compliant Exports

WP Sell Services ensures data exports comply with privacy regulations:

**Personal Data Handling:**
- Exports include only necessary fields
- Buyer/vendor names can be anonymized
- Email addresses can be redacted
- IP addresses excluded by default

### User Data Export Requests

Handle GDPR data export requests:

1. Go to **WordPress Admin → Tools → Export Personal Data**
2. Enter user email
3. Click **Send Request**
4. User receives email with download link
5. Export includes all WP Sell Services data:
   - Profile information
   - Orders (as buyer or vendor)
   - Messages and conversations
   - Reviews written/received
   - Earnings and withdrawals

### Anonymizing Exported Data

When exporting for analysis:

1. Enable **Anonymize Personal Data** in export settings
2. System replaces:
   - Names → "Buyer #123", "Vendor #456"
   - Emails → "buyer-***@***.com"
   - Addresses → Redacted
   - Phone numbers → Redacted

Preserves data analysis while protecting privacy.

## Cloud Storage Integration

**[PRO]** Export directly to cloud storage:

### Supported Providers

- **Amazon S3**: AWS storage buckets
- **Google Cloud Storage**: Google Cloud buckets
- **Dropbox**: Business and personal accounts
- **FTP/SFTP**: Any FTP server

### Configuring Cloud Storage

1. Go to **Settings → Export Schedules → Cloud Storage**
2. Click **Add Storage Provider**
3. Select provider type
4. Enter credentials:
   - **S3**: Access Key, Secret Key, Bucket, Region
   - **Google Cloud**: Service Account JSON, Bucket
   - **Dropbox**: OAuth connection
   - **FTP**: Host, Port, Username, Password
5. Test connection
6. Save configuration

### Using Cloud Storage in Schedules

When creating scheduled exports:
1. Enable **Upload to Cloud Storage**
2. Select configured provider
3. Specify folder path
4. Choose file naming format:
   - `orders-YYYY-MM-DD.csv`
   - `commission-report-YYYY-MM.pdf`

Files upload automatically after generation.

## File Naming and Organization

### Default File Names

Exports use descriptive naming:

```
orders-export-2026-02-03.csv
vendor-earnings-john-doe-2026-01.pdf
commission-report-2026-01.csv
vendor-performance-all-2026-Q1.pdf
service-statistics-web-development-2026-02.csv
```

### Custom Naming Templates

**[PRO]** Configure custom file names:

Available variables:
- `{type}`: Export type (orders, earnings, etc.)
- `{date}`: Current date (YYYY-MM-DD)
- `{month}`: Month (YYYY-MM)
- `{year}`: Year (YYYY)
- `{vendor}`: Vendor username (for vendor exports)
- `{category}`: Service category

Example template:
```
{type}-{vendor}-{month}.csv
→ earnings-john-doe-2026-02.csv
```

## Advanced Export Features

### Multi-Format Exports

Export same data in multiple formats simultaneously:
1. Select **CSV + PDF** in export dialog
2. Receive both files in download or email
3. CSV for data analysis, PDF for presentation

### Batch Exports

Export multiple reports at once:
1. Go to **Analytics → Batch Export**
2. Select report types:
   - ☑ Orders
   - ☑ Earnings
   - ☑ Vendor Performance
   - ☑ Service Statistics
3. Set date range (applies to all)
4. Click **Generate All Exports**
5. Download as ZIP file

### Custom Field Exports

**[PRO]** Choose which fields to include:

1. Click **Customize Fields** in export dialog
2. Select/deselect columns
3. Reorder fields by dragging
4. Save as template for future exports

Useful for:
- Reducing file size
- Removing sensitive data
- Matching import formats
- Compliance requirements

## Excel and Google Sheets Integration

### Opening CSV Exports

**In Microsoft Excel:**
1. Open Excel
2. Go to **Data → From Text/CSV**
3. Select exported CSV file
4. Verify data preview
5. Click **Load**

**In Google Sheets:**
1. Open Google Sheets
2. Go to **File → Import**
3. Upload CSV file
4. Select **Import location**
5. Click **Import data**

### Formatting Exported Data

CSV exports include:
- Headers in first row
- UTF-8 encoding (supports all characters)
- Comma-separated values
- Dates in YYYY-MM-DD format
- Currency without symbols (numeric only)

## Troubleshooting Export Issues

### Export Not Generating

**Symptoms**: Export button does nothing or shows error

**Solutions**:
1. Check PHP memory limit (increase to 256MB or higher)
2. Verify folder permissions (wp-content/uploads writable)
3. Check date range (very large ranges may timeout)
4. Try smaller date range or fewer filters
5. Check error log: **WP Admin → Tools → Site Health → Error Log**

### Email Not Received

**Symptoms**: Scheduled export email doesn't arrive

**Solutions**:
1. Check spam/junk folder
2. Verify recipient email in schedule settings
3. Test WordPress email: **Settings → Export Schedules → Send Test Email**
4. Configure SMTP plugin (recommended for reliability)
5. Check export history: **Export Schedules → History**

### CSV Opens with Garbled Text

**Symptoms**: Special characters display incorrectly

**Solutions**:
1. Open CSV using import wizard (not double-click)
2. Specify UTF-8 encoding when importing
3. Use Google Sheets (handles UTF-8 automatically)
4. In Excel: **Data → From Text/CSV → UTF-8**

### Cloud Upload Failing

**Symptoms**: Export generated but cloud upload fails

**Solutions**:
1. Verify cloud credentials are correct
2. Check storage quota (not full)
3. Test connection: **Cloud Storage → Test Connection**
4. Verify folder path exists
5. Check file size limits for provider

## Best Practices

### Regular Exports

Maintain regular export schedules:
- **Daily**: Order summaries for monitoring
- **Weekly**: Vendor performance for management
- **Monthly**: Commission statements for accounting
- **Quarterly**: Comprehensive platform reports

### Backup Exports

Use exports as data backups:
1. Schedule weekly full data exports
2. Store in cloud storage
3. Retain for 90 days minimum
4. Include all report types
5. Verify exports periodically

### Data Retention

Comply with regulations:
- Export historical data before deletion
- Retain financial records per local laws (typically 7 years)
- Document export and retention policies
- Securely delete old exports when no longer needed

### Security

Protect exported data:
- Never share raw exports publicly
- Anonymize before sharing with third parties
- Use secure transfer methods (encrypted email, SFTP)
- Password-protect sensitive PDFs
- Limit access to export features (admin/manager roles only)

## Related Documentation

- [Vendor Analytics](vendor-analytics.md) - Vendor analytics dashboard
- [Admin Analytics](admin-analytics.md) - Platform analytics dashboard
- [Order Management](../order-workflow/managing-orders.md) - Managing orders
- [Commission Settings](../settings/commission-settings.md) - Configure commission rates
