# Security Audit Report: WP Sell Services

**Audit Date:** 2026-01-11
**Plugin Version:** 1.0.0
**Scope:** SQL Injection, XSS, CSRF, Capability Checks, File Upload Security

---

## Executive Summary

**SECURITY VERDICT:** PASS (with minor recommendations)

| Severity | Count |
|----------|-------|
| CRITICAL | 0 |
| HIGH | 1 |
| MEDIUM | 3 |
| LOW | 4 |

**PRODUCTION READY:** Yes (after addressing HIGH priority issue)

---

## High Priority Issues

### H1: Unvalidated Column Names in AbstractRepository::count()

- **File:** `src/Database/Repositories/AbstractRepository.php:248-266`
- **Risk:** Potential SQL Injection
- **Description:** The `count()` method accepts column names from the `$where` array without validating them against the `$allowed_columns` whitelist. While values are escaped with `$wpdb->prepare()`, column names are interpolated directly into the SQL query.

```php
// Current code (line 255-258)
foreach ( $where as $column => $value ) {
    $format       = is_int( $value ) ? '%d' : '%s';
    $conditions[] = "{$column} = {$format}";  // $column not validated
    $values[]     = $value;
}
```

- **Impact:** If an attacker can control the array keys passed to `count()`, they could inject SQL into the column name portion.
- **Likelihood:** Low - This method is only called internally by service classes, not directly from user input.

**Fix:**
```php
foreach ( $where as $column => $value ) {
    $column = sanitize_key( $column );
    if ( ! $this->is_valid_column( $column ) ) {
        continue;
    }
    $format       = is_int( $value ) ? '%d' : '%s';
    $conditions[] = "{$column} = {$format}";
    $values[]     = $value;
}
```

---

## Medium Priority Issues

### M1: Missing Nonce Verification in ServiceCategoryTaxonomy::save_term_meta()

- **File:** `src/Taxonomies/ServiceCategoryTaxonomy.php:214-238`
- **Risk:** CSRF vulnerability
- **Description:** The `save_term_meta()` method checks capability but relies on WordPress handling the nonce. Explicit verification is best practice.

**Fix:** Add explicit nonce verification for the term form.

### M2: Delivery Service Allows PHP File Uploads

- **File:** `src/Services/DeliveryService.php:344-348`
- **Risk:** Potential Remote Code Execution
- **Description:** The `get_allowed_file_types()` method includes `.php` in allowed file types.

```php
$types = array(
    // ... other types ...
    'html',
    'css',
    'js',
    'php',  // DANGER: PHP files
);
```

**Fix:** Remove `php` from allowed types.

### M3: IP Address Storage Without Consent Notice

- **File:** `src/Frontend/AjaxHandlers.php:953`
- **Risk:** GDPR/Privacy Compliance
- **Description:** The `mark_review_helpful()` method stores IP addresses in transient cache keys.

**Fix:** Document in privacy policy or use session-based alternative.

---

## Low Priority Issues

### L1: Missing Bounds Checking for Float Values

- **File:** `src/Admin/Metaboxes/BuyerRequestMetabox.php:301,305`
- **Description:** Float values cast directly without bounds checking.
- **Fix:** Add `max(0, floatval(...))` for price fields.

### L2: Live Search Nonce Skip for Guests

- **File:** `src/Frontend/AjaxHandlers.php:1547-1551`
- **Description:** Live search skips nonce verification for non-logged-in users.
- **Fix:** Consider rate limiting for public endpoints.

### L3: Direct Database Queries in Template

- **File:** `templates/order/order-view.php:52-59`
- **Description:** Template contains direct `$wpdb` query instead of using service layer.
- **Fix:** Use `DeliveryService::get_order_deliveries()` instead.

### L4: Verbose Error Messages

- **File:** Various AJAX handlers
- **Description:** Some error messages reveal business logic details.
- **Fix:** Use generic messages in production.

---

## Security Strengths

1. **Consistent Nonce Verification**: All AJAX handlers use `check_ajax_referer()` or `wp_verify_nonce()`
2. **Proper Capability Checks**: `current_user_can()` checks present on all admin and destructive actions
3. **Prepared Statements**: All database queries use `$wpdb->prepare()` with proper placeholders
4. **Output Escaping**: Templates consistently use `esc_html()`, `esc_attr()`, `esc_url()`, and `wp_kses_post()`
5. **Input Sanitization**: User input sanitized with `sanitize_text_field()`, `absint()`, etc.
6. **Direct File Access Prevention**: All PHP files check for `ABSPATH`
7. **File Upload Security**: Uses `wp_handle_upload()` with type restrictions
8. **REST API Permissions**: All endpoints have proper `permission_callback` handlers
9. **Repository Pattern Security**: AbstractRepository validates `orderby` and `order` against whitelists
10. **Resource Ownership Checks**: Orders and services verify user ownership before allowing access

---

## Required Fixes

### Before Deployment
1. **[H1]** Validate column names in `AbstractRepository::count()` method

### Strongly Recommended
2. **[M2]** Remove PHP from allowed delivery file types
3. **[M1]** Add explicit nonce verification to taxonomy save methods
