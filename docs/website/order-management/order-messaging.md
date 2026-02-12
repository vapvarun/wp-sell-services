# Order Messaging

Effective communication is essential for successful orders. This guide covers the built-in messaging system that keeps vendors and buyers connected throughout the order lifecycle.

## Messaging System Overview

WP Sell Services includes an order-based messaging system that allows:
- Direct communication between buyer and vendor
- File attachments in messages
- Real-time message notifications
- Message history preservation
- Admin message monitoring (view only)

**Key Features:**
- Threaded conversations per order
- Email notifications for new messages
- File sharing (documents, images, references)
- Message read status tracking
- Unread count badges
- Mobile-responsive interface

![Order messaging interface](../images/frontend-order-messaging.png)

## Conversation Creation

**Automatic:** A conversation is created automatically when an order is placed:

```php
// Triggered by OrderWorkflowManager when payment confirmed
$conversation = ConversationService::create_for_order($order_id);
```

**Conversation Data:**
- Subject: "Order {ORDER_NUMBER}"
- Participants: `[customer_id, vendor_id]`
- Order reference: Linked to order record
- Status: Open by default, closed when order completed

## Accessing Order Messages

### Vendor Access

**From Vendor Dashboard:**
1. Go to **Vendor Dashboard → Orders**
2. Click on order
3. Click **Messages** tab
4. View conversation history
5. Type and send messages

**Quick Message (Theme-Dependent):**
- Some themes show **Message** button on order card
- Click to open messaging modal
- Send quick message without opening full order

![Vendor message access](../images/admin-vendor-message-access.png)

### Buyer Access

**From Buyer Dashboard:**
1. Go to **Buyer Dashboard → My Orders**
2. Click on order
3. Click **Messages** tab
4. View and reply to messages

**Message Notifications:**
- Notification badge on dashboard
- Unread message count per order
- Email notifications (configurable)

### Admin Access

**From Admin Panel:**
1. Go to **WP Sell Services → Orders**
2. Open any order
3. View **Messages** tab
4. See all buyer-vendor communication

**Important:** Admins can VIEW messages but cannot REPLY directly. This is by design to keep conversations between the two parties. Admin intervention should happen through dispute resolution or direct user contact.

**Admin Capabilities:**
- View all messages in any conversation
- See message timestamps and read status
- Download message attachments
- Monitor for policy violations
- Export conversation history

![Admin message monitoring](../images/admin-message-monitoring.png)

## Message Types

The system supports 6 message types (stored in database `type` field):

### 1. Text Message

**Type:** `text`

**Description:** Standard text communication between parties

**Sent By:** Buyer or Vendor

**Example:**
```
"I've reviewed your requirements and will start work tomorrow."
```

**Properties:**
- `sender_id`: User ID who sent message
- `content`: Message text
- `attachments`: Empty array
- `read_by`: Array of user IDs who read it

### 2. Attachment Message

**Type:** `attachment`

**Description:** Message with file attachments

**Sent By:** Buyer or Vendor

**Example:**
```
"Here are the reference files you requested."
+ logo.png (45 KB)
+ brand-guidelines.pdf (2.3 MB)
```

**Properties:**
- `attachments`: Array of file objects with `id`, `name`, `url`, `type`, `size`
- `content`: Optional text accompanying files

### 3. Delivery Message

**Type:** `delivery`

**Description:** Vendor submits order delivery

**Sent By:** System (triggered by vendor action)

**Example:**
```
"Delivery submitted - Version 1
Your order has been delivered. Please review and accept or request revision."
+ final-design.zip (12 MB)
```

**Properties:**
- `sender_id`: Vendor ID
- `content`: Delivery message from vendor
- `attachments`: Delivery files
- `metadata`: Delivery version, status

**Note:** Created automatically when `DeliveryService::submit()` is called.

### 4. Revision Request Message

**Type:** `revision`

**Description:** Buyer requests changes to delivery

**Sent By:** System (triggered by buyer action)

**Example:**
```
"Revision requested
Please make the following changes:
- Adjust the header color to match brand
- Fix typo on page 3"
```

**Properties:**
- `sender_id`: Buyer ID
- `content`: Revision feedback from buyer
- `metadata`: Revision count

**Note:** Created automatically when `DeliveryService::request_revision()` is called.

### 5. Status Change Message

**Type:** `status_change`

**Description:** Order status transition notification

**Sent By:** System

**Example:**
```
"Order status changed from In Progress to Pending Approval"
```

**Properties:**
- `sender_id`: 0 (system)
- `content`: Status change description
- `metadata`: `old_status`, `new_status`

**Note:** Created automatically by `OrderService::log_status_change()` on every status transition.

### 6. System Message

**Type:** `system`

**Description:** Automated notifications and admin messages

**Sent By:** System (sender_id = 0)

**Examples:**
```
"Extension requested: 3 days. Reason: Need more time to finalize design"
"Extension approved: Deadline extended by 3 days."
"Order marked as late - deadline exceeded"
"Requirements timeout: Order auto-started after 7 days"
```

**Properties:**
- `sender_id`: 0 (indicates system message)
- `content`: System-generated text
- `metadata`: Context-specific data

