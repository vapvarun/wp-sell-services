<?php
/**
 * Account Deletion Service
 *
 * Self-service account deletion, as required by App Store Guideline 5.1.1(v)
 * and Google Play's Data deletion policy: an account a member can create in the
 * app is an account they must be able to delete in the app, without emailing
 * anyone.
 *
 * WHAT DELETION MEANS HERE, because "delete everything" is the wrong answer on
 * a marketplace. An order, a review, a message and a dispute each belong to TWO
 * people. Erasing them because one of those people is leaving would take the
 * other one's history with it: the seller's completed jobs and the earnings
 * behind them, the buyer's proof of what they paid for, and the owner's revenue
 * record for a sale that really happened and was really taxed.
 *
 * So the member goes and their record of themselves goes with them — profile,
 * portfolio, listings, notifications, devices, saved items, every credential —
 * while the rows that document a transaction between two parties stay, showing
 * a deleted member where a name used to be. That split is what GDPR Art. 17(3)
 * contemplates when erasure meets a legal retention duty, and it is what every
 * comparable marketplace does.
 *
 * @package WPSellServices\Services
 * @since   1.5.2
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Models\ServiceOrder;
use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * AccountDeletionService class.
 *
 * @since 1.5.2
 */
class AccountDeletionService {

	/**
	 * Order statuses that do not stand in the way of deletion.
	 *
	 * Everything NOT listed here counts as an obligation. Asked this way round
	 * deliberately: a status added in some later release blocks deletion until
	 * someone decides it should not, so a new order state can strand a member
	 * for a release, but can never let one vanish mid-job.
	 *
	 * The membership of this list is the argument:
	 *
	 *   completed / cancelled / rejected  nothing is owed in either direction.
	 *   refunded / partially_refunded     money already went back.
	 *   pending_payment                   nobody has paid and no work started,
	 *                                     so there is nothing to strand.
	 *
	 * Note what is absent. `delivered` is not settled: the seller has handed
	 * over work the buyer has not yet accepted or paid out on. `disputed` is
	 * the opposite of settled.
	 *
	 * @since 1.5.2
	 * @var string[]
	 */
	private const SETTLED_STATUSES = array(
		ServiceOrder::STATUS_COMPLETED,
		ServiceOrder::STATUS_CANCELLED,
		ServiceOrder::STATUS_REJECTED,
		ServiceOrder::STATUS_REFUNDED,
		ServiceOrder::STATUS_PARTIALLY_REFUNDED,
		ServiceOrder::STATUS_PENDING_PAYMENT,
	);

	/**
	 * How many in-flight orders to name in the refusal.
	 *
	 * The count is always exact — it comes from COUNT(*). This caps only how
	 * many get listed, so a member with 200 open orders gets a readable answer
	 * instead of a payload nothing can render.
	 *
	 * @since 1.5.2
	 * @var int
	 */
	private const MAX_LISTED_ORDERS = 20;

	/**
	 * What stands between this member and deletion.
	 *
	 * Split into things that BLOCK (someone is owed work or money) and things
	 * that are merely worth telling them before they commit (a wallet balance
	 * they are about to forfeit).
	 *
	 * A positive balance deliberately does NOT block. Withdrawals have a
	 * minimum, so a member holding less than it would otherwise be unable to
	 * ever delete their account — which is exactly the trap the App Store rule
	 * exists to prevent. They are told the number instead and decide.
	 *
	 * @since 1.5.2
	 *
	 * @param int $user_id User ID.
	 * @return array{blocked:bool,orders:array<int,array<string,mixed>>,order_count:int,open_withdrawals:int,wallet_balance:float,currency:string}
	 */
	public function get_obligations( int $user_id ): array {
		$order_count = ServiceOrder::count(
			array(
				'user_id'        => $user_id,
				'status__not_in' => self::SETTLED_STATUSES,
			)
		);

		$orders = array();

		if ( $order_count > 0 ) {
			$in_flight = ServiceOrder::query(
				array(
					'user_id'        => $user_id,
					'status__not_in' => self::SETTLED_STATUSES,
					'limit'          => self::MAX_LISTED_ORDERS,
					'orderby'        => 'created_at',
					'order'          => 'DESC',
				)
			);

			foreach ( $in_flight as $order ) {
				$orders[] = array(
					'id'     => $order->get_id(),
					'number' => $order->order_number,
					'status' => $order->get_status(),
					// The one status vocabulary, so the app never maps its own.
					'label'  => $order->get_status_label(),
					'role'   => $order->get_vendor_id() === $user_id ? 'seller' : 'buyer',
				);
			}
		}

		$earnings         = new EarningsService();
		$open_withdrawals = $earnings->count_open_withdrawals( $user_id );

		// Only a seller has a balance to forfeit; asking for a buyer would run
		// the whole earnings summary to be told zero.
		$balance = 0.0;

		if ( wpss_is_vendor( $user_id ) ) {
			$summary = $earnings->get_summary( $user_id );
			$balance = (float) ( $summary['available_balance'] ?? 0 );
		}

		return array(
			'blocked'          => ( $order_count > 0 || $open_withdrawals > 0 ),
			'orders'           => $orders,
			'order_count'      => $order_count,
			'open_withdrawals' => $open_withdrawals,
			'wallet_balance'   => $balance,
			'currency'         => (string) wpss_get_currency(),
		);
	}

