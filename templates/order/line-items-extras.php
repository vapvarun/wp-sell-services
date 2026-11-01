<?php
/**
 * Tips and extensions paid on an order, listed beneath its line items.
 *
 * They are orders of their own, so the parent's total never held them. Listed, never summed.
 * Table rows only: the caller puts them inside a <tfoot>, so they are drawn after the Total and never read as part of it.
 * Shared by line-items.php (buyer, vendor, admin) and the admin view of a milestone contract.
 *
 * Override: {theme}/wp-sell-services/order/line-items-extras.php
 *
 * @package WPSellServices
 * @since   1.9.2
 *
 * @var object[] $wpss_extras            Rows from OrderRepository::get_paid_extras().
 * @var bool     $wpss_extras_admin_link Link each line to its admin order screen.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_extras ) ) {
	return;
}
?>
		<tr class="wpss-line-items__extras">
			<th scope="colgroup" colspan="2" class="wpss-line-items__extras-title"><?php esc_html_e( 'Also paid on this order', 'wp-sell-services' ); ?></th>
		</tr>
		<?php foreach ( $wpss_extras as $wpss_extra ) : ?>
			<?php
			$wpss_is_tip   = \WPSellServices\Models\ServiceOrder::SUB_ORDER_TYPE_TIP === $wpss_extra->platform;
			$wpss_label    = $wpss_is_tip ? __( 'Tip', 'wp-sell-services' ) : __( 'Extension', 'wp-sell-services' );
			$wpss_refunded = in_array( $wpss_extra->status, array( 'refunded', 'partially_refunded' ), true );
			?>
			<tr>
				<th scope="row" class="wpss-line-items__name">
					<?php if ( ! empty( $wpss_extras_admin_link ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . (int) $wpss_extra->id ) ); ?>"><?php echo esc_html( $wpss_label ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $wpss_label ); ?>
					<?php endif; ?>
					<span class="wpss-line-items__detail">
						<?php
						echo esc_html( '#' . $wpss_extra->order_number );
						echo $wpss_refunded ? esc_html( ' (' . wpss_get_order_status_label( (string) $wpss_extra->status ) . ')' ) : '';
						?>
					</span>
				</th>
				<td class="wpss-line-items__amount"><?php echo esc_html( wpss_format_price( (float) $wpss_extra->total, (string) $wpss_extra->currency ) ); ?></td>
			</tr>
		<?php endforeach; ?>
