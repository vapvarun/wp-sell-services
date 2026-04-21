<?php
/**
 * Template Partial: Vendor Portfolio
 *
 * Displays a responsive grid of portfolio cards for a vendor.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/partials/vendor-portfolio.php
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var array $portfolio_items Array of portfolio item arrays.
 * @var int   $vendor_id       Vendor user ID.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $portfolio_items ) ) {
	return;
}
?>

<style>
.wpss-portfolio-public {
	--_cols: 3;
}

.wpss-portfolio-public__grid {
	display: grid;
	grid-template-columns: repeat( var( --_cols ), 1fr );
	gap: var( --wpss-space-4, 16px );
}

.wpss-portfolio-public__card {
	position: relative;
	border-radius: var( --wpss-radius, 8px );
	overflow: hidden;
	background: var( --wpss-bg, #fff );
	box-shadow: var( --wpss-shadow-sm, 0 1px 3px rgba(0,0,0,.1) );
	cursor: pointer;
	aspect-ratio: 4 / 3;
}

/* Image */
.wpss-portfolio-public__image {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
	transition: transform 0.3s ease;
}

.wpss-portfolio-public__card:hover .wpss-portfolio-public__image {
	transform: scale( 1.04 );
}

/* Placeholder when there is no image */
.wpss-portfolio-public__placeholder {
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var( --wpss-bg-muted, #f3f4f6 );
	color: var( --wpss-text-hint, #9ca3af );
}

/* Title bar always visible at the bottom */
.wpss-portfolio-public__title-bar {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	padding: var( --wpss-space-3, 12px ) var( --wpss-space-4, 16px );
	background: linear-gradient( to top, rgba(0,0,0,.65) 0%, transparent 100% );
	color: #fff;
}

.wpss-portfolio-public__title {
	margin: 0;
	font-size: var( --wpss-text-sm, 13px );
	font-weight: 600;
	line-height: 1.3;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* Featured badge */
.wpss-portfolio-public__featured {
	position: absolute;
	top: var( --wpss-space-2, 8px );
	left: var( --wpss-space-2, 8px );
	padding: 2px var( --wpss-space-2, 8px );
	border-radius: var( --wpss-radius-full, 9999px );
	background: var( --wpss-warning, #f59e0b );
	color: #fff;
	font-size: var( --wpss-text-xs, 12px );
	font-weight: 600;
	line-height: 1.6;
	pointer-events: none;
}

/* Hover overlay */
.wpss-portfolio-public__overlay {
	position: absolute;
	inset: 0;
	background: rgba(15, 10, 40, .82);
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
	padding: var( --wpss-space-4, 16px );
	opacity: 0;
	transition: opacity 0.25s ease;
	color: #fff;
}

.wpss-portfolio-public__card:hover .wpss-portfolio-public__overlay,
.wpss-portfolio-public__card:focus-visible .wpss-portfolio-public__overlay {
	opacity: 1;
}

.wpss-portfolio-public__overlay-title {
	margin: 0 0 var( --wpss-space-2, 8px );
	font-size: var( --wpss-text-base, 14px );
	font-weight: 700;
	line-height: 1.3;
}

.wpss-portfolio-public__desc {
	margin: 0 0 var( --wpss-space-3, 12px );
	font-size: var( --wpss-text-xs, 12px );
	line-height: 1.5;
	color: rgba(255,255,255,.82);
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

/* Tags row */
.wpss-portfolio-public__tags {
	display: flex;
	flex-wrap: wrap;
	gap: var( --wpss-space-1, 4px );
	margin-bottom: var( --wpss-space-3, 12px );
}

.wpss-portfolio-public__tag {
	padding: 2px var( --wpss-space-2, 8px );
	border-radius: var( --wpss-radius-full, 9999px );
	background: rgba(255,255,255,.15);
	font-size: var( --wpss-text-xs, 12px );
	color: rgba(255,255,255,.9);
	line-height: 1.6;
	font-weight: 500;
}

/* External link icon button */
.wpss-portfolio-public__ext-link {
	display: inline-flex;
	align-items: center;
	gap: var( --wpss-space-1, 4px );
	color: rgba(255,255,255,.9);
	font-size: var( --wpss-text-xs, 12px );
	text-decoration: none;
	border: 1px solid rgba(255,255,255,.3);
	border-radius: var( --wpss-radius-sm, 6px );
	padding: 3px var( --wpss-space-2, 8px );
	transition: background 0.2s ease;
	align-self: flex-start;
}

.wpss-portfolio-public__ext-link:hover {
	background: rgba(255,255,255,.18);
	color: #fff;
}

/* Responsive */
@media ( max-width: 900px ) {
	.wpss-portfolio-public {
		--_cols: 2;
	}
}

@media ( max-width: 540px ) {
	.wpss-portfolio-public {
		--_cols: 1;
	}
}
</style>

<div class="wpss-portfolio-public">
	<div class="wpss-portfolio-public__grid">
		<?php
	// Collect all items for the lightbox.
	$lightbox_items = array();
	?>
	<?php foreach ( $portfolio_items as $item ) : ?>
		<?php
		$lightbox_items[] = array(
			'id'          => absint( $item['id'] ),
			'title'       => $item['title'] ?? '',
			'description' => $item['description'] ?? '',
			'image'       => ! empty( $item['media'] ) ? ( $item['media'][0]['large'] ?? $item['media'][0]['url'] ?? '' ) : '',
			'tags'        => $item['tags'] ?? array(),
			'external'    => $item['external_url'] ?? '',
		);
		?>
			<?php
			$item_id     = absint( $item['id'] );
			$title       = $item['title'] ?? '';
			$description = $item['description'] ?? '';
			$media       = $item['media'] ?? [];
			$external    = $item['external_url'] ?? '';
			$tags        = $item['tags'] ?? [];
			$is_featured = ! empty( $item['is_featured'] );

			// Resolve the display image (large → url → nothing).
			$image_url = '';
			if ( ! empty( $media ) ) {
				$first     = $media[0];
				$image_url = $first['large'] ?? $first['url'] ?? '';
			}

			// Trim description for overlay.
			$desc_excerpt = $description ? wp_trim_words( $description, 20 ) : '';
			?>
			<div class="wpss-portfolio-public__card"
				tabindex="0"
				role="article"
				aria-label="<?php echo esc_attr( $title ); ?>"
				data-item-id="<?php echo esc_attr( $item_id ); ?>"
			>
				<?php if ( $image_url ) : ?>
					<img
						class="wpss-portfolio-public__image"
						src="<?php echo esc_url( $image_url ); ?>"
						alt="<?php echo esc_attr( $title ); ?>"
						loading="lazy"
					>
				<?php else : ?>
					<div class="wpss-portfolio-public__placeholder" aria-hidden="true">
						<i data-lucide="image" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
					</div>
				<?php endif; ?>

				<?php if ( $is_featured ) : ?>
					<span class="wpss-portfolio-public__featured">
						<?php esc_html_e( 'Featured', 'wp-sell-services' ); ?>
					</span>
				<?php endif; ?>

				<!-- Always-visible title bar at bottom of image -->
				<div class="wpss-portfolio-public__title-bar">
					<p class="wpss-portfolio-public__title"><?php echo esc_html( $title ); ?></p>
				</div>

				<!-- Hover overlay: description, tags, external link -->
				<div class="wpss-portfolio-public__overlay" aria-hidden="true">
					<p class="wpss-portfolio-public__overlay-title"><?php echo esc_html( $title ); ?></p>

					<?php if ( $desc_excerpt ) : ?>
						<p class="wpss-portfolio-public__desc"><?php echo esc_html( $desc_excerpt ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $tags ) ) : ?>
						<div class="wpss-portfolio-public__tags">
							<?php foreach ( $tags as $tag ) : ?>
								<span class="wpss-portfolio-public__tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $external ) : ?>
						<a
							href="<?php echo esc_url( $external ); ?>"
							class="wpss-portfolio-public__ext-link"
							target="_blank"
							rel="noopener noreferrer"
							tabindex="-1"
						>
							<i data-lucide="external-link" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
							<?php esc_html_e( 'View Project', 'wp-sell-services' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
