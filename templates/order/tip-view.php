<?php
/**
 * Tip Order View
 *
 * Rendered when the current order in the dashboard context has
 * platform='tip'. Tips are payment records, not service-delivery orders,
 * so they skip requirements/messaging/delivery panels and show a simple
 * receipt card tailored to the viewer (buyer or vendor).
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $current_order The tip order row.
 * @var int                                 $user_id       Current user ID.
 */

defined( 'ABSPATH' ) || exit;

$is_buyer   = (int) $current_order->customer_id === $user_id;
$is_vendor  = (int) $current_order->vendor_id === $user_id;
$is_paid    = 'completed' === $current_order->status;
$currency   = $current_order->currency ?: ( get_option( 'wpss_general', array() )['currency'] ?? 'USD' );
$gross      = (float) $current_order->total;
$net_vendor = (float) ( $current_order->vendor_earnings ?? $gross );
$platform_f = (float) ( $current_order->platform_fee ?? 0 );
$parent_id  = (int) ( $current_order->platform_order_id ?? 0 );
$parent_url = $parent_id ? add_query_arg( 'order_id', $parent_id, remove_query_arg( 'order_id' ) ) : '';
$note       = $current_order->vendor_notes ?? '';

$counterparty_id = $is_buyer ? (int) $current_order->vendor_id : (int) $current_order->customer_id;
$counterparty    = get_userdata( $counterparty_id );

$format = static function ( float $amount ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amount, $currency )
		: number_format_i18n( $amount, 2 ) . ' ' . $currency;
};
?>

<div class="wpss-tip-view">
	<div class="wpss-tip-view__card">
		<div class="wpss-tip-view__icon" aria-hidden="true">
			<i data-lucide="heart" class="wpss-icon wpss-icon--lg"></i>
		</div>

		<h2 class="wpss-tip-view__title">
			<?php
			if ( $is_vendor ) {
				echo esc_html( $is_paid ? __( 'Tip received', 'wp-sell-services' ) : __( "Tip pending — buyer hasn't paid yet", 'wp-sell-services' ) );
			} elseif ( $is_buyer ) {
				echo esc_html( $is_paid ? __( 'Tip sent', 'wp-sell-services' ) : __( "Tip not sent yet — payment didn't complete", 'wp-sell-services' ) );
			} else {
				echo esc_html__( 'Tip', 'wp-sell-services' );
			}
			?>
		</h2>

		<p class="wpss-tip-view__amount">
			<?php
			if ( $is_vendor && $is_paid ) {
				// Vendor sees net credited (after commission split).
				echo esc_html( $format( $net_vendor ) );
			} else {
				// Buyer (or pending state) sees the gross they paid / will pay.
				echo esc_html( $format( $gross ) );
			}
			?>
		</p>

		<dl class="wpss-tip-view__meta">
			<?php if ( $counterparty ) : ?>
				<div>
					<dt><?php echo esc_html( $is_vendor ? __( 'From', 'wp-sell-services' ) : __( 'To', 'wp-sell-services' ) ); ?></dt>
					<dd><?php echo esc_html( $counterparty->display_name ); ?></dd>
				</div>
			<?php endif; ?>

			<div>
				<dt><?php esc_html_e( 'Tip order #', 'wp-sell-services' ); ?></dt>
				<dd><?php echo esc_html( $current_order->order_number ); ?></dd>
			</div>

			<?php if ( $is_vendor && $is_paid && $platform_f > 0 ) : ?>
				<div>
					<dt><?php esc_html_e( 'Gross tip', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $format( $gross ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Platform fee', 'wp-sell-services' ); ?></dt>
					<dd>&minus;<?php echo esc_html( $format( $platform_f ) ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $note ) ) : ?>
				<div class="wpss-tip-view__message">
					<dt><?php esc_html_e( 'Message', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $note ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<?php if ( $parent_url ) : ?>
			<a href="<?php echo esc_url( $parent_url ); ?>" class="wpss-btn wpss-btn--primary wpss-tip-view__back">
				<?php esc_html_e( 'View original order', 'wp-sell-services' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