**Use Cases:**
- Extension requests/responses
- Deadline notifications
- Milestone updates **[PRO]**
- Auto-completion notices
- Timeout actions

## Sending Messages

### Composing a Message

1. Open order messages tab
2. Type message in text area
3. (Optional) Format message:
   - Line breaks for paragraphs
   - Keep it professional
4. (Optional) Attach files
5. Click **Send Message**

![Message composition](../images/frontend-message-compose.png)

### File Attachments

**Allowed File Types:**
```
Images: jpg, jpeg, png, gif, webp
Documents: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv
Archives: zip, rar, 7z
Media: mp3, wav, mp4, mov, avi, webm
Design: psd, ai, eps, sketch, fig
Other: json, xml
```

**Removed for Security:**
- ❌ SVG - Can contain embedded JavaScript (XSS risk)
- ❌ HTML - Executable code risk
- ❌ CSS - Can contain expressions/imports
- ❌ JS - Executable JavaScript

**File Size Limit:** Determined by WordPress `upload_max_filesize` setting (typically 2-10MB default, configurable up to 50MB for requirements)

**Attachment Process:**
1. File validated client-side (type, size)
2. Uploaded via WordPress media library
3. Attachment created with `post_status: 'private'`
4. Stored with order reference in metadata
5. URL and metadata returned to message system

### Message Validation

**Required:**
- Message content OR attachment (cannot be empty)
- Sender must be conversation participant
- Conversation must not be closed

**Blocked Scenarios:**
- Admin trying to reply (can only view)
- Non-participant trying to send
- Conversation marked as closed
- Order in `cancelled` status

### Read Status

**Tracking:**
- `read_by` field stores user IDs who have read the message
- Sender automatically marked as read
- Recipients marked as read when they view conversation
- Unread count calculated per user

**Mark as Read:**
```php
ConversationService::mark_as_read($conversation_id, $user_id);
```

**Result:**
- All unread messages marked as read for that user
- Unread count for user reset to 0
- Unread badge updated in UI

## Notifications

### Email Notifications

**Trigger:** New message sent (excluding system messages from sender perspective)

**Recipients:**
- Other participant (not the sender)
- Only if email notifications enabled

**Email Content:**
- Subject: "New message on Order {ORDER_NUMBER}"
- Sender name
- Message excerpt (first 100 characters)
- Link to view full message in dashboard

**Configuration:** Settings → Emails → New Message

### In-App Notifications

**Unread Count Badge:**
- Shows on dashboard menu/icon
- Updates in real-time (if polling enabled)
- Persists across page loads

**Notification System:**
- Integrated with `NotificationService`
- Creates notification record when message sent
- Links to order messages tab
- Marked as read when user views conversation

## Best Practices

### Professional Communication

✅ **Do:**
- Use proper grammar and spelling
- Be polite and courteous
- Respond within 24 hours
- Stay on-topic (order-related)
- Use paragraphs for readability
- Thank the other party
- Provide clear, specific feedback

❌ **Don't:**
- Use all caps (LOOKS LIKE SHOUTING)
- Be rude or confrontational
- Share personal contact info to bypass platform
- Discuss off-platform payments
- Use offensive language
- Ignore messages
- Request work outside order scope

### Message Templates

**Vendor: Order Started**
```
Hi [Buyer Name],

Thank you for your order! I've reviewed your requirements and have everything I need to get started.

I'll deliver your [deliverable] by [deadline date]. I'll keep you updated on progress.

Feel free to reach out if you have any questions!

Best regards,
[Your Name]
```

**Vendor: Progress Update**
```
Hi [Buyer Name],

Quick update on your order:

✅ Completed: [Task 1]
✅ Completed: [Task 2]
🔄 In Progress: [Task 3]
📋 Next: [Task 4]

Everything is on track for delivery by [deadline].

Best regards,
[Your Name]
```

**Buyer: Question**
```
Hi [Vendor Name],

I have a quick question about [specific aspect]:

[Your question]

Please let me know when you have a chance.

Thanks!
[Your Name]
```

**Buyer: Revision Request**
```
Hi [Vendor Name],

Thank you for the delivery! It's looking great overall. I'd like to request a few minor revisions:

1. [Specific change needed]
2. [Specific change needed]
3. [Specific change needed]

Please let me know if you have any questions about these changes.

Thanks!
[Your Name]
```

## Conversation Management

### Closing Conversations

**Automatic Closure:**
- Conversation closed when order reaches `completed` status
- Prevents further messages after order finished
- Read-only mode for participants

**Manual Closure:**
- Admin can manually close conversation
- Useful for cancelled or disputed orders
- Messages still viewable, cannot add new ones

### Reopening Conversations

Not supported by default. Once closed, conversations remain read-only.

**Workaround for Admin:**
- Update order status to allow messaging
- Contact parties via email if needed
- Use dispute system for post-completion issues

### Message History

**Retention:** Messages stored permanently in database

**Deletion:** Admin can delete individual messages if needed (database access required)

**Export:** Admin can export conversation history:
1. View conversation in admin
2. Click **Export** button
3. Download as CSV or PDF

