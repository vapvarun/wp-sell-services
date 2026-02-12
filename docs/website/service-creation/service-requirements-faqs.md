# Service Requirements & FAQs

Define what information you need from buyers before starting work, and answer common questions.

## Requirements Overview

Requirements are custom questions buyers must answer when placing an order. They ensure you get all necessary information upfront.

### Requirements Limits

| Version | Maximum Requirements |
|---------|---------------------|
| Free | 5 requirements |
| **[PRO]** | Unlimited (filter: `wpss_service_max_requirements` = `-1`) |

## Requirement Field Types

Four field types supported by the wizard:

### 1. Text (Short Answer)

Single-line text input for brief responses.

**Use Cases:**
- Business name
- Domain name
- Social media handle
- Contact email

**Example:**
```json
{
  "question": "What is your business name?",
  "type": "text",
  "required": true,
  "options": ""
}
```

### 2. Textarea (Long Answer)

Multi-line text area for detailed responses.

**Use Cases:**
- Project description
- Design preferences
- Content requirements
- Target audience details

**Example:**
```json
{
  "question": "Describe your project in detail",
  "type": "textarea",
  "required": true,
  "options": ""
}
```

### 3. File Upload

Allow buyers to upload files.

**Use Cases:**
- Logo files
- Brand guidelines
- Reference images
- Content documents

**File Upload Limits:**
- **24 allowed extensions** (not 6 as in old docs)
- Maximum file size: **50MB** (not 10MB)
- Extensions: jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, ppt, pptx, txt, rtf, csv, zip, rar, 7z, mp3, wav, mp4, mov, avi, psd, ai, eps, svg

**Example:**
```json
{
  "question": "Upload your company logo",
  "type": "file",
  "required": false,
  "options": ""
}
```

### 4. Select (Multiple Choice)

Dropdown with predefined options.

**Use Cases:**
- Style preference
- Color scheme
- Target platform
- Industry type

**Example:**
```json
{
  "question": "Choose your preferred style",
  "type": "select",
  "required": true,
  "options": "Modern, Classic, Minimalist, Vintage"
}
```

**Options Format:** Comma-separated values

## Field Types NOT Available

The wizard does NOT support:

- **Radio buttons** - Not exposed in wizard UI
- **Multi-select checkboxes** - Not implemented
- **Date picker** - Not available
- **Number input** - Use text field instead

Only the 4 types listed above are available in the wizard.

## Requirement Configuration

### Question Field

- Text input
- The question buyers will see
- Required field
- Used as field key when storing responses

### Type Dropdown

Four options:
- Text
- Textarea
- File
- Select

### Required Checkbox

- Toggle for mandatory vs optional
- If checked, buyers must answer before submitting requirements
- Validation enforced in `RequirementsService::validate()`

### Options Field

- Text input
- Only shown when type is "Select"
- Comma-separated list
- Example: `Option 1, Option 2, Option 3`

## Requirements Data Structure

Stored in `_wpss_requirements` post meta:

```json
[
  {
    "question": "What is your business name?",
    "type": "text",
    "required": true,
    "options": ""
  },
  {
    "question": "Choose industry",
    "type": "select",
    "required": false,
    "options": "Tech, Healthcare, Finance, Retail"
  },
  {
    "question": "Upload brand guidelines",
    "type": "file",
    "required": false,
    "options": ""
  }
]
```

## Buyer Submission Process

### Order Requirements Submission

After purchasing, buyer fills requirement form:

1. Order created with status `pending_requirements`
2. Buyer accesses requirements form
3. Buyer answers all required questions
4. Buyer uploads any required files
5. Submits requirements
6. Order status changes to `in_progress`
7. Vendor receives notification to start work

### Late Submission

If setting enabled (`wpss_allow_late_requirements_submission`):

- Buyers can submit requirements after order starts
- Only if no requirements submitted yet
- Vendor gets notification of late submission
- Order continues with new information

### File Uploads

Files uploaded via requirements are:

- Validated against 24 allowed extensions
- Limited to 50MB per file
- Set to `post_status='private'` for security
- Accessible only to buyer and vendor
- Stored in WordPress media library

## Requirements Service Class

**Location:** `src/Services/RequirementsService.php`

**Key Methods:**
```php
$req_service = new RequirementsService();

// Get service requirement fields
$fields = $req_service->get_service_fields( $service_id );

// Submit requirements for order
$result = $req_service->submit( $order_id, $field_data, $files );

// Check if order has requirements
$has_reqs = $req_service->has_requirements( $order_id );

// Get formatted requirements for display
$formatted = $req_service->get_formatted( $order_id );

// Validate requirements
$validation = $req_service->validate( $fields, $field_data, $files );
```

## Validation

Requirements validated in `RequirementsService::validate()`:

**Required Field Check:**
```php
if ( $required && empty( $value ) ) {
    $errors[ $field_key ] = sprintf( '%s is required.', $field_label );
}
```

