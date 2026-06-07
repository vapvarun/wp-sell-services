<?php
/**
 * Template: Service Disputes List
 *
 * Displays a list of disputes in the My Account area.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/myaccount/service-disputes.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var array $disputes Array of dispute objects.
 * @var int   $user_id  Current user ID.
 */

defined( 'ABSPATH' ) || exit;

use WPSellServices\Services\DisputeService;

$statuses = DisputeService::get_statuses();

/**
 * Fires before the service disputes content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_service_disputes_before', $user_id );
?>

<div class="wpss-disputes-list-page">
	<?php if ( empty( $disputes ) ) : ?>
		<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
			<?php esc_html_e( 'You have no disputes.', 'wp-sell-services' ); ?>
		</div>
	<?php else : ?>
		<div class="wpss-disputes-header">
			<p class="wpss-disputes-count">
				<?php
				printf(
					/* translators: %d: number of disputes */
					esc_html( _n( '%d dispute', '%d disputes', count( $disputes ), 'wp-sell-services' ) ),
					count( $disputes )
				);
				?>
			</p>
		</div>

		<div class="wpss-disputes-table-wrapper">
			<table class="wpss-disputes-table woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
				<thead>
					<tr>
						<th class="wpss-dispute-id"><?php esc_html_e( 'Dispute', 'wp-sell-services' ); ?></th>
						<th class="wpss-dispute-order"><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
						<th class="wpss-dispute-reason"><?php esc_html_e( 'Reason', 'wp-sell-services' ); ?></th>
						<th class="wpss-dispute-status"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
						<th class="wpss-dispute-date"><?php esc_html_e( 'Opened', 'wp-sell-services' ); ?></th>
						<th class="wpss-dispute-actions"><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $disputes as $dispute ) : ?>
						<?php
						$service      = get_post( $dispute->service_id );
						$status_label = $statuses[ $dispute->status ] ?? $dispute->status;
						$dispute_url  = function_exists( 'wc_get_endpoint_url' )
							? wc_get_endpoint_url( 'service-disputes', $dispute->id, wc_get_page_permalink( 'myaccount' ) )
							: add_query_arg(
								array(
									'section'    => 'disputes',
									'dispute_id' => $dispute->id,
								),
								wpss_get_dashboard_url()
							);
						$is_customer  = (int) $dispute->customer_id === $user_id;
						?>
						<tr class="wpss-dispute-row wpss-dispute-status-<?php echo esc_attr( $dispute->status ); ?>">
							<td class="wpss-dispute-id" data-title="<?php esc_attr_e( 'Dispute', 'wp-sell-services' ); ?>">
								<a href="<?php echo esc_url( $dispute_url ); ?>" class="wpss-dispute-link">
									#<?php echo esc_html( $dispute->id ); ?>
								</a>
							</td>
							<td class="wpss-dispute-order" data-title="<?php esc_attr_e( 'Order', 'wp-sell-services' ); ?>">
								<div class="wpss-order-info">
									<span class="wpss-order-number">#<?php echo esc_html( $dispute->order_id ); ?></span>
									<?php if ( $service ) : ?>
										<span class="wpss-service-name"><?php echo esc_html( wp_trim_words( $service->post_title, 5, '...' ) ); ?></span>
									<?php endif; ?>
								</div>
							</td>
							<td class="wpss-dispute-reason" data-title="<?php esc_attr_e( 'Reason', 'wp-sell-services' ); ?>">
								<?php echo esc_html( wp_trim_words( $dispute->reason, 8, '...' ) ); ?>
							</td>
							<td class="wpss-dispute-status" data-title="<?php esc_attr_e( 'Status', 'wp-sell-services' ); ?>">
								<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $dispute->status ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
							<td class="wpss-dispute-date" data-title="<?php esc_attr_e( 'Opened', 'wp-sell-services' ); ?>">
								<time datetime="<?php echo esc_attr( $dispute->created_at ); ?>">
									<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $dispute->created_at ) ) ); ?>
								</time>
							</td>
							<td class="wpss-dispute-actions" data-title="<?php esc_attr_e( 'Actions', 'wp-sell-services' ); ?>">
								<a href="<?php echo esc_url( $dispute_url ); ?>" class="woocommerce-button button wpss-btn wpss-btn-sm">
									<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the service disputes content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_service_disputes_after', $user_id );
?>
