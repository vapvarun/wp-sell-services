# WP Sell Services: Feature Spotlights

Ten feature highlights showcasing the core capabilities of WP Sell Services.

---

## 1. Service Creation Wizard

### Build Professional Service Listings in Minutes

Transform your expertise into sellable services with a guided 6-step wizard that walks you through every detail from pricing to delivery.

The Service Creation Wizard removes the guesswork from launching marketplace offerings. Instead of wrestling with complex forms, vendors follow a logical progression through Basic Info, Pricing, Gallery, Requirements, Extras, and final Review. Each step validates your input and shows exactly what's missing before you can publish.

Built with Alpine.js for smooth transitions and auto-save functionality, the wizard ensures you never lose progress. Jump between steps to refine details, save drafts to finish later, and see a real-time preview of how buyers will view your completed service. The system enforces marketplace quality standards while giving you creative control over packages, features, and presentation.

**Key capabilities:**

- 6-step guided workflow with validation at each stage
- Create up to 3 pricing tiers (Basic, Standard, Premium) with unique features
- Upload featured image plus 4 gallery images to showcase your work
- Define custom requirements to collect buyer information before work begins
- Add up to 5 FAQs and 3 service add-ons for upselling
- Auto-save functionality preserves your progress between sessions
- Optional moderation queue for admin approval before going live

**Use case:**

A WordPress developer wants to sell custom plugin development. She uses the wizard to create a Basic package at $500 (7-day delivery), a Standard package at $850 (5-day delivery with priority support), and a Premium package at $1,200 (3-day delivery with unlimited revisions). She uploads screenshots from previous projects, defines requirements asking for feature specifications and design preferences, and adds three paid extras: "Rush delivery (-2 days, +$200)", "Source code documentation (+$100)", and "30-day maintenance (+$150)". The wizard validates everything, shows the package comparison table, and publishes her service to the marketplace in under 15 minutes.

**Next step:** Create your first service and start accepting orders.

---

## 2. Flexible Pricing Packages

### Offer Choices That Convert More Buyers

Give buyers exactly what they need with up to 3 pricing tiers that showcase value at every budget level.

Most buyers don't want the cheapest option or the most expensive one. They want the middle choice that feels like the best value. WP Sell Services understands buyer psychology by supporting Basic, Standard, and Premium packages on every service. Each tier can have different delivery times, revision counts, and feature sets, creating a clear value ladder that guides buyers toward higher-value purchases.

The package system stores everything as structured data: price, delivery days, revision limits, and a dynamic feature list. When buyers view your service, they see a side-by-side comparison table making it easy to spot differences. You control which packages are active, can customize names (change "Basic" to "Starter" if you prefer), and set independent pricing for each tier.

**Key capabilities:**

- Up to 3 packages per service (Basic always required, Standard and Premium optional)
- Independent pricing, delivery times (1-30 days), and revision counts per package
- Unlimited feature bullet points to highlight what's included in each tier
- Dynamic package enable/disable without recreating the service
- Minimum $5 price enforcement on Basic package prevents underpricing
- Package data syncs to WooCommerce product variations automatically
- Backward-compatible meta fields for legacy integrations

**Use case:**

A logo designer structures three packages: Basic ($150, 3 days, 1 revision) includes "3 initial concepts, 1 final logo, JPG/PNG files." Standard ($275, 5 days, 3 revisions) adds "5 initial concepts, social media kit, vector files (AI/EPS), brand colors guide." Premium ($450, 7 days, unlimited revisions) includes everything plus "Complete brand identity, letterhead design, business card design, style guide PDF, exclusive rights." The comparison table makes the value difference obvious, and 60% of buyers choose Standard because it feels like the best deal.

**Next step:** Structure your packages to maximize order value.

---

## 3. Complete Order Lifecycle

### Track Every Order from Payment to Completion

Manage the entire order journey with 11 distinct statuses that keep buyers and vendors aligned at every stage.

