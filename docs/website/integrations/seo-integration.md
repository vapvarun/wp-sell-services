# SEO Integration

Built-in SEO features and integration with popular SEO plugins for better search engine visibility.

## Built-In SEO Features

WP Sell Services includes SEO optimizations out of the box.

### Service Schema Markup

Structured data for better search engine understanding.

**Schema Types Implemented:**

**Service Schema:**
```json
{
  "@context": "https://schema.org/",
  "@type": "Service",
  "name": "Professional Logo Design",
  "description": "Custom logo design for your business...",
  "provider": {
    "@type": "Person",
    "name": "John Designer"
  },
  "offers": {
    "@type": "Offer",
    "price": "150.00",
    "priceCurrency": "USD"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "47"
  }
}
```

**Product Schema:**
(For e-commerce compatibility)
```json
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "Logo Design - Premium Package",
  "image": "https://example.com/logo-design.jpg",
  "description": "Premium logo design package...",
  "brand": "MarketplaceName",
  "offers": {
    "@type": "Offer",
    "url": "https://example.com/services/logo-design/",
    "priceCurrency": "USD",
    "price": "200.00",
    "availability": "https://schema.org/InStock"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.9",
    "bestRating": "5",
    "worstRating": "1",
    "ratingCount": "52"
  }
}
```

**Vendor Schema:**
```json
{
  "@context": "https://schema.org/",
  "@type": "Person",
  "name": "Jane Designer",
  "description": "Professional graphic designer with 10+ years experience",
  "url": "https://example.com/vendor/jane-designer/",
  "image": "https://example.com/avatars/jane.jpg",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.7",
    "reviewCount": "89"
  },
  "offers": {
    "@type": "AggregateOffer",
    "lowPrice": "50",
    "highPrice": "500",
    "priceCurrency": "USD"
  }
}
```

**Review Schema:**
```json
{
  "@context": "https://schema.org/",
  "@type": "Review",
  "itemReviewed": {
    "@type": "Service",
    "name": "Logo Design"
  },
  "reviewRating": {
    "@type": "Rating",
    "ratingValue": "5",
    "bestRating": "5"
  },
  "author": {
    "@type": "Person",
    "name": "Sarah Smith"
  },
  "reviewBody": "Excellent work! Very professional and delivered on time.",
  "datePublished": "2026-01-15"
}
```

**Benefits:**
- Rich snippets in Google search results
- Star ratings displayed in SERPs
- Price information visible
- Better click-through rates
- Enhanced visibility

### SEO-Friendly URLs

Clean, readable permalink structure.

**Service URLs:**
```
Good: /services/logo-design/
Bad:  /service?id=123
```

**Vendor URLs:**
```
Good: /vendor/jane-designer/
Bad:  /user?id=42
```

**Category URLs:**
```
Good: /service-category/graphic-design/
Bad:  /category.php?cat=5
```

**Buyer Request URLs:**
```
Good: /buyer-requests/need-logo-design/
Bad:  /request?r=789
```

**Configuration:**

1. Go to **Settings → Permalinks**
2. Select **Post name** (recommended)
3. Save changes
4. WP Sell Services automatically uses clean URLs

**Custom Service Base:**

Change service URL structure:

```php
// In functions.php
add_filter( 'wpss_service_permalink_base', function() {
    return 'gigs'; // Changes /services/ to /gigs/
} );
```

Result: `/gigs/logo-design/`

---

## Yoast SEO Integration

Full compatibility with the most popular WordPress SEO plugin.

### Yoast SEO Features

**Service Page Optimization:**

When editing a service:
- Yoast SEO metabox appears
- Focus keyphrase analysis
- Content analysis (readability, SEO)
- Snippet preview
- Social media previews

**SEO Settings:**
- Custom title template
- Custom meta description
- Open Graph settings
- Twitter card settings
- Canonical URL

**Example Title Template:**
```
%%title%% - From $%%price%% | %%sitename%%
```

Result: "Logo Design - From $50 | Creative Marketplace"

**Example Meta Description:**
```
%%excerpt%% Starting at $%%price%%. Order now from %%vendor_name%% on %%sitename%%.
```

### Configuring Yoast for Services

**Service SEO Templates:**

1. Go to **SEO → Search Appearance → Content Types**
2. Find **Services** section
3. Configure:
   - **Show Services in Search:** Yes
   - **SEO Title:** `%%title%% - %%category%% Service | %%sitename%%`
   - **Meta Description:** `%%excerpt%% Order this %%category%% service from $%%price%%.`
