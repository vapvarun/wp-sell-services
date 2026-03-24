# WP Sell Services — Coding Standards

> PHP/JS/CSS rules, WPCS config, security patterns.

**Last Updated:** 2026-03-24

---

## PHP Standards

### WPCS Configuration
Both plugins use WordPress Coding Standards via `phpcs.xml`:
- **Ruleset:** WordPress-Extra
- **PHP Compatibility:** 8.1+
- **Exceptions:** Short array syntax `[]` allowed, PSR-4 file naming

### Commands
```bash
# Lint
composer phpcs

# Auto-fix
composer phpcbf

# Check specific file
./vendor/bin/phpcs --standard=phpcs.xml src/Services/OrderService.php
```

### PHP 8.1+ Features Used
- Typed properties and return types
- `declare(strict_types=1)` on all source files
- Named arguments where clarity improves
- `match()` expressions
- Enums (where applicable)
- Union types
- Readonly properties

### File Structure
Every PHP file must:
1. Start with `<?php` and `declare(strict_types=1);`
2. Have `defined('ABSPATH') || exit;` guard
3. Declare namespace
4. Use PSR-4 autoloading (via Composer)

---

## Security Patterns (Mandatory)

### Input Handling
```php
// ALWAYS sanitize + unslash superglobals
$value = sanitize_text_field( wp_unslash( $_POST['field'] ?? '' ) );
$id    = absint( $_POST['id'] ?? 0 );
$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

// JSON input
$data = json_decode( sanitize_text_field( wp_unslash( $_POST['json'] ?? '{}' ) ), true );
```

### Output Escaping
```php
// HTML content
echo esc_html( $value );

// HTML attributes
echo esc_attr( $value );

// URLs
echo esc_url( $url );

// Rich HTML (user content)
echo wp_kses_post( $content );

// JavaScript values
echo esc_js( $value );
```

### Database Queries
```php
// ALWAYS use $wpdb->prepare()
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table} WHERE vendor_id = %d AND status = %s",
        $vendor_id,
        $status
    )
);

// NEVER interpolate user input into SQL
// For IN() clauses, build placeholders:
$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
$wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", ...$ids );
```

### CSRF Protection
```php
// AJAX handlers — ALWAYS first line
check_ajax_referer( 'wpss_action_name', 'nonce' );

// REST endpoints — use permission_callback (never __return_true for writes)
'permission_callback' => array( $this, 'check_permissions' ),

// Form submissions
wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'action_name' );
```

### Authorization
```php
// Admin-only operations
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( array( 'message' => 'Unauthorized' ) );
}

// Resource ownership
$order = $order_service->get( $order_id );
if ( (int) $order->vendor_id !== get_current_user_id() ) {
    wp_send_json_error( array( 'message' => 'Not authorized' ) );
}
```

### Rate Limiting
```php
// Applied to all sensitive operations
if ( RateLimiter::check_and_track( 'order_action', get_current_user_id() ) ) {
    RateLimiter::send_error( 'order_action' );
    return; // Always return after rate limit error
}
```

### Redirects
```php
// ALWAYS use wp_safe_redirect(), NEVER wp_redirect()
wp_safe_redirect( $url );
exit;
```

---

## REST API Rules

### New Features = REST First
All new features MUST include REST API endpoints. No new `wp_ajax_*` handlers.

### Controller Pattern
```php
class MyController extends RestController {
    public function register_routes(): void {
        register_rest_route( $this->namespace, '/my-resource', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_items' ],
            'permission_callback' => [ $this, 'check_permissions' ],
            'args'                => $this->get_collection_params(),
        ] );
    }
}
```

### Permission Methods
| Method | Who | Use For |
|--------|-----|---------|
| `check_permissions()` | Logged-in user | Standard authenticated endpoints |
| `check_admin_permissions()` | `manage_options` | Admin-only endpoints |
| `check_item_permissions()` | Resource owner or admin | Per-resource authorization |
| `__return_true` | Public | Read-only public data ONLY |

---

## JavaScript Standards

### AJAX Pattern
```javascript
$.ajax({
    url: wpssData.ajaxUrl,
    method: 'POST',
    data: {
        action: 'wpss_action_name',
        nonce: wpssData.nonce,
        // ... params
    },
    success: function(response) {
        if (response.success) {
            // Handle success
        } else {
            alert(response.data.message);
        }
    }
});
```

### REST API Pattern (preferred for new code)
```javascript
$.ajax({
    url: wpssData.restUrl + 'wpss/v1/endpoint',
    method: 'POST',
    beforeSend: function(xhr) {
        xhr.setRequestHeader('X-WP-Nonce', wpssData.restNonce);
    },
    data: { /* params */ },
});
```

### XSS Prevention in JS
```javascript
// NEVER use .html() with server responses
// Use .text() instead:
$notice.empty().append($('<p>').text(message));
```

---

## CSS Standards

### Naming
- All classes prefixed with `wpss-`
- BEM-like naming: `.wpss-order-card__status`
- RTL support via `-rtl.css` files

### Enqueuing
```php
// ALWAYS enqueue via wp_enqueue_style/script
// NEVER use inline <style> blocks in templates
wp_enqueue_style( 'wpss-dashboard', WPSS_PLUGIN_URL . 'assets/css/dashboard.css', [], WPSS_VERSION );
```

---

## Ownership Rules (Free vs Pro)

| Free Plugin Owns | Pro Plugin Owns |
|------------------|-----------------|
| Core marketplace, CPTs, database | WooCommerce/EDD/FluentCart/SureCart adapters |
| Standalone checkout + Offline/Stripe/PayPal | Razorpay gateway |
| Base admin settings UI | Advanced analytics |
| Frontend dashboard framework | Wallet integrations |
| Order workflow, conversations | Tiered commission |
| Commission system (flat rate) | Vendor subscriptions |
| All 17 base DB tables | 7 Pro DB tables |

**Rules:**
1. Pro extends Free via hooks — never by duplicating code
2. Each gateway/adapter class owns its own settings
3. If adding a hook in Free, check if Pro already uses a similar hook
4. Never register the same hook in both plugins
