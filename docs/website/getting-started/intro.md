# Introduction to WP Sell Services

WP Sell Services transforms your WordPress site into a complete Fiverr-style service marketplace. Vendors sell digital services, buyers browse and purchase services, and you earn commission on every transaction.

## What is WP Sell Services?

WP Sell Services is a full-featured marketplace plugin for creating service-based platforms. Freelancers and agencies can offer their expertise while you manage the marketplace and earn revenue through commissions.

**Perfect for building:**
- Freelance marketplaces
- Gig platforms
- Service directories
- Agency networks
- Micro-job sites

## Key Features

### Service Management
- **Service Listings**: Vendors create detailed service pages with descriptions, pricing, and media
- **Package Tiers**: Offer Basic, Standard, and Premium packages with different pricing and deliverables
- **Service Add-ons**: Extra services buyers can purchase (rush delivery, extra revisions)
- **Custom Requirements**: Collect buyer information before work starts via custom forms

### Order System
- **Complete Workflow**: 10+ order statuses track every stage from payment to completion
- **Delivery Management**: Vendors submit deliverables, buyers request revisions
- **Deadline Extensions**: Request and approve delivery date changes
- **Order Messaging**: Built-in chat system for buyer-vendor communication
- **Milestones** **[PRO]**: Split payments for large projects

### Marketplace Features
- **Buyer Requests**: Buyers post project needs, vendors submit proposals
- **Reviews & Ratings**: 5-star rating system with written reviews
- **Dispute Resolution**: Structured dispute system with admin mediation
- **Commission System**: Earn percentage or flat-rate commission on sales

### Vendor Profiles
- **Seller Levels**: Basic, Verified, and Pro vendor tiers
- **Portfolio Showcase**: Display past work and achievements
- **Vendor Dashboard**: Manage services, orders, earnings, and statistics
- **Vacation Mode**: Temporarily pause accepting new orders

### Frontend Tools
- **6 Gutenberg Blocks**: Service Grid, Search, Featured Services, Categories, Seller Cards, Buyer Requests
- **Shortcodes**: Display services, vendor profiles, dashboards anywhere
- **Responsive Design**: Mobile-friendly templates for all pages

### Developer Features
- **20 REST API Controllers**: Complete API for mobile apps and custom integrations
- **Batch Endpoint**: Execute up to 25 API requests in single HTTP call
- **100+ Hooks & Filters**: Extensive customization via WordPress hooks
- **Template Override System**: Customize appearance without modifying plugin files

## Requirements

| Component | Minimum Version |
|-----------|----------------|
| WordPress | 6.4 or higher |
| PHP | 8.1 or higher |
| MySQL | 5.7 or higher |
| WooCommerce | 8.0+ (optional — enables checkout and payment processing) |

**Note**: WooCommerce is optional. The plugin works independently for all marketplace functions. When WooCommerce is active, checkout and payment processing are enabled automatically. **[PRO]** version adds additional e-commerce platforms and standalone payment gateways.

## Getting Started

Ready to build your marketplace? Follow these guides:

1. **[Installation Guide](installation.md)** - Install the plugin and dependencies
2. **[Initial Setup](initial-setup.md)** - Configure marketplace settings
3. **[Free vs Pro Comparison](free-vs-pro.md)** - Understand version differences

## Architecture

### Database Tables

WP Sell Services creates dedicated database tables for optimal performance:

- **Orders**: Service orders and transactions
- **Conversations**: Order messaging
- **Deliveries**: Final work submissions
- **Reviews**: Ratings and feedback
- **Disputes**: Dispute management
- **Vendor Profiles**: Vendor information
- **Service Packages**: Pricing tiers
- **Buyer Requests**: Project postings
- **Proposals**: Vendor bids
- **Earnings**: Commission tracking
- **Withdrawals**: Payout requests
- **Notifications**: In-app alerts
- **Portfolio Items**: Vendor showcases
- **Extension Requests**: Deadline changes
- **Milestones** **[PRO]**: Payment milestones
- **Tips**: Optional tipping

### Custom Post Types

- **wpss_service**: Service listings
- **wpss_request**: Buyer requests

### Taxonomies

- **wpss_service_category**: Hierarchical service categories
- **wpss_service_tag**: Service tags for filtering

### User Roles

- **wpss_vendor**: Vendor role with service management capabilities
- Administrator capabilities extended with marketplace management

## Integration Options

### Free Version
- **WooCommerce**: Full integration with WooCommerce for checkout and payments
- All WooCommerce payment gateways supported

### Pro Version **[PRO]**
- **EDD (Easy Digital Downloads)**: Lightweight digital products platform
- **FluentCart**: Modern, conversion-optimized checkout
- **SureCart**: Cloud-hosted checkout solution
- **Standalone Mode**: No e-commerce plugin required
- **Direct Payment Gateways**: Stripe, PayPal, Razorpay integration

## Support & Documentation

- **REST API**: `/wp-json/wpss/v1/` with 20 controllers
- **Developer Hooks**: [Hooks & Filters Reference](../developer-guide/hooks-filters.md)
- **Template System**: Override templates in your theme
- **Custom Integrations**: Build e-commerce adapters, payment gateways, storage providers

## Next Steps

1. **Install** the plugin following the [Installation Guide](installation.md)
2. **Configure** your marketplace in [Initial Setup](initial-setup.md)
3. **Compare** free and pro features in [Free vs Pro](free-vs-pro.md)
4. **Explore** developer documentation in the [Developer Guide](../developer-guide/)

Transform your WordPress site into a thriving service marketplace with WP Sell Services.