4. Save changes

**Service Category Templates:**

1. Go to **SEO → Search Appearance → Taxonomies**
2. Find **Service Categories**
3. Configure:
   - **SEO Title:** `%%term_title%% Services | %%sitename%%`
   - **Meta Description:** `Browse %%term_title%% services from verified vendors.`

**Vendor Profile Templates:**

Custom templates for vendor pages:
```
Title: %%vendor_name%% - %%vendor_rating%% Stars | %%sitename%%
Description: Hire %%vendor_name%%, a %%vendor_level%% vendor with %%vendor_reviews%% reviews.
```

### Yoast Variables

Custom variables for service marketplace:

| Variable | Description | Example |
|----------|-------------|---------|
| `%%price%%` | Starting price | 50 |
| `%%category%%` | Service category | Logo Design |
| `%%vendor_name%%` | Vendor name | Jane Designer |
| `%%vendor_rating%%` | Vendor rating | 4.8 |
| `%%delivery_time%%` | Delivery days | 3 days |
| `%%order_count%%` | Order count | 47 orders |

**Adding Custom Variables:**

```php
// In functions.php
add_filter( 'wpseo_replacements', function( $replacements ) {
    $replacements['%%price%%'] = wpss_get_service_starting_price();
    $replacements['%%vendor_name%%'] = wpss_get_vendor_name();
    return $replacements;
} );
```

---

## Rank Math Integration

Alternative SEO plugin with powerful features.

### Rank Math Features

**Service Optimization:**
- SEO score analysis
- Content AI (Pro)
- Schema markup builder
- Internal linking suggestions
- Keyword tracking

**Schema Builder:**

Rank Math's schema builder integrates with service data:

1. Edit service
2. Go to **Rank Math → Schema** tab
3. Schema type auto-detected: Product/Service
4. Customize schema fields
5. Preview generated JSON-LD

**Custom Schema Fields:**

Map service data to schema:
- Service name → Product name
- Service price → Offer price
- Service rating → AggregateRating
- Vendor name → Brand/Seller
- Delivery time → DeliveryTime

### Rank Math Configuration

**Service Settings:**

1. Go to **Rank Math → Titles & Meta → Services**
2. Configure templates:
   - **Title:** `%%title%% %%sep%% %%category%% Service`
   - **Description:** `%%excerpt%% Order from $%%price%%.`
3. Enable **Add Schema Markup**
4. Select **Schema Type:** Service or Product
5. Save settings

**Breadcrumb Configuration:**

Enable breadcrumbs for better navigation:

1. Go to **Rank Math → General Settings → Breadcrumbs**
2. Enable breadcrumbs
3. Configure separator (e.g., ` > `)
4. Display breadcrumbs on service pages

**Breadcrumb Example:**
```
Home > Services > Graphic Design > Logo Design
```

### Rank Math Variables

Similar to Yoast, supports custom variables:

```php
add_filter( 'rank_math/vars/replacements', function( $vars ) {
    $vars['wpss_price'] = [
        'name' => 'Service Price',
        'description' => 'Service starting price',
        'variable' => 'wpss_price',
        'example' => '$50',
    ];
    return $vars;
} );
```

---

## Service URL Structure

Optimize permalink structure for SEO.

### Recommended Structure

**Option 1: Service Name Only**
```
/services/logo-design/
```
- Clean and simple
- Good for general marketplaces
- Easy to remember

**Option 2: Category + Service Name**
```
/services/graphic-design/logo-design/
```
- Better categorization
- Clear hierarchy
- Good for niche context

**Option 3: Vendor + Service Name**
```
/vendor/jane-designer/logo-design/
```
- Personal branding
- Vendor-centric
- Good for consultant marketplaces

**Change Structure:**

```php
// In functions.php
add_filter( 'wpss_service_permalink_structure', function() {
    return '/services/%service_category%/%service_name%/';
} );
```

### Category and Tag SEO

**Category Archives:**

Optimize category pages as service discovery hubs:

**Title:** "Logo Design Services - Hire Logo Designers"
**Description:** "Browse 150+ professional logo design services from verified designers. Prices from $50. Fast delivery. 100% satisfaction guaranteed."
**H1:** "Logo Design Services"

**Content Tips:**
- Add category description (200-300 words)
- Include target keywords naturally
- List benefits of category services
- Add FAQ section
- Link to related categories

