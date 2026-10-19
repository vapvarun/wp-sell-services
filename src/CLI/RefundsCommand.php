<?php
/**
 * WP-CLI: refunds a payment platform made without saying which gig they were for.
 *
 * @package WPSellServices\CLI
 * @since   1.9.0
 */

namespace WPSellServices\CLI;

use WP_CLI;
use WPSellServices\Services\PendingRefundService;

defined( 'ABSPATH' ) || exit;

/**
 * Lists and assigns refunds that wait for a gig.
 */
class RefundsCommand {

	/**
	 * List refunds waiting to be assigned to a gig.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json or csv. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpss refunds pending
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function pending( array $args, array $assoc_args ): void {
		$rows = array();

		foreach ( PendingRefundService::all( 200 ) as $record ) {
			$rows[] = array(
				'transaction' => (string) $record['transaction_id'],
				'gateway'     => (string) $record['gateway'],
				'remaining'   => (string) $record['remaining'],
				'currency'    => (string) $record['currency'],
				'orders'      => implode( ',', array_map( 'intval', (array) $record['orders'] ) ),
			);
		}

		if ( empty( $rows ) ) {
			WP_CLI::success( 'No refunds are waiting for a gig.' );
			return;
		}

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'transaction', 'gateway', 'remaining', 'currency', 'orders' ) );
	}

	/**
	 * Assign a waiting refund to the gig it was for.
	 *
	 * ## OPTIONS
	 *
	 * <order_id>
	 * : The gig (order) the refund was for. It must be one of the orders on the refunded payment.
	 *
	 * [--amount=<amount>]
	 * : Part of the waiting refund to assign. Default: all of it, up to what the gig can still take.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpss refunds assign 412 --amount=20
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function assign( array $args, array $assoc_args ): void {
		$amount = isset( $assoc_args['amount'] ) ? (float) $assoc_args['amount'] : null;
		$result = ( new PendingRefundService() )->assign( (int) $args[0], $amount );

		if ( ! $result['ok'] ) {
			WP_CLI::error( $result['message'] );
		}

		WP_CLI::success( sprintf( 'Assigned %s to order %d.', (string) $result['amount'], (int) $args[0] ) );
	}
}
