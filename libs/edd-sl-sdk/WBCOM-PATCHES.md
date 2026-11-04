# Wbcom build of the EDD Software Licensing SDK

**Source:** `vapvarun/edd-sl-sdk` tag `1.0.4-wbcom.3` (registers as `1.0.4.3`, so it wins over older copies
bundled by other plugins). EDD's repo is that repo's `edd-reference` remote.

Never patch this folder. Fix the SDK repo, tag it, and copy the shipped files back here
(`edd-sl-sdk.php`, `src/`, `assets/`, `templates/`, `languages/`, `composer.json`).

Verify the copy: `grep -c 1_0_4_3 libs/edd-sl-sdk/edd-sl-sdk.php` must be greater than 0.

The three fixes this file used to list (non-object API data guard, `requires` / `requires_php` null-coalesce,
asset URLs via `plugins_url()`) are part of the tag, alongside `fallback_url` (a second host when the first
cannot be reached).

It is vendored once, in the free plugin. Pro requires the free plugin and loads this same copy.