Orders in WP Sell Services flow through a carefully designed lifecycle that automates routine tasks while giving control when you need it. From the moment a buyer clicks "Order Now," the system tracks payment (pending_payment), collects project requirements (pending_requirements), monitors vendor work progress (in_progress), handles deliveries and revisions (pending_approval, revision_requested), and manages completion (completed).

Each status change triggers notifications, updates dashboards, and enforces business rules. Late orders automatically get flagged when deadlines pass. Deliveries auto-complete after 3 days if buyers don't respond. Orders stuck in pending requirements can auto-start or auto-cancel based on your settings. You get full visibility into what's happening without manual status tracking.

**Key capabilities:**

- 11 order statuses covering every scenario from payment to disputes
- Automated status transitions based on configurable rules and timeframes
- Deadline tracking with 24-hour reminder emails to vendors
- Auto-completion after 3 days when buyers don't review deliveries
- Late order detection via hourly cron checks
- Manual status changes with admin override capabilities
- Order timeline showing every status change with timestamps

**Use case:**

A buyer purchases a website redesign service on Monday at 10 AM. Payment confirms immediately (pending_requirements). She submits detailed requirements by 2 PM, starting the clock (in_progress) with a Friday 2 PM deadline. Thursday at 2 PM, the vendor receives a deadline reminder. Friday at noon, the vendor uploads the delivery (pending_approval). The buyer is traveling and doesn't respond. Monday at noon, the order auto-completes, releasing payment to the vendor's wallet. The buyer later logs in, sees the completed work, and leaves a 5-star review. Zero manual intervention required.

**Next step:** Enable automatic order management in your settings.

---

## 4. Built-in Messaging System

### Keep Communication Professional and Trackable

Connect buyers and vendors with a dedicated messaging system that handles conversations, file sharing, and automatic updates.

Every order gets its own private conversation thread the moment payment clears. Buyers and vendors can exchange text messages, share files up to 10MB, and track message read status. Unlike external email or chat tools that scatter communication across platforms, the built-in system keeps everything attached to the order with full history preservation.

The system automatically posts status change messages when orders move between states, delivery notifications when vendors submit work, and system updates when deadlines are extended. Admins can view all conversations for dispute resolution but can't directly participate, keeping the channel between the two parties. Email notifications ensure nobody misses important messages even when they're away from the dashboard.

**Key capabilities:**

- Threaded conversations automatically created for every order
- Support for text messages plus file attachments (images, documents, archives, design files)
- Real-time read/unread status tracking with badge notifications
- Automatic system messages for status changes, deliveries, and revisions
- Email notifications for new messages (configurable per user)
- Message history preservation with export capability for admins
- Mobile-responsive interface accessible from any device

**Use case:**

A vendor receives a service order and immediately messages the buyer: "Thanks for your order! I've reviewed your requirements. Quick question about the color scheme—do you want the blue from your logo or a darker navy?" The buyer responds from her phone within an hour: "Darker navy would be perfect. I'm attaching our brand guidelines." She uploads a PDF. The vendor receives an email notification, downloads the file, and confirms: "Got it! I'll have the first draft ready by Thursday." When he submits the delivery on Thursday, a system message appears in the thread: "Delivery submitted - Please review and accept or request revision." The entire project conversation lives in one searchable thread.

**Next step:** Set up email notifications for real-time order updates.

---

## 5. Reviews & Ratings System

### Build Trust with Verified Buyer Feedback

Transform completed orders into social proof with a comprehensive review system that showcases vendor reputation.

