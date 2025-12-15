# WP Sell Services - File Index

Last Updated: 2025-12-15

## Directory Structure Overview

```
wp-sell-services/
├── src/                     # PHP source (PSR-4)
│   ├── Admin/               # Admin functionality
│   ├── API/                 # REST API endpoints
│   ├── Blocks/              # Gutenberg blocks
│   ├── Core/                # Plugin bootstrap
│   ├── CustomFields/        # Form field system
│   ├── Database/            # Schema & repositories
│   ├── Frontend/            # Frontend functionality
│   ├── Integrations/        # E-commerce adapters
│   ├── Models/              # Data models
│   ├── PostTypes/           # CPT registration
│   ├── SEO/                 # SEO & Schema markup
│   ├── Services/            # Business logic
│   └── Taxonomies/          # Taxonomy registration
├── assets/                  # CSS, JS, images
├── templates/               # PHP templates
├── docs/                    # Documentation
└── vendor/                  # Composer dependencies
```

---

## Core (`src/Core/`)

| File | Purpose | Status |
|------|---------|--------|
| `Plugin.php` | Main plugin class, bootstraps all components | ✅ Complete |
| `Activator.php` | Plugin activation hooks, creates tables | ✅ Complete |
| `Deactivator.php` | Plugin deactivation hooks | ✅ Complete |
| `Loader.php` | Action/filter hook loader | ✅ Complete |

---

## Admin (`src/Admin/`)

| File | Purpose | Status |
|------|---------|--------|
| `Admin.php` | Admin initialization, menus, scripts | ✅ Complete |
| `Settings.php` | Settings page with tabs | ✅ Complete |
| `Metaboxes/ServiceMetabox.php` | Service packages, FAQs, requirements | ✅ Complete |
| `Metaboxes/BuyerRequestMetabox.php` | Buyer request budget, deadline | ✅ Complete |
| `Metaboxes/OrderMetabox.php` | Order details in admin | ✅ Complete |
| `Pages/ManualOrderPage.php` | Manual order creation | ✅ Complete |
| `Pages/VendorsPage.php` | Vendor management page | ✅ Complete |
| `Tables/OrdersListTable.php` | WP_List_Table for orders | ✅ Complete |
| `Tables/DisputesListTable.php` | WP_List_Table for disputes | ✅ Complete |

### Missing Admin Files
- [ ] `Pages/MigrationPage.php` - Migration from woo-sell-services

---

## API (`src/API/`)

| File | Purpose | Status |
|------|---------|--------|
| `API.php` | REST API initialization | ✅ Complete |
| `RestController.php` | Base controller class | ✅ Complete |
| `ServicesController.php` | `/wpss/v1/services` endpoints | ✅ Complete |
| `OrdersController.php` | `/wpss/v1/orders` endpoints | ✅ Complete |
| `ReviewsController.php` | `/wpss/v1/reviews` endpoints | ✅ Complete |
| `VendorsController.php` | `/wpss/v1/vendors` endpoints | ✅ Complete |
| `ConversationsController.php` | `/wpss/v1/conversations` endpoints | ✅ Complete |
| `DisputesController.php` | `/wpss/v1/disputes` endpoints | ✅ Complete |
| `BuyerRequestsController.php` | `/wpss/v1/buyer-requests` endpoints | ✅ Complete |
| `ProposalsController.php` | `/wpss/v1/proposals` endpoints | ✅ Complete |

### Missing API Files
- [ ] `AuthController.php` - JWT/OAuth authentication (PRO feature)

---

## Blocks (`src/Blocks/`)

| File | Purpose | Status |
|------|---------|--------|
| `AbstractBlock.php` | Base block class | ✅ Complete |
| `BlocksManager.php` | Block registration | ✅ Complete |
| `ServiceGrid.php` | Service grid display | ✅ Complete |
| `ServiceCategories.php` | Category listing block | ✅ Complete |
| `ServiceSearch.php` | Search form block | ✅ Complete |
| `FeaturedServices.php` | Featured services carousel | ✅ Complete |
| `SellerCard.php` | Vendor profile card | ✅ Complete |
| `BuyerRequests.php` | Buyer requests listing | ✅ Complete |

