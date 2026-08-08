<?php
/**
 * Blocks REST Controller
 *
 * @package WPSellServices\API
 * @since   1.5.1
 */

declare(strict_types=1);

namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Member-to-member blocking.
 *
 * PORTFOLIO STANDARD, and the half nothing in this portfolio had. Reporting
 * asks the owner to act; blocking lets a member protect themselves right now,
 * without waiting for anyone. App Store Guideline 1.2 wants both, and the
 * second is the one that actually ends a bad interaction.
 *
 *   GET    /blocks              who I have blocked
 *   POST   /blocks/{user_id}    block someone
 *   DELETE /blocks/{user_id}    unblock them
 *
 * Storage is user meta — see {@see wpss_get_blocked_users()} for why a table
 * would buy nothing here. Enforcement lives at the points where two members
 * actually reach each other, not in this controller.
 *
 * @since 1.5.1
 */
class BlocksController extends RestController {

	/**
	 * Resource type.
	 *
	 * @var string
	 */
	protected $rest_base = 'blocks';

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
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'user_id' => array(
							'validate_callback' => array( $this, 'validate_id' ),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'user_id' => array(
							'validate_callback' => array( $this, 'validate_id' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Who this member has blocked.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$items = array();

		foreach ( wpss_get_blocked_users() as $blocked_id ) {
			$user = get_userdata( $blocked_id );

			// A member who deleted their account stays in the stored list but is
			// not worth rendering — the block is meaningless once they are gone.
			if ( ! $user ) {
				continue;
			}

			$items[] = array(
				'id'     => $blocked_id,
				'name'   => $user->display_name,
				'avatar' => get_avatar_url( $blocked_id ),
			);
		}

		return new WP_REST_Response( array( 'blocked' => $items ), 200 );
	}

	/**
	 * Block someone.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$user_id = get_current_user_id();
		$target  = (int) $request->get_param( 'user_id' );

		if ( $target === $user_id ) {
			return $this->error(
				'wpss_cannot_block_self',
				__( 'You cannot block yourself.', 'wp-sell-services' ),
				400
			);
		}

		if ( ! get_userdata( $target ) ) {
			return $this->error(
				'wpss_user_not_found',
				__( 'That member no longer exists.', 'wp-sell-services' ),
				404
			);
		}

		// NOT gated on account standing. A suspended member is still entitled to
		// stop someone contacting them — blocking takes nothing from anyone else,
		// and refusing it would leave the person most likely to be on the wrong
		// end of an argument without the one control that protects them.
		$blocked = wpss_get_blocked_users( $user_id );

		if ( ! in_array( $target, $blocked, true ) ) {
			$blocked[] = $target;
			update_user_meta( $user_id, 'wpss_blocked_users', $blocked );

			/**
			 * Fires when one member blocks another.
			 *
			 * @since 1.5.1
			 *
			 * @param int $user_id Member doing the blocking.
			 * @param int $target  Member being blocked.
			 */
			do_action( 'wpss_user_blocked', $user_id, $target );
		}

		// Idempotent: blocking someone already blocked is a success, not an
		// error. The member's intent is satisfied either way.
		return new WP_REST_Response( array( 'blocked' => true ), 200 );
	}

	/**
	 * Unblock someone.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		$user_id = get_current_user_id();
		$target  = (int) $request->get_param( 'user_id' );
		$blocked = wpss_get_blocked_users( $user_id );
		$index   = array_search( $target, $blocked, true );

		if ( false !== $index ) {
			unset( $blocked[ $index ] );
			update_user_meta( $user_id, 'wpss_blocked_users', array_values( $blocked ) );

			/**
			 * Fires when one member unblocks another.
			 *
			 * @since 1.5.1
			 *
			 * @param int $user_id Member doing the unblocking.
			 * @param int $target  Member being unblocked.
			 */
			do_action( 'wpss_user_unblocked', $user_id, $target );
		}

		return new WP_REST_Response( array( 'blocked' => false ), 200 );
	}
}