## Privacy & Security

### Access Control

**Who Can View:**
- Order buyer (customer)
- Order vendor
- Site administrators

**Who Cannot View:**
- Other buyers
- Other vendors
- Guests/non-logged-in users

**Enforcement:**
```php
// Conversation model checks participant list
public function can_view(int $user_id): bool {
    return in_array($user_id, $this->participants) || user_can($user_id, 'manage_options');
}
```

### Data Security

**Message Storage:**
- Stored in `wpss_messages` table
- Content is plain text (no encryption at rest)
- Access controlled at application level

**File Attachments:**
- Stored in WordPress uploads directory
- Created as `private` posts
- Direct URL access blocked for non-participants
- Served via PHP with permission checks

**XSS Prevention:**
- Message content escaped on output
- HTML tags stripped from user input
- Attachments validated for file type

## Troubleshooting

### Messages Not Sending

**Symptoms:** Send button doesn't work or message disappears

**Causes:**
- JavaScript error
- Empty message (no content or file)
- User not logged in
- Conversation closed

**Solutions:**
1. Check browser console for JavaScript errors
2. Ensure message has content or attachment
3. Verify user is logged in
4. Check conversation status in database

### Email Notifications Not Working

**Symptoms:** Recipient not receiving email notifications

**Causes:**
- Email notifications disabled in settings
- Email address incorrect
- Server email deliverability issue
- Email in spam folder

**Solutions:**
1. Check Settings → Emails: Ensure "New Message" enabled
2. Verify user email address in profile
3. Test WordPress email: Send test email
4. Check spam/junk folder
5. Configure SMTP plugin (WP Mail SMTP recommended)

### Unread Count Not Updating

**Symptoms:** Badge shows incorrect unread count

**Causes:**
- Cache not cleared
- Mark as read function failed
- Database sync issue

**Solutions:**
1. Refresh page (Ctrl+F5 / Cmd+R)
2. Clear browser cache
3. Clear WordPress object cache
4. Run `ConversationService::mark_as_read()` manually

### Admin Cannot Reply

**Symptoms:** Admin sees messages but no reply box

**This is expected behavior.** Admins can VIEW conversations but not participate.

**Reason:** Conversations are between buyer and vendor only. Admin involvement should be through:
- Dispute resolution system
- Direct email contact
- Admin notes on order

**Workaround (if absolutely needed):**
- Temporarily change admin user ID to match vendor ID (not recommended)
- Use system message via code: `ConversationService::add_system_message()`

## Developer Reference

### Hooks

**Actions:**
```php
// Fires when message is sent
do_action('wpss_message_sent', $message, $conversation);

// Fires when conversation created
do_action('wpss_conversation_created', $conversation_id, $order_id);
```

**Filters:**
```php
// Modify allowed file types for message attachments
apply_filters('wpss_delivery_allowed_file_types', $types);
```

**Note:** There is NO `wpss_message_allowed_file_types` hook. Delivery file types are used for message attachments.

### Database Schema

**Table:** `{prefix}wpss_conversations`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `order_id` | bigint(20) | Order ID (foreign key) |
| `subject` | varchar(255) | Conversation subject |
| `participants` | text | JSON array of user IDs |
| `message_count` | int | Total messages |
| `unread_counts` | text | JSON object: `{user_id: count}` |
| `is_closed` | tinyint(1) | Closed flag |
| `last_message_at` | datetime | Last message timestamp |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

**Table:** `{prefix}wpss_messages`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `conversation_id` | bigint(20) | Conversation ID (foreign key) |
| `sender_id` | bigint(20) | User ID (0 for system) |
| `type` | varchar(50) | Message type (text, attachment, delivery, revision, status_change, system) |
| `content` | longtext | Message text |
| `attachments` | longtext | JSON array of file data |
| `metadata` | longtext | JSON object for type-specific data |
| `read_by` | text | JSON object: `{user_id: true}` |
| `is_edited` | tinyint(1) | Edit flag |
| `created_at` | datetime | Send timestamp |
| `updated_at` | datetime | Last update timestamp |

### Programmatic Usage

**Get Conversation:**
```php
$conversation_service = new ConversationService();
$conversation = $conversation_service->get_by_order($order_id);
```

**Send Message:**
```php
$message = $conversation_service->send_message(
    $conversation->id,
    $sender_user_id,
    'Message content',
    $attachments = [],
    $type = Message::TYPE_TEXT
);
```

**Add System Message:**
```php
$conversation_service->add_system_message(
    $conversation->id,
    'Order deadline extended by 3 days',
    $metadata = ['extra_days' => 3]
);
```

**Mark as Read:**
```php
$conversation_service->mark_as_read($conversation->id, $user_id);
```

**Get Unread Count:**
```php
$unread_count = $conversation_service->get_total_unread_count($user_id);
```

## Related Documentation

- [Order Lifecycle](order-lifecycle.md)
- [Requirements Collection](requirements-collection.md)
- [Deliveries & Revisions](deliveries-revisions.md)
- [Disputes & Resolution](../disputes-resolution/opening-disputes.md)