---

## Custom Fields (`src/CustomFields/`)

| File | Purpose | Status |
|------|---------|--------|
| `FieldInterface.php` | Field contract | ✅ Complete |
| `FieldManager.php` | Field registration & retrieval | ✅ Complete |
| `FieldRenderer.php` | Renders fields as HTML | ✅ Complete |
| `FieldValidator.php` | Validates field input | ✅ Complete |
| `Fields/AbstractField.php` | Base field class | ✅ Complete |
| `Fields/TextField.php` | Text input | ✅ Complete |
| `Fields/TextareaField.php` | Textarea | ✅ Complete |
| `Fields/SelectField.php` | Dropdown select | ✅ Complete |
| `Fields/MultiSelectField.php` | Multiple select | ✅ Complete |
| `Fields/RadioField.php` | Radio buttons | ✅ Complete |
| `Fields/CheckboxField.php` | Checkboxes | ✅ Complete |
| `Fields/NumberField.php` | Number input | ✅ Complete |
| `Fields/DateField.php` | Date picker | ✅ Complete |
| `Fields/FileUploadField.php` | File upload | ✅ Complete |

### Missing Field Types
- [ ] `Fields/WYSIWYGField.php` - Rich text editor

---

## Database (`src/Database/`)

| File | Purpose | Status |
|------|---------|--------|
| `SchemaManager.php` | Creates database tables | ✅ Complete |
| `MigrationManager.php` | Version migrations | ✅ Complete |
| `Repositories/AbstractRepository.php` | Base repository | ✅ Complete |
| `Repositories/OrderRepository.php` | Order data access | ✅ Complete |
| `Repositories/ConversationRepository.php` | Conversation data access | ✅ Complete |
| `Repositories/DeliveryRepository.php` | Delivery data access | ✅ Complete |
| `Repositories/ReviewRepository.php` | Review data access | ✅ Complete |
| `Repositories/ServicePackageRepository.php` | Package data access | ✅ Complete |
| `Repositories/VendorProfileRepository.php` | Vendor profile data | ✅ Complete |
| `Repositories/DisputeRepository.php` | Dispute data access | ✅ Complete |
| `Repositories/NotificationRepository.php` | Notification data access | ✅ Complete |
| `Repositories/ProposalRepository.php` | Proposal data access | ✅ Complete |
| `Repositories/ExtensionRequestRepository.php` | Extension request data | ✅ Complete |

---

## Frontend (`src/Frontend/`)

| File | Purpose | Status |
|------|---------|--------|
| `Frontend.php` | Frontend initialization, scripts, templates | ✅ Complete |
| `Shortcodes.php` | All shortcode definitions | ✅ Complete |
| `AjaxHandlers.php` | Frontend AJAX handlers | ✅ Complete |
| `VendorDashboard.php` | Vendor dashboard with tabs | ✅ Complete |
| `TemplateLoader.php` | Template override system | ✅ Complete |
| `SingleServiceView.php` | Single service page controller | ✅ Complete |

---

## Integrations (`src/Integrations/`)

| File | Purpose | Status |
|------|---------|--------|
| `IntegrationManager.php` | Manages e-commerce adapters | ✅ Complete |
| `Contracts/EcommerceAdapterInterface.php` | Adapter contract | ✅ Complete |
| `Contracts/ProductProviderInterface.php` | Product sync contract | ✅ Complete |
| `Contracts/OrderProviderInterface.php` | Order handling contract | ✅ Complete |
| `Contracts/CheckoutProviderInterface.php` | Checkout contract | ✅ Complete |
| `Contracts/AccountProviderInterface.php` | Account pages contract | ✅ Complete |
| `WooCommerce/WooCommerceAdapter.php` | Main WC adapter | ✅ Complete |
| `WooCommerce/WCProductProvider.php` | Service-Product sync | ✅ Complete |
| `WooCommerce/WCOrderProvider.php` | WC order handling | ✅ Complete |
| `WooCommerce/WCCheckoutProvider.php` | WC checkout hooks | ✅ Complete |
| `WooCommerce/WCAccountProvider.php` | My Account endpoints | ✅ Complete |
| `WooCommerce/WCEmailProvider.php` | Custom WC emails | ✅ Complete |

