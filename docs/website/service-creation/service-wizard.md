# Service Creation Wizard

The Service Creation Wizard guides vendors through a 6-step process to create service listings. This wizard ensures you capture all necessary information before publishing your service.

## Wizard Overview

The wizard contains 6 steps that must be completed:

```
Basic Info → Pricing → Gallery → Requirements → Extras & FAQs → Review
```

All steps can be saved as draft and resumed later. The wizard uses Alpine.js for frontend interactivity and auto-saves progress.

## Accessing the Wizard

**Shortcode:**
```
[wpss_service_wizard]              - Create new service
[wpss_service_wizard id="123"]     - Edit existing service
```

**Prerequisites:**
- Must be logged in
- Must be an approved vendor
- Vendor account status must be "Active" (not pending or suspended)

## Step 1: Basic Info

Configure your service title, category, description, and tags.

### Service Title

Your service title appears in marketplace listings and search results.

**Requirements:**
- Maximum 80 characters
- Minimum 10 characters for publishing
- No special validation rules enforced

**Best Practices:**
- Start with "I will..." format
- Include primary keywords
- Be specific about what you deliver
- Avoid all-caps or excessive punctuation

**Example Titles:**
- "I will design a professional WordPress website"
- "I will write SEO-optimized blog posts for your business"
- "I will create a custom logo with unlimited revisions"

### Category

Select a primary category from the available service categories.

**Behavior:**
- Dropdown populated from `wpss_service_category` taxonomy
- Hierarchical categories supported
- Changing category after receiving orders may require admin approval

### Subcategory

Optional subcategory selection. Appears only after selecting a primary category.

**Implementation:**
- Dynamically populated based on parent category selection
- Uses hierarchical taxonomy structure
- Not required for publishing

### Service Description

Detailed description of what you offer.

**Requirements:**
- Maximum 5,000 characters
- Minimum 120 characters for publishing
- Supports HTML formatting via `wp_kses_post()`

**Character Counter:**
- Real-time counter shows `X / 5000` characters
- Warning displayed if under 120 characters minimum

**What to Include:**
- What you will deliver
- Your process or approach
- Requirements from buyers
- What's NOT included
- Timeline expectations

### Tags

Add relevant keywords to improve discoverability.

**Limits:**
- Maximum 5 tags (hardcoded, cannot be changed)
- Comma-separated input
- Stored in `wpss_service_tag` taxonomy

**Example:**
```
web design, responsive, WordPress, custom, business
```

**Note:** Tags are trimmed and limited to first 5 when saving.

## Step 2: Pricing

Create up to 3 pricing packages for your service.

### Package Tiers

Three package tiers are available:

| Tier | Enabled by Default | Can be Disabled |
|------|-------------------|-----------------|
| Basic | Yes (always enabled) | No |
| Standard | No | Yes |
| Premium | No | Yes |

**Limits:**
- Maximum 3 packages (Free and Pro both support 3 packages)
- Basic package is always required
- Standard and Premium are optional

### Package Fields

Each package contains the following fields:

#### Package Name
- Text input
- Defaults to tier name ("Basic", "Standard", "Premium")
- Required for Basic package
- Optional for Standard/Premium if not enabled

#### Package Description
- Textarea input
- Describes what's included in this package
- Required for Basic package
- Optional for Standard/Premium if not enabled

#### Price
- Number input (float)
- Currency symbol shown as prefix
- Minimum $5 for Basic package (validation on publish)
- Step: 0.01 (allows cents)
- Required for Basic package

#### Delivery Time
- Dropdown select
- Options: 1, 2, 3, 5, 7, 14, 21, 30 days
- Required for Basic package
- Stored as number of days

#### Revisions
- Dropdown select
- Options: 0, 1, 2, 3, 5, Unlimited (-1)
- Defaults: Basic (1), Standard (2), Premium (3)
- Optional field

#### Features Included
- Dynamic list of text inputs
- Add/remove feature items
- Each feature is a single line description
- No limit on number of features
- Stored as array in package data

### Enabling Standard/Premium

Standard and Premium packages have an "Enable this package" toggle:

```html
<input type="checkbox" x-model="data.packages.standard.enabled">
```

When disabled, the package is not offered to buyers.

### Package Data Structure

Packages are stored in `_wpss_packages` meta as:

```json
{
  "basic": {
    "enabled": true,
    "name": "Basic",
    "description": "Entry-level package",
    "price": 50.00,
    "delivery_time": 3,
    "revisions": 1,
    "features": ["Feature 1", "Feature 2"]
  },
  "standard": {...},
  "premium": {...}
}
```

## Step 3: Gallery

Upload images and videos to showcase your work.

### Main Image (Featured Image)

- **Required** for publishing
- Recommended dimensions: 800x600px
- Set as WordPress post thumbnail (`set_post_thumbnail`)
- Single image only

### Additional Images

