# Vacation Mode

Vacation mode lets you temporarily pause new orders while maintaining your vendor profile and account status.

## What is Vacation Mode?

Vacation mode is a simple on/off toggle that:

- Prevents buyers from placing new orders
- Maintains your vendor profile visibility
- Preserves your seller level
- Allows optional vacation message to buyers
- Continues to fulfill existing active orders

Use vacation mode when you need a temporary break but plan to return.

## Vacation Mode Fields

The system stores two fields in `wpss_vendor_profiles` table:

| Field | Database Column | Type | Description |
|-------|-----------------|------|-------------|
| **Vacation Mode** | `vacation_mode` | tinyint(1) | On/off toggle (0 or 1) |
| **Vacation Message** | `vacation_message` | text | Optional message to buyers |

**Important:** There are NO date fields. Vacation mode has:
- NO start date
- NO end date
- NO automatic return functionality

It's a manual toggle you must turn on and off yourself.

## How to Enable Vacation Mode

### Step-by-Step Activation

1. Log in to your vendor account
2. Navigate to **Dashboard → Settings** (or Profile)
3. Toggle **Vacation Mode** to ON
4. Enter optional vacation message (recommended)
5. Save changes

### Vacation Message

Write a brief, professional message to buyers:

**Example:**
```
Thanks for visiting! I'm currently unavailable and not accepting
new orders. I'll return soon. Feel free to message me and I'll
respond when I'm back.
```

Keep it:
- Brief (100-200 characters)
- Professional
- Informative about when you might return (optional)

## What Happens When Active

### For Buyers

When buyers visit your profile with vacation mode enabled:

- **Vacation Notice**: Banner or message displayed
- **Order Buttons Disabled**: Cannot purchase services
- **Vacation Message**: Your custom message shown
- **Services Visible**: Listings remain viewable
- **Messaging Available**: Buyers can still send messages

### For Your Services

Your service listings:

- Remain published and searchable
- Display "On Vacation" indicator
- Cannot be ordered
- Maintain SEO and search position
- Continue to appear in favorites

### For Existing Orders

Orders placed before vacation mode:

- **Continue Normally**: You can still work on active orders
- **Full Dashboard Access**: All vendor features available
- **Deliveries**: Submit work as usual
- **Messages**: Communicate with buyers
- **Deadlines**: NOT automatically extended

**Important:** Existing order deadlines remain unchanged. Request extensions if needed.

## How to Disable Vacation Mode

### Returning from Vacation

When ready to accept orders again:

1. Navigate to **Dashboard → Settings** (or Profile)
2. Toggle **Vacation Mode** to OFF
3. Save changes
4. Services become available immediately

### What Happens on Return

When vacation mode is disabled:

- Order buttons re-enabled instantly
- Vacation message removed
- Services accept orders immediately
- Profile returns to normal display
- No notification sent (you manually control it)

## Impact on Your Account

### Seller Level

Vacation mode does NOT directly affect:

- Your seller level (new, level_1, level_2, top_rated)
- Existing statistics
- Review history
- Earnings records

**However:** Extended inactivity may indirectly impact seller level if you don't complete orders or respond to messages.

### Search Visibility

During vacation:

- Services remain in search results
- "On Vacation" filter may exclude you from some searches
- SEO position maintained
- Profile stays indexed

### Activity Tracking

While vacation mode is a marker, the system may still track:

- Response time (if you respond to messages)
- Order completion (for active orders)
- Review ratings (from completed orders)

Complete any active orders to maintain good standing.

## Best Practices

### Before Activating

1. **Complete Active Orders**: Deliver pending work when possible
2. **Notify Buyers**: Inform buyers of upcoming vacation
3. **Request Extensions**: Get approval for deadline changes
4. **Set Clear Message**: Write informative vacation message
5. **Check Settings**: Verify vacation mode toggle works

### During Vacation

1. **Monitor Active Orders**: Don't abandon existing commitments
2. **Check Messages**: Optionally review buyer inquiries
3. **Fulfill Orders**: Complete work accepted before vacation
4. **Plan Return**: Decide when to disable vacation mode

### Upon Return

1. **Disable Vacation Mode**: Toggle off manually
2. **Review Messages**: Respond to inquiries
3. **Check Orders**: Address any pending issues
4. **Update Services**: Refresh delivery times if needed
5. **Manage Capacity**: Don't overcommit immediately

## Limitations

### No Automation

- **Manual Control Only**: You must toggle vacation mode yourself
- **No Scheduled Return**: No automatic reactivation
- **No Date Tracking**: System doesn't track vacation duration
- **No Reminders**: No alerts to turn it back on/off

**Tip:** Set a personal reminder to disable vacation mode when ready.

### Existing Orders

Vacation mode does NOT:

- Cancel active orders
- Extend deadlines automatically
- Excuse late deliveries
- Prevent negative reviews
- Pause order timers

You remain responsible for all accepted orders.

### No Duration Limits

The system has:

- No maximum vacation period
- No frequency restrictions
- No warnings for extended use

However, prolonged vacation may:
- Reduce buyer confidence
- Lower search ranking over time
- Affect seller level progression
- Decrease return traffic

Use vacation mode responsibly for temporary breaks only.

## Alternatives to Vacation Mode

### Pause Individual Services

Instead of full vacation mode:

1. Navigate to **Dashboard → Services**
2. Click **Pause** on specific services
3. Other services remain available

Use this for partial availability.

### Extend Delivery Times

Temporarily increase delivery times:

1. Edit your services
2. Increase delivery days per package
3. Save changes

Buyers can still order with longer wait times.

### Stop Accepting New Orders

- Set service status to "Draft"
- Services hidden from marketplace
- Existing orders continue
- Revert to "Published" when ready

## Troubleshooting

### Vacation Mode Not Saving

**Check:**
- Vacation message field (if required)
- Browser JavaScript enabled
- No PHP/server errors
- Proper permissions

**Solution:**
- Clear browser cache
- Try different browser
- Check error logs
- Contact support

### Services Still Show Order Button

**Possible Causes:**
- Cache not cleared
- Vacation mode not actually enabled
- Template override issue

**Solution:**
- Wait 5 minutes for cache to clear
- Hard refresh page (Ctrl+F5)
- Verify vacation mode is ON
- Check with incognito mode

### Cannot Disable Vacation Mode

**Verify:**
- You're logged in as vendor
- Correct user account
- No JavaScript errors
- Settings page loading properly

**Solution:**
- Clear cookies and cache
- Contact site administrator
- Check browser console for errors

## Database Reference

Vacation mode data stored in `wpss_vendor_profiles`:

```sql
vacation_mode TINYINT(1) DEFAULT 0
vacation_message TEXT
```

Values:
- `vacation_mode`: 0 (off) or 1 (on)
- `vacation_message`: Optional text message

Updated via `VendorService::set_vacation_mode($user_id, $enabled, $message)` method.

## Related Resources

- [Vendor Profile](vendor-profile-portfolio.md) - Manage your settings
- [Vendor Dashboard](vendor-dashboard.md) - Access vacation mode toggle
- [Seller Levels](seller-levels.md) - Impact on progression
- [Order Management](../order-management/order-lifecycle-wpss.md) - Handle active orders