**Tag Pages:**

Use tags for long-tail keywords:

**Example Tags:**
- modern-logo-design
- minimalist-logo
- logo-redesign
- startup-logo

**Tag Optimization:**
- Unique descriptions per tag
- Combine related tags
- Avoid tag spam (too many similar tags)

---

## Open Graph and Social Sharing

Optimize how services appear when shared on social media.

### Open Graph Meta Tags

Automatically generated for all services:

```html
<meta property="og:type" content="product" />
<meta property="og:title" content="Professional Logo Design - From $150" />
<meta property="og:description" content="Custom logo design for your business..." />
<meta property="og:url" content="https://example.com/services/logo-design/" />
<meta property="og:image" content="https://example.com/uploads/logo-design-preview.jpg" />
<meta property="og:site_name" content="Creative Marketplace" />
<meta property="product:price:amount" content="150.00" />
<meta property="product:price:currency" content="USD" />
```

**Result:**
When shared on Facebook, LinkedIn, etc., shows:
- Service image
- Service title
- Service description
- Price
- Clickable link

### Twitter Card Meta Tags

```html
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="Professional Logo Design" />
<meta name="twitter:description" content="Custom logo design from $150" />
<meta name="twitter:image" content="https://example.com/uploads/logo-design-preview.jpg" />
<meta name="twitter:creator" content="@jane_designer" />
```

**Card Types:**
- **Summary:** Small image, text
- **Summary Large Image:** Large preview image
- **Product:** Includes price, availability

### Custom Social Images

Set custom images for social sharing:

**Per Service:**
1. Edit service
2. Scroll to **Social Sharing** section
3. Upload custom image (1200×630 recommended)
4. Image used for og:image and twitter:image

**Default Images:**
- Uses first gallery image if no custom image
- Falls back to vendor avatar
- Falls back to site logo

---

## Sitemap Integration

Ensure search engines discover all marketplace pages.

### Service Sitemap

Automatically included in WordPress XML sitemap:

**What's Included:**
- All published services
- Service categories
- Service tags
- Vendor profiles

**Sitemap URL:**
```
https://example.com/wp-sitemap-services-1.xml
```

**Sitemap Entry:**
```xml
<url>
  <loc>https://example.com/services/logo-design/</loc>
  <lastmod>2026-01-15</lastmod>
  <changefreq>weekly</changefreq>
  <priority>0.8</priority>
</url>
```

### Yoast SEO Sitemap

If using Yoast SEO:

1. Go to **SEO → General → Features**
2. Ensure **XML Sitemaps** enabled
3. Go to **SEO → Search Appearance → Services**
4. Enable **Show Services in Search Results**
5. Services automatically in sitemap

**View Sitemap:**
```
https://example.com/sitemap_index.xml
```

### Rank Math Sitemap

If using Rank Math:

1. Go to **Rank Math → Sitemap Settings**
2. Enable sitemap
3. Enable **Services** post type
4. Set priority (0.8 recommended)
5. Set change frequency (weekly)

**Exclude:**
- Draft services
- Private services
- Services pending review

---

## Content Optimization

Best practices for SEO-friendly service content.

### Service Title Optimization

**Do's:**
- Include main keyword (e.g., "Logo Design")
- Keep under 60 characters
- Make it descriptive and unique
- Avoid keyword stuffing

**Examples:**

Good:
- "Professional Logo Design for Your Business"
- "Custom WordPress Website Development"
- "Expert Social Media Marketing Strategy"

Bad:
- "Logo Design Logo Designer Logos Logo"
- "Best Professional Expert Top Logo Design Services Cheap Affordable"

### Service Description SEO

**Structure:**

1. **Opening paragraph** (150 words):
   - Main keyword in first sentence
   - What service offers
   - Who it's for
   - Unique value proposition

2. **Details section** (300+ words):
   - What's included
   - Process/workflow
   - Technologies/tools used
   - Experience/credentials

3. **Benefits section** (200 words):
   - Why choose this service
   - Customer results
   - Guarantees

4. **FAQ section**:
   - Common questions
   - Long-tail keywords
   - Structured data opportunity

**Keyword Usage:**
- Main keyword: 3-5 times
- Related keywords: Naturally throughout
- Keyword density: 1-2%
- Focus on readability over keyword forcing

### Heading Structure

Use proper heading hierarchy:

