<?php
/**
 * Dashboard Section: Downloads
 *
 * The buyer's digital purchases they may still download, newest first, each with its current file.
 * The same rows as GET /wpss/v1/downloads (wpss_buyer_download_for_client()).
 *
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/dashboard/sections/downloads.php
 *
 * @package WPSellServices\Templates
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var int $user_id Current user ID.
 */

defined( 'ABSPATH' ) || exit;

/** This action is documented in templates/dashboard/sections/favorites.php */
do_action( 'wpss_dashboard_section_before', 'downloads', $user_id );

$wpss_per_page = 20;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param.
$wpss_page  = isset( $_GET['downloads_page'] ) ? max( 1, absint( $_GET['downloads_page'] ) ) : 1;
$wpss_total = wpss_get_buyer_downloads( $user_id, 0, 0 );
$wpss_pages = (int) ceil( $wpss_total / $wpss_per_page );
$wpss_rows  = array_map( 'wpss_buyer_download_for_client', wpss_get_buyer_downloads( $user_id, 0, $wpss_per_page, ( $wpss_page - 1 ) * $wpss_per_page ) );
?>

<div class="wpss-section wpss-section--downloads wpss-card wpss-downloads">
	<?php if ( ! $wpss_rows ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon" aria-hidden="true">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon::render() returns hand-built SVG with internally-escaped attributes.
				echo \WPSellServices\Services\Icon::render( 'download', array( 'class' => 'wpss-icon--lg' ) );
				?>
			</div>
			<h3 class="wpss-empty-state__title"><?php esc_html_e( 'No downloads yet', 'wp-sell-services' ); ?></h3>
			<p class="wpss-empty-state__text"><?php esc_html_e( 'Digital products you buy appear here, ready to download.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<ul class="wpss-downloads__list">
			<?php foreach ( $wpss_rows as $wpss_row ) : ?>
				<li class="wpss-downloads__item">
					<div class="wpss-downloads__body">
						<h3 class="wpss-downloads__title">
							<?php if ( $wpss_row['service']['link'] ) : ?>
								<a href="<?php echo esc_url( $wpss_row['service']['link'] ); ?>"><?php echo esc_html( $wpss_row['service']['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $wpss_row['service']['title'] ); ?>
							<?php endif; ?>
						</h3>
						<p class="wpss-downloads__meta">
							<a href="<?php echo esc_url( $wpss_row['order_url'] ); ?>">
								<?php
								/* translators: %s: order number. */
								echo esc_html( sprintf( __( 'Order #%s', 'wp-sell-services' ), $wpss_row['order_number'] ) );
								?>
							</a>
							<?php if ( $wpss_row['file'] ) : ?>
								<span><?php echo esc_html( $wpss_row['file']['name'] . ' · ' . size_format( $wpss_row['file']['size'] ) ); ?></span>
							<?php endif; ?>
						</p>
					</div>
					<?php if ( $wpss_row['file'] ) : ?>
						<a class="wpss-btn wpss-btn--primary" href="<?php echo esc_url( $wpss_row['file']['url'] ); ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ __( 'Download %s', 'wp-sell-services' ), $wpss_row['service']['title'] ) ); ?>">
							<i data-lucide="download" class="wpss-icon" aria-hidden="true"></i>
							<?php esc_html_e( 'Download', 'wp-sell-services' ); ?>
						</a>
					<?php else : ?>
						<span class="wpss-downloads__missing"><?php esc_html_e( 'This file is no longer available.', 'wp-sell-services' ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $wpss_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Download pages', 'wp-sell-services' ); ?>">
				<?php if ( $wpss_page > 1 ) : ?>
					<a href="<?php echo esc_url( $wpss_page > 2 ? add_query_arg( 'downloads_page', $wpss_page - 1 ) : remove_query_arg( 'downloads_page' ) ); ?>" class="wpss-pagination__link wpss-pagination__link--prev">
						<i data-lucide="chevron-left" class="wpss-icon" aria-hidden="true"></i>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<span class="wpss-pagination__current">
					<?php
					/* translators: 1: current page, 2: total pages */
					printf( esc_html__( 'Page %1$d of %2$d', 'wp-sell-services' ), (int) $wpss_page, (int) $wpss_pages );
					?>
				</span>
				<?php if ( $wpss_page < $wpss_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'downloads_page', $wpss_page + 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--next">
						<?php esc_html_e( 'Next', 'wp-sell-services' ); ?>
						<i data-lucide="chevron-right" class="wpss-icon" aria-hidden="true"></i>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
