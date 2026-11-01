<?php
/**
 * Order line items: the package, every add-on, tax and the total.
 *
 * One partial for the buyer's and vendor's order view and the admin order
 * screen. The order page used to show only a total, so a buyer could not see
 * which add-ons they had paid for (Basecamp 10336467932, item 4). Renders the
 * snapshot stored on the order - nothing is re-priced here.
 *
 * Override: {theme}/wp-sell-services/order/line-items.php
 *
 * @package WPSellServices
 * @since   1.8.0
 * @version 1.9.1
 *
 * @var \WPSellServices\Models\ServiceOrder $wpss_order The order.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_order ) ) {
	return;
}

$wpss_items     = $wpss_order->get_items();
$wpss_currency  = (string) $wpss_order->currency;
$wpss_tax       = (float) ( $wpss_order->meta['tax_amount'] ?? 0 );
$wpss_included  = ! empty( $wpss_order->meta['tax_included'] );
$wpss_tax_label = wpss_get_order_tax( $wpss_order )['label'];
// What the lines add up to, against what was charged. A coupon (Pro stores it as meta.discount) or a custom price
// moves the Total away from the lines, and the page used to say nothing about it.
$wpss_lines_sum = array_sum( array_column( $wpss_items, 'total' ) ) + ( $wpss_included ? 0 : $wpss_tax );
$wpss_gap       = round( (float) $wpss_order->total - $wpss_lines_sum, 2 );
// One query per order page; a tip or extension has none of its own, so it returns nothing there.
$wpss_extras = ( new \WPSellServices\Database\Repositories\OrderRepository() )->get_paid_extras( (int) $wpss_order->id );
?>
<table class="wpss-line-items">
	<caption class="screen-reader-text"><?php esc_html_e( 'What this order includes', 'wp-sell-services' ); ?></caption>
	<tbody>
		<?php foreach ( $wpss_items as $wpss_item ) : ?>
			<tr>
				<th scope="row" class="wpss-line-items__name">
					<?php echo esc_html( $wpss_item['name'] ); ?>
					<?php if ( $wpss_item['quantity'] > 1 ) : ?>
						<span class="wpss-line-items__qty">&times; <?php echo esc_html( (string) $wpss_item['quantity'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $wpss_item['description'] ) : ?>
						<span class="wpss-line-items__detail"><?php echo esc_html( $wpss_item['description'] ); ?></span>
					<?php endif; ?>
				</th>
				<td class="wpss-line-items__amount"><?php echo esc_html( wpss_format_price( (float) $wpss_item['total'], $wpss_currency ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( $wpss_tax > 0 ) : ?>
			<tr class="wpss-line-items__tax">
				<th scope="row" class="wpss-line-items__name">
					<?php
					echo esc_html(
						$wpss_included
							/* translators: %s: tax label, e.g. VAT */
							? sprintf( __( '%s (included)', 'wp-sell-services' ), $wpss_tax_label )
							: $wpss_tax_label
					);
					?>
				</th>
				<td class="wpss-line-items__amount"><?php echo esc_html( wpss_format_price( $wpss_tax, $wpss_currency ) ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( abs( $wpss_gap ) >= 0.01 && (float) $wpss_order->total > 0 ) : ?>
			<tr class="wpss-line-items__tax wpss-line-items__adjust">
				<th scope="row" class="wpss-line-items__name">
					<?php echo $wpss_gap < 0 ? esc_html__( 'Discount', 'wp-sell-services' ) : esc_html__( 'Price adjustment', 'wp-sell-services' ); ?>
				</th>
				<td class="wpss-line-items__amount"><?php echo esc_html( ( $wpss_gap < 0 ? '-' : '' ) . wpss_format_price( abs( $wpss_gap ), $wpss_currency ) ); ?></td>
			</tr>
		<?php endif; ?>
	</tbody>
	<tfoot>
		<tr class="wpss-line-items__total">
			<th scope="row"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
			<td class="wpss-line-items__amount"><?php echo esc_html( wpss_format_price( (float) $wpss_order->total, $wpss_currency ) ); ?></td>
		</tr>
		<?php
		// Rows inside the footer, so they are drawn after the Total, never mistaken for part of it.
		wpss_get_template_part(
			'order/line-items-extras',
			'',
			array(
				'wpss_extras'            => $wpss_extras,
				'wpss_extras_admin_link' => ! empty( $wpss_extras_admin_link ),
			)
		);
		?>
	</tfoot>
</table>
