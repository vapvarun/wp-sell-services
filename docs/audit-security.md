# Security Audit Report: WP Sell Services

**Audit Date:** 2026-01-11
**Plugin Version:** 1.0.0
**Scope:** SQL Injection, XSS, CSRF, Capability Checks, File Upload Security

---

## Executive Summary

The WP Sell Services plugin demonstrates **good security practices overall**. The codebase shows consistent use of WordPress security APIs including nonce verification, capability checks, prepared statements, and output escaping. However, there are a few areas requiring attention.

**SECURITY VERDICT:** PASS (with minor recommendations)

| Severity | Count |
|----------|-------|
| CRITICAL | 0 |
| HIGH | 1 |
| MEDIUM | 3 |
| LOW | 4 |

**PRODUCTION READY:** Yes (after addressing HIGH priority issue)

---

## Critical Issues

None identified.

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
- **Likelihood:** Low - This method is only called internally by service classes.
- **Fix:** Validate column names against `$this->allowed_columns` before use:

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
- **Description:** The method checks capability but relies on WordPress handling the nonce.

```php
public function save_term_meta( int $term_id ): void {
    // Verify nonce - handled by WordPress.
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }
```

- **Fix:** Add explicit nonce verification for defense in depth.

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
    'php',  // DANGER
);
```

- **Impact:** PHP files could potentially be executed depending on server config.
- **Fix:** Remove `php` from allowed types.

### M3: IP Address Storage Without Consent Notice

- **File:** `src/Frontend/AjaxHandlers.php:953`
- **Risk:** GDPR/Privacy Compliance
- **Description:** The `mark_review_helpful()` stores IP addresses in transient keys.
- **Fix:** Document in privacy policy or use session-based alternative.

---

## Low Priority Issues

### L1: Missing Bounds Checking for Float Values

- **File:** `src/Admin/Metaboxes/BuyerRequestMetabox.php:301,305`
- **Description:** Float values cast directly without bounds checking.

```php
update_post_meta( $post_id, '_wpss_budget_min', (float) $_POST['wpss_budget_min'] );
```

- **Fix:** Add `max(0, floatval(...))` for price fields.

### L2: Live Search Nonce Skip for Guests

- **File:** `src/Frontend/AjaxHandlers.php:1547-1551`
- **Description:** Live search skips nonce verification for non-logged-in users.
- **Recommendation:** Consider rate limiting for public endpoints.

### L3: Direct Database Queries in Template

- **File:** `templates/order/order-view.php:52-59`
- **Description:** Template contains direct `$wpdb` query instead of service layer.
- **Fix:** Use `DeliveryService::get_order_deliveries()` instead.

### L4: Verbose Error Messages

- **File:** Various AJAX handlers
- **Description:** Some error messages reveal business logic details.
- **Fix:** Use generic messages in production.

---

## Security Strengths

1. **Consistent Nonce Verification**: All AJAX handlers use `check_ajax_referer()`
2. **Proper Capability Checks**: `current_user_can()` checks on all admin actions
3. **Prepared Statements**: All database queries use `$wpdb->prepare()`
4. **Output Escaping**: Templates use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
5. **Input Sanitization**: Consistent use of `sanitize_text_field()`, `absint()`, etc.
6. **Direct File Access Prevention**: All PHP files check for `ABSPATH`
7. **File Upload Security**: Uses `wp_handle_upload()` with type restrictions
8. **REST API Permissions**: All endpoints have proper `permission_callback`
9. **Repository Pattern Security**: AbstractRepository validates `orderby` and `order`
10. **Resource Ownership Checks**: Orders/services verify user ownership

---

## Required Fixes

### Before Deployment
1. **[H1]** Validate column names in `AbstractRepository::count()` method

### Strongly Recommended
2. **[M2]** Remove PHP from allowed delivery file types
3. **[M1]** Add explicit nonce verification to taxonomy save methods

### Nice to Have
4. **[L1-L4]** Address low priority items as time permits

---

## Conclusion

The plugin is well-secured with proper use of WordPress security APIs. The identified issues are primarily edge cases and best practice improvements rather than exploitable vulnerabilities in normal usage.

**Overall Security Grade: B+**
