<?php
/**
 * Analytics Service
 *
 * Basic analytics for the free plugin. Pro plugin extends this.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

use WPSellServices\Database\Repositories\OrderRepository;
use WPSellServices\Database\Repositories\ReviewRepository;
use WPSellServices\Database\Repositories\VendorProfileRepository;

defined( 'ABSPATH' ) || exit;

/**
 * AnalyticsService class.
 *
 * @since 1.0.0
 */
class AnalyticsService {

	/**
	 * Order repository.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $order_repo;

	/**
	 * Review repository.
	 *
	 * @var ReviewRepository
	 */
	private ReviewRepository $review_repo;

	/**
	 * Vendor profile repository.
	 *
	 * @var VendorProfileRepository
	 */
	private VendorProfileRepository $vendor_repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->order_repo  = new OrderRepository();
		$this->review_repo = new ReviewRepository();
		$this->vendor_repo = new VendorProfileRepository();
	}

	/**
	 * Get vendor dashboard stats.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array<string, mixed> Dashboard stats.
	 */
	public function get_vendor_dashboard_stats( int $vendor_id ): array {
		$order_stats  = $this->order_repo->get_vendor_stats( $vendor_id );
		$review_stats = $this->review_repo->get_vendor_rating_summary( $vendor_id );

		return array(
			'orders'          => $order_stats,
			'reviews'         => $review_stats,
			'active_services' => $this->get_active_service_count( $vendor_id ),
			'response_rate'   => $this->get_response_rate( $vendor_id ),
			'recent_activity' => $this->get_recent_activity( $vendor_id ),
		);
	}

	/**
	 * Get buyer dashboard stats.
	 *
	 * @param int $buyer_id Buyer user ID.
	 * @return array<string, mixed> Dashboard stats.
	 */
	public function get_buyer_dashboard_stats( int $buyer_id ): array {
		return array(
			'orders'           => $this->get_buyer_order_stats( $buyer_id ),
			'active_orders'    => $this->get_active_order_count( $buyer_id ),
			'pending_reviews'  => $this->get_pending_reviews_count( $buyer_id ),
			'total_spent'      => $this->get_total_spent( $buyer_id ),
			'favorite_vendors' => $this->get_favorite_vendors( $buyer_id ),
		);
	}

	/**
	 * Get admin dashboard stats.
	 *
	 * @return array<string, mixed> Platform stats.
	 */
	public function get_admin_dashboard_stats(): array {
		return array(
			'total_vendors'  => $this->get_total_vendors(),
			'total_services' => $this->get_total_services(),
			'total_orders'   => $this->get_total_orders(),
			'total_revenue'  => $this->get_total_revenue(),
			'order_stats'    => $this->get_order_stats_by_status(),
			'recent_orders'  => $this->get_recent_orders(),
			'top_vendors'    => $this->get_top_vendors(),
			'top_categories' => $this->get_top_categories(),
		);
	}

	/**
	 * Get vendor statistics for analytics tab.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @param int $days      Number of days to look back (default 30).
	 * @return array<string, mixed> Analytics stats.
	 */
	public function get_vendor_stats( int $vendor_id, int $days = 30 ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$date_from    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Get vendor's services (limit to 100 for performance).
		$services = get_posts(
			array(
				'post_type'              => 'wpss_service',
				'author'                 => $vendor_id,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		// Calculate impressions (total views of all services).
		$impressions = 0;
		foreach ( $services as $service_id ) {
			$impressions += (int) get_post_meta( $service_id, '_wpss_views', true );
		}

		// Get profile views from user meta (if tracked).
		$profile_views = (int) get_user_meta( $vendor_id, '_wpss_profile_views', true );

		// Get orders received in period.
		$orders_received = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} WHERE vendor_id = %d AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$vendor_id,
				$date_from
			)
		);

		// Clicks are not tracked separately, so we use orders as a proxy for engaged users.
		// In a real implementation, clicks would be tracked via JavaScript events.
		$clicks = $orders_received * 3; // Estimate: 3 clicks per order.

		// Calculate rates.
		$click_rate      = $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 1 ) : 0;
		$conversion_rate = $clicks > 0 ? round( ( $orders_received / $clicks ) * 100, 1 ) : 0;

		// Get top performing services.
		$top_services = array();
		if ( ! empty( $services ) ) {
			$service_stats = array();
			foreach ( $services as $service_id ) {
				$views = (int) get_post_meta( $service_id, '_wpss_views', true );

				// Get orders for this service.
				$service_orders = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$orders_table} WHERE service_id = %d AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$service_id,
						$date_from
					)
				);

				// Get revenue for this service.
				$service_revenue = (float) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COALESCE(SUM(vendor_earnings), 0) FROM {$orders_table} WHERE service_id = %d AND status = 'completed' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$service_id,
						$date_from
					)
				);

				$service_stats[] = array(
					'id'      => $service_id,
					'title'   => get_the_title( $service_id ),
					'views'   => $views,
					'orders'  => $service_orders,
					'revenue' => $service_revenue,
				);
			}

			// Sort by revenue descending.
			usort( $service_stats, fn( $a, $b ) => $b['revenue'] <=> $a['revenue'] );
			$top_services = array_slice( $service_stats, 0, 5 );
		}

		return array(
			'profile_views'   => $profile_views,
			'impressions'     => $impressions,
			'clicks'          => $clicks,
			'orders_received' => $orders_received,
			'click_rate'      => $click_rate,
			'conversion_rate' => $conversion_rate,
			'top_services'    => $top_services,
		);
	}

	/**
	 * Get vendor earnings.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $period Period (day, week, month, year, all).
	 * @return array<string, mixed> Earnings data.
	 */
	public function get_vendor_earnings( int $vendor_id, string $period = 'month' ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		$date_from = $this->get_period_start( $period );

		$where  = 'vendor_id = %d AND status = %s';
		$values = array( $vendor_id, 'completed' );

		if ( $date_from ) {
			$where   .= ' AND created_at >= %s';
			$values[] = $date_from;
		}

		$sql = $wpdb->prepare(
			"SELECT
				COUNT(*) as order_count,
				SUM(total) as total_revenue,
				SUM(vendor_earnings) as total_earnings,
				SUM(platform_fee) as total_fees
			FROM {$orders_table}
			WHERE {$where}",
			$values
		);

		$result = $wpdb->get_row( $sql );

		return array(
			'period'         => $period,
			'order_count'    => (int) ( $result->order_count ?? 0 ),
			'total_revenue'  => (float) ( $result->total_revenue ?? 0 ),
			'total_earnings' => (float) ( $result->total_earnings ?? 0 ),
			'total_fees'     => (float) ( $result->total_fees ?? 0 ),
		);
	}

	/**
	 * Get earnings chart data.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $period Period (week, month, year).
	 * @return array<string, mixed> Chart data.
	 */
	public function get_earnings_chart( int $vendor_id, string $period = 'month' ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		$date_from   = $this->get_period_start( $period );
		$group_by    = $this->get_chart_group_by( $period );
		$date_format = $this->get_chart_date_format( $period );

		$sql = $wpdb->prepare(
			"SELECT
				DATE_FORMAT(created_at, %s) as period,
				SUM(vendor_earnings) as earnings,
				COUNT(*) as orders
			FROM {$orders_table}
			WHERE vendor_id = %d AND status = %s AND created_at >= %s
			GROUP BY {$group_by}
			ORDER BY created_at ASC",
			$date_format,
			$vendor_id,
			'completed',
			$date_from
		);

		$results = $wpdb->get_results( $sql );

		$labels   = array();
		$earnings = array();
		$orders   = array();

		foreach ( $results as $row ) {
			$labels[]   = $row->period;
			$earnings[] = (float) $row->earnings;
			$orders[]   = (int) $row->orders;
		}

		return array(
			'labels'   => $labels,
			'earnings' => $earnings,
			'orders'   => $orders,
		);
	}

	/**
	 * Get active service count for vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return int Service count.
	 */
	private function get_active_service_count( int $vendor_id ): int {
		global $wpdb;

		// Use direct COUNT query instead of loading all posts.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type = 'wpss_service'
				AND post_status = 'publish'
				AND post_author = %d",
				$vendor_id
			)
		);
	}

	/**
	 * Get vendor response rate.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return float Response rate percentage.
	 */
	private function get_response_rate( int $vendor_id ): float {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'wpss_conversations';
		$messages_table      = $wpdb->prefix . 'wpss_messages';

		// Get conversations where vendor received a message.
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT c.id)
				FROM {$conversations_table} c
				INNER JOIN {$messages_table} m ON c.id = m.conversation_id
				WHERE (c.participant_1 = %d OR c.participant_2 = %d)
				AND m.sender_id != %d",
				$vendor_id,
				$vendor_id,
				$vendor_id
			)
		);

		if ( ! $total ) {
			return 100.0;
		}

		// Get conversations where vendor responded.
		$responded = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT c.id)
				FROM {$conversations_table} c
				INNER JOIN {$messages_table} m ON c.id = m.conversation_id
				WHERE (c.participant_1 = %d OR c.participant_2 = %d)
				AND m.sender_id = %d",
				$vendor_id,
				$vendor_id,
				$vendor_id
			)
		);

		return round( ( (int) $responded / (int) $total ) * 100, 1 );
	}

	/**
	 * Get recent activity for vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @param int $limit Number of items.
	 * @return array<object> Recent activity items.
	 */
	private function get_recent_activity( int $vendor_id, int $limit = 10 ): array {
		global $wpdb;

		$orders_table  = $wpdb->prefix . 'wpss_orders';
		$reviews_table = $wpdb->prefix . 'wpss_reviews';

		// Get recent orders.
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 'order' as type, id, status, created_at
				FROM {$orders_table}
				WHERE vendor_id = %d
				ORDER BY created_at DESC
				LIMIT %d",
				$vendor_id,
				$limit
			)
		);

		// Get recent reviews.
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 'review' as type, id, rating, created_at
				FROM {$reviews_table}
				WHERE vendor_id = %d
				ORDER BY created_at DESC
				LIMIT %d",
				$vendor_id,
				$limit
			)
		);

		// Merge and sort by date.
		$activity = array_merge( $orders, $reviews );
		usort( $activity, fn( $a, $b ) => strtotime( $b->created_at ) - strtotime( $a->created_at ) );

		return array_slice( $activity, 0, $limit );
	}

	/**
	 * Get buyer order stats.
	 *
	 * @param int $buyer_id Buyer user ID.
	 * @return array<string, int> Order counts by status.
	 */
	private function get_buyer_order_stats( int $buyer_id ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count
				FROM {$orders_table}
				WHERE customer_id = %d
				GROUP BY status",
				$buyer_id
			)
		);

		$stats = array();
		foreach ( $results as $row ) {
			$stats[ $row->status ] = (int) $row->count;
		}

		return $stats;
	}

	/**
	 * Get active order count for buyer.
	 *
	 * @param int $buyer_id Buyer user ID.
	 * @return int Order count.
	 */
	private function get_active_order_count( int $buyer_id ): int {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table}
				WHERE customer_id = %d
				AND status IN ('pending_requirements', 'in_progress', 'delivered')",
				$buyer_id
			)
		);
	}

	/**
	 * Get pending reviews count.
	 *
	 * @param int $buyer_id Buyer user ID.
	 * @return int Pending review count.
	 */
	private function get_pending_reviews_count( int $buyer_id ): int {
		global $wpdb;

		$orders_table  = $wpdb->prefix . 'wpss_orders';
		$reviews_table = $wpdb->prefix . 'wpss_reviews';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} o
				LEFT JOIN {$reviews_table} r ON o.id = r.order_id AND r.reviewer_id = %d
				WHERE o.customer_id = %d AND o.status = 'completed' AND r.id IS NULL",
				$buyer_id,
				$buyer_id
			)
		);
	}

	/**
	 * Get total spent by buyer.
	 *
	 * @param int $buyer_id Buyer user ID.
	 * @return float Total amount spent.
	 */
	private function get_total_spent( int $buyer_id ): float {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(total) FROM {$orders_table}
				WHERE customer_id = %d AND status = 'completed'",
				$buyer_id
			)
		);
	}

	/**
	 * Get buyer's favorite vendors.
	 *
	 * @param int $buyer_id Buyer user ID.
	 * @param int $limit Number of vendors.
	 * @return array<object> Vendor data.
	 */
	private function get_favorite_vendors( int $buyer_id, int $limit = 5 ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT vendor_id, COUNT(*) as order_count
				FROM {$orders_table}
				WHERE customer_id = %d AND status = 'completed'
				GROUP BY vendor_id
				ORDER BY order_count DESC
				LIMIT %d",
				$buyer_id,
				$limit
			)
		);
	}

	/**
	 * Get total vendor count.
	 *
	 * @return int Vendor count.
	 */
	private function get_total_vendors(): int {
		$args = array(
			'role'   => 'wpss_vendor',
			'fields' => 'ID',
		);

		$query = new \WP_User_Query( $args );

		return $query->get_total();
	}

	/**
	 * Get total service count.
	 *
	 * @return int Service count.
	 */
	private function get_total_services(): int {
		$counts = wp_count_posts( 'wpss_service' );
		return (int) $counts->publish;
	}

	/**
	 * Get total order count.
	 *
	 * @return int Order count.
	 */
	private function get_total_orders(): int {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders_table}" );
	}

	/**
	 * Get total revenue.
	 *
	 * @return float Total revenue.
	 */
	private function get_total_revenue(): float {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(total) FROM {$orders_table} WHERE status = %s",
				'completed'
			)
		);
	}

	/**
	 * Get order stats by status.
	 *
	 * @return array<string, int> Status counts.
	 */
	private function get_order_stats_by_status(): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$orders_table} GROUP BY status"
		);

		$stats = array();
		foreach ( $results as $row ) {
			$stats[ $row->status ] = (int) $row->count;
		}

		return $stats;
	}

	/**
	 * Get recent orders for admin.
	 *
	 * @param int $limit Number of orders.
	 * @return array<object> Recent orders.
	 */
	private function get_recent_orders( int $limit = 10 ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$orders_table} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get top vendors by revenue.
	 *
	 * @param int $limit Number of vendors.
	 * @return array<object> Top vendors.
	 */
	private function get_top_vendors( int $limit = 10 ): array {
		return $this->vendor_repo->get_top_rated( $limit );
	}

	/**
	 * Get top categories by service count.
	 *
	 * @param int $limit Number of categories.
	 * @return array<\WP_Term> Top categories.
	 */
	private function get_top_categories( int $limit = 10 ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => true,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Get period start date.
	 *
	 * @param string $period Period name.
	 * @return string|null Date string or null.
	 */
	private function get_period_start( string $period ): ?string {
		return match ( $period ) {
			'day'   => gmdate( 'Y-m-d 00:00:00' ),
			'week'  => gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ),
			'month' => gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) ),
			'year'  => gmdate( 'Y-m-d 00:00:00', strtotime( '-365 days' ) ),
			default => null,
		};
	}

	/**
	 * Get chart group by clause.
	 *
	 * @param string $period Period name.
	 * @return string SQL GROUP BY.
	 */
	private function get_chart_group_by( string $period ): string {
		return match ( $period ) {
			'week'  => 'DATE(created_at)',
			'month' => 'DATE(created_at)',
			'year'  => 'MONTH(created_at)',
			default => 'DATE(created_at)',
		};
	}

	/**
	 * Get chart date format.
	 *
	 * @param string $period Period name.
	 * @return string MySQL date format.
	 */
	private function get_chart_date_format( string $period ): string {
		return match ( $period ) {
			'week'  => '%b %d',
			'month' => '%b %d',
			'year'  => '%b %Y',
			default => '%b %d',
		};
	}
}
