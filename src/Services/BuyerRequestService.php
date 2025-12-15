<?php
/**
 * Buyer Request Service
 *
 * Business logic for buyer request management.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

use WPSellServices\PostTypes\BuyerRequestPostType;
use WPSellServices\Taxonomies\ServiceCategoryTaxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * BuyerRequestService class.
 *
 * @since 1.0.0
 */
class BuyerRequestService {

	/**
	 * Request statuses.
	 */
	public const STATUS_OPEN      = 'open';
	public const STATUS_IN_REVIEW = 'in_review';
	public const STATUS_HIRED     = 'hired';
	public const STATUS_EXPIRED   = 'expired';
	public const STATUS_CANCELLED = 'cancelled';

	/**
	 * Budget types.
	 */
	public const BUDGET_FIXED = 'fixed';
	public const BUDGET_RANGE = 'range';

	/**
	 * Proposals table.
	 *
	 * @var string
	 */
	private string $proposals_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->proposals_table = $wpdb->prefix . 'wpss_proposals';
	}

	/**
	 * Create a buyer request.
	 *
	 * @param array<string, mixed> $data Request data.
	 * @return int|false Post ID or false on failure.
	 */
	public function create( array $data ): int|false {
		$defaults = [
			'title'           => '',
			'description'     => '',
			'category_id'     => 0,
			'budget_type'     => self::BUDGET_FIXED,
			'budget_min'      => 0,
			'budget_max'      => 0,
			'delivery_days'   => 7,
			'attachments'     => [],
			'skills_required' => [],
			'expires_at'      => '',
		];

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields.
		if ( empty( $data['title'] ) || empty( $data['description'] ) ) {
			return false;
		}

		$post_data = [
			'post_type'    => BuyerRequestPostType::POST_TYPE,
			'post_title'   => sanitize_text_field( $data['title'] ),
			'post_content' => wp_kses_post( $data['description'] ),
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Save meta.
		$this->save_meta( $post_id, $data );

		// Set category.
		if ( $data['category_id'] ) {
			wp_set_object_terms( $post_id, [ (int) $data['category_id'] ], ServiceCategoryTaxonomy::TAXONOMY );
		}

		/**
		 * Fires when a buyer request is created.
		 *
		 * @since 1.0.0
		 * @param int   $post_id Post ID.
		 * @param array $data    Request data.
		 */
		do_action( 'wpss_buyer_request_created', $post_id, $data );

		return $post_id;
	}

	/**
	 * Update a buyer request.
	 *
	 * @param int                  $request_id Request post ID.
	 * @param array<string, mixed> $data Request data.
	 * @return bool True on success.
	 */
	public function update( int $request_id, array $data ): bool {
		$request = get_post( $request_id );

		if ( ! $request || $request->post_type !== BuyerRequestPostType::POST_TYPE ) {
			return false;
		}

		$post_data = [ 'ID' => $request_id ];

		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $data['description'] );
		}

		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				return false;
			}
		}

		// Update meta.
		$this->save_meta( $request_id, $data );

		// Update category.
		if ( isset( $data['category_id'] ) ) {
			wp_set_object_terms( $request_id, [ (int) $data['category_id'] ], ServiceCategoryTaxonomy::TAXONOMY );
		}

		/**
		 * Fires when a buyer request is updated.
		 *
		 * @since 1.0.0
		 * @param int   $request_id Request post ID.
		 * @param array $data       Updated data.
		 */
		do_action( 'wpss_buyer_request_updated', $request_id, $data );

		return true;
	}

	/**
	 * Save request meta.
	 *
	 * @param int                  $request_id Request post ID.
	 * @param array<string, mixed> $data Meta data.
	 * @return void
	 */
	private function save_meta( int $request_id, array $data ): void {
		$meta_fields = [
			'budget_type'     => 'sanitize_key',
			'budget_min'      => 'floatval',
			'budget_max'      => 'floatval',
			'delivery_days'   => 'absint',
			'status'          => 'sanitize_key',
			'expires_at'      => 'sanitize_text_field',
		];

		foreach ( $meta_fields as $field => $sanitize ) {
			if ( isset( $data[ $field ] ) ) {
				update_post_meta( $request_id, '_wpss_' . $field, $sanitize( $data[ $field ] ) );
			}
		}

		// Handle arrays.
		if ( isset( $data['attachments'] ) && is_array( $data['attachments'] ) ) {
			update_post_meta( $request_id, '_wpss_attachments', array_map( 'absint', $data['attachments'] ) );
		}

		if ( isset( $data['skills_required'] ) && is_array( $data['skills_required'] ) ) {
			update_post_meta( $request_id, '_wpss_skills_required', array_map( 'sanitize_text_field', $data['skills_required'] ) );
		}

		// Set default status if not set.
		if ( ! get_post_meta( $request_id, '_wpss_status', true ) ) {
			update_post_meta( $request_id, '_wpss_status', self::STATUS_OPEN );
		}

		// Set default expiry if not set.
		if ( ! get_post_meta( $request_id, '_wpss_expires_at', true ) && empty( $data['expires_at'] ) ) {
			$default_days = (int) get_option( 'wpss_request_expiry_days', 30 );
			$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$default_days} days" ) );
			update_post_meta( $request_id, '_wpss_expires_at', $expires_at );
		}
	}

	/**
	 * Get buyer request.
	 *
	 * @param int $request_id Request post ID.
	 * @return object|null Request object or null.
	 */
	public function get( int $request_id ): ?object {
		$post = get_post( $request_id );

		if ( ! $post || $post->post_type !== BuyerRequestPostType::POST_TYPE ) {
			return null;
		}

		return $this->format_request( $post );
	}

	/**
	 * Format request post with meta.
	 *
	 * @param \WP_Post $post Post object.
	 * @return object Formatted request.
	 */
	private function format_request( \WP_Post $post ): object {
		$request = (object) [
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'description'     => $post->post_content,
			'author_id'       => (int) $post->post_author,
			'status'          => get_post_meta( $post->ID, '_wpss_status', true ) ?: self::STATUS_OPEN,
			'budget_type'     => get_post_meta( $post->ID, '_wpss_budget_type', true ) ?: self::BUDGET_FIXED,
			'budget_min'      => (float) get_post_meta( $post->ID, '_wpss_budget_min', true ),
			'budget_max'      => (float) get_post_meta( $post->ID, '_wpss_budget_max', true ),
			'delivery_days'   => (int) get_post_meta( $post->ID, '_wpss_delivery_days', true ),
			'attachments'     => get_post_meta( $post->ID, '_wpss_attachments', true ) ?: [],
			'skills_required' => get_post_meta( $post->ID, '_wpss_skills_required', true ) ?: [],
			'expires_at'      => get_post_meta( $post->ID, '_wpss_expires_at', true ),
			'created_at'      => $post->post_date,
			'proposal_count'  => $this->get_proposal_count( $post->ID ),
		];

		// Get category.
		$categories = wp_get_post_terms( $post->ID, ServiceCategoryTaxonomy::TAXONOMY );
		$request->category = ! empty( $categories ) ? $categories[0] : null;

		return $request;
	}

	/**
	 * Get open requests.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of requests.
	 */
	public function get_open( array $args = [] ): array {
		$defaults = [
			'posts_per_page' => 20,
			'paged'          => 1,
			'category_id'    => 0,
			'budget_min'     => 0,
			'budget_max'     => 0,
			'order_by'       => 'date',
			'order'          => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$query_args = [
			'post_type'      => BuyerRequestPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $args['posts_per_page'],
			'paged'          => $args['paged'],
			'orderby'        => $args['order_by'],
			'order'          => $args['order'],
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				[
					'key'     => '_wpss_status',
					'value'   => self::STATUS_OPEN,
					'compare' => '=',
				],
				[
					'relation' => 'OR',
					[
						'key'     => '_wpss_expires_at',
						'value'   => current_time( 'mysql' ),
						'compare' => '>',
						'type'    => 'DATETIME',
					],
					[
						'key'     => '_wpss_expires_at',
						'compare' => 'NOT EXISTS',
					],
				],
			],
		];

		// Filter by category.
		if ( $args['category_id'] ) {
			$query_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => ServiceCategoryTaxonomy::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => [ (int) $args['category_id'] ],
				],
			];
		}

		// Filter by budget.
		if ( $args['budget_min'] > 0 ) {
			$query_args['meta_query'][] = [
				'key'     => '_wpss_budget_min',
				'value'   => $args['budget_min'],
				'compare' => '>=',
				'type'    => 'DECIMAL',
			];
		}

		if ( $args['budget_max'] > 0 ) {
			$query_args['meta_query'][] = [
				'key'     => '_wpss_budget_max',
				'value'   => $args['budget_max'],
				'compare' => '<=',
				'type'    => 'DECIMAL',
			];
		}

		$query = new \WP_Query( $query_args );

		$requests = [];
		foreach ( $query->posts as $post ) {
			$requests[] = $this->format_request( $post );
		}

		return $requests;
	}

	/**
	 * Get requests by buyer.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of requests.
	 */
	public function get_by_buyer( int $user_id, array $args = [] ): array {
		$defaults = [
			'posts_per_page' => 20,
			'paged'          => 1,
			'status'         => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$query_args = [
			'post_type'      => BuyerRequestPostType::POST_TYPE,
			'post_status'    => 'publish',
			'author'         => $user_id,
			'posts_per_page' => $args['posts_per_page'],
			'paged'          => $args['paged'],
		];

		if ( $args['status'] ) {
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_wpss_status',
					'value' => $args['status'],
				],
			];
		}

		$query = new \WP_Query( $query_args );

		$requests = [];
		foreach ( $query->posts as $post ) {
			$requests[] = $this->format_request( $post );
		}

		return $requests;
	}

	/**
	 * Update request status.
	 *
	 * @param int    $request_id Request post ID.
	 * @param string $status New status.
	 * @return bool True on success.
	 */
	public function update_status( int $request_id, string $status ): bool {
		$valid_statuses = [
			self::STATUS_OPEN,
			self::STATUS_IN_REVIEW,
			self::STATUS_HIRED,
			self::STATUS_EXPIRED,
			self::STATUS_CANCELLED,
		];

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		$old_status = get_post_meta( $request_id, '_wpss_status', true );

		update_post_meta( $request_id, '_wpss_status', $status );

		/**
		 * Fires when request status changes.
		 *
		 * @since 1.0.0
		 * @param int    $request_id Request post ID.
		 * @param string $status     New status.
		 * @param string $old_status Old status.
		 */
		do_action( 'wpss_buyer_request_status_changed', $request_id, $status, $old_status );

		return true;
	}

	/**
	 * Mark request as hired.
	 *
	 * @param int $request_id Request post ID.
	 * @param int $vendor_id Hired vendor ID.
	 * @param int $proposal_id Accepted proposal ID.
	 * @return bool True on success.
	 */
	public function mark_hired( int $request_id, int $vendor_id, int $proposal_id ): bool {
		update_post_meta( $request_id, '_wpss_hired_vendor_id', $vendor_id );
		update_post_meta( $request_id, '_wpss_accepted_proposal_id', $proposal_id );

		return $this->update_status( $request_id, self::STATUS_HIRED );
	}

	/**
	 * Get proposal count for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return int Proposal count.
	 */
	public function get_proposal_count( int $request_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->proposals_table} WHERE request_id = %d",
				$request_id
			)
		);
	}

	/**
	 * Expire old requests.
	 *
	 * @return int Number of expired requests.
	 */
	public function expire_old_requests(): int {
		$args = [
			'post_type'      => BuyerRequestPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				[
					'key'   => '_wpss_status',
					'value' => self::STATUS_OPEN,
				],
				[
					'key'     => '_wpss_expires_at',
					'value'   => current_time( 'mysql' ),
					'compare' => '<',
					'type'    => 'DATETIME',
				],
			],
		];

		$request_ids = get_posts( $args );
		$count = 0;

		foreach ( $request_ids as $request_id ) {
			if ( $this->update_status( $request_id, self::STATUS_EXPIRED ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Search requests.
	 *
	 * @param string               $search Search term.
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of requests.
	 */
	public function search( string $search, array $args = [] ): array {
		$defaults = [
			'posts_per_page' => 20,
			'paged'          => 1,
			'status'         => self::STATUS_OPEN,
		];

		$args = wp_parse_args( $args, $defaults );

		$query_args = [
			'post_type'      => BuyerRequestPostType::POST_TYPE,
			'post_status'    => 'publish',
			's'              => $search,
			'posts_per_page' => $args['posts_per_page'],
			'paged'          => $args['paged'],
		];

		if ( $args['status'] ) {
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_wpss_status',
					'value' => $args['status'],
				],
			];
		}

		$query = new \WP_Query( $query_args );

		$requests = [];
		foreach ( $query->posts as $post ) {
			$requests[] = $this->format_request( $post );
		}

		return $requests;
	}

	/**
	 * Convert accepted proposal to order.
	 *
	 * Creates a service order from an accepted buyer request proposal.
	 *
	 * @param int $request_id  Request post ID.
	 * @param int $proposal_id Proposal ID to accept.
	 * @return array Result with success status and order_id if successful.
	 */
	public function convert_to_order( int $request_id, int $proposal_id ): array {
		$request = $this->get( $request_id );

		if ( ! $request ) {
			return [
				'success' => false,
				'message' => __( 'Buyer request not found.', 'wp-sell-services' ),
			];
		}

		// Verify request is open or in review.
		if ( ! in_array( $request->status, [ self::STATUS_OPEN, self::STATUS_IN_REVIEW ], true ) ) {
			return [
				'success' => false,
				'message' => __( 'This request is no longer accepting proposals.', 'wp-sell-services' ),
			];
		}

		// Get proposal.
		$proposal_service = new ProposalService();
		$proposal = $proposal_service->get( $proposal_id );

		if ( ! $proposal ) {
			return [
				'success' => false,
				'message' => __( 'Proposal not found.', 'wp-sell-services' ),
			];
		}

		// Verify proposal belongs to this request.
		if ( $proposal['request_id'] !== $request_id ) {
			return [
				'success' => false,
				'message' => __( 'Proposal does not belong to this request.', 'wp-sell-services' ),
			];
		}

		// Verify proposal is pending.
		if ( ProposalService::STATUS_PENDING !== $proposal['status'] ) {
			return [
				'success' => false,
				'message' => __( 'This proposal has already been processed.', 'wp-sell-services' ),
			];
		}

		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		// Generate order number.
		$order_number = 'WPSS-' . strtoupper( wp_generate_password( 8, false ) );

		// Calculate delivery deadline.
		$delivery_days = $proposal['proposed_days'] ?: $request->delivery_days ?: 7;
		$deadline = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delivery_days} days" ) );

		// Create order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$orders_table,
			[
				'order_number'       => $order_number,
				'customer_id'        => $request->author_id,
				'vendor_id'          => $proposal['vendor_id'],
				'service_id'         => $proposal['service_id'] ?: 0,
				'package_id'         => null,
				'addons'             => wp_json_encode( [] ),
				'platform'           => 'request',
				'platform_order_id'  => $request_id,
				'subtotal'           => $proposal['proposed_price'],
				'addons_total'       => 0,
				'total'              => $proposal['proposed_price'],
				'currency'           => get_option( 'wpss_currency', 'USD' ),
				'status'             => 'pending_payment',
				'delivery_deadline'  => $deadline,
				'original_deadline'  => $deadline,
				'payment_status'     => 'pending',
				'revisions_included' => 0,
				'revisions_used'     => 0,
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		if ( ! $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to create order. Please try again.', 'wp-sell-services' ),
			];
		}

		$order_id = $wpdb->insert_id;

		// Accept the proposal.
		$proposal_service->update_status( $proposal_id, ProposalService::STATUS_ACCEPTED );

		// Reject other proposals for this request.
		$proposal_service->reject_other_proposals( $request_id, $proposal_id );

		// Mark request as hired.
		$this->mark_hired( $request_id, $proposal['vendor_id'], $proposal_id );

		// Store request details in order meta for reference.
		$wpdb->insert(
			$wpdb->prefix . 'wpss_order_requirements',
			[
				'order_id'     => $order_id,
				'field_data'   => wp_json_encode( [
					'request_title'       => $request->title,
					'request_description' => $request->description,
					'proposal_cover'      => $proposal['cover_letter'],
				] ),
				'attachments'  => wp_json_encode( $request->attachments ),
				'submitted_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s' ]
		);

		// Create conversation for the order.
		$conversation_service = new ConversationService();
		$conversation_service->create_for_order( $order_id );

		// Notify vendor.
		$notification_service = new NotificationService();
		$notification_service->send(
			$proposal['vendor_id'],
			'proposal_accepted',
			__( 'Proposal Accepted!', 'wp-sell-services' ),
			sprintf(
				/* translators: %s: request title */
				__( 'Your proposal for "%s" has been accepted. Please wait for payment to start working.', 'wp-sell-services' ),
				$request->title
			),
			[ 'order_id' => $order_id, 'request_id' => $request_id ]
		);

		/**
		 * Fires when a buyer request is converted to an order.
		 *
		 * @since 1.0.0
		 * @param int    $order_id    New order ID.
		 * @param int    $request_id  Request post ID.
		 * @param int    $proposal_id Accepted proposal ID.
		 * @param object $request     Request object.
		 * @param array  $proposal    Proposal data.
		 */
		do_action( 'wpss_request_converted_to_order', $order_id, $request_id, $proposal_id, $request, $proposal );

		return [
			'success'      => true,
			'message'      => __( 'Order created successfully. Proceed to payment.', 'wp-sell-services' ),
			'order_id'     => $order_id,
			'order_number' => $order_number,
		];
	}

	/**
	 * Get available statuses.
	 *
	 * @return array<string, string> Status slugs and labels.
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_OPEN      => __( 'Open', 'wp-sell-services' ),
			self::STATUS_IN_REVIEW => __( 'In Review', 'wp-sell-services' ),
			self::STATUS_HIRED     => __( 'Hired', 'wp-sell-services' ),
			self::STATUS_EXPIRED   => __( 'Expired', 'wp-sell-services' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'wp-sell-services' ),
		];
	}

	/**
	 * Get budget types.
	 *
	 * @return array<string, string> Budget type slugs and labels.
	 */
	public static function get_budget_types(): array {
		return [
			self::BUDGET_FIXED => __( 'Fixed Price', 'wp-sell-services' ),
			self::BUDGET_RANGE => __( 'Price Range', 'wp-sell-services' ),
		];
	}
}