**Select Type Validation:**
```php
$choices = explode( ',', trim( $field['options'] ) );
if ( ! in_array( $value, $choices, true ) ) {
    $errors[ $field_key ] = 'Invalid selection.';
}
```

**File Upload Validation:**
```php
// Check file extension
if ( ! in_array( $ext, $allowed_types, true ) ) {
    continue; // Skip invalid file
}

// Check file size (50MB max)
if ( $file['size'] > 50 * 1024 * 1024 ) {
    continue;
}
```

## FAQs Overview

Frequently Asked Questions section helps buyers make informed decisions.

### FAQ Limits

| Version | Maximum FAQs |
|---------|--------------|
| Free | 5 FAQs |
| **[PRO]** | Unlimited (filter: `wpss_service_max_faq` = `-1`) |

## FAQ Configuration

### Question Field

- Text input
- The FAQ question
- Required

### Answer Field

- Textarea
- The answer to the question
- Supports HTML via `wp_kses_post()`
- Required

## FAQ Data Structure

Stored in `_wpss_faqs` post meta:

```json
[
  {
    "question": "Do you offer refunds?",
    "answer": "Yes, we offer full refunds within 14 days if you're not satisfied."
  },
  {
    "question": "How long will it take?",
    "answer": "Standard delivery is 5 days. Express options available."
  }
]
```

## Best Practices

### Requirements

**Ask Only What You Need:**
- Too many questions reduce conversion
- Focus on essential information
- Combine related questions

**Be Specific:**
- "What colors do you prefer?" vs "Tell me about your project"
- Give examples in question text
- Explain why you need the information

**Use Correct Field Types:**
- Text for short answers (name, email)
- Textarea for descriptions
- Select for predefined choices
- File for reference materials

**Order Logically:**
- Most important questions first
- Group related questions
- Optional questions at end

### FAQs

**Answer Common Questions:**
- Review buyer messages for patterns
- Address concerns proactively
- Update based on questions received

**Keep Answers Concise:**
- 2-3 sentences per answer
- Use bullet points for clarity
- Link to detailed info if needed

**Cover These Topics:**
- Refund policy
- Delivery timeframes
- Revision process
- What's included/excluded
- Communication process

**Example FAQ Structure:**
```
Q: What do you need from me to start?
A: After ordering, you'll fill a simple form with your business name,
preferred colors, and any reference examples. Takes about 5 minutes.

Q: How many revisions are included?
A: Basic package includes 2 revisions, Standard includes 3,
and Premium includes unlimited revisions.

Q: Do you offer rush delivery?
A: Yes! Add the Express Delivery option for 24-hour turnaround
at checkout for an additional $25.
```

## Common Issues

### Requirements Not Saving

**Causes:**
- Limit of 5 requirements reached (free version)
- Missing question text
- Invalid field type

**Fix:**
1. Remove unused requirements
2. Ensure all questions have text
3. Select valid field type
4. Upgrade to Pro for unlimited

### File Upload Failing

**Causes:**
- File exceeds 50MB
- Unsupported file type (not in 24 allowed extensions)
- Upload directory permissions

**Fix:**
1. Compress files under 50MB
2. Use supported file types
3. Check `/wp-content/uploads/` permissions
4. Try different file format

### Buyer Can't Submit Requirements

**Causes:**
- Required fields not filled
- Invalid file upload
- Order status not `pending_requirements`
- Validation errors

**Fix:**
1. Check all required fields completed
2. Verify file uploads successful
3. Confirm order status correct
4. Review browser console for errors

## Technical Details

### Requirements Storage

**Service Requirements:** `_wpss_requirements` post meta (array of fields)

**Order Requirements:** `wpss_order_requirements` custom table

**Table Structure:**
```sql
CREATE TABLE wpss_order_requirements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  field_data TEXT, -- JSON of answers
  attachments TEXT, -- JSON of uploaded files
  submitted_at DATETIME NOT NULL
)
```

### Allowed File Types

Complete list of 24 allowed extensions:

```php
$types = array(
    'jpg', 'jpeg', 'png', 'gif', 'webp',  // Images
    'pdf',                                 // PDF
    'doc', 'docx',                        // Word
    'xls', 'xlsx',                        // Excel
    'ppt', 'pptx',                        // PowerPoint
    'txt', 'rtf', 'csv',                  // Text
    'zip', 'rar', '7z',                   // Archives
    'mp3', 'wav',                         // Audio
    'mp4', 'mov', 'avi',                  // Video
    'psd', 'ai', 'eps', 'svg'             // Design
);
```

Filter available: `wpss_requirements_allowed_file_types`

## Related Documentation

- **[Service Wizard](./service-wizard.md)** - Requirements step in wizard
- **[Order Management](../order-management/order-workflow.md)** - Requirements submission workflow
- **[Publishing & Moderation](./publishing-moderation.md)** - Service approval
