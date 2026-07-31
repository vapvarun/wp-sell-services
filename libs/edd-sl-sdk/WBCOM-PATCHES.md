# Wbcom patches to the EDD Software Licensing SDK

**Upstream:** `easy-digital-downloads/edd-sl-sdk` v1.0.3
**Canonical patched copy:** `buddynext/libs/edd-sl-sdk` — this directory is kept
**byte-identical** to it.

Verify no drift:

```bash
diff -rq \
  ~/"Local Sites/buddynext-dev/app/public/wp-content/plugins/buddynext/libs/edd-sl-sdk" \
  libs/edd-sl-sdk
```

Empty output = in sync. Run this after any SDK version bump.

## Why this file exists

These three patches were each found the hard way, on a different product. Before
this file existed they were fixed once in BuddyNext and then re-discovered — and
re-fixed differently — in WP Sell Services. Any future SDK upgrade MUST re-apply
all three, or the bugs come back on every plugin at once.

## The patches

### 1. `src/Updaters/Plugin.php` — guard non-object API data

```php
// If we still don't have an object here, the API call failed and there's no cache.
if ( ! is_object( $_data ) ) {
    return $_data;
}
```

Without it, a failed store request with an empty cache fatals on PHP 8+ when the
next line reads a property off `false`.

### 2. `src/Updaters/Plugin.php` — null-coalesce `requires` and `requires_php`

Upstream already guards `url`, `package`, `new_version` and `tested` with
`?? ''` but leaves `requires` / `requires_php` bare, in the same
`get_limited_data()` block.

Our EDD download rows do not populate those fields, so PHP 8+ emits
`Warning: Undefined property: stdClass::$requires` whenever that payload is
built. It is built only when the store reports a NEWER version than the
installed one, which is why the warnings appear for customers with a pending
update but not on an up-to-date dev install.

Store-side follow-up: populate `requires` / `requires_php` / `tested` on the
download rows so WordPress shows real "Requires WP / Requires PHP" values in the
update UI instead of blanks.

### 3. `src/Utilities/Path.php` — resolve asset URLs via `plugins_url()`

Upstream derives the SDK asset URL from `$_SERVER['DOCUMENT_ROOT']`. Wherever
`realpath()` crosses a symlink or the document root does not prefix-match the
plugin path — LocalWP and many live hosts — the `str_replace` strips nothing, the
full filesystem path leaks into the URL, and every SDK CSS/JS asset 404s.

`plugins_url()` handles HTTPS, host, subdirectory installs, symlinks and
multisite correctly.

## Placement rule

The SDK lives in `libs/`, never `vendor/`. It is not a Composer dependency — it
was previously kept alive inside `vendor/` only by `.gitignore` negations, where
any `composer install` that prunes vendor would delete it and ship a plugin whose
SDK entry file loads but whose `src/` is gone.

It is vendored **once**, in the free plugin. Pro requires the free plugin
(`Requires Plugins: wp-sell-services`) and loads this same copy.