### PRO Integration Files (Not in Free)
- [ ] `EDD/EDDAdapter.php`
- [ ] `FluentCart/FluentCartAdapter.php`
- [ ] `SureCart/SureCartAdapter.php`
- [ ] `Standalone/StandaloneAdapter.php`
- [ ] `Stripe/StripeGateway.php`
- [ ] `PayPal/PayPalGateway.php`

---

## Models (`src/Models/`)

| File | Purpose | Status |
|------|---------|--------|
| `Service.php` | Service data model | ✅ Complete |
| `ServiceItem.php` | Service item (for orders) | ✅ Complete |
| `ServicePackage.php` | Package tier model | ✅ Complete |
| `ServiceAddon.php` | Add-on model | ✅ Complete |
| `ServiceOrder.php` | Order model with statuses | ✅ Complete |
| `Conversation.php` | Conversation model | ✅ Complete |
| `Message.php` | Message model | ✅ Complete |
| `Review.php` | Review model | ✅ Complete |
| `Dispute.php` | Dispute model | ✅ Complete |
| `VendorProfile.php` | Vendor profile model | ✅ Complete |
| `BuyerRequest.php` | Buyer request model | ✅ Complete |
| `Proposal.php` | Proposal model | ✅ Complete |
| `Notification.php` | Notification model | ✅ Complete |
| `ExtensionRequest.php` | Extension request model | ✅ Complete |

---

## Post Types (`src/PostTypes/`)

| File | Purpose | Status |
|------|---------|--------|
| `ServicePostType.php` | `wpss_service` CPT + taxonomies | ✅ Complete |
| `BuyerRequestPostType.php` | `wpss_request` CPT | ✅ Complete |

---

## Services (`src/Services/`) - Business Logic

| File | Purpose | Status |
|------|---------|--------|
| `ServiceManager.php` | Service CRUD operations | ✅ Complete |
| `OrderService.php` | Order management | ✅ Complete |
| `OrderWorkflowManager.php` | Status automation, cron jobs | ✅ Complete |
| `RequirementsService.php` | Requirements submission flow | ✅ Complete |
| `ExtensionRequestService.php` | Deadline extensions | ✅ Complete |
| `ConversationService.php` | Messaging system | ✅ Complete |
| `DeliveryService.php` | Delivery submissions | ✅ Complete |
| `ReviewService.php` | Review management | ✅ Complete |
| `DisputeService.php` | Dispute handling | ✅ Complete |
| `DisputeWorkflowManager.php` | Dispute escalation, auto-responses | ✅ Complete |
| `NotificationService.php` | User notifications | ✅ Complete |
| `VendorService.php` | Vendor operations | ✅ Complete |
| `EarningsService.php` | Vendor earnings management | ✅ Complete |
| `PortfolioService.php` | Vendor portfolio management | ✅ Complete |
| `BuyerRequestService.php` | Buyer request operations | ✅ Complete |
| `ProposalService.php` | Proposal handling | ✅ Complete |
| `SearchService.php` | Search & filtering | ✅ Complete |
| `AnalyticsService.php` | Stats & analytics | ✅ Complete |
| `FAQService.php` | Service FAQs | ✅ Complete |
| `GalleryService.php` | Service gallery | ✅ Complete |

---

## SEO (`src/SEO/`)

| File | Purpose | Status |
|------|---------|--------|
| `SEO.php` | Main SEO class, meta tags, Open Graph | ✅ Complete |
| `SchemaMarkup.php` | JSON-LD structured data generation | ✅ Complete |
| `YoastIntegration.php` | Yoast SEO plugin integration | ✅ Complete |
| `RankMathIntegration.php` | Rank Math plugin integration | ✅ Complete |
| `ServiceSchemaPiece.php` | Yoast schema graph piece | ✅ Complete |

---

## Taxonomies (`src/Taxonomies/`)

