<?php
/**
 * Reports REST Controller
 *
 * @package WPSellServices\API
 * @since   1.5.1
 */

declare(strict_types=1);

namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Member-filed reports on a person, a service, a review or a message.
 *
 * PORTFOLIO STANDARD. App Store Guideline 1.2 requires a way to report
 * objectionable content AND a way to block the member behind it wherever people
 * post to each other. Nothing in this portfolio had either, so this controller
 * and its sibling {@see BlocksController} are written to be lifted into the
 * other plugins with only the target types changed.
 *
 * The shape that keeps them portable:
 *
 *   POST   /reports          file one, against any target type
 *   GET    /reports          the owner's queue (admin only)
 *   POST   /reports/{id}/resolve
 *
 * Nothing here mentions services, orders or vendors outside the target-type
 * vocabulary, which is filterable per product.
 *
 * @since 1.5.1
 */
class ReportsController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'reports';

	/**
	 * How a report can be closed.
	 *
	 * Deliberately small. An owner deciding what to do about a member does not
	 * need a taxonomy; they need to record whether they agreed and move on.
	 *
	 * @var string[]
	 */
	private const RESOLUTIONS = array( 'upheld', 'dismissed' );

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
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'target_type' => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array_keys( wpss_get_report_target_types() ),
						),
						'target_id'   => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
						'reason'      => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array_keys( wpss_get_report_reasons() ),
						),
						'details'     => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					// The queue is the owner's, not a member's: a member must not
					// be able to read who reported whom.
					'permission_callback' => 'wpss_rest_require_admin',
					'args'                => array(
						'status'   => array(
							'type'    => 'string',
							'enum'    => array( 'open', 'resolved', 'any' ),
							'default' => 'open',
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/resolve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resolve_item' ),
					'permission_callback' => 'wpss_rest_require_admin',
					'args'                => array(
						'id'         => array(
							'validate_callback' => array( $this, 'validate_id' ),
						),
						'resolution' => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => self::RESOLUTIONS,
						),
					),
				),
			)
		);
	}

	/**
	 * File a report.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		global $wpdb;

		$reporter_id = get_current_user_id();
		$target_type = (string) $request->get_param( 'target_type' );
		$target_id   = (int) $request->get_param( 'target_id' );

		// A suspended or banned member does not get to file reports. Reporting is
		// the one lever an abusive member can still pull to harass someone after
		// losing every other one.
		$status_block = wpss_account_status_block( $reporter_id );

		if ( $status_block ) {
			return $status_block;
		}

		$reported_user_id = $this->resolve_reported_user( $target_type, $target_id );

		if ( is_wp_error( $reported_user_id ) ) {
			return $reported_user_id;
		}

		if ( $reported_user_id === $reporter_id ) {
			return $this->error(
				'wpss_cannot_report_self',
				__( 'You cannot report your own content.', 'wp-sell-services' ),
				400
			);
		}

		$table = $wpdb->prefix . 'wpss_reports';

		// One report per member per target, enforced by the UNIQUE key rather
		// than by checking first — a check-then-insert races with itself and two
		// taps on a slow connection would file two reports.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table}
				( target_type, target_id, reported_user_id, reporter_id, reason, details, status, created_at )
				VALUES ( %s, %d, %d, %d, %s, %s, 'open', %s )",
				$target_type,
				$target_id,
				$reported_user_id,
				$reporter_id,
				(string) $request->get_param( 'reason' ),
				(string) ( $request->get_param( 'details' ) ?? '' ),
				current_time( 'mysql' )
			)
		);

		// $wpdb->query() has THREE outcomes and only two used to be handled.
		// FALSE means the write did not happen — a missing table, a lock, a full
		// disk. Because `0 === false` is false, a failure fell through to the
		// "new report" branch and this endpoint answered 201 "our team will
		// review this" while storing nothing. On an abuse-reporting path that is
		// the worst possible failure: the member is reassured, no record exists,
		// and nobody ever learns. Fail loudly instead.
		if ( false === $inserted ) {
			$error = $wpdb->last_error;

			// Logged rather than returned: $wpdb->last_error can carry schema
			// detail, and this endpoint is reachable by any logged-in member.
			if ( $error && function_exists( 'wpss_log' ) ) {
				wpss_log( 'Report insert failed: ' . $error, 'error' );
			} else {
				error_log( 'WPSS report insert failed: ' . $error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return new WP_Error(
				'wpss_report_not_saved',
				__( 'We could not record that report. Please try again, and contact support if it keeps happening.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		// INSERT IGNORE returning 0 means the unique key caught a duplicate. That
		// is a success from the member's side — they reported it, it is on
		// record — so it answers 200 rather than an error that would invite them
		// to try again.
		$already_reported = 0 === $inserted;

		if ( ! $already_reported ) {
			/**
			 * Fires when a member files a report.
			 *
			 * @since 1.5.1
			 *
			 * @param int    $report_id        Stored report ID.
			 * @param string $target_type      What was reported.
			 * @param int    $target_id        Target ID.
			 * @param int    $reported_user_id Member the report is against.
			 */
			do_action( 'wpss_report_filed', (int) $wpdb->insert_id, $target_type, $target_id, $reported_user_id );
		}

		return new WP_REST_Response(
			array(
				'reported' => true,
				'message'  => $already_reported
					? __( 'You have already reported this. Our team is reviewing it.', 'wp-sell-services' )
					: __( 'Thanks for letting us know. Our team will review this.', 'wp-sell-services' ),
			),
			$already_reported ? 200 : 201
		);
	}

	/**
	 * Which member is a report actually against?
	 *
	 * Resolved once at write time and stored, so the owner can ask "what has
	 * been filed against this person" without joining four tables.
	 *
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target ID.
	 * @return int|WP_Error Member ID, or an error when the target does not exist.
	 */
	private function resolve_reported_user( string $target_type, int $target_id ) {
		global $wpdb;

		switch ( $target_type ) {
			case 'user':
				$user = get_userdata( $target_id );

				return $user ? (int) $user->ID : $this->missing_target();

			case 'service':
				$service = get_post( $target_id );

				if ( ! $service || 'wpss_service' !== $service->post_type ) {
					return $this->missing_target();
				}

				return (int) $service->post_author;

			case 'review':
				// reviewer_id, NOT customer_id. This table carries both, and they
				// are different people whenever a vendor writes the review —
				// customer_id is the order's buyer. Reporting a vendor's review
				// and having it land against the buyer would put the wrong
				// member in front of a moderator.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$author = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT reviewer_id FROM {$wpdb->prefix}wpss_reviews WHERE id = %d",
						$target_id
					)
				);

				return null === $author ? $this->missing_target() : (int) $author;

			case 'message':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$author = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT sender_id FROM {$wpdb->prefix}wpss_messages WHERE id = %d",
						$target_id
					)
				);

				return null === $author ? $this->missing_target() : (int) $author;
		}

		// Unreachable while the enum and this switch agree, but a product that
		// filters in a new target type without extending this method should get
		// a clear refusal rather than a report stored against user 0.
		return $this->error(
			'wpss_report_target_unsupported',
			__( 'That kind of content cannot be reported yet.', 'wp-sell-services' ),
			400
		);
	}

	/**
	 * The "target does not exist" refusal.
	 *
	 * @return WP_Error
	 */
	private function missing_target(): WP_Error {
		return $this->error(
			'wpss_report_target_not_found',
			__( 'That content no longer exists.', 'wp-sell-services' ),
			404
		);
	}

	/**
	 * The owner's queue.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'wpss_reports';
		$status   = (string) $request->get_param( 'status' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		$offset   = ( $page - 1 ) * $per_page;

		// Two explicit branches rather than one query with a variable number of
		// bound parameters. The dynamic version worked, but it left the
		// placeholder count and the argument count agreeing only by inspection —
		// which is exactly the shape that breaks the next time someone adds a
		// filter and updates one of the two.
		//
		// COUNT(*) rather than counting a fetched result set, so the total stays
		// honest and cheap once this table holds thousands of rows.
		if ( 'any' === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status )
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$status,
					$per_page,
					$offset
				)
			);
		}

		$items = array_map( array( $this, 'prepare_report' ), $rows ?: array() );

		return $this->paginated_response( $items, $total, $page, $per_page );
	}

	/**
	 * Close a report.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function resolve_item( $request ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_reports';
		$id    = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			array(
				'status'      => 'resolved',
				'resolution'  => (string) $request->get_param( 'resolution' ),
				'resolved_by' => get_current_user_id(),
				'resolved_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $id,
				'status' => 'open',
			),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d', '%s' )
		);

		// Scoped to status = 'open' so two moderators working the queue at once
		// cannot both "resolve" the same row and have the second silently
		// overwrite the first one's decision.
		if ( ! $updated ) {
			return $this->error(
				'wpss_report_already_resolved',
				__( 'That report has already been dealt with.', 'wp-sell-services' ),
				409
			);
		}

		return new WP_REST_Response( array( 'resolved' => true ), 200 );
	}

	/**
	 * Shape a report row for the queue.
	 *
	 * @param object $row Database row.
	 * @return array<string,mixed>
	 */
	private function prepare_report( $row ): array {
		$reasons  = wpss_get_report_reasons();
		$reported = get_userdata( (int) $row->reported_user_id );
		$reporter = get_userdata( (int) $row->reporter_id );

		return array(
			'id'             => (int) $row->id,
			'target_type'    => $row->target_type,
			'target_id'      => (int) $row->target_id,
			'reason'         => $row->reason,
			// The label travels with the row for the same reason order statuses
			// now do: a client that renders its own copy of this map drifts from
			// whatever the site's filter says.
			'reason_label'   => $reasons[ $row->reason ] ?? $row->reason,
			'details'        => (string) $row->details,
			'status'         => $row->status,
			'resolution'     => $row->resolution,
			'reported_user'  => array(
				'id'   => (int) $row->reported_user_id,
				'name' => $reported ? $reported->display_name : '',
			),
			'reporter'       => array(
				'id'   => (int) $row->reporter_id,
				'name' => $reporter ? $reporter->display_name : '',
			),
			'account_status' => wpss_get_account_status( (int) $row->reported_user_id ),
			'created_at'     => $this->format_datetime( $row->created_at ),
			'resolved_at'    => $this->format_datetime( $row->resolved_at ),
		);
	}
}