	/**
	 * Delete a member's account.
	 *
	 * Order matters. Credentials die first, so a token cannot be used to act
	 * during the seconds the rest takes; listings come down before the user row
	 * goes, so nothing is left purchasable from a seller who no longer exists.
	 *
	 * @since 1.5.2
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error True on success.
	 */
	public function delete( int $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'wpss_user_not_found',
				__( 'That account no longer exists.', 'wp-sell-services' ),
				array( 'status' => 404 )
			);
		}

		/*
		 * Never let the last administrator delete themselves through the app.
		 * A marketplace with no one who can reach wp-admin is unrecoverable,
		 * and this route is reachable by anyone holding a token.
		 */
		if ( user_can( $user_id, 'manage_options' ) ) {
			return new WP_Error(
				'wpss_admin_cannot_self_delete',
				__( 'Administrator accounts cannot be deleted from the app. Please use the WordPress admin.', 'wp-sell-services' ),
				array( 'status' => 403 )
			);
		}

		// Re-checked here rather than trusted from the caller: this is the last
		// point before the row goes, and the service is callable from WP-CLI
		// and from anything else that grows a delete path later.
		$obligations = $this->get_obligations( $user_id );

		if ( $obligations['blocked'] ) {
			return new WP_Error(
				'wpss_account_has_obligations',
				$this->obligation_message( $obligations ),
				array(
					'status'           => 409,
					'orders'           => $obligations['orders'],
					'order_count'      => $obligations['order_count'],
					'open_withdrawals' => $obligations['open_withdrawals'],
				)
			);
		}

		/**
		 * Fires before a member's account is deleted.
		 *
		 * Everything is still readable at this point. This is where an
		 * integration exports, notifies, or settles anything of its own.
		 *
		 * @since 1.5.2
		 *
		 * @param int     $user_id User ID.
		 * @param WP_User $user    The user about to be deleted.
		 */
		do_action( 'wpss_before_account_deletion', $user_id, $user );

		// 1. Kill every credential first.
		wpss_revoke_app_sessions( $user_id );
		wp_destroy_all_sessions();

		// 2. Take their listings out of circulation.
		$this->trash_listings( $user_id );

		// 3. Delete the user, preserving records shared with a counterparty.
		//
		// The filter is added only for the duration of this call. It changes
		// what the `delete_user` cascade in Plugin.php does, and leaving it in
		// place would silently change what an ADMIN deleting a user does too.
		$preserve = static fn(): bool => true;
		add_filter( 'wpss_cascade_preserve_shared_records', $preserve, 10, 0 );

		// Keep core's hands off the listings we just trashed, or it force-deletes
		// them and fires the very cascade the trash was chosen to avoid.
		$previous_flags = $this->set_delete_with_user( false );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$deleted = wp_delete_user( $user_id );

		$this->restore_delete_with_user( $previous_flags );
		remove_filter( 'wpss_cascade_preserve_shared_records', $preserve, 10 );

		if ( ! $deleted ) {
			return new WP_Error(
				'wpss_account_not_deleted',
				__( 'Your account could not be deleted. Please try again, or contact the site owner.', 'wp-sell-services' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a member's account has been deleted.
		 *
		 * The user row is gone by now, so handlers get the id and nothing else.
		 * Pro uses this to clear its own tables — see ProAccountDeletion.
		 *
		 * @since 1.5.2
		 *
		 * @param int $user_id User ID that was deleted.
		 */
		do_action( 'wpss_after_account_deletion', $user_id );

		return true;
	}

	/**
	 * Trash every listing this member owns.
	 *
	 * TRASHED, not deleted, and the distinction carries the whole design.
	 *
	 * Force-deleting a service fires `before_delete_post`, which cascades into
	 * every ORDER placed against that service — so a departing seller would
	 * take their buyers' purchase history with them. Trashing fires nothing.
	 *
	 * It also keeps the order readable. An order stores `service_id` and no
	 * title of its own, so a buyer looking at what they bought last year needs
	 * that post to still resolve. A trashed post is gone from search, gone from
	 * the archive, and cannot be bought — which is all that was actually needed.
	 *
	 * @since 1.5.2
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function trash_listings( int $user_id ): void {
		/*
		 * Batched until empty rather than one big fetch.
		 *
		 * A single capped query leaves a prolific seller's overflow listings
		 * live and purchasable from an account that no longer exists — the
		 * worst possible remainder. No offset is needed because each pass
		 * trashes what it reads, so the next 'publish,draft,pending' page is
		 * always fresh work.
		 */
		do {
			$post_ids = get_posts(
				array(
					'author'         => $user_id,
					'post_type'      => array( 'wpss_service', 'wpss_request' ),
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			$found = count( $post_ids );

			foreach ( $post_ids as $post_id ) {
				wp_trash_post( (int) $post_id );
			}
		} while ( 100 === $found );
	}

	/**
	 * Stop WordPress force-deleting this member's listings on its way out.
	 *
	 * `wp_delete_user()` deletes every post in a type that supports `author`
	 * unless the type says otherwise, and both marketplace types do support it.
	 * That force-delete would fire the service cascade this class just went to
	 * the trouble of avoiding.
	 *
	 * There is no filter on the list core builds, so the supported seam is the
	 * registration flag itself, read off the post type object at delete time.
	 * Flipped for the duration of the call and put back afterwards.
	 *
	 * @since 1.5.2
	 *
	 * @param bool $delete_with_user Value to set.
	 * @return array<string,bool|null> Previous values, keyed by post type.
	 */
	private function set_delete_with_user( bool $delete_with_user ): array {
		$previous = array();

		foreach ( array( 'wpss_service', 'wpss_request' ) as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( null === $object ) {
				continue;
			}

			$previous[ $post_type ]   = $object->delete_with_user;
			$object->delete_with_user = $delete_with_user;
		}

		return $previous;
	}

	/**
	 * Restore the `delete_with_user` flags captured by set_delete_with_user().
	 *
	 * @since 1.5.2
	 *
	 * @param array<string,bool|null> $previous Previous values keyed by post type.
	 * @return void
	 */
	private function restore_delete_with_user( array $previous ): void {
		foreach ( $previous as $post_type => $value ) {
			$object = get_post_type_object( $post_type );

			if ( null !== $object ) {
				$object->delete_with_user = $value;
			}
		}
	}

	/**
	 * Human-readable reason the account cannot be deleted yet.
	 *
	 * @since 1.5.2
	 *
	 * @param array<string,mixed> $obligations Result of get_obligations().
	 * @return string
	 */
	private function obligation_message( array $obligations ): string {
		$order_count = (int) $obligations['order_count'];
		$withdrawals = (int) $obligations['open_withdrawals'];

		if ( $order_count > 0 && $withdrawals > 0 ) {
			return sprintf(
				/* translators: 1: number of orders, 2: number of withdrawals. */
				_n(
					'You have %1$d order still in progress and %2$d withdrawal being processed. Finish or cancel them, and wait for the payout to clear, before deleting your account.',
					'You have %1$d orders still in progress and %2$d withdrawals being processed. Finish or cancel them, and wait for the payouts to clear, before deleting your account.',
					$order_count,
					'wp-sell-services'
				),
				$order_count,
				$withdrawals
			);
		}

		if ( $order_count > 0 ) {
			return sprintf(
				/* translators: %d: number of orders still in progress. */
				_n(
					'You have %d order still in progress. Finish or cancel it before deleting your account.',
					'You have %d orders still in progress. Finish or cancel them before deleting your account.',
					$order_count,
					'wp-sell-services'
				),
				$order_count
			);
		}

		return sprintf(
			/* translators: %d: number of withdrawals being processed. */
			_n(
				'You have %d withdrawal being processed. Please wait for it to clear before deleting your account.',
				'You have %d withdrawals being processed. Please wait for them to clear before deleting your account.',
				$withdrawals,
				'wp-sell-services'
			),
			$withdrawals
		);
	}
}