- Maximum 4 images in free version
- **[PRO]** Unlimited images (filter: `wpss_service_max_gallery`)
- Stored in `_wpss_gallery` meta as image IDs

### Image Upload Limits

- Maximum file size: 5MB per image
- Allowed formats: JPG, JPEG, PNG, GIF, WebP
- MIME type validation enforced on upload
- Ownership verification (must be uploaded by current user or admin)

### Video

Add optional video URL.

- Free: 1 video URL
- **[PRO]** 3 video URLs (filter: `wpss_service_max_videos`)
- YouTube and Vimeo embeds only
- URL input field (`esc_url_raw` sanitization)
- No direct video uploads in wizard

### Gallery Data Structure

```json
{
  "main": {
    "id": 123,
    "url": "https://..."
  },
  "images": [
    {"id": 124, "url": "https://..."},
    {"id": 125, "url": "https://..."}
  ],
  "video": "https://youtube.com/watch?v=..."
}
```

## Step 4: Requirements

Define what information you need from buyers before starting work.

### Requirement Fields

Each requirement has:

#### Question
- Text input
- The question buyers will answer
- Required field

#### Answer Type
- Dropdown with 4 options:
  - **Text** - Short text input
  - **Textarea** - Long text input
  - **File** - File upload
  - **Select** - Multiple choice dropdown

**Note:** Only these 4 types are available. Radio and multi-select types do NOT exist.

#### Required Toggle
- Checkbox
- Marks requirement as mandatory or optional
- Buyers must answer required fields to submit requirements

#### Options (for Select type)
- Text input, comma-separated
- Only shown when "Select" type is chosen
- Example: `Option 1, Option 2, Option 3`

### Requirement Limits

- Free: Maximum 5 requirements
- **[PRO]** Unlimited requirements (filter: `wpss_service_max_requirements`)

### File Upload Settings

When buyers upload files for requirements:

- Allowed extensions: 24 types (jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, ppt, pptx, txt, rtf, csv, zip, rar, 7z, mp3, wav, mp4, mov, avi, psd, ai, eps, svg)
- Maximum file size: 50MB per file
- Files are set to `post_status = 'private'` for security
- Multiple files can be uploaded per file requirement

### Requirements Data Structure

```json
[
  {
    "question": "What is your business name?",
    "type": "text",
    "required": true,
    "options": ""
  },
  {
    "question": "Choose your preferred style",
    "type": "select",
    "required": false,
    "options": "Modern, Classic, Minimalist"
  }
]
```

## Step 5: Extras & FAQs

Add optional extras (add-ons) and frequently asked questions.

### Service Extras (Add-ons)

Add-ons are stored in the custom `wpss_service_addons` database table, not post meta.

#### Add-on Limits

- Free: Maximum 3 add-ons
- **[PRO]** Unlimited add-ons (filter: `wpss_service_max_extras`)

#### Wizard Add-on Fields

The wizard exposes simplified fields:

- **Title** - Add-on name
- **Description** - What's included
- **Price** - Additional cost
- **Extra Days** - Additional delivery time

#### Add-on Field Types

Four field types are supported:

| Type | Description | Buyer Interface |
|------|-------------|-----------------|
| `checkbox` | Simple yes/no toggle | Single checkbox |
| `quantity` | Select multiple units | Quantity input with min/max |
| `dropdown` | Pick one option | Dropdown select |
| `text` | Custom text input | Text field |

**Note:** Radio buttons and multi-select checkboxes do NOT exist as field types.

#### Add-on Pricing Types

- **Flat** - Fixed price added to order
- **Percentage** - Percentage of package price **[PRO]**
- **Quantity-based** - Price multiplied by quantity **[PRO]**

### FAQs

Frequently Asked Questions section.

#### FAQ Limits

- Free: Maximum 5 FAQs
- **[PRO]** Unlimited FAQs (filter: `wpss_service_max_faq`)

#### FAQ Fields

- **Question** - Text input
- **Answer** - Textarea (supports HTML via `wp_kses_post`)

#### FAQ Data Structure

```json
[
  {
    "question": "Do you offer refunds?",
    "answer": "Yes, we offer full refunds within 14 days..."
  }
]
```

Stored in `_wpss_faqs` post meta.

## Step 6: Review

Final review step before publishing.

### Review Display

Shows summary of:

- Service preview card with main image and title
- Starting price (from Basic package)
- Completion checklist with real-time validation

### Completion Checklist

The wizard validates:

- [ ] Service title (10+ characters)
- [ ] Category selected
- [ ] Description (120+ characters)
- [ ] Basic package pricing complete
- [ ] Main image uploaded

### Validation Errors

If validation fails, errors are displayed in an errors list. The "Publish Service" button remains enabled but AJAX handler will reject submission.

### Save Draft

At any step, click "Save Draft" to save progress without publishing.

- Status: `draft`
- Validation: Not enforced
- Visibility: Only visible to author and admins

### Publish Service

Submits service for publishing or moderation.

