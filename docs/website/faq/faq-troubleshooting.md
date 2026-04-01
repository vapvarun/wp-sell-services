# FAQ and Troubleshooting

Answers to the most common questions about WP Sell Services, plus solutions for issues you might run into.

---

## Frequently Asked Questions

### Do I need WooCommerce to use this plugin?

No. WP Sell Services works completely standalone with its own built-in checkout, Stripe, PayPal, and Offline payment support. You do not need WooCommerce or any other e-commerce plugin. The Pro version optionally adds WooCommerce, EDD, FluentCart, and SureCart as alternative checkout platforms if you want them.

### How do I change the commission rate?

Go to **WP Sell Services > Settings > Payments**. You can set a platform-wide commission percentage. With Pro, you can also set per-vendor commission rates and tiered commission rules.

### Can vendors set their own prices?

Yes. Each service has its own pricing packages (Basic, Standard, Premium), and vendors set the prices for each tier. You control the commission the platform takes, but vendors decide what they charge.

### What happens if a vendor does not deliver on time?

Late orders are flagged in the system. Buyers can open a dispute, request a cancellation, or wait for the vendor to deliver. The dispute system lets both parties present their case, and an admin can step in to mediate and resolve the issue (including issuing refunds).

### Is the marketplace mobile-friendly?

Yes. All frontend pages -- service listings, dashboards, order management, messaging -- are fully responsive and work on phones and tablets. The plugin also includes a full REST API, making it ready for native mobile app development.

### Can I white-label the plugin?

**[PRO]** Yes, with the Pro version. You can rebrand the entire marketplace: custom platform name, logo, colors, and email branding. The "WP Sell Services" name does not appear anywhere your users can see it.

### How do vendors get paid?

Vendors accumulate earnings as their orders are completed. They can request a withdrawal from their dashboard, and the admin approves and processes the payout. **[PRO]** With Pro, you can also set up automatic payouts via PayPal or Stripe Connect so vendors get paid without manual intervention.

### Can buyers request custom work?

Yes. The Buyer Requests feature lets buyers post detailed project descriptions with budgets and timelines. Vendors browse these requests and submit proposals. When a buyer accepts a proposal, an order is created automatically.

### What payment gateways are supported?

The free version includes Stripe, PayPal, and Offline (manual) payment gateways, all built into the standalone checkout. **[PRO]** The Pro version adds Razorpay and integrates with payment gateways from WooCommerce, EDD, FluentCart, and SureCart.

### Can I run multiple marketplaces on a multisite network?

Yes. WP Sell Services works on WordPress multisite. Each site in the network runs its own independent marketplace.

---

## Common Issues and Fixes

### Services not showing up

1. **Flush permalinks** -- Go to **Settings > Permalinks** and click Save Changes
2. **Check service status** -- Services must be published, not draft or pending
3. **Clear cache** -- Clear your site cache and browser cache
4. **Assign categories** -- Services need at least one category assigned

### Vendor registration not working

1. **Enable WordPress registration** -- Go to **Settings > General** and check "Anyone can register"
2. **Enable vendor registration** -- Go to **WP Sell Services > Settings > Vendors** and enable it
3. **Check approval mode** -- If admin approval is required, approve pending vendors at **WP Sell Services > Vendors > Pending**

### Pages showing 404 errors

Go to **Settings > Permalinks** and click Save Changes. This refreshes URL routing. Also verify the page is published and assigned in **WP Sell Services > Settings > Pages**.

### Orders not appearing in vendor dashboard

- Verify the order exists and has the correct vendor assigned
- Check that the order status is valid
- Look for errors in your WordPress debug log

### Emails not arriving

1. **Test WordPress email** -- Try resetting a password to see if any WordPress emails work
2. **Install an SMTP plugin** -- WP Mail SMTP or FluentSMTP dramatically improves deliverability
3. **Check spam folder** -- WordPress emails frequently land in spam without SMTP
4. **Verify notification settings** -- Make sure the email type is enabled in **Settings > Emails**

### Buyer requests not expiring automatically

Request expiration runs on WordPress cron, which requires site traffic to trigger. On low-traffic sites:
1. Install the free WP Crontrol plugin to check scheduled tasks
2. Set up a real server cron job to ping your site every 15 minutes

### Delivery files not uploading

1. **Check file size limits** -- The default max is 50MB. Increase in **Settings > Advanced** if needed
2. **Check allowed file types** -- Make sure the file extension is permitted
3. **Check server limits** -- Your hosting may have lower PHP upload limits than the plugin setting
4. **Verify disk space** -- Make sure your server has available storage

### Slow performance

1. **Install a caching plugin** -- WP Super Cache, W3 Total Cache, or LiteSpeed Cache
2. **Use an object cache** -- Redis or Memcached if your hosting supports it
3. **Optimize your database** -- Regularly clean transients and optimize tables
4. **Consider a search plugin** -- For large marketplaces, Relevanssi or ElasticSearch improves search speed

---

## Getting Help

**Enable debug mode** for troubleshooting: Go to **WP Sell Services > Settings > Advanced** and enable Debug Mode. Check `wp-content/debug.log` for detailed error messages.

**Have your details ready** when contacting support:
- WordPress version and PHP version
- WP Sell Services version
- Active theme name
- List of active plugins
- Error messages from the debug log
- Steps to reproduce the issue

**Support channels:**
- **Free version** -- WordPress.org support forum
- **Pro version** -- Priority email support
