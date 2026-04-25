<?php
/**
 * Extension Order View
 *
 * Rendered when the current order in the dashboard context has
 * platform='extension'. Extensions are payment records on top of an
 * existing service order, not standalone service deliveries, so they
 * skip the requirements/messaging/delivery panels and show a receipt
 * card tailored to the viewer (buyer or vendor) with the extra days
 * + amount spelled out.
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $current_order Extension sub-order row.
 * @var int                                 $user_id       Current user ID.
 */

defined( 'ABSPATH' ) || exit;

$is_buyer   = (int) $current_order->customer_id === $user_id;
$is_vendor  = (int) $current_order->vendor_id === $user_id;
$is_paid    = 'completed' === $current_order->status;
$is_pending = 'pending_payment' === $current_order->status;
$currency   = $current_order->currency ?: ( get_option( 'wpss_general', array() )['currency'] ?? 'USD' );
$gross      = (float) $current_order->total;
$net_vendor = (float) ( $current_order->vendor_earnings ?? $gross );
$platform_f = (float) ( $current_order->platform_fee ?? 0 );
$parent_id  = (int) ( $current_order->platform_order_id ?? 0 );
$parent_url = $parent_id ? add_query_arg( 'order_id', $parent_id, remove_query_arg( 'order_id' ) ) : '';
$base_url   = function_exists( 'wpss_get_checkout_base_url' ) ? wpss_get_checkout_base_url() : home_url( '/checkout/' );
$pay_url    = add_query_arg( 'pay_order', (int) $current_order->id, $base_url );

// Extension metadata lives on the sub-order's `meta` JSON plus the linked
// wpss_extension_requests row. Prefer the JSON for extra_days and reason
// since it is written atomically with the sub-order.
$meta       = $current_order->meta ?? '';
$meta       = is_string( $meta ) ? json_decode( $meta, true ) : ( is_array( $meta ) ? $meta : array() );
$extra_days = (int) ( $meta['extra_days'] ?? 0 );
$reason     = (string) ( $meta['reason'] ?? ( $current_order->vendor_notes ?? '' ) );

$counterparty_id = $is_buyer ? (int) $current_order->vendor_id : (int) $current_order->customer_id;
$counterparty    = get_userdata( $counterparty_id );

$format = static function ( float $amount ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amount, $currency )
		: number_format_i18n( $amount, 2 ) . ' ' . $currency;
};
?>

<div class="wpss-tip-view wpss-extension-view">
	<?php
	// CB6 (plans/ORDER-FLOW-AUDIT.md): top breadcrumb to parent service order.
	if ( $parent_id ) :
		$parent_order = \WPSellServices\Models\ServiceOrder::find( $parent_id );
		if ( $parent_order ) :
			?>
			<div class="wpss-suborder-crumb">
				<i data-lucide="corner-down-right" class="wpss-icon" aria-hidden="true"></i>
				<?php
				printf(
					/* translators: %s: parent order number link */
					esc_html__( 'Extension on order %s', 'wp-sell-services' ),
					'<a href="' . esc_url( $parent_url ) . '">#' . esc_html( $parent_order->order_number ) . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URL + label escaped above.
				);
				?>
			</div>
			<?php
		endif;
	endif;
	?>
	<div class="wpss-tip-view__card">
		<div class="wpss-tip-view__icon wpss-extension-view__icon" aria-hidden="true">
			<i data-lucide="clock-alert" class="wpss-icon wpss-icon--lg"></i>
		</div>

		<h2 class="wpss-tip-view__title">
			<?php
			if ( $is_vendor ) {
				if ( $is_paid ) {
					esc_html_e( 'Extra work paid', 'wp-sell-services' );
				} elseif ( $is_pending ) {
					esc_html_e( 'Quote sent — awaiting buyer payment', 'wp-sell-services' );
				} else {
					esc_html_e( 'Quote declined', 'wp-sell-services' );
				}
			} elseif ( $is_buyer ) {
				if ( $is_paid ) {
					esc_html_e( 'Extra work paid', 'wp-sell-services' );
				} elseif ( $is_pending ) {
					esc_html_e( 'Quote from seller', 'wp-sell-services' );
				} else {
					esc_html_e( 'Quote declined', 'wp-sell-services' );
				}
			} else {
				esc_html_e( 'Extension', 'wp-sell-services' );
			}
			?>
		</h2>

		<p class="wpss-tip-view__amount">
			<?php
			if ( $is_vendor && $is_paid ) {
				echo esc_html( $format( $net_vendor ) );
			} else {
				echo esc_html( $format( $gross ) );
			}
			?>
		</p>

		<?php if ( $extra_days > 0 ) : ?>
			<p class="wpss-extension-view__days">
				<?php
				printf(
					/* translators: %d: extra days */
					esc_html( _n( '+%d day added to delivery', '+%d days added to delivery', $extra_days, 'wp-sell-services' ) ),
					absint( $extra_days )
				);
				?>
			</p>
		<?php endif; ?>

		<dl class="wpss-tip-view__meta">
			<?php if ( $counterparty ) : ?>
				<div>
					<dt><?php echo esc_html( $is_vendor ? __( 'From', 'wp-sell-services' ) : __( 'Seller', 'wp-sell-services' ) ); ?></dt>
					<dd><?php echo esc_html( $counterparty->display_name ); ?></dd>
				</div>
			<?php endif; ?>

			<div>
				<dt><?php esc_html_e( 'Extension order #', 'wp-sell-services' ); ?></dt>
				<dd><?php echo esc_html( $current_order->order_number ); ?></dd>
			</div>

			<?php if ( $is_vendor && $is_paid && $platform_f > 0 ) : ?>
				<div>
					<dt><?php esc_html_e( 'Buyer paid', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $format( $gross ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Platform fee', 'wp-sell-services' ); ?></dt>
					<dd>&minus;<?php echo esc_html( $format( $platform_f ) ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $reason ) ) : ?>
				<div class="wpss-tip-view__message">
					<dt><?php esc_html_e( 'Reason', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $reason ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<div class="wpss-tip-view__actions">
			<?php if ( $is_buyer && $is_pending ) : ?>
				<a href="<?php echo esc_url( $pay_url ); ?>" class="wpss-btn wpss-btn--primary">
					<?php
					printf(
						/* translators: %s: extension amount */
						esc_html__( 'Accept & Pay %s', 'wp-sell-services' ),
						esc_html( $format( $gross ) )
					);
					?>
				</a>
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-extension-decline-btn"
					data-order="<?php echo esc_attr( (int) $current_order->id ); ?>"
					data-parent="<?php echo esc_attr( $parent_id ); ?>">
					<?php esc_html_e( 'Decline', 'wp-sell-services' ); ?>
				</button>
			<?php endif; ?>

			<?php if ( $parent_url ) : ?>
				<a href="<?php echo esc_url( $parent_url ); ?>" class="wpss-btn wpss-btn--outline">
					<?php esc_html_e( 'View original order', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>
