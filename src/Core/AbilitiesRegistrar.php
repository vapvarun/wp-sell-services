<?php
/**
 * Abilities API Registrar
 *
 * Registers WP Sell Services abilities with the WordPress Abilities API (WP 6.9+).
 * This makes the marketplace AI-assistant ready by exposing structured capabilities
 * that AI agents can discover and invoke.
 *
 * @package WPSellServices\Core
 * @since   1.4.0
 */

declare(strict_types=1);

namespace WPSellServices\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers marketplace abilities with the WordPress Abilities API.
 *
 * @since 1.4.0
 */
class AbilitiesRegistrar {

	/**
	 * The REST API namespace.
	 *
	 * @var string
	 */
	private const API_NAMESPACE = 'wpss/v1';

	/**
	 * Initialize the registrar.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the marketplace ability category.
	 *
	 * @return void
	 */
	public function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'wpss-marketplace',
			array(
				'label'       => __( 'Service Marketplace', 'wp-sell-services' ),
				'description' => __( 'Manage your service marketplace — services, orders, vendors, and earnings.', 'wp-sell-services' ),
			)
		);
	}

	/**
	 * Register all marketplace abilities.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_browse_services();
		$this->register_view_service();
		$this->register_create_service();
		$this->register_manage_orders();
		$this->register_send_message();
		$this->register_view_earnings();
		$this->register_request_withdrawal();
		$this->register_post_buyer_request();
		$this->register_submit_proposal();
		$this->register_leave_review();
		$this->register_view_notifications();
		$this->register_manage_favorites();
	}

	/**
	 * Register the browse-services ability.
	 *
	 * @return void
	 */
	private function register_browse_services(): void {
		wp_register_ability(
			'wpss/browse-services',
			array(
				'label'               => __( 'Browse Services', 'wp-sell-services' ),
				'description'         => __( 'Search and browse services by category, price, rating, and delivery time.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'    => array(
							'type'        => 'string',
							'description' => __( 'Search term to filter services.', 'wp-sell-services' ),
						),
						'category'  => array(
							'type'        => 'integer',
							'description' => __( 'Category ID to filter by.', 'wp-sell-services' ),
						),
						'min_price' => array(
							'type'        => 'number',
							'description' => __( 'Minimum price filter.', 'wp-sell-services' ),
						),
						'max_price' => array(
							'type'        => 'number',
							'description' => __( 'Maximum price filter.', 'wp-sell-services' ),
						),
						'sort_by'   => array(
							'type'        => 'string',
							'enum'        => array( 'newest', 'price_low', 'price_high', 'rating', 'popular' ),
							'description' => __( 'Sort order for results.', 'wp-sell-services' ),
						),
						'page'      => array(
							'type'        => 'integer',
							'description' => __( 'Page number for pagination.', 'wp-sell-services' ),
							'minimum'     => 1,
						),
						'per_page'  => array(
							'type'        => 'integer',
							'description' => __( 'Number of results per page.', 'wp-sell-services' ),
							'minimum'     => 1,
							'maximum'     => 50,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'Paginated list of services with metadata.', 'wp-sell-services' ),
					'properties'  => array(
						'services'    => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'object',
							),
						),
						'total'       => array(
							'type' => 'integer',
						),
						'total_pages' => array(
							'type' => 'integer',
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_browse_services' ),
				'permission_callback' => static function (): bool {
					return true;
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the view-service ability.
	 *
	 * @return void
	 */
	private function register_view_service(): void {
		wp_register_ability(
			'wpss/view-service',
			array(
				'label'               => __( 'View Service', 'wp-sell-services' ),
				'description'         => __( 'Get detailed service information including packages, reviews, and vendor profile.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'The service ID to retrieve.', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'Full service details with packages, reviews, and vendor info.', 'wp-sell-services' ),
				),
				'execute_callback'    => array( $this, 'execute_view_service' ),
				'permission_callback' => static function (): bool {
					return true;
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the create-service ability.
	 *
	 * @return void
	 */
	private function register_create_service(): void {
		wp_register_ability(
			'wpss/create-service',
			array(
				'label'               => __( 'Create Service', 'wp-sell-services' ),
				'description'         => __( 'Create a new service listing with packages, pricing, and delivery time. Requires vendor role.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'title', 'description', 'category', 'packages' ),
					'properties'           => array(
						'title'       => array(
							'type'        => 'string',
							'description' => __( 'Service title.', 'wp-sell-services' ),
						),
						'description' => array(
							'type'        => 'string',
							'description' => __( 'Full service description.', 'wp-sell-services' ),
						),
						'category'    => array(
							'type'        => 'integer',
							'description' => __( 'Service category ID.', 'wp-sell-services' ),
						),
						'packages'    => array(
							'type'        => 'array',
							'description' => __( 'Service packages with pricing and delivery time.', 'wp-sell-services' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'name'          => array( 'type' => 'string' ),
									'description'   => array( 'type' => 'string' ),
									'price'         => array( 'type' => 'number' ),
									'delivery_days' => array( 'type' => 'integer' ),
									'revisions'     => array( 'type' => 'integer' ),
								),
							),
						),
						'tags'        => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Service tags.', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'The created service object.', 'wp-sell-services' ),
					'properties'  => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_create_service' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'wpss_manage_services' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the manage-orders ability.
	 *
	 * @return void
	 */
	private function register_manage_orders(): void {
		wp_register_ability(
			'wpss/manage-orders',
			array(
				'label'               => __( 'Manage Orders', 'wp-sell-services' ),
				'description'         => __( 'View, accept, deliver, complete, or cancel service orders.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'action'   => array(
							'type'        => 'string',
							'enum'        => array( 'list', 'view', 'accept', 'deliver', 'complete', 'cancel' ),
							'description' => __( 'The action to perform on orders.', 'wp-sell-services' ),
						),
						'order_id' => array(
							'type'        => 'integer',
							'description' => __( 'The order ID (required for single-order actions).', 'wp-sell-services' ),
						),
						'status'   => array(
							'type'        => 'string',
							'description' => __( 'Filter orders by status.', 'wp-sell-services' ),
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'Order data or list of orders.', 'wp-sell-services' ),
				),
				'execute_callback'    => array( $this, 'execute_manage_orders' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the send-message ability.
	 *
	 * @return void
	 */
	private function register_send_message(): void {
		wp_register_ability(
			'wpss/send-message',
			array(
				'label'               => __( 'Send Message', 'wp-sell-services' ),
				'description'         => __( 'Send a message in an order conversation.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'order_id', 'message' ),
					'properties'           => array(
						'order_id' => array(
							'type'        => 'integer',
							'description' => __( 'The order ID to send a message in.', 'wp-sell-services' ),
						),
						'message'  => array(
							'type'        => 'string',
							'description' => __( 'The message content.', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'The sent message object.', 'wp-sell-services' ),
					'properties'  => array(
						'id'      => array( 'type' => 'integer' ),
						'sent_at' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_send_message' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the view-earnings ability.
	 *
	 * @return void
	 */
	private function register_view_earnings(): void {
		wp_register_ability(
			'wpss/view-earnings',
			array(
				'label'               => __( 'View Earnings', 'wp-sell-services' ),
				'description'         => __( 'Check earnings summary, available balance, and withdrawal history. Requires vendor role.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'period' => array(
							'type'        => 'string',
							'enum'        => array( '7d', '30d', '90d', '1y', 'all' ),
							'description' => __( 'Time period for earnings summary.', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'Earnings summary with totals and withdrawal history.', 'wp-sell-services' ),
					'properties'  => array(
						'total_earned'       => array( 'type' => 'number' ),
						'available_balance'  => array( 'type' => 'number' ),
						'pending_clearance'  => array( 'type' => 'number' ),
						'total_withdrawn'    => array( 'type' => 'number' ),
						'recent_withdrawals' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_view_earnings' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'wpss_manage_services' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the request-withdrawal ability.
	 *
	 * @return void
	 */
	private function register_request_withdrawal(): void {
		wp_register_ability(
			'wpss/request-withdrawal',
			array(
				'label'               => __( 'Request Withdrawal', 'wp-sell-services' ),
				'description'         => __( 'Request a withdrawal of available earnings. Requires vendor role.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'amount' ),
					'properties'           => array(
						'amount' => array(
							'type'        => 'number',
							'description' => __( 'Amount to withdraw.', 'wp-sell-services' ),
							'minimum'     => 0.01,
						),
						'method' => array(
							'type'        => 'string',
							'description' => __( 'Preferred withdrawal method.', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'Withdrawal request confirmation.', 'wp-sell-services' ),
					'properties'  => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string' ),
						'amount' => array( 'type' => 'number' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_request_withdrawal' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'wpss_manage_services' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the post-buyer-request ability.
	 *
	 * @return void
	 */
	private function register_post_buyer_request(): void {
		wp_register_ability(
			'wpss/post-buyer-request',
			array(
				'label'               => __( 'Post Buyer Request', 'wp-sell-services' ),
				'description'         => __( 'Post a buyer request for vendors to bid on with proposals.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'title', 'description', 'budget' ),
					'properties'           => array(
						'title'       => array(
							'type'        => 'string',
							'description' => __( 'Request title.', 'wp-sell-services' ),
						),
						'description' => array(
							'type'        => 'string',
							'description' => __( 'Detailed description of what you need.', 'wp-sell-services' ),
						),
						'budget'      => array(
							'type'        => 'number',
							'description' => __( 'Maximum budget.', 'wp-sell-services' ),
						),
						'category'    => array(
							'type'        => 'integer',
							'description' => __( 'Service category ID.', 'wp-sell-services' ),
						),
						'deadline'    => array(
							'type'        => 'string',
							'format'      => 'date',
							'description' => __( 'Desired completion date (YYYY-MM-DD).', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'The created buyer request.', 'wp-sell-services' ),
					'properties'  => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_post_buyer_request' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the submit-proposal ability.
	 *
	 * @return void
	 */
	private function register_submit_proposal(): void {
		wp_register_ability(
			'wpss/submit-proposal',
			array(
				'label'               => __( 'Submit Proposal', 'wp-sell-services' ),
				'description'         => __( 'Submit a proposal for a buyer request. Requires vendor role.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'request_id', 'price', 'delivery_days', 'cover_letter' ),
					'properties'           => array(
						'request_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The buyer request ID to submit a proposal for.', 'wp-sell-services' ),
						),
						'price'         => array(
							'type'        => 'number',
							'description' => __( 'Proposed price.', 'wp-sell-services' ),
						),
						'delivery_days' => array(
							'type'        => 'integer',
							'description' => __( 'Proposed delivery time in days.', 'wp-sell-services' ),
						),
						'cover_letter'  => array(
							'type'        => 'string',
							'description' => __( 'Cover letter explaining your proposal.', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'The submitted proposal.', 'wp-sell-services' ),
					'properties'  => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_submit_proposal' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'wpss_respond_to_requests' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the leave-review ability.
	 *
	 * @return void
	 */
	private function register_leave_review(): void {
		wp_register_ability(
			'wpss/leave-review',
			array(
				'label'               => __( 'Leave Review', 'wp-sell-services' ),
				'description'         => __( 'Leave a review for a completed order.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'order_id', 'rating' ),
					'properties'           => array(
						'order_id' => array(
							'type'        => 'integer',
							'description' => __( 'The completed order ID to review.', 'wp-sell-services' ),
						),
						'rating'   => array(
							'type'        => 'integer',
							'description' => __( 'Rating from 1 to 5.', 'wp-sell-services' ),
							'minimum'     => 1,
							'maximum'     => 5,
						),
						'comment'  => array(
							'type'        => 'string',
							'description' => __( 'Review comment text.', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'The created review.', 'wp-sell-services' ),
					'properties'  => array(
						'id'     => array( 'type' => 'integer' ),
						'rating' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_leave_review' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the view-notifications ability.
	 *
	 * @return void
	 */
	private function register_view_notifications(): void {
		wp_register_ability(
			'wpss/view-notifications',
			array(
				'label'               => __( 'View Notifications', 'wp-sell-services' ),
				'description'         => __( 'Check unread marketplace notifications.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'unread_only' => array(
							'type'        => 'boolean',
							'description' => __( 'Only return unread notifications.', 'wp-sell-services' ),
						),
						'page'        => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page'    => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'List of notifications with unread count.', 'wp-sell-services' ),
					'properties'  => array(
						'notifications' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'unread_count'  => array( 'type' => 'integer' ),
						'total'         => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_view_notifications' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the manage-favorites ability.
	 *
	 * @return void
	 */
	private function register_manage_favorites(): void {
		wp_register_ability(
			'wpss/manage-favorites',
			array(
				'label'               => __( 'Manage Favorites', 'wp-sell-services' ),
				'description'         => __( 'Add or remove services from your favorites list.', 'wp-sell-services' ),
				'category'            => 'wpss-marketplace',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action', 'service_id' ),
					'properties'           => array(
						'action'     => array(
							'type'        => 'string',
							'enum'        => array( 'add', 'remove', 'list' ),
							'description' => __( 'Whether to add, remove, or list favorites.', 'wp-sell-services' ),
						),
						'service_id' => array(
							'type'        => 'integer',
							'description' => __( 'The service ID to add or remove.', 'wp-sell-services' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'Favorite action result or favorites list.', 'wp-sell-services' ),
				),
				'execute_callback'    => array( $this, 'execute_manage_favorites' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Execute callbacks — delegate to REST API internal dispatch.
	// -------------------------------------------------------------------------

	/**
	 * Execute browse-services ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_browse_services( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		$request = new \WP_REST_Request( 'GET', '/' . self::API_NAMESPACE . '/services' );
		$request->set_query_params(
			array(
				'search'    => $input['search'] ?? '',
				'category'  => $input['category'] ?? '',
				'min_price' => $input['min_price'] ?? '',
				'max_price' => $input['max_price'] ?? '',
				'sort_by'   => $input['sort_by'] ?? 'newest',
				'page'      => $input['page'] ?? 1,
				'per_page'  => $input['per_page'] ?? 10,
			)
		);

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute view-service ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_view_service( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['id'] ) ) {
			return new \WP_Error( 'missing_id', __( 'Service ID is required.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$request  = new \WP_REST_Request( 'GET', '/' . self::API_NAMESPACE . '/services/' . (int) $input['id'] );
		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute create-service ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_create_service( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		$request = new \WP_REST_Request( 'POST', '/' . self::API_NAMESPACE . '/services' );
		$request->set_body_params( $input );

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute manage-orders ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_manage_orders( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$action = $input['action'] ?? 'list';

		if ( 'list' === $action ) {
			$request = new \WP_REST_Request( 'GET', '/' . self::API_NAMESPACE . '/orders' );
			$request->set_query_params(
				array(
					'status'   => $input['status'] ?? '',
					'page'     => $input['page'] ?? 1,
					'per_page' => $input['per_page'] ?? 10,
				)
			);
		} elseif ( 'view' === $action && ! empty( $input['order_id'] ) ) {
			$request = new \WP_REST_Request( 'GET', '/' . self::API_NAMESPACE . '/orders/' . (int) $input['order_id'] );
		} else {
			if ( empty( $input['order_id'] ) ) {
				return new \WP_Error( 'missing_order_id', __( 'Order ID is required for this action.', 'wp-sell-services' ), array( 'status' => 400 ) );
			}

			$request = new \WP_REST_Request( 'POST', '/' . self::API_NAMESPACE . '/orders/' . (int) $input['order_id'] . '/' . sanitize_key( $action ) );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute send-message ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_send_message( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['order_id'] ) || empty( $input['message'] ) ) {
			return new \WP_Error( 'missing_params', __( 'Order ID and message are required.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$request = new \WP_REST_Request( 'POST', '/' . self::API_NAMESPACE . '/orders/' . (int) $input['order_id'] . '/messages' );
		$request->set_body_params( array( 'content' => $input['message'] ) );

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute view-earnings ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_view_earnings( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		$request = new \WP_REST_Request( 'GET', '/' . self::API_NAMESPACE . '/earnings/summary' );
		if ( ! empty( $input['period'] ) ) {
			$request->set_query_params( array( 'period' => $input['period'] ) );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute request-withdrawal ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_request_withdrawal( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['amount'] ) ) {
			return new \WP_Error( 'missing_amount', __( 'Withdrawal amount is required.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$request = new \WP_REST_Request( 'POST', '/' . self::API_NAMESPACE . '/earnings/withdrawals' );
		$request->set_body_params(
			array(
				'amount' => $input['amount'],
				'method' => $input['method'] ?? '',
			)
		);

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute post-buyer-request ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_post_buyer_request( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		$request = new \WP_REST_Request( 'POST', '/' . self::API_NAMESPACE . '/buyer-requests' );
		$request->set_body_params( $input );

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute submit-proposal ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_submit_proposal( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['request_id'] ) ) {
			return new \WP_Error( 'missing_request_id', __( 'Buyer request ID is required.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$request = new \WP_REST_Request( 'POST', '/' . self::API_NAMESPACE . '/buyer-requests/' . (int) $input['request_id'] . '/proposals' );
		$request->set_body_params(
			array(
				'price'         => $input['price'] ?? 0,
				'delivery_days' => $input['delivery_days'] ?? 0,
				'cover_letter'  => $input['cover_letter'] ?? '',
			)
		);

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute leave-review ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_leave_review( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		if ( empty( $input['order_id'] ) || empty( $input['rating'] ) ) {
			return new \WP_Error( 'missing_params', __( 'Order ID and rating are required.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$request = new \WP_REST_Request( 'POST', '/' . self::API_NAMESPACE . '/orders/' . (int) $input['order_id'] . '/review' );
		$request->set_body_params(
			array(
				'rating'  => $input['rating'],
				'comment' => $input['comment'] ?? '',
			)
		);

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute view-notifications ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_view_notifications( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		$request = new \WP_REST_Request( 'GET', '/' . self::API_NAMESPACE . '/notifications' );
		$request->set_query_params(
			array(
				'unread_only' => ! empty( $input['unread_only'] ),
				'page'        => $input['page'] ?? 1,
				'per_page'    => $input['per_page'] ?? 10,
			)
		);

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Execute manage-favorites ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_manage_favorites( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$action = $input['action'] ?? 'list';

		if ( 'list' === $action ) {
			$request = new \WP_REST_Request( 'GET', '/' . self::API_NAMESPACE . '/favorites' );
		} elseif ( 'add' === $action ) {
			if ( empty( $input['service_id'] ) ) {
				return new \WP_Error( 'missing_service_id', __( 'Service ID is required.', 'wp-sell-services' ), array( 'status' => 400 ) );
			}
			$request = new \WP_REST_Request( 'POST', '/' . self::API_NAMESPACE . '/favorites/' . (int) $input['service_id'] );
		} elseif ( 'remove' === $action ) {
			if ( empty( $input['service_id'] ) ) {
				return new \WP_Error( 'missing_service_id', __( 'Service ID is required.', 'wp-sell-services' ), array( 'status' => 400 ) );
			}
			$request = new \WP_REST_Request( 'DELETE', '/' . self::API_NAMESPACE . '/favorites/' . (int) $input['service_id'] );
		} else {
			return new \WP_Error( 'invalid_action', __( 'Invalid action. Use add, remove, or list.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}
}
