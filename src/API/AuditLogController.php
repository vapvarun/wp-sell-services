<?php
/**
 * Audit Log REST Controller
 *
 * Admin-only read endpoint exposing the forensic audit trail produced by
 * {@see \WPSellServices\Services\AuditLogService}. Powers external SIEM
 * integrations, mobile admin apps, and the forthcoming in-WP admin audit
 * viewer.
 *
 * @package WPSellServices\API
 * @since   1.1.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPSellServices\Services\AuditLogService;

/**
 * REST controller for the audit log.
 *
 * Route: `GET /wpss/v1/audit-log`
 *
 * @since 1.1.0
 */
class AuditLogController extends RestController {

	/**
	 * REST base path (appended to the namespace).
	 *
	 * @var string
	 */
	protected $rest_base = 'audit-log';

	/**
	 * Audit log service.
	 *
	 * @var AuditLogService
	 */
	private AuditLogService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new AuditLogService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Describe the query parameters the collection endpoint accepts.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return array(
			'object_type' => array(
				'description' => __( 'Filter by object type (e.g. order, proposal).', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'object_id'   => array(
				'description' => __( 'Filter by object ID. Requires object_type.', 'wp-sell-services' ),
				'type'        => 'integer',
			),
			'actor_id'    => array(
				'description' => __( 'Filter by actor (user) ID.', 'wp-sell-services' ),
				'type'        => 'integer',
			),
			'event_type'  => array(
				'description' => __( 'Filter by event type (e.g. order.status_change).', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'is_forced'   => array(
				'description' => __( 'Return only events that bypassed natural rules.', 'wp-sell-services' ),
				'type'        => 'boolean',
				'default'     => false,
			),
			'from_date'   => array(
				'description' => __( 'Lower bound (inclusive) in ISO 8601 format.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'to_date'     => array(
				'description' => __( 'Upper bound (inclusive) in ISO 8601 format.', 'wp-sell-services' ),
				'type'        => 'string',
			),
			'page'        => array(
				'description' => __( 'Page number, starting at 1.', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'    => array(
				'description' => __( 'Rows per page (1-100).', 'wp-sell-services' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			),
		);
	}

	/**
	 * GET /audit-log handler.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$result = $this->service->query(
			array(
				'object_type' => $request->get_param( 'object_type' ),
				'object_id'   => $request->get_param( 'object_id' ),
				'actor_id'    => $request->get_param( 'actor_id' ),
				'event_type'  => $request->get_param( 'event_type' ),
				'is_forced'   => (bool) $request->get_param( 'is_forced' ),
				'from_date'   => $request->get_param( 'from_date' ),
				'to_date'     => $request->get_param( 'to_date' ),
				'page'        => (int) $request->get_param( 'page' ),
				'per_page'    => (int) $request->get_param( 'per_page' ),
			)
		);

		$rows = array_map( array( $this, 'prepare_row_for_response' ), $result['rows'] );

		$response = new WP_REST_Response( $rows );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / $result['per_page'] ) );

		return $response;
	}

	/**
	 * Shape a DB row into the public API payload.
	 *
	 * Decodes the `context` JSON blob so clients don't have to double-parse.
	 *
	 * @param object $row Raw DB row.
	 * @return array<string, mixed>
	 */
	private function prepare_row_for_response( object $row ): array {
		return array(
			'id'          => (int) $row->id,
			'actor_id'    => (int) $row->actor_id,
			'actor_role'  => $row->actor_role,
			'event_type'  => $row->event_type,
			'object_type' => $row->object_type,
			'object_id'   => (int) $row->object_id,
			'action'      => $row->action,
			'from_value'  => $row->from_value,
			'to_value'    => $row->to_value,
			'is_forced'   => (bool) $row->is_forced,
			'context'     => $row->context ? json_decode( $row->context, true ) : null,
			'created_at'  => $row->created_at,
		);
	}
}
