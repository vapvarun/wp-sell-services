# Setting Up Cloud Storage **[PRO]**

Connect your marketplace to Amazon S3, Google Cloud Storage, or DigitalOcean Spaces for scalable, fast file delivery.

---

![Cloud Storage settings on the Advanced tab](../images/settings-cloud-storage-currency.png)

## Before You Start

You will need:
- WP Sell Services Pro active and licensed
- An account with your chosen cloud provider
- Access credentials (API keys or service account) from the provider

---

## Amazon S3

### Step 1: Create an S3 Bucket

1. Sign in to the [AWS Console](https://aws.amazon.com/console/)
2. Go to **S3** and click **Create Bucket**
3. Choose a bucket name (e.g., "yoursite-deliveries") and region
4. Keep the default security settings (block public access)
5. Click **Create Bucket**

### Step 2: Create Access Keys

1. Go to **IAM > Users > Add User**
2. Create a user with programmatic access
3. Attach the **AmazonS3FullAccess** policy (or a custom policy limited to your bucket)
4. Save the **Access Key ID** and **Secret Access Key**

### Step 3: Configure in WP Sell Services

1. Go to **Sell Services > Settings > Advanced**
2. Select **Amazon S3** as the provider
3. Enter your Access Key ID, Secret Access Key, bucket name, and region
4. Click **Test Connection** to verify
5. Save Changes

---

## Google Cloud Storage

### Step 1: Create a Storage Bucket

1. Sign in to the [Google Cloud Console](https://console.cloud.google.com/)
2. Go to **Cloud Storage > Buckets > Create**
3. Choose a bucket name and location
4. Set access control to "Uniform"
5. Click **Create**

### Step 2: Create a Service Account

1. Go to **IAM & Admin > Service Accounts > Create Service Account**
2. Give it a name like "wpss-storage"
3. Grant the **Storage Object Admin** role
4. Create a JSON key and download it

### Step 3: Configure in WP Sell Services

1. Go to **Sell Services > Settings > Advanced**
2. Select **Google Cloud Storage** as the provider
3. Upload or paste your service account JSON key
4. Enter your bucket name
5. Click **Test Connection** to verify
6. Save Changes

---

## DigitalOcean Spaces

### Step 1: Create a Space

1. Sign in to the [DigitalOcean Control Panel](https://cloud.digitalocean.com/)
2. Go to **Spaces** and click **Create a Space**
3. Choose a datacenter region
4. Give it a name (e.g., "yoursite-deliveries")
5. Click **Create a Space**

### Step 2: Generate API Keys

1. Go to **API > Spaces Keys > Generate New Key**
2. Save the **Key** and **Secret**

### Step 3: Configure in WP Sell Services

1. Go to **Sell Services > Settings > Advanced**
2. Select **DigitalOcean Spaces** as the provider
3. Enter your Key, Secret, Space name, and region
4. Click **Test Connection** to verify
5. Save Changes

---

## After Setup

**Saving credentials does not move delivery files.** Deliveries are still stored
in the WordPress media library. What you have connected is the bucket the REST
endpoint `POST /wpss-pro/v1/storage/upload` writes to, for your own
integrations -- nothing in the plugin calls it yet.

### Test It

Do not test by uploading a delivery: it will land locally and that is expected,
not a misconfiguration.

To confirm the credentials are good, use **Test Connection** on the settings
card. That really does reach your bucket, and a pass means the credentials,
region and permissions are right.

### Fallback Behavior

The upload endpoint falls back to the media library when the provider is set to
Local or is not registered. If a **configured** provider fails -- bad
credentials, network error -- it returns an error rather than silently writing
locally, so a broken bucket cannot look like a working one.

There is no admin warning for a failing provider today. Watch for it in your own
integration's error handling.

---

## Choosing a Provider

| Factor | Amazon S3 | Google Cloud | DigitalOcean Spaces |
|--------|-----------|-------------|-------------------|
| Pricing | Pay per use | Pay per use | $5/month flat start |
| Ease of setup | Moderate | Moderate | Simple |
| CDN included | Extra (CloudFront) | Extra (Cloud CDN) | Included |
| Best for | Large, global marketplaces | Asia-Pacific focus | Small to medium marketplaces |
| S3-compatible | Yes (native) | No | Yes |

---

## Troubleshooting

**"Connection failed" when testing?**
Double-check your credentials (access key, secret, bucket name, region). Make sure the bucket exists and the credentials have permission to read and write to it.

**Files not uploading to cloud?**
Check that cloud storage is selected as the active provider in settings. Also verify your server can make outbound HTTPS connections (some hosting providers block them).

**Slow downloads?**
Enable the CDN option for your provider. S3 uses CloudFront, GCS uses Cloud CDN, and DigitalOcean Spaces includes a CDN by default.
