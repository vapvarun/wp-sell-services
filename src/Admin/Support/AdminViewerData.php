<?php
/**
 * Admin Viewer Data Coercion
 *
 * Bridges the raw `$wpdb` rows the admin viewers receive (audit log,
 * sub-orders, review queue, vendor profile) to the strictly-typed,
 * documented array-shapes those viewers should render. Each method takes a
 * raw row and returns a normalised `array{...}` whose keys and value types
 * PHPStan can verify at level 6, so the viewers themselves never touch
 * `mixed` offsets.
 *
 * The heavy lifting of per-column coercion is delegated to {@see AdminRow};
 * this class only assembles the per-shape result arrays. It performs NO
 * queries and contains NO business logic — it is the typed read boundary the
 * feature directions build on.
 *
 * @package WPSellServices\Admin\Support
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Support;

use WPSellServices\Contracts\AdminViewerShapes;
use WPSellServices\Data\AdminRow;

defined( 'ABSPATH' ) || exit;

/**
 * Typed accessors for the admin viewer rows.
 *
 * @since 1.2.0
 */
final class AdminViewerData {

	/**
	 * Normalise a raw audit-log row into the typed audit-log shape.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed>|object $row Raw `wpss_audit_log` row.
	 * @return array{
	 *     id: int,
	 *     actor_id: int,
	 *     actor_role: string,
	 *     event_type: string,
	 *     object_type: string,
	 *     object_id: int,
	 *     action: string,
	 *     from_value: string,
	 *     to_value: string,
	 *     is_forced: bool,
	 *     context: array<string, mixed>,
	 *     created_at: string
	 * }
	 */
	public static function audit_log_row( array|object $row ): array {
		$r = AdminRow::from( $row );

		return array(
			'id'          => $r->int( 'id' ),
			'actor_id'    => $r->int( 'actor_id' ),
			'actor_role'  => $r->string( 'actor_role' ),
			'event_type'  => $r->string( 'event_type' ),
			'object_type' => $r->string( 'object_type' ),
			'object_id'   => $r->int( 'object_id' ),
			'action'      => $r->string( 'action' ),
			'from_value'  => $r->string( 'from_value' ),
			'to_value'    => $r->string( 'to_value' ),
			'is_forced'   => $r->bool( 'is_forced' ),
			'context'     => $r->json( 'context' ),
			'created_at'  => $r->string( 'created_at' ),
		);
	}

	/**
	 * Normalise a raw order row into the typed sub-order shape.
	 *
	 * `platform` defaults to `standalone` for native orders; `platform_order_id`
	 * is the parent reference for tip/extension/milestone/request sub-orders and
	 * is null when the order is not a sub-order.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed>|object $row Raw `wpss_orders` row.
	 * @return array{
	 *     id: int,
	 *     order_number: string,
	 *     customer_id: int,
	 *     vendor_id: int,
	 *     service_id: int,
	 *     platform: string,
	 *     platform_order_id: int|null,
	 *     platform_item_id: int|null,
	 *     total: float,
	 *     currency: string,
	 *     status: string,
	 *     created_at: string,
	 *     is_sub_order: bool
	 * }
	 */
	public static function sub_order_item( array|object $row ): array {
		$r        = AdminRow::from( $row );
		$platform = $r->string( 'platform', AdminViewerShapes::PLATFORM_STANDALONE );

		return array(
			'id'                => $r->int( 'id' ),
			'order_number'      => $r->string( 'order_number' ),
			'customer_id'       => $r->int( 'customer_id' ),
			'vendor_id'         => $r->int( 'vendor_id' ),
			'service_id'        => $r->int( 'service_id' ),
			'platform'          => $platform,
			'platform_order_id' => $r->nullable_int( 'platform_order_id' ),
			'platform_item_id'  => $r->nullable_int( 'platform_item_id' ),
			'total'             => $r->float( 'total' ),
			'currency'          => $r->string( 'currency', 'USD' ),
			'status'            => $r->string( 'status', 'pending_payment' ),
			'created_at'        => $r->string( 'created_at' ),
			'is_sub_order'      => AdminViewerShapes::PLATFORM_STANDALONE !== $platform,
		);
	}

	/**
	 * Normalise a raw review row into the typed review-queue shape.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed>|object $row Raw `wpss_reviews` row.
	 * @return array{
	 *     id: int,
	 *     order_id: int,
	 *     reviewer_id: int,
	 *     reviewee_id: int,
	 *     service_id: int,
	 *     customer_id: int,
	 *     vendor_id: int,
	 *     rating: int,
	 *     review: string,
	 *     review_type: string,
	 *     vendor_reply: string,
	 *     status: string,
	 *     is_public: bool,
	 *     helpful_count: int,
	 *     created_at: string
	 * }
	 */
	public static function review_queue_row( array|object $row ): array {
		$r = AdminRow::from( $row );

		return array(
			'id'            => $r->int( 'id' ),
			'order_id'      => $r->int( 'order_id' ),
			'reviewer_id'   => $r->int( 'reviewer_id' ),
			'reviewee_id'   => $r->int( 'reviewee_id' ),
			'service_id'    => $r->int( 'service_id' ),
			'customer_id'   => $r->int( 'customer_id' ),
			'vendor_id'     => $r->int( 'vendor_id' ),
			'rating'        => $r->int( 'rating' ),
			'review'        => $r->string( 'review' ),
			'review_type'   => $r->string( 'review_type', 'customer_to_vendor' ),
			'vendor_reply'  => $r->string( 'vendor_reply' ),
			'status'        => $r->string( 'status', 'approved' ),
			'is_public'     => $r->bool( 'is_public', true ),
			'helpful_count' => $r->int( 'helpful_count' ),
			'created_at'    => $r->string( 'created_at' ),
		);
	}

	/**
	 * Normalise a raw vendor-profile row into the admin moderation field shape.
	 *
	 * `verification_tier` is the seller level (new / rising / top_rated / pro);
	 * `custom_commission_rate` is the per-vendor commission override and is null
	 * when the platform default applies.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed>|object $row Raw `wpss_vendor_profiles` row.
	 * @return array{
	 *     id: int,
	 *     user_id: int,
	 *     display_name: string,
	 *     status: string,
	 *     seller_level: string,
	 *     commission_override: float|null,
	 *     vacation_mode: bool,
	 *     vacation_message: string,
	 *     created_at: string
	 * }
	 */
	public static function vendor_profile_fields( array|object $row ): array {
		$r = AdminRow::from( $row );

		return array(
			'id'                  => $r->int( 'id' ),
			'user_id'             => $r->int( 'user_id' ),
			'display_name'        => $r->string( 'display_name' ),
			'status'              => $r->string( 'status', 'active' ),
			'seller_level'        => $r->string( 'verification_tier', 'new' ),
			'commission_override' => $r->nullable_float( 'custom_commission_rate' ),
			'vacation_mode'       => $r->bool( 'vacation_mode' ),
			'vacation_message'    => $r->string( 'vacation_message' ),
			'created_at'          => $r->string( 'created_at' ),
		);
	}
}
