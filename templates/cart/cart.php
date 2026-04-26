<?php
/**
 * Template: Cart Page
 *
 * Displays the standalone cart with all items, totals, and checkout CTA.
 *
 * @package WPSellServices\Templates
 * @since   1.6.0
 *
 * @var array $cart_items Cart items from _wpss_cart user meta.
 */

defined( 'ABSPATH' ) || exit;
?>
<style>
.wpss-cart-page {
	max-width: 1100px;
	margin: 0 auto;
	padding: var(--wpss-space-6, 24px) var(--wpss-space-4, 16px);
	font-family: var(--wpss-font-sans, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif);
}

.wpss-cart-page__heading {
	font-size: 1.75rem;
	font-weight: 700;
	color: var(--wpss-color-text, #0f172a);
	margin: 0 0 var(--wpss-space-6, 24px);
}

.wpss-cart-page__layout {
	display: grid;
	grid-template-columns: 1fr 320px;
	gap: var(--wpss-space-6, 24px);
	align-items: start;
}

@media (max-width: 768px) {
	.wpss-cart-page__layout {
		grid-template-columns: 1fr;
	}
}

/* --- Items Column --- */
.wpss-cart-items {
	display: flex;
	flex-direction: column;
	gap: var(--wpss-space-4, 16px);
}

.wpss-cart-item {
	background: var(--wpss-color-surface, #ffffff);
	border: 1px solid var(--wpss-color-border, #e2e8f0);
	border-radius: var(--wpss-radius-lg, 12px);
	padding: var(--wpss-space-4, 16px);
	display: grid;
	grid-template-columns: 88px 1fr auto;
	gap: var(--wpss-space-4, 16px);
	align-items: start;
	transition: box-shadow 0.15s ease;
}

.wpss-cart-item:hover {
	box-shadow: var(--wpss-shadow-md, 0 4px 16px rgba(0,0,0,0.08));
}

@media (max-width: 540px) {
	.wpss-cart-item {
		grid-template-columns: 72px 1fr;
	}
	.wpss-cart-item__remove {
		grid-column: 1 / -1;
		justify-self: end;
	}
}

.wpss-cart-item__image {
	width: 88px;
	height: 66px;
	border-radius: var(--wpss-radius-md, 8px);
	overflow: hidden;
	background: var(--wpss-color-muted, #f1f5f9);
	flex-shrink: 0;
}

.wpss-cart-item__image img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}

.wpss-cart-item__placeholder {
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--wpss-color-text-muted, #94a3b8);
}

.wpss-cart-item__body {
	min-width: 0;
}

.wpss-cart-item__title {
	font-size: 1rem;
	font-weight: 600;
	color: var(--wpss-color-text, #0f172a);
	margin: 0 0 4px;
	line-height: 1.4;
}

.wpss-cart-item__title a {
	color: inherit;
	text-decoration: none;
}

.wpss-cart-item__title a:hover {
	color: var(--wpss-color-primary, #7c3aed);
}

.wpss-cart-item__vendor {
	font-size: 0.8125rem;
	color: var(--wpss-color-text-muted, #64748b);
	margin: 0 0 var(--wpss-space-2, 8px);
}

.wpss-cart-item__meta {
	display: flex;
	flex-wrap: wrap;
	gap: var(--wpss-space-2, 8px);
	align-items: center;
	margin-bottom: var(--wpss-space-2, 8px);
}

.wpss-cart-item__package {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	background: var(--wpss-color-primary-soft, #ede9fe);
	color: var(--wpss-color-primary, #7c3aed);
	font-size: 0.75rem;
	font-weight: 600;
	padding: 2px 10px;
	border-radius: 999px;
}

.wpss-cart-item__price {
	font-size: 1rem;
	font-weight: 700;
	color: var(--wpss-color-text, #0f172a);
}

.wpss-cart-item__addons {
	margin-top: var(--wpss-space-2, 8px);
	padding-top: var(--wpss-space-2, 8px);
	border-top: 1px solid var(--wpss-color-border, #e2e8f0);
}

.wpss-cart-item__addons-label {
	font-size: 0.75rem;
	font-weight: 600;
	color: var(--wpss-color-text-muted, #64748b);
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin-bottom: 4px;
}

.wpss-cart-item__addon {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 0.8125rem;
	color: var(--wpss-color-text, #0f172a);
	padding: 2px 0;
}

.wpss-cart-item__addon-price {
	font-weight: 600;
	color: var(--wpss-color-text-muted, #64748b);
}

.wpss-cart-item__remove {
	flex-shrink: 0;
}

.wpss-cart-item__remove-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border: none;
	background: transparent;
	color: var(--wpss-color-text-muted, #94a3b8);
	border-radius: var(--wpss-radius-md, 8px);
	cursor: pointer;
	transition: background 0.15s, color 0.15s;
	padding: 0;
}

.wpss-cart-item__remove-btn:hover {
	background: var(--wpss-color-danger-soft, #fee2e2);
	color: var(--wpss-color-danger, #dc2626);
}

/* --- Totals Sidebar --- */
.wpss-cart-summary {
	background: var(--wpss-color-surface, #ffffff);
	border: 1px solid var(--wpss-color-border, #e2e8f0);
	border-radius: var(--wpss-radius-lg, 12px);
	padding: var(--wpss-space-5, 20px);
	position: sticky;
	top: 100px;
}

.wpss-cart-summary__heading {
	font-size: 1rem;
	font-weight: 700;
	color: var(--wpss-color-text, #0f172a);
	margin: 0 0 var(--wpss-space-4, 16px);
	padding-bottom: var(--wpss-space-4, 16px);
	border-bottom: 1px solid var(--wpss-color-border, #e2e8f0);
}

.wpss-cart-summary__line {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 0.9375rem;
	color: var(--wpss-color-text-muted, #64748b);
	margin-bottom: var(--wpss-space-2, 8px);
}

.wpss-cart-summary__total {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 1.125rem;
	font-weight: 700;
	color: var(--wpss-color-text, #0f172a);
	padding-top: var(--wpss-space-4, 16px);
	margin-top: var(--wpss-space-2, 8px);
	border-top: 2px solid var(--wpss-color-border, #e2e8f0);
}

.wpss-cart-summary__cta {
	margin-top: var(--wpss-space-4, 16px);
}

.wpss-cart-summary__cta .wpss-btn {
	width: 100%;
	justify-content: center;
}

/* --- Empty State --- */
.wpss-cart-empty {
	text-align: center;
	padding: var(--wpss-space-12, 48px) var(--wpss-space-6, 24px);
	background: var(--wpss-color-surface, #ffffff);
	border: 1px solid var(--wpss-color-border, #e2e8f0);
	border-radius: var(--wpss-radius-lg, 12px);
}

.wpss-cart-empty__icon {
	color: var(--wpss-color-text-muted, #94a3b8);
	margin-bottom: var(--wpss-space-4, 16px);
}

.wpss-cart-empty__title {
	font-size: 1.25rem;
	font-weight: 700;
	color: var(--wpss-color-text, #0f172a);
	margin: 0 0 var(--wpss-space-2, 8px);
}

.wpss-cart-empty__text {
	font-size: 0.9375rem;
	color: var(--wpss-color-text-muted, #64748b);
	margin: 0 0 var(--wpss-space-5, 20px);
}

/* Removing animation */
.wpss-cart-item.is-removing {
	opacity: 0.4;
	pointer-events: none;
	transition: opacity 0.25s;
}
</style>

<div class="wpss-cart-page">
	<h1 class="wpss-cart-page__heading"><?php esc_html_e( 'Your Cart', 'wp-sell-services' ); ?></h1>

	<?php if ( empty( $cart_items ) ) : ?>
		<div class="wpss-cart-empty">
			<div class="wpss-cart-empty__icon">
				<i data-lucide="shopping-cart" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			</div>
			<h2 class="wpss-cart-empty__title"><?php esc_html_e( 'Your cart is empty', 'wp-sell-services' ); ?></h2>
			<p class="wpss-cart-empty__text"><?php esc_html_e( 'Browse our services and find the perfect match for your needs.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( wpss_get_page_url( 'services_page' ) ?: home_url( '/' ) ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Browse Services', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-cart-page__layout">

			<!-- Cart Items -->
			<div class="wpss-cart-items">
				<?php
				$subtotal = 0.0;

				foreach ( $cart_items as $item_key => $item ) :
					$service_id = absint( $item['service_id'] ?? 0 );
					$package    = is_array( $item['package'] ?? null ) ? $item['package'] : array();
					$addons     = is_array( $item['addons'] ?? null ) ? $item['addons'] : array();
					$item_total = (float) ( $item['total'] ?? 0 );
					$subtotal  += $item_total;

					$service_title = $service_id ? get_the_title( $service_id ) : __( 'Service', 'wp-sell-services' );
					$service_url   = $service_id ? get_permalink( $service_id ) : '';

					// Vendor name.
					$vendor_name = '';
					if ( $service_id ) {
						$vendor_id   = (int) get_post_field( 'post_author', $service_id );
						$vendor_user = $vendor_id ? get_userdata( $vendor_id ) : false;
						$vendor_name = $vendor_user ? $vendor_user->display_name : '';
					}

					// Thumbnail.
					$thumb_id  = $service_id ? get_post_thumbnail_id( $service_id ) : 0;
					$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';
					$thumb_alt = $thumb_id ? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
					?>
					<div class="wpss-cart-item" data-item-key="<?php echo esc_attr( $item_key ); ?>">

						<!-- Thumbnail -->
						<div class="wpss-cart-item__image">
							<?php if ( $thumb_url ) : ?>
								<img
									src="<?php echo esc_url( $thumb_url ); ?>"
									alt="<?php echo esc_attr( $thumb_alt ?: $service_title ); ?>"
									loading="lazy"
								>
							<?php else : ?>
								<div class="wpss-cart-item__placeholder">
									<i data-lucide="image" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
								</div>
							<?php endif; ?>
						</div>

						<!-- Body -->
						<div class="wpss-cart-item__body">
							<h3 class="wpss-cart-item__title">
								<?php if ( $service_url ) : ?>
									<a href="<?php echo esc_url( $service_url ); ?>">
										<?php echo esc_html( $service_title ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $service_title ); ?>
								<?php endif; ?>
							</h3>

							<?php if ( $vendor_name ) : ?>
								<p class="wpss-cart-item__vendor">
									<?php
									printf(
										/* translators: %s: vendor display name */
										esc_html__( 'by %s', 'wp-sell-services' ),
										esc_html( $vendor_name )
									);
									?>
								</p>
							<?php endif; ?>

							<div class="wpss-cart-item__meta">
								<?php if ( ! empty( $package['name'] ) ) : ?>
									<span class="wpss-cart-item__package">
										<?php echo esc_html( $package['name'] ); ?>
									</span>
								<?php endif; ?>
								<span class="wpss-cart-item__price">
									<?php echo esc_html( wpss_format_price( $item_total ) ); ?>
								</span>
							</div>

							<?php if ( ! empty( $addons ) ) : ?>
								<div class="wpss-cart-item__addons">
									<div class="wpss-cart-item__addons-label">
										<?php esc_html_e( 'Add-ons', 'wp-sell-services' ); ?>
									</div>
									<?php foreach ( $addons as $addon ) : ?>
										<?php
										$addon_title = sanitize_text_field( $addon['title'] ?? '' );
										$addon_price = (float) ( $addon['price'] ?? 0 );
										?>
										<div class="wpss-cart-item__addon">
											<span><?php echo esc_html( $addon_title ); ?></span>
											<span class="wpss-cart-item__addon-price">
												+<?php echo esc_html( wpss_format_price( $addon_price ) ); ?>
											</span>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

						<!-- Remove -->
						<div class="wpss-cart-item__remove">
							<button
								type="button"
								class="wpss-cart-item__remove-btn wpss-remove-cart-item"
								data-item-key="<?php echo esc_attr( $item_key ); ?>"
								aria-label="<?php esc_attr_e( 'Remove item', 'wp-sell-services' ); ?>"
							>
								<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Totals Sidebar -->
			<div class="wpss-cart-summary">
				<h2 class="wpss-cart-summary__heading"><?php esc_html_e( 'Order Summary', 'wp-sell-services' ); ?></h2>

				<div class="wpss-cart-summary__line">
					<span>
						<?php
						printf(
							/* translators: %d: number of items */
							esc_html( _n( '%d item', '%d items', count( $cart_items ), 'wp-sell-services' ) ),
							count( $cart_items )
						);
						?>
					</span>
					<span><?php echo esc_html( wpss_format_price( $subtotal ) ); ?></span>
				</div>

				<div class="wpss-cart-summary__total">
					<span><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></span>
					<span><?php echo esc_html( wpss_format_price( $subtotal ) ); ?></span>
				</div>

				<div class="wpss-cart-summary__cta">
					<a href="<?php echo esc_url( wpss_get_checkout_base_url() ); ?>" class="wpss-btn wpss-btn--primary">
						<?php esc_html_e( 'Proceed to Checkout', 'wp-sell-services' ); ?>
					</a>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<script>
( function( $ ) {
	'use strict';

	$( '.wpss-remove-cart-item' ).on( 'click', function() {
		var $btn     = $( this );
		var $item    = $btn.closest( '.wpss-cart-item' );
		var itemKey  = $btn.data( 'item-key' );
		var nonce    = ( typeof wpssData !== 'undefined' && wpssData.cartNonce ) ? wpssData.cartNonce : '';

		if ( ! itemKey || ! nonce ) {
			return;
		}

		$item.addClass( 'is-removing' );

		$.ajax( {
			url:    ( typeof wpssData !== 'undefined' && wpssData.ajaxUrl ) ? wpssData.ajaxUrl : ajaxurl,
			type:   'POST',
			data:   {
				action:   'wpss_remove_cart_item',
				nonce:    nonce,
				item_key: itemKey,
			},
			success: function( response ) {
				if ( response.success ) {
					$item.slideUp( 250, function() {
						$item.remove();

						// Update cart count in header if element exists.
						var newCount = parseInt( response.data.cart_count, 10 ) || 0;
						$( '.wpss-cart-count' ).text( newCount );
						if ( typeof wpssData !== 'undefined' ) {
							wpssData.cartCount = newCount;
						}

						// Show empty state if no items remain.
						if ( $( '.wpss-cart-item' ).length === 0 ) {
							location.reload();
						}
					} );
				} else {
					$item.removeClass( 'is-removing' );
				}
			},
			error: function() {
				$item.removeClass( 'is-removing' );
			},
		} );
	} );
} )( jQuery );
</script>
