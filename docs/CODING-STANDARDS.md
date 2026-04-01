# WP Sell Services — Coding Standards

> PHP/JS/CSS rules, WPCS config, security patterns.

**Last Updated:** 2026-04-01

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

## PHPStan (Static Analysis)

This project uses PHPStan at **level 5** with the WordPress extension (`szepeviktor/phpstan-wordpress`).

### Configuration

- **Config file:** `phpstan.neon`
- **Level:** 5
- **Baseline:** `phpstan-baseline.neon` (tracks existing issues that predate PHPStan adoption)
- **Bootstrap:** `phpstan-bootstrap.php` + Composer autoload
- **Scanned paths:** `src/` (excludes `vendor/`, `node_modules/`, `includes/`, `tests/`, `src/SEO/`)

### Commands

```bash
# Run full analysis
vendor/bin/phpstan analyse --memory-limit=1G

# Check specific file
vendor/bin/phpstan analyse src/Services/OrderService.php --memory-limit=1G

# Regenerate baseline (after intentionally accepting new issues)
vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G
```

### Rules

- **New code must not introduce PHPStan errors.** If `phpstan analyse` fails on your changes, fix them before opening a PR.
- **The baseline exists for pre-existing issues only.** Do not add new entries to the baseline to bypass errors in new code.
- **PHPStan regressions block PRs.** The CI pipeline runs PHPStan on every push and PR. If it fails, the PR cannot be merged.
- **Ignored error patterns** (defined in `phpstan.neon`):
  - `apply_filters` / `do_action` invoked with dynamic parameter counts (WordPress hook pattern)
  - Variables from `extract()` in templates
  - Mixed-type offset access from WP functions returning mixed

### Common Fixes

| Error | Fix |
|-------|-----|
| `Parameter $x of method has no type hint` | Add typed parameter: `function foo(int $x)` |
| `Method should return X but returns Y` | Fix the return type or the return value |
| `Cannot access offset on mixed` | Add a type assertion or `@var` annotation |
| `Variable $x might not be defined` | Initialize the variable or add an `isset()` check |

---

## CI/CD Pipeline

GitHub Actions runs automatically on every push and PR to `main`.

### Checks

| Check | Tool | Blocking? | Details |
|-------|------|-----------|---------|
| PHP Lint | `php -l` | **Yes** | Syntax check on PHP 8.1, 8.2, 8.3, 8.4 |
| WPCS | PHP_CodeSniffer | **Yes** | 0 errors required (warnings are allowed) |
| PHPStan | PHPStan level 5 | **Yes** | No regressions from baseline |
| PHPUnit | PHPUnit | No | Unit tests across PHP/WP matrix |

### What "Blocking" Means

- **WPCS errors block PRs.** The PR cannot be merged until `composer phpcs` reports 0 errors. Warnings are acceptable and do not block.
- **PHPStan regressions block PRs.** Any new PHPStan error that is not in the baseline will fail the CI check and prevent merge.
- **PHP lint failures block PRs.** Syntax errors on any supported PHP version (8.1-8.4) will fail the check.
- **PHPUnit failures do not block** but should be investigated. Test failures indicate regressions.

### Branch Protection

- Direct pushes to `main` are not allowed
- PRs require at least 1 approval
- All blocking CI checks must pass before merge

### Running CI Checks Locally

Before pushing, run these to catch issues early:

```bash
# 1. WPCS (most common failure)
composer phpcs

# 2. PHPStan
vendor/bin/phpstan analyse --memory-limit=1G

# 3. PHP lint (quick syntax check)
php -l src/Services/YourNewFile.php

# 4. Tests
composer test
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
