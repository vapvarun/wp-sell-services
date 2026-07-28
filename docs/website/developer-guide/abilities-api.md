# Abilities API

WP Sell Services registers **12 abilities** through the WordPress Abilities API,
and Pro adds **3 more**. They give AI agents, MCP clients, and any Abilities-aware
tool a described, permission-checked way to work with your marketplace -- browse
services, place and manage orders, message, review, and read earnings -- without
that client needing to learn the REST surface.

Every ability is registered with an input schema, an output schema, an execute
callback, and its own permission check.

**They are a facade over the REST API, not a second implementation.** Each
execute callback builds a `WP_REST_Request` against the plugin's own
`wpss/v1` routes and dispatches it internally. That has three consequences worth
knowing:

- Behaviour cannot drift between an ability and its REST route -- there is one code path.
- The route's own permission callback still runs, on top of the ability's. An ability whose permission check passes can still be refused by the endpoint.
- Any filter you have added to a REST route applies to the ability too, for free.

## Requirements

The Abilities API is part of **WordPress 6.9 and later**. WP Sell Services
supports WordPress 6.4+, so on 6.4-6.8 the registrar detects that
`wp_register_ability()` is absent and **registers nothing**. No error, no notice:
the marketplace works exactly as before, and the abilities simply are not there.

To check on a given site:

```bash
wp eval 'var_dump( function_exists( "wp_register_ability" ) );'
wp eval 'print_r( array_keys( wp_get_abilities() ) );'
```

## The category

All abilities live under one category:

| Slug | Label |
|------|-------|
| `wpss-marketplace` | Service Marketplace |

Registered on `wp_abilities_api_categories_init`; the abilities themselves on
`wp_abilities_api_init`.

## Free abilities

All are exposed to REST (`show_in_rest`) and none are marked destructive.

| Ability | Permission | Read-only | Input |
|---------|-----------|-----------|-------|
| `wpss/browse-services` | Public | Yes | `search`, `category`, `min_price`, `max_price`, `sort_by`, `page`, `per_page` |
| `wpss/view-service` | Public | Yes | `id` |
| `wpss/create-service` | `wpss_manage_services` | No | `title`, `description`, `category`, `packages[]` |
| `wpss/manage-orders` | Logged in | No | `action`, `order_id`, `status`, `page`, `per_page` |
| `wpss/send-message` | Logged in | No | `order_id`, `message` |
| `wpss/view-earnings` | `wpss_manage_services` | Yes | `period` |
| `wpss/request-withdrawal` | `wpss_manage_services` | No | `amount`, `method` |
| `wpss/post-buyer-request` | Logged in | No | `title`, `description`, `budget`, `category`, `deadline` |
| `wpss/submit-proposal` | `wpss_respond_to_requests` | No | `request_id`, `price`, `delivery_days`, `cover_letter` |
| `wpss/leave-review` | Logged in | No | `order_id`, `rating`, `comment` |
| `wpss/view-notifications` | Logged in | Yes | `unread_only`, `page`, `per_page` |
| `wpss/manage-favorites` | Logged in | No | `action`, `service_id` |

`wpss/browse-services` and `wpss/view-service` are the only two with a permission
callback that returns `true` unconditionally -- they expose the same public
catalog a visitor can already see. Everything else requires at least a logged-in
user.

`wpss/manage-orders` and `wpss/manage-favorites` take an `action` enum rather
than splitting into one ability per verb, which keeps the surface small for
clients enumerating what they can do.

## Pro abilities **[PRO]**

Registered only when Pro is active **with a valid license** -- an expired license
means Pro loads nothing, so these disappear along with the rest of Pro.

| Ability | Permission | Read-only | Input |
|---------|-----------|-----------|-------|
| `wpss/analytics-overview` | `manage_options` or `wpss_view_analytics` | Yes | `period`, `metrics[]` |
| `wpss/manage-vendor-subscriptions` | `manage_options` | Yes | `action`, `plan_id`, `vendor_id` |
| `wpss/configure-stripe-connect` | `manage_options` | Yes | `action` |

## Consuming them

### REST

Abilities are discoverable and runnable under the core namespace:

```
/wp-json/wp-abilities/v1/abilities
/wp-json/wp-abilities/v1/abilities/wpss/browse-services
```

Note this is **`wp-abilities/v1`**, WordPress's namespace -- not the plugin's
`wpss/v1`. The two are separate surfaces over the same services.

A client sees only the abilities whose permission callback passes for the
authenticated user, so an anonymous request lists the two public ones and a
vendor's token lists considerably more.

### JavaScript

Use `@wordpress/abilities` rather than hand-rolling fetches, so permission
filtering and schema validation come for free:

```js
import { store as abilitiesStore } from '@wordpress/abilities';
```

## Choosing abilities or REST

They are complementary, not alternatives:

- **Abilities** are self-describing. A client can enumerate what exists, read the input schema, and call it without prior knowledge of your API. That is what makes them useful to an AI agent.
- **REST** is finer-grained and complete. Every route in [REST API Controllers](rest-api-controllers.md) is available; abilities cover the twelve highest-value marketplace actions.

Build a normal integration on REST. Reach for abilities when the caller is an
agent, an MCP server, or anything that has to discover capability at runtime.

## Adding your own

Register on the same hook and reuse the existing category so your ability appears
alongside the marketplace's:

```php
add_action( 'wp_abilities_api_init', function () {
    if ( ! function_exists( 'wp_register_ability' ) ) {
        return; // WordPress < 6.9.
    }

    wp_register_ability( 'my-plugin/export-orders', array(
        'label'               => __( 'Export Orders', 'my-plugin' ),
        'description'         => __( 'Export marketplace orders as CSV for a date range.', 'my-plugin' ),
        'category'            => 'wpss-marketplace',
        'input_schema'        => array(
            'type'       => 'object',
            'properties' => array(
                'from' => array( 'type' => 'string', 'format' => 'date' ),
                'to'   => array( 'type' => 'string', 'format' => 'date' ),
            ),
        ),
        'output_schema'       => array( 'type' => 'object' ),
        'execute_callback'    => 'my_plugin_export_orders',
        'permission_callback' => static function (): bool {
            return current_user_can( 'wpss_manage_orders' );
        },
        'meta'                => array(
            'annotations' => array(
                'readonly'    => true,
                'destructive' => false,
            ),
            'show_in_rest' => true,
        ),
    ) );
} );
```

Two things to get right:

- **Gate on a real capability.** Your permission callback is the only thing standing between an agent and the action. Reuse the plugin's capabilities -- see [Capabilities & Roles](capabilities.md).
- **Annotate honestly.** `readonly` and `destructive` are how a client decides whether to confirm with a human first. Marking a write as read-only invites an agent to call it unattended.

## Troubleshooting

| Symptom | Cause |
|---------|-------|
| No `wpss/*` abilities at all | WordPress below 6.9, so registration self-skips |
| Pro abilities missing | Pro inactive or its license invalid |
| Ability listed but a client cannot call it | Either the ability's permission callback, or the underlying REST route's, returned false for that user |
| Yours does not appear | Registered on the wrong hook, or `show_in_rest` not set |
| Changes not reflected | Object cache -- flush and retry |

## Related

- [Capabilities & Roles](capabilities.md)
- [REST API Controllers](rest-api-controllers.md)
- [Building Custom Integrations](custom-integrations.md)
- [Database Schema](database-schema.md)
