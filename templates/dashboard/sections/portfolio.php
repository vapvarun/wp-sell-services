<?php
/**
 * Dashboard Section: Portfolio
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

if ( ! $is_vendor ) {
	return;
}

$portfolio_service = new \WPSellServices\Services\PortfolioService();
$items             = $portfolio_service->get_by_vendor( $user_id, array( 'limit' => 50 ) );
$item_count        = $portfolio_service->get_count( $user_id );
$max_items         = (int) get_option( 'wpss_max_portfolio_items', 50 );

// Get vendor's services for the dropdown.
$vendor_services = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'author'         => $user_id,
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

/**
 * Fires before the portfolio dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('portfolio').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'portfolio', get_userdata( $user_id ) );
?>

<div class="wpss-section wpss-section--portfolio">
	<div class="wpss-portfolio__header">
		<p class="wpss-portfolio__count">
			<?php
			printf(
				/* translators: 1: current count, 2: max items */
				esc_html__( '%1$d of %2$d items', 'wp-sell-services' ),
				absint( $item_count ),
				absint( $max_items )
			);
			?>
		</p>
		<?php if ( $item_count < $max_items ) : ?>
			<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--small" id="wpss-portfolio-add-btn">
				<?php esc_html_e( 'Add Portfolio Item', 'wp-sell-services' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( empty( $items ) ) : ?>
		<div class="wpss-dashboard__empty">
			<h3><?php esc_html_e( 'No portfolio items yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Showcase your best work to attract more buyers.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<?php
		$portfolio_index = 0;
		$preview_items   = array();
		?>
		<div class="wpss-portfolio__grid" id="wpss-portfolio-grid">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$preview_items[] = array(
					'title'       => $item['title'],
					'description' => $item['description'] ?? '',
					'image'       => ! empty( $item['media'] ) ? ( $item['media'][0]['large'] ?? $item['media'][0]['url'] ?? '' ) : '',
					'tags'        => $item['tags'] ?? array(),
					'external'    => $item['external_url'] ?? '',
				);
				?><?php
				$media_ids    = wp_json_encode(
					array_map(
						function ( $m ) {
							return $m['id'];
						},
						$item['media']
					)
				);
				$media_thumbs = wp_json_encode(
					array_map(
						function ( $m ) {
							return $m['thumbnail'] ?? $m['url'];
						},
						$item['media']
					)
				);
				?>
				<div class="wpss-portfolio__item"
					data-item-id="<?php echo esc_attr( $item['id'] ); ?>"
					data-description="<?php echo esc_attr( $item['description'] ); ?>"
					data-external-url="<?php echo esc_attr( $item['external_url'] ); ?>"
					data-tags="<?php echo esc_attr( implode( ', ', $item['tags'] ) ); ?>"
					data-service-id="<?php echo esc_attr( $item['service_id'] ?? 0 ); ?>"
					data-is-featured="<?php echo esc_attr( $item['is_featured'] ? '1' : '0' ); ?>"
					data-media="<?php echo esc_attr( $media_ids ); ?>"
					data-media-thumbs="<?php echo esc_attr( $media_thumbs ); ?>"
				>
					<?php if ( ! empty( $item['media'] ) ) : ?>
						<div class="wpss-portfolio__media wpss-portfolio-preview" data-index="<?php echo esc_attr( $portfolio_index ); ?>" role="button" tabindex="0" style="cursor:pointer;">
							<img src="<?php echo esc_url( $item['media'][0]['medium'] ?? $item['media'][0]['url'] ?? '' ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>">
						</div>
					<?php else : ?>
						<div class="wpss-portfolio__media wpss-portfolio__media--placeholder wpss-portfolio-preview" data-index="<?php echo esc_attr( $portfolio_index ); ?>" role="button" tabindex="0" style="cursor:pointer;">
							<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5">
								<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
							</svg>
						</div>
					<?php endif; ?>

					<div class="wpss-portfolio__info">
						<h4 class="wpss-portfolio__title wpss-portfolio-preview" data-index="<?php echo esc_attr( $portfolio_index ); ?>" role="button" tabindex="0" style="cursor:pointer;"><?php echo esc_html( $item['title'] ); ?></h4>
						<?php if ( ! empty( $item['description'] ) ) : ?>
							<p class="wpss-portfolio__desc"><?php echo esc_html( wp_trim_words( $item['description'], 15 ) ); ?></p>
						<?php endif; ?>

						<div class="wpss-portfolio__actions">
							<?php if ( ! empty( $item['is_featured'] ) ) : ?>
								<span class="wpss-badge wpss-badge--warning wpss-badge--small"><?php esc_html_e( 'Featured', 'wp-sell-services' ); ?></span>
							<?php endif; ?>
							<button type="button" class="wpss-btn wpss-btn--small wpss-btn--link wpss-btn--outline wpss-portfolio-edit" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
								<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
							</button>
							<button type="button" class="wpss-btn wpss-btn--small wpss-btn--link wpss-btn--outline wpss-portfolio-toggle-featured" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
								<?php echo $item['is_featured'] ? esc_html__( 'Unfeature', 'wp-sell-services' ) : esc_html__( 'Feature', 'wp-sell-services' ); ?>
							</button>
							<button type="button" class="wpss-btn wpss-btn--small wpss-btn--link wpss-btn--outline wpss-btn--danger wpss-portfolio-delete" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'wp-sell-services' ); ?>
							</button>
						</div>
					</div>
				</div>
				<?php ++$portfolio_index; ?>
			<?php endforeach; ?>
		</div>

		<!-- Portfolio Preview Lightbox -->
		<div class="wpss-portfolio-lightbox" id="wpss-dashboard-portfolio-lightbox" role="dialog" aria-modal="true" aria-hidden="true">
			<div class="wpss-portfolio-lightbox__backdrop"></div>
			<div class="wpss-portfolio-lightbox__content">
				<button type="button" class="wpss-portfolio-lightbox__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">&times;</button>
				<button type="button" class="wpss-portfolio-lightbox__nav wpss-portfolio-lightbox__prev" aria-label="<?php esc_attr_e( 'Previous', 'wp-sell-services' ); ?>">&#8249;</button>
				<button type="button" class="wpss-portfolio-lightbox__nav wpss-portfolio-lightbox__next" aria-label="<?php esc_attr_e( 'Next', 'wp-sell-services' ); ?>">&#8250;</button>
				<div class="wpss-portfolio-lightbox__image-wrap">
					<img class="wpss-portfolio-lightbox__image" src="" alt="">
				</div>
				<div class="wpss-portfolio-lightbox__info">
					<h3 class="wpss-portfolio-lightbox__title"></h3>
					<p class="wpss-portfolio-lightbox__desc"></p>
					<div class="wpss-portfolio-lightbox__tags"></div>
					<a class="wpss-portfolio-lightbox__link" href="#" target="_blank" rel="noopener noreferrer" style="display:none;">
						<?php esc_html_e( 'View Project', 'wp-sell-services' ); ?> &rarr;
					</a>
				</div>
			</div>
		</div>
		<style>
		.wpss-portfolio-lightbox{display:none;position:fixed;inset:0;z-index:99999}
		.wpss-portfolio-lightbox[aria-hidden="false"]{display:flex;align-items:center;justify-content:center}
		.wpss-portfolio-lightbox__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.85)}
		.wpss-portfolio-lightbox__content{position:relative;display:flex;flex-direction:column;max-width:900px;max-height:90vh;width:90vw;background:#fff;border-radius:12px;overflow:hidden;z-index:1}
		.wpss-portfolio-lightbox__close{position:absolute;top:12px;right:12px;z-index:2;background:rgba(0,0,0,.5);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:24px;cursor:pointer;line-height:1}
		.wpss-portfolio-lightbox__close:hover{background:rgba(0,0,0,.8)}
		.wpss-portfolio-lightbox__nav{position:absolute;top:50%;transform:translateY(-50%);z-index:2;background:rgba(0,0,0,.4);color:#fff;border:none;width:40px;height:40px;border-radius:50%;font-size:28px;cursor:pointer;line-height:1}
		.wpss-portfolio-lightbox__nav:hover{background:rgba(0,0,0,.7)}
		.wpss-portfolio-lightbox__prev{left:12px}
		.wpss-portfolio-lightbox__next{right:12px}
		.wpss-portfolio-lightbox__image-wrap{flex:1;min-height:0;overflow:hidden;background:#f3f4f6;display:flex;align-items:center;justify-content:center}
		.wpss-portfolio-lightbox__image{max-width:100%;max-height:60vh;object-fit:contain}
		.wpss-portfolio-lightbox__info{padding:20px 24px;border-top:1px solid #e5e7eb}
		.wpss-portfolio-lightbox__title{font-size:18px;font-weight:600;margin:0 0 6px}
		.wpss-portfolio-lightbox__desc{font-size:14px;color:#6b7280;margin:0 0 12px;line-height:1.6}
		.wpss-portfolio-lightbox__tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
		.wpss-portfolio-lightbox__tags:empty{display:none}
		.wpss-portfolio-lightbox__tags span{padding:2px 10px;background:#f3f4f6;border-radius:20px;font-size:12px;color:#374151;font-weight:500}
		.wpss-portfolio-lightbox__link{display:inline-flex;align-items:center;gap:4px;color:#6366f1;font-size:14px;font-weight:500;text-decoration:none}
		.wpss-portfolio-lightbox__link:hover{text-decoration:underline}
		</style>
		<script>
		(function(){
			var items=<?php echo wp_json_encode( $preview_items ); ?>;
			if(!items.length)return;
			var lb=document.getElementById('wpss-dashboard-portfolio-lightbox');
			if(!lb)return;
			var imgEl=lb.querySelector('.wpss-portfolio-lightbox__image');
			var titleEl=lb.querySelector('.wpss-portfolio-lightbox__title');
			var descEl=lb.querySelector('.wpss-portfolio-lightbox__desc');
			var tagsEl=lb.querySelector('.wpss-portfolio-lightbox__tags');
			var linkEl=lb.querySelector('.wpss-portfolio-lightbox__link');
			var cur=0;
			function show(i){
				cur=i;var it=items[i];
				imgEl.src=it.image||'';imgEl.alt=it.title;
				titleEl.textContent=it.title;descEl.textContent=it.description;
				while(tagsEl.firstChild)tagsEl.removeChild(tagsEl.firstChild);
				(it.tags||[]).forEach(function(t){var s=document.createElement('span');s.textContent=t;tagsEl.appendChild(s);});
				if(it.external){linkEl.href=it.external;linkEl.style.display='';}else{linkEl.style.display='none';}
				lb.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';
			}
			function close(){lb.setAttribute('aria-hidden','true');document.body.style.overflow='';}
			document.querySelectorAll('.wpss-portfolio-preview').forEach(function(el){
				el.addEventListener('click',function(e){e.stopPropagation();show(parseInt(this.dataset.index,10));});
			});
			lb.querySelector('.wpss-portfolio-lightbox__backdrop').addEventListener('click',close);
			lb.querySelector('.wpss-portfolio-lightbox__close').addEventListener('click',close);
			lb.querySelector('.wpss-portfolio-lightbox__prev').addEventListener('click',function(){show((cur-1+items.length)%items.length);});
			lb.querySelector('.wpss-portfolio-lightbox__next').addEventListener('click',function(){show((cur+1)%items.length);});
			document.addEventListener('keydown',function(e){
				if(lb.getAttribute('aria-hidden')!=='false')return;
				if(e.key==='Escape')close();
				if(e.key==='ArrowLeft')show((cur-1+items.length)%items.length);
				if(e.key==='ArrowRight')show((cur+1)%items.length);
			});
		})();
		</script>
	<?php endif; ?>

	<!-- Add/Edit Portfolio Modal -->
	<div class="wpss-modal" id="wpss-portfolio-modal" role="dialog" aria-modal="true" aria-labelledby="wpss-portfolio-modal-title">
		<div class="wpss-modal__overlay"></div>
		<div class="wpss-modal__content">
			<div class="wpss-modal__header">
				<h3 id="wpss-portfolio-modal-title"><?php esc_html_e( 'Add Portfolio Item', 'wp-sell-services' ); ?></h3>
				<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">&times;</button>
			</div>
			<form id="wpss-portfolio-form" method="post">
				<?php wp_nonce_field( 'wpss_portfolio_nonce', 'portfolio_nonce' ); ?>
				<input type="hidden" name="item_id" id="wpss-portfolio-item-id" value="0">

				<div class="wpss-form-row">
					<label for="portfolio-title"><?php esc_html_e( 'Title', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<input type="text" id="portfolio-title" name="title" class="wpss-input" required>
				</div>

				<div class="wpss-form-row">
					<label for="portfolio-description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
					<textarea id="portfolio-description" name="description" rows="3" class="wpss-textarea"></textarea>
				</div>

				<div class="wpss-form-row">
					<label><?php esc_html_e( 'Images', 'wp-sell-services' ); ?></label>
					<div class="wpss-portfolio-media-preview" id="wpss-portfolio-media-preview"></div>
					<input type="hidden" name="media" id="wpss-portfolio-media" value="[]">
					<button type="button" class="wpss-btn wpss-btn--small wpss-btn--secondary" id="wpss-portfolio-upload-media">
						<?php esc_html_e( 'Upload Images', 'wp-sell-services' ); ?>
					</button>
				</div>

				<div class="wpss-form-row">
					<label for="portfolio-external-url"><?php esc_html_e( 'External URL', 'wp-sell-services' ); ?></label>
					<input type="url" id="portfolio-external-url" name="external_url" class="wpss-input" placeholder="https://">
				</div>

				<div class="wpss-form-row">
					<label for="portfolio-tags"><?php esc_html_e( 'Tags', 'wp-sell-services' ); ?></label>
					<input type="text" id="portfolio-tags" name="tags" class="wpss-input" placeholder="<?php esc_attr_e( 'e.g., logo, branding, modern', 'wp-sell-services' ); ?>">
					<p class="wpss-form-hint"><?php esc_html_e( 'Comma-separated list of tags.', 'wp-sell-services' ); ?></p>
				</div>

				<?php if ( ! empty( $vendor_services ) ) : ?>
					<div class="wpss-form-row">
						<label for="portfolio-service"><?php esc_html_e( 'Related Service', 'wp-sell-services' ); ?></label>
						<select id="portfolio-service" name="service_id" class="wpss-input">
							<option value="0"><?php esc_html_e( '-- None --', 'wp-sell-services' ); ?></option>
							<?php foreach ( $vendor_services as $service_id_opt ) : ?>
								<option value="<?php echo esc_attr( $service_id_opt ); ?>">
									<?php echo esc_html( get_the_title( $service_id_opt ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<div class="wpss-form-row">
					<label class="wpss-toggle">
						<input type="checkbox" name="is_featured" value="1" id="portfolio-featured">
						<span class="wpss-toggle__label"><?php esc_html_e( 'Mark as Featured', 'wp-sell-services' ); ?></span>
					</label>
				</div>

				<div class="wpss-modal__footer">
					<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__close-btn"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
					<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Save', 'wp-sell-services' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php
/**
 * Fires after the portfolio dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('portfolio').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'portfolio', $user_id );
?>