```html
<h1>Professional Logo Design</h1>

<h2>What You'll Get</h2>
<p>Service details...</p>

<h2>Design Process</h2>
<h3>1. Discovery Phase</h3>
<p>Understanding your brand...</p>

<h3>2. Concept Development</h3>
<p>Creating initial designs...</p>

<h2>Why Choose This Service</h2>
<p>Benefits...</p>

<h2>Frequently Asked Questions</h2>
<h3>How long does it take?</h3>
<p>Answer...</p>
```

---

## Performance SEO

Speed and technical optimization for better rankings.

### Page Speed

**Optimization Built-In:**
- Lazy loading images
- Optimized queries
- Minified assets
- Browser caching headers

**Additional Optimizations:**
1. Use caching plugin (WP Rocket, W3 Total Cache)
2. Enable CDN (Cloudflare, Amazon CloudFront)
3. Optimize images before upload
4. Use WebP format **[PRO]**
5. Enable GZIP compression

**Target Metrics:**
- Page load: < 3 seconds
- Largest Contentful Paint: < 2.5s
- First Input Delay: < 100ms
- Cumulative Layout Shift: < 0.1

### Mobile Optimization

**Responsive Design:**
- All templates mobile-responsive
- Touch-friendly buttons
- Readable fonts (16px minimum)
- No horizontal scrolling

**Mobile Performance:**
- Reduced image sizes on mobile
- Conditional script loading
- Mobile-specific caching

**Test Tools:**
- Google Mobile-Friendly Test
- PageSpeed Insights
- Lighthouse (Chrome DevTools)

### Core Web Vitals

**Monitor and Improve:**

1. Install **Site Kit by Google** plugin
2. Connect Search Console
3. View Core Web Vitals report
4. Identify problem pages
5. Optimize based on recommendations

**Common Issues:**
- Large images → Optimize/resize
- Render-blocking JS → Defer loading
- Layout shifts → Set image dimensions
- Slow server → Upgrade hosting or use CDN

---

## Local SEO (For Location-Based Services)

Optimize for local search if vendors serve specific locations.

### Location Schema

Add location data to vendor schema:

```json
{
  "@type": "LocalBusiness",
  "name": "Jane Designer",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 Main St",
    "addressLocality": "New York",
    "addressRegion": "NY",
    "postalCode": "10001",
    "addressCountry": "US"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": "40.7128",
    "longitude": "-74.0060"
  }
}
```

### Location Pages

Create location-specific landing pages:

**Examples:**
- /services/logo-design-new-york/
- /services/web-development-london/
- /services/seo-services-sydney/

**Content:**
- City-specific description
- Local vendor listings
- Local testimonials
- City-specific FAQs

---

## Monitoring and Analytics

Track SEO performance.

### Google Search Console

**Setup:**
1. Verify site ownership
2. Submit sitemap
3. Monitor:
   - Search queries
   - Click-through rates
   - Service page impressions
   - Crawl errors

**Key Metrics:**
- Top-performing services
- Search queries bringing traffic
- Pages with errors
- Mobile usability issues

### SEO Reporting

**Track Rankings:**
- Service page positions for target keywords
- Category page rankings
- Vendor profile visibility

**Tools:**
- Rank Math (built-in rank tracking)
- Google Search Console
- SEMrush / Ahrefs (external)

---

## Troubleshooting

### Services Not in Google

**Check:**
1. Services are published (not draft)
2. In sitemap (visit sitemap URL)
3. Sitemap submitted to Search Console
4. No robots.txt blocking
5. No noindex meta tag
6. Allow time (2-4 weeks for new sites)

### Schema Errors

**Validate:**
1. Use Google's Rich Results Test
2. Enter service URL
3. Fix reported errors
4. Common issues:
   - Missing required fields
   - Invalid price format
   - Wrong schema type

### Low Click-Through Rate

**Improve:**
1. Optimize meta titles (add power words)
2. Write compelling descriptions
3. Include price in meta description
4. Add star ratings (schema markup)
5. Use FAQ schema for rich snippets

---

## Related Documentation

- [Search Filters](../marketplace-display/search-filters.md) - Internal search optimization
- [Template Overrides](../marketplace-display/template-overrides.md) - Custom page structure
- [Advanced Settings](../admin-settings/advanced-settings.md) - Performance optimization

---

## Next Steps

1. Install Yoast SEO or Rank Math
2. Configure SEO templates for services
3. Optimize service descriptions
4. Submit sitemap to Google Search Console
5. Monitor search performance
6. Iterate based on data