**If moderation is enabled** (admin setting):
- Post status: `pending`
- Moderation status meta: `pending`
- Admin receives notification
- Vendor receives "submitted for review" message

**If moderation is disabled:**
- Post status: `publish`
- Service goes live immediately
- Syncs to WooCommerce product (if WooCommerce active)

## Wizard Limits Summary

| Feature | Free | Pro Filter |
|---------|------|-----------|
| Packages | 3 | `wpss_service_max_packages` (still 3) |
| Gallery Images | 4 | `wpss_service_max_gallery` (-1 = unlimited) |
| Videos | 1 | `wpss_service_max_videos` (3) |
| Extras/Add-ons | 3 | `wpss_service_max_extras` (-1 = unlimited) |
| FAQs | 5 | `wpss_service_max_faq` (-1 = unlimited) |
| Requirements | 5 | `wpss_service_max_requirements` (-1 = unlimited) |
| Tags | 5 | Hardcoded (no filter) |
| Description | 5,000 chars | No Pro extension |

## Wizard Navigation

### Navigation Buttons

- **Previous** - Go to previous step (hidden on first step)
- **Save Draft** - Save progress without validation
- **Continue** - Proceed to next step (hidden on last step)
- **Publish Service** - Submit for publishing (only on last step)

### Progress Indicator

Top of wizard shows all 6 steps with:

- Current step highlighted
- Completed steps marked with checkmark
- Clickable to jump between steps
- Icons for each step (Dashicons)

## Technical Details

### Shortcode Implementation

**PHP Handler:**
```php
ServiceWizard::render_wizard( $atts )
```

**Attributes:**
- `id` - Service post ID for editing (optional)

### JavaScript

Uses Alpine.js for reactive data binding:

**Component:** `wpssServiceWizard()`

**Data Structure:**
```javascript
{
  currentStep: 'basic',
  data: {
    title: '',
    description: '',
    category: '',
    subcategory: '',
    tags: '',
    packages: {...},
    gallery: {...},
    requirements: [],
    extras: [],
    faqs: []
  },
  saving: false,
  publishing: false
}
```

### AJAX Endpoints

| Action | Hook | Purpose |
|--------|------|---------|
| `wpss_wizard_save_draft` | `wp_ajax_wpss_wizard_save_draft` | Save draft |
| `wpss_wizard_publish` | `wp_ajax_wpss_wizard_publish` | Publish service |
| `wpss_wizard_upload_gallery` | `wp_ajax_wpss_wizard_upload_gallery` | Upload image |
| `wpss_wizard_remove_gallery` | `wp_ajax_wpss_wizard_remove_gallery` | Remove image |

### Nonce Verification

All AJAX requests verified with:
```php
check_ajax_referer( 'wpss_service_wizard', 'nonce' );
```

Nonce field: `wpss_wizard_nonce`

## Pro Features

**[PRO]** features are enabled via filters and are NOT implemented in the free wizard UI:

| Feature | Filter/Hook | Default Free |
|---------|-------------|--------------|
| AI Title Suggestions | `wpss_service_wizard_features` → `ai_title` | `false` |
| Templates | `wpss_service_wizard_features` → `templates` | `false` |
| Bulk Upload | `wpss_service_wizard_features` → `bulk_upload` | `false` |
| Direct Video Upload | `wpss_service_wizard_features` → `video_upload` | `false` |
| Custom Package Fields | `wpss_service_wizard_features` → `custom_fields` | `false` |
| Scheduled Publishing | `wpss_service_wizard_features` → `scheduled_publish` | `false` |

These features are not visible in the free wizard and must be implemented by the Pro plugin.

## Common Issues

### Service Won't Save

**Causes:**
- Session expired
- Nonce verification failed
- Missing required meta fields
- Permission check failed

**Fix:**
1. Check vendor account status (must be "Active")
2. Refresh page and try again
3. Check browser console for JavaScript errors
4. Verify user has `wpss_vendor` role or is admin

### Images Won't Upload

**Causes:**
- File size exceeds 5MB
- Unsupported image format
- Upload directory permissions
- Attachment ownership verification failed

**Fix:**
1. Compress images to under 5MB
2. Use JPG, PNG, GIF, or WebP only
3. Check `/wp-content/uploads/` permissions
4. Try different image

### Cannot Publish

**Causes:**
- Validation errors not visible
- Required fields incomplete
- Vendor account suspended
- Moderation queue full

**Fix:**
1. Review all validation errors on Step 6
2. Complete Basic package fully
3. Check vendor dashboard for account status
4. Contact admin if issue persists

## Related Documentation

- **[Pricing & Packages](./pricing-packages.md)** - Package configuration details
- **[Service Add-ons](./service-addons.md)** - Add-on field types and pricing
- **[Service Media](./service-media.md)** - Gallery and video requirements
- **[Requirements & FAQs](./service-requirements-faqs.md)** - Buyer requirements setup
- **[Publishing & Moderation](./publishing-moderation.md)** - Service approval workflow
