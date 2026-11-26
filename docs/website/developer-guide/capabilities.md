# Capabilities & Roles

WP Sell Services adds one role and a small set of capabilities. Read this page
before you build a custom role or restrict an area. Every statement below
matches what activation writes (`Activator::create_roles()`).

## The vendor role

`wpss_vendor` is registered on activation. A user gets it when they become a
vendor, and it is additive: a subscriber who becomes a vendor keeps reading and
gains selling.

It carries `read`, `upload_files`, five plugin capabilities, and the service
post-type capabilities for their own listings (`edit_wpss_services`,
`edit_published_wpss_services`, `publish_wpss_services`, `delete_wpss_services`,
`delete_published_wpss_services`).

Vendors do **not** get `edit_posts`. Services use their own capability type, so
a vendor cannot write blog posts, and a blog author cannot list services.

## The capabilities

| Capability | Grants |
|-----------|--------|
| `wpss_vendor` | Marks the user as a vendor |
| `wpss_manage_services` | Create and edit own services; also gates vendor earnings reads and withdrawal requests |
| `wpss_vendor_orders` | Work on own orders as the seller |
| `wpss_view_analytics` | Vendor analytics marker (admin analytics need `manage_options`) |
| `wpss_respond_to_requests` | Submit proposals on buyer requests |
| `wpss_manage_orders` | Admin Orders screen and forcing any order status (staff only) |
| `wpss_manage_disputes` | Admin Disputes screen (staff only) |
| `wpss_manage_settings` | Plugin Settings screen (staff only) |

The first five are the vendor set. Vendors never hold `wpss_manage_orders`.

## Who gets what

| Role | Capabilities |
|------|-------------|
| **administrator** | The vendor set, `wpss_manage_orders`, the others/private service caps, the buyer request caps, `wpss_manage_disputes`, `wpss_manage_settings` |
| **shop_manager** (WooCommerce) | The same staff set as administrator, without `wpss_manage_disputes` and `wpss_manage_settings` |
| **wpss_vendor** | The vendor set listed above |
| **author** | None. Activation removes any WPSS capability left from versions before 1.7.1 |
| **editor**, **contributor**, **subscriber** | None |

Administrators also hold `wpss_manage_vendors`. No screen or route checks it.

## What each capability gates

| Surface | Capability |
|---------|-----------|
| Admin: top-level Sell Services menu and Dashboard | `manage_options` |
| Admin: Orders screen | `wpss_manage_orders` |
| Admin: Disputes screen | `wpss_manage_disputes` (resolving a dispute needs `manage_options`) |
| Admin: Settings screen | `wpss_manage_settings` |
| Admin: Vendors, Withdrawals, Service Moderation, Review Moderation | `manage_options` |
| REST: moderation routes and other admin routes | `manage_options` |
| REST: create service | `wpss_manage_services` |
| Ability: `wpss/create-service` | `wpss_manage_services` |
| Ability: `wpss/view-earnings`, `wpss/request-withdrawal` | `wpss_manage_services` |
| Ability: `wpss/submit-proposal` | `wpss_respond_to_requests` |
| Ability: analytics overview **[PRO]** | `manage_options` |

**Moderation is admin-only.** Approving services, moderating reviews and
managing vendors need `manage_options`. No plugin capability delegates these
tasks to a lower role.

> **Note:** vendor **earnings** reads are gated on `wpss_manage_services`, not
> on a dedicated earnings capability. If you strip `wpss_manage_services` from
> a role to stop it publishing services, you also remove its access to earnings
> and withdrawals.

## Checking capabilities in code

Use `current_user_can()` as normal:

```php
if ( current_user_can( 'wpss_manage_disputes' ) ) {
    // Show the disputes link.
}
```

To ask "is this user a vendor?", use the plugin helper instead of checking the
role name. It is filterable, so sites that grant vendor status another way
still work:

```php
if ( wpss_is_vendor( $user_id ) ) {
    // ...
}

// Override the answer, e.g. treat a membership level as vendor status.
add_filter( 'wpss_is_vendor', function ( $is_vendor, $user_id ) {
    return $is_vendor || my_plugin_has_seller_membership( $user_id );
}, 10, 2 );
```

## Customising

### Remove a capability

```php
add_action( 'init', function () {
    $role = get_role( 'shop_manager' );
    if ( $role && $role->has_cap( 'wpss_manage_orders' ) ) {
        $role->remove_cap( 'wpss_manage_orders' );
    }
} );
```

Role capabilities are stored in the database, so this only needs to run once.
The `has_cap()` guard keeps it idempotent and cheap. Plugin activation and
upgrades re-add the staff set, so keep the code active.

### Make someone a vendor

Approve them through the vendor registration flow or **Sell Services >
Vendors**. Do not hand-assign the vendor capabilities to another role: the
plugin also creates the vendor profile row that the directory and payouts read.

## Gotchas

- **Roles persist.** `add_role()` and `add_cap()` write to the database. Removing your code does not remove the role, so clean up on your own deactivation hook.
- **Multisite super admins** pass `current_user_can()` for everything, so an admin-only screen is visible network-wide by design.
- **Verify after a migration.** `wp wpss preflight` checks that the expected capabilities exist; a role editor plugin can strip them without warning.

## Related

- [Database Schema](database-schema.md)
- [Abilities API](abilities-api.md)
- [REST API Controllers](rest-api-controllers.md)
- [Building Custom Integrations](custom-integrations.md)
- [Vendor Settings](../vendor-system/vendor-settings.md)