| File | Purpose | Status |
|------|---------|--------|
| `ServiceCategoryTaxonomy.php` | Service categories | ✅ Complete |
| `ServiceTagTaxonomy.php` | Service tags | ✅ Complete |

---

## Templates (`templates/`)

| Directory/File | Purpose | Status |
|----------------|---------|--------|
| `archive-service.php` | Service archive page | ✅ Complete |
| `single-service.php` | Single service page | ✅ Complete |
| `content-service-card.php` | Service card component | ✅ Complete |
| `content-no-services.php` | Empty state | ✅ Complete |
| `partials/` | Reusable template parts | ✅ Partial |
| `order/` | Order-related templates | ✅ Partial |
| `myaccount/` | My Account templates | ✅ Partial |
| `dashboard/` | Vendor dashboard | ✅ Partial |
| `vendor/` | Vendor profile templates | ✅ Partial |

### Missing Templates
- [ ] `checkout/checkout.php` - Checkout page
- [ ] `checkout/confirmation.php` - Order confirmation
- [x] `order/requirements-form.php` - Requirements submission form ✅
- [x] `order/conversation.php` - Order messaging view ✅
- [ ] `dashboard/orders.php` - Vendor orders list
- [ ] `dashboard/earnings.php` - Vendor earnings page
- [ ] `emails/` - Email templates directory

---

## Assets (`assets/`)

| Directory/File | Purpose | Status |
|----------------|---------|--------|
| `css/admin.css` | Admin styles | ✅ Complete |
| `css/frontend.css` | Frontend styles | ✅ Complete |
| `css/blocks.css` | Block styles | ✅ Complete |
| `css/blocks-editor.css` | Block editor styles | ✅ Complete |
| `css/single-service.css` | Single service page styles | ✅ Complete |
| `js/admin.js` | Admin scripts | ✅ Complete |
| `js/blocks.js` | Block scripts | ✅ Complete |
| `js/blocks-frontend.js` | Frontend block scripts | ✅ Complete |
| `js/conversation.js` | Real-time messaging | ✅ Complete |
| `js/dashboard.js` | Vendor dashboard scripts | ✅ Complete |
| `js/single-service.js` | Single service page scripts | ✅ Complete |
| `js/frontend.js` | Main frontend scripts | ✅ Complete |
| `js/checkout.js` | Checkout functionality | ✅ Complete |

### Missing Assets
- All assets complete!

---

## Summary

### Completion Status

| Category | Complete | Total | Percentage |
|----------|----------|-------|------------|
| Core | 4 | 4 | 100% |
| Admin | 9 | 10 | 90% |
| API | 10 | 11 | 91% |
| Blocks | 8 | 8 | 100% |
| Custom Fields | 14 | 15 | 93% |
| Database | 13 | 13 | 100% |
| Frontend | 6 | 6 | 100% |
| Integrations | 11 | 17 | 65% |
| Models | 14 | 14 | 100% |
| Post Types | 2 | 2 | 100% |
| Services | 20 | 20 | 100% |
| SEO | 5 | 5 | 100% |
| Taxonomies | 2 | 2 | 100% |
| Assets | 13 | 13 | 100% |
| Templates | +2 | - | - |
| **Total** | **133** | **140** | **95%** |

### Priority Items to Complete

1. ~~**WooCommerce Email Integration**~~ - ✅ `WCEmailProvider.php` complete
2. ~~**Request to Order Conversion**~~ - ✅ Added to `BuyerRequestService.php`
3. ~~**Missing Models**~~ - ✅ BuyerRequest, Proposal, Notification, ExtensionRequest complete
4. ~~**API Controllers**~~ - ✅ Conversations, Disputes, BuyerRequests, Proposals complete
5. ~~**SEO Integration**~~ - ✅ Schema markup, Yoast, Rank Math complete
6. ~~**Database Repositories**~~ - ✅ DisputeRepository, NotificationRepository, ProposalRepository, ExtensionRequestRepository complete
7. ~~**Single Service View**~~ - ✅ SingleServiceView controller, CSS, JS complete
8. **Frontend Templates** - Requirements form, conversation view
9. **Admin Pages** - Vendor management, Migration from woo-sell-services