Every completed order becomes an opportunity for buyers to leave detailed feedback. The system requires verified purchases (you can't review a service without ordering it), collects both an overall 1-5 star rating and three optional sub-ratings (Communication, Quality, Value), and allows written reviews to explain the experience.

Reviews appear on service pages, vendor profiles, and search results. The platform automatically calculates average ratings per service and per vendor, tracks total review counts, and displays "Verified Purchase" badges. Vendors can publicly reply to reviews, turning negative feedback into opportunities to demonstrate customer service. Optional moderation lets admins review sensitive feedback before publication.

**Key capabilities:**

- One review per order with verified purchase badges
- Overall rating (1-5 stars) plus optional sub-ratings for Communication, Quality, and Value
- Written reviews with 10-character minimum for meaningful feedback
- 30-day review window after order completion (configurable by admin)
- Vendor reply system for public responses to all reviews
- Reviews can be edited by customers via REST API
- Optional admin moderation with approve/reject workflow

**Use case:**

A buyer completes a logo design order and receives a "Review Your Order" email 3 days later. She rates it 5 stars overall with Communication: 5, Quality: 5, Value: 4, and writes: "Amazing work! Sarah delivered 5 concept options and incorporated all my feedback perfectly. The final logo exceeded expectations. Only giving 4 stars on value because the Premium package was pricey, but honestly worth every penny for the quality." The vendor replies: "Thank you so much! It was a pleasure working on your brand identity. Feel free to reach out anytime you need design work!" The review appears on the service page, boosting the vendor's average from 4.7 to 4.8 stars. Future buyers see authentic feedback from someone who actually used the service.

**Next step:** Encourage buyers to leave reviews after order completion.

---

## 6. Dispute Resolution System

### Resolve Conflicts Fairly with Built-in Mediation

Protect both parties with a formal dispute process that documents issues and enables admin intervention.

When direct communication fails and revisions don't solve the problem, disputes provide a structured escalation path. Buyers or vendors can open disputes from 6 predefined reason categories, submit detailed descriptions, and attach evidence as JSON data. The system immediately pauses order progression, notifies both parties, and flags admin moderators.

Disputes move through open, pending_review, escalated, resolved, and closed statuses. Admins can review all evidence, add notes, request clarifications, and ultimately decide on five resolution types: full refund, partial refund, favor vendor, favor buyer, or mutual agreement. The system automatically processes refunds, updates order statuses, and closes the dispute when resolved.

**Key capabilities:**

- 6 dispute reasons: Not Delivered, Not as Described, Poor Quality, Late Delivery, Communication Issues, Other
- Evidence submission as JSON with support for text, links, images, and file attachments
- 5 dispute statuses tracking the resolution process
- Admin mediation with 5 resolution types (full refund, partial refund, favor vendor, favor buyer, mutual agreement)
- Automatic refund processing and order status updates based on resolution
- Dispute history preserved for vendor reputation tracking
- Both buyer and vendor can initiate disputes at any time

**Use case:**

A buyer orders custom WordPress development but receives a plugin that doesn't match the requirements. After two revision requests and no satisfactory updates, she opens a dispute selecting "Not as Described" as the reason. She writes: "Service promised WooCommerce integration and email verification. Delivered plugin has neither feature and vendor stopped responding to messages 5 days ago." She pastes links to screenshots showing the issues. The vendor receives a notification but doesn't respond within 3 days. An admin reviews the evidence, checks the original service description, confirms the features were promised, and resolves with "favor_buyer" and a full refund. The order status changes to cancelled, the buyer's payment is returned, and the vendor's dispute rate increases.

**Next step:** Set up dispute moderation policies in your marketplace.

---

## 7. Unified Vendor Dashboard

### Manage Your Entire Business from One Interface

Access all selling and buying activities through a responsive dashboard that adapts to your role.

The dashboard uses a single `[wpss_dashboard]` shortcode but intelligently shows different sections based on whether you're a buyer, vendor, or both. Buyers see order tracking and request management. Vendors get all buyer features plus service listings, sales orders, earnings, and messages. The sidebar groups features into Buying, Selling, and Account sections with visual indicators for unread messages and pending orders.

Built with responsive design, the dashboard works seamlessly on desktop, tablet, and mobile. Quick actions let you message buyers, upload deliveries, request extensions, or edit services without navigating through multiple pages. Real-time statistics show active orders, total revenue, average ratings, and monthly activity at a glance.

**Key capabilities:**

- Unified interface for buyers and vendors with role-based section visibility
- Organized into Buying (Orders, Requests), Selling (Services, Sales, Earnings), and Account (Messages, Profile)
- Real-time statistics showing active orders, revenue, ratings, and monthly activity
- Quick actions on order rows for common tasks (message, deliver, extend)
- Responsive design with collapsible sidebar for tablets and hamburger menu for mobile
- Direct URLs for each section enabling deep linking and bookmarks
- One-click vendor registration with "Start Selling" button for buyers

**Use case:**

A freelance designer logs into her dashboard on Monday morning. The overview shows 3 active sales orders, 2 buyer requests with new proposals, and $2,450 in available earnings. She clicks into Sales to review her orders. Order #1247 needs delivery by tomorrow—she clicks "Upload Delivery" and submits the files right from the dashboard. Order #1248 is pending requirements—she clicks "Message Buyer" and asks for clarification. In the Requests section, she reviews a proposal she submitted last week and sees it's still pending. Finally, she navigates to Earnings, confirms her available balance, and clicks "Request Withdrawal" for $2,000. All tasks completed from one interface in under 10 minutes.

**Next step:** Explore your dashboard sections and set up your profile.

---

## 8. Buyer Requests & Proposals

### Let Customers Find You Instead of You Finding Them

Flip the marketplace model with a reverse bidding system where buyers post projects and vendors compete with proposals.

Buyer Requests solve the problem of custom projects that don't fit pre-packaged services. Buyers describe what they need, set a budget (fixed amount or range), specify a delivery timeline, and list required skills. Vendors browse active requests, submit proposals with custom pricing and timelines, and write cover letters explaining their approach.

Buyers review all proposals side-by-side, comparing vendor ratings, proposed prices, delivery days, and pitch quality. When they accept a proposal, the system immediately creates an order with pending_payment status, rejects all other proposals, and marks the request as hired. The entire process from posting to hiring can happen in days instead of weeks.

**Key capabilities:**

- Request creation with title, description, category, budget (fixed or range), and optional skills list
- 4 request statuses: open, in_review, hired, expired (auto-expire after 30 days configurable)
- Vendor proposals with cover letter, proposed price, delivery days, and optional service links
- One proposal per vendor per request with edit capability while pending
- Automatic order creation with pending_payment status when proposal accepted
- Request and proposal management via REST API for mobile apps
- All other proposals auto-rejected when one is accepted

**Use case:**

A SaaS company needs a custom WordPress membership plugin but can't find exactly what they need on existing services. They post a Buyer Request with a detailed description, $800-$1,200 budget range, and 14-day delivery preference. Within 24 hours, they receive 8 proposals. Most quote around $1,000 with 10-14 day timelines, but one vendor proposes $950 for 12 days and includes links to 3 similar membership plugins she built. Her cover letter outlines a detailed 12-day development plan. The buyer accepts her proposal, and the system creates order #1523 at $950 with a 12-day deadline. Payment processes, requirements are collected, and work begins—all because the buyer posted what they needed instead of searching hundreds of services.

**Next step:** Enable Buyer Requests to capture custom project opportunities.

---

## 9. Display Anywhere with Blocks & Shortcodes

### Build Custom Marketplace Pages Without Code

Create service catalogs, vendor directories, search pages, and dashboards using 6 Gutenberg blocks or 13 flexible shortcodes.

Whether you prefer the visual block editor or the speed of shortcodes, WP Sell Services gives you both options for displaying marketplace content. The 6 Gutenberg blocks (Service Grid, Service Search, Service Categories, Featured Services, Seller Card, Buyer Requests) offer visual editing with sidebar controls for columns, limits, categories, and sorting. The 13 shortcodes provide the same features with attribute-based customization for developers and classic editor users.

Both systems output the same templates, respect the same filters, and load the same frontend assets. Use blocks for marketing pages built by content editors, and use shortcodes in widget areas, PHP templates, or dynamic content. Mix both approaches on the same site based on what makes sense for each use case.

**Key capabilities:**

- 6 Gutenberg blocks with visual editing and sidebar configuration panels
- 13 shortcodes for programmatic content display and widget areas
- Blocks for: Service Grid, Service Search, Categories, Featured Services, Vendor Profile, Buyer Requests
- Shortcodes for: Services, Search, Categories, Featured, Vendors, Top Vendors, Buyer Requests, Post Request, Orders, Order Details, Login, Register, Dashboard
- Configurable attributes for filtering (category, tag, vendor), sorting (date, price, rating, sales), and layout (columns, limits)
- Template override system for custom HTML output
- Frontend assets load only when blocks/shortcodes are present on page

**Use case:**

A marketplace owner builds a homepage using Gutenberg blocks. She starts with the Service Search block at the top with placeholder text "What service do you need?" Then adds a heading "Featured Services" followed by the Featured Services block set to 8 services in 4 columns. Below that, she inserts "Browse by Category" and adds the Service Categories block showing the top 12 categories in a 4-column grid. For the vendor directory page, she switches to shortcodes in a classic editor template: `[wpss_service_search]` at the top, then `[wpss_top_vendors limit="5"]` for the featured section, followed by `[wpss_vendors orderby="rating" columns="4"]` for the full directory. Both pages display perfectly, even though one uses blocks and the other uses shortcodes.

**Next step:** Create marketplace pages with blocks or shortcodes.

---

## 10. Complete REST API for Developers

### Build Mobile Apps and Custom Integrations

Access every marketplace feature through a comprehensive REST API with 20 controllers and mobile-optimized batch requests.

WP Sell Services treats the REST API as a first-class citizen, not an afterthought. Every feature built into the plugin—services, orders, reviews, vendors, conversations, disputes, buyer requests, proposals, notifications, portfolio, earnings, extensions, milestones, tipping, seller levels, moderation, favorites, media, cart, and authentication—has corresponding API endpoints.

The API follows WordPress REST API standards with proper authentication (cookie, application passwords, JWT tokens), standardized pagination, consistent error formats, and CORS support for external applications. The batch endpoint lets mobile apps make up to 25 sub-requests in a single HTTP call, reducing network overhead. Rate limiting (Pro) protects against abuse while allowing legitimate high-volume usage.

**Key capabilities:**

- 20 REST API controllers covering all marketplace features
- Standard authentication via cookies, application passwords, or JWT tokens (Pro)
- Batch endpoint supports 25 simultaneous requests per call for mobile efficiency
- Generic endpoints for categories, tags, settings, current user info, dashboard stats, and global search
- Consistent pagination with standard WordPress headers (X-WP-Total, X-WP-TotalPages)
- CORS headers enabled for cross-origin requests to designated domains
- Rate limiting (Pro) with 300 requests/hour for users, 1000 for app passwords

**Use case:**

A developer builds a native iOS app for the marketplace. The app uses application passwords for authentication and makes a batch request on app launch to load everything at once: `GET /services?per_page=10`, `GET /vendors?per_page=5`, `GET /dashboard`, `GET /notifications/unread`. One HTTP call returns all four responses. When users browse services, the app uses `GET /services?category=5&orderby=rating` to filter by category and sort by rating. When they place an order, `POST /orders` creates the order and `POST /orders/{id}/start` begins work after payment. The entire marketplace experience works natively on mobile with zero web views, all powered by the REST API.

**Next step:** Review the API documentation and start building.

---

## Summary

WP Sell Services delivers a complete marketplace platform where every feature works together to create seamless buyer and vendor experiences. From service creation through order completion, the system automates routine tasks, enforces quality standards, and provides the flexibility to customize for any use case.

Ready to launch your marketplace? Install WP Sell Services and start accepting orders today.
