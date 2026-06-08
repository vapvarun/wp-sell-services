<?php
/**
 * Admin Viewer Type Contracts
 *
 * Canonical PHPStan type aliases for the data the new admin viewers consume.
 * This interface declares NO methods and is never implemented at runtime — it
 * exists purely as a single, importable home for the array-shape definitions so
 * every admin viewer (audit log, sub-orders, review queue, vendor profile) can
 * `@phpstan-import-type` the exact shape it reads instead of fighting `mixed`
 * and "access offset on mixed" errors at PHPStan level 6.
 *
 * Each alias mirrors a real data source already in the codebase:
 *
 * - {@see \WPSellServices\Services\AuditLogService::query()} returns rows of
 *   the `AuditLogRow` shape (raw `wpss_audit_log` rows decorated as objects).
 * - The orders list (`wpss_orders`) surfaces sub-order context via the
 *   `SubOrderItem` shape (platform marker + parent reference).
 * - The reviews table (`wpss_reviews`) feeds the moderation queue via the
 *   `ReviewQueueRow` shape.
 * - The vendor profile (`wpss_vendor_profiles`) exposes the admin-relevant
 *   moderation fields via the `VendorProfileFields` shape.
 *
 * NOTHING in this file affects runtime behaviour. It is a type contract only.
 *
 * @package WPSellServices\Contracts
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Type registry for the admin viewers.
 *
 * Import the aliases you need by adding an import line to the consuming
 * class docblock, for example (note: written without the leading "@" here so
 * this documentation block is not itself parsed as an import):
 *
 *     phpstan-import-type AuditLogRow from \WPSellServices\Contracts\AdminViewerShapes
 *
 * @phpstan-type AuditLogRow object{
 *     id: int,
 *     actor_id: int,
 *     actor_role: string|null,
 *     event_type: string,
 *     object_type: string,
 *     object_id: int,
 *     action: string|null,
 *     from_value: string|null,
 *     to_value: string|null,
 *     is_forced: int|bool,
 *     context: string|null,
 *     created_at: string
 * }
 *
 * @phpstan-type AuditLogQueryResult array{
 *     rows: array<int, AuditLogRow>,
 *     total: int,
 *     page: int,
 *     per_page: int
 * }
 *
 * @phpstan-type SubOrderItem object{
 *     id: int,
 *     order_number: string,
 *     customer_id: int,
 *     vendor_id: int,
 *     service_id: int,
 *     platform: string,
 *     platform_order_id: int|null,
 *     platform_item_id: int|null,
 *     total: string|float,
 *     currency: string,
 *     status: string,
 *     created_at: string
 * }
 *
 * @phpstan-type ReviewQueueRow object{
 *     id: int,
 *     order_id: int,
 *     reviewer_id: int,
 *     reviewee_id: int,
 *     service_id: int,
 *     customer_id: int,
 *     vendor_id: int,
 *     rating: int,
 *     review: string|null,
 *     review_type: string,
 *     vendor_reply: string|null,
 *     status: string,
 *     is_public: int|bool,
 *     helpful_count: int,
 *     created_at: string
 * }
 *
 * @phpstan-type VendorProfileFields object{
 *     id: int,
 *     user_id: int,
 *     display_name: string|null,
 *     status: string,
 *     verification_tier: string,
 *     custom_commission_rate: string|float|null,
 *     vacation_mode: int|bool,
 *     vacation_message: string|null,
 *     created_at: string
 * }
 *
 * @since 1.2.0
 */
interface AdminViewerShapes {

	/**
	 * Object type slug for the audit-log subject reference on orders.
	 *
	 * @var string
	 */
	public const OBJECT_TYPE_ORDER = 'order';

	/**
	 * Object type slug for the audit-log subject reference on disputes.
	 *
	 * @var string
	 */
	public const OBJECT_TYPE_DISPUTE = 'dispute';

	/**
	 * Object type slug for the audit-log subject reference on reviews.
	 *
	 * @var string
	 */
	public const OBJECT_TYPE_REVIEW = 'review';

	/**
	 * Object type slug for the audit-log subject reference on vendor profiles.
	 *
	 * @var string
	 */
	public const OBJECT_TYPE_VENDOR = 'vendor';

	/**
	 * Platform marker for native standalone orders (not a sub-order).
	 *
	 * @var string
	 */
	public const PLATFORM_STANDALONE = 'standalone';
}
