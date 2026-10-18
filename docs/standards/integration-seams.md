# Integration seams

Rule: adding a provider (a payment gateway, a store plugin, a wallet, a storage service, later a shipping
carrier) is **one class plus one filter line**. Core never branches on a provider's name.
This is the Wbcom Core-First "Integration seams" rule applied to this plugin.

WP Sell Services is the commerce core for services, built so other product types (physical goods later)
plug in the same way. Service-only logic (packages, requirements, revisions, delivery) must not leak into
generic order, checkout or payment code.

## The pattern

1. **One interface per seam**, in Free: `src/Integrations/Contracts/<Seam>ProviderInterface.php`. It holds
   every behaviour core calls (render, save, connect, charge, upload ...). Pro and third parties implement
   it; Pro's own interfaces only `extend` Free's.
2. **A registry filled by a filter**: `wpss_<seam>_providers` (or the existing name in the table). The
   value is `id => instance`.
3. **One reader.** Core reads the registry through `wpss_validate_registry( $items, Contract::class, $filter )`,
   which keeps only conforming instances and raises a developer notice for the rest.
4. **The provider owns** its settings fields, its settings card, its strings, and its REST or webhook
   callbacks. Core renders what the provider returns.
5. **A contract test** registers a stub only through the filter and proves it works
   (`tests/test-integration-seam-contract.php`).

## Seams today

| Seam | Filter | Contract (Free) | Status |
|---|---|---|---|
| E-commerce rail | `wpss_ecommerce_adapters` | `EcommerceAdapterInterface` | Done; contract-tested |
| Storage | `wpss_storage_providers` | `StorageProviderInterface` | Done; contract-tested |
| Wallet | `wpss_wallet_providers` | `WalletProviderInterface` | Done; contract-tested |
| Payment gateway | `wpss_payment_gateways` | `PaymentGatewayInterface` | Done, registry validated. A gateway extends `AbstractPaymentGateway` and owns its REST create/confirm (`rest_create_intent`, `rest_confirm_payment`) and its settings card (`get_settings_card`, with `order`). `PaymentController` and `Settings` name no gateway |
| Dashboard section | `wpss_dashboard_sections` | array | Done; a section registered here is also a known route; contract-tested |
| SEO plugin | `wpss_seo_integrations` | `SeoIntegrationInterface` | Done; Yoast, Rank Math and AIOSEO are three providers; contract-tested |
| Product type, fulfillment | planned | | Inventory and proposed seam in `docs/architecture/PRODUCT-TYPE-INVENTORY.md`. Not built in 1.9.0 |

Removed in 1.9.0: `wpss_email_providers` (registered, never read by anything).

## Adding a provider (checklist)

- [ ] Class implements the seam interface; `get_id()` matches the registry key.
- [ ] Registered with `add_filter( 'wpss_<seam>_providers', fn( $p ) => $p + array( 'id' => new Mine() ) )`.
- [ ] Settings come from the provider's own fields; no edit to `Settings.php`.
- [ ] No `if ( 'myid' === $id )` added anywhere in core.
- [ ] Hook documented in `docs/website/developer-guide/hooks-filters.md`.
