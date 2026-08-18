<?php
/**
 * Template + rendering helpers: template loading, pagination, assets, video embeds.
 *
 * Split out of src/functions.php, which had grown to 6,187 lines and 148
 * global functions in a single file. This is a positional move only - no
 * function was renamed, resignatured or changed, so every call site is
 * untouched. src/functions.php now just requires these files.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * THE status → CSS class authority for status badges.
 *
 * One place that turns any status value into its badge class, so no surface can
 * invent its own mapping and drift. Two prior bugs this replaces:
 *
 *  - `OrdersListTable` kept a hand-maintained status→class array that was
 *    MISSING refunded / delivered / accepted / resolved, so every one of those
 *    fell through to a `wpss-status-pending` default and a refunded order
 *    rendered amber ("pending") instead of its own colour.
 *  - Half the render sites emitted `wpss-status-<raw_status>` (underscore) while
 *    the other half emitted `str_replace('_','-', $status)` (hyphen), so the CSS
 *    had to carry BOTH spellings of every multi-word status.
 *
 * Emits the HYPHEN spelling (CSS-idiomatic) — every render site routed through
 * here produces the same class, and the CSS needs one rule per status, not two.
 * The status keeps its own semantic colour, defined once in the status-badge
 * CSS. Filterable so a site can recolour a status without editing core.
 *
 * @since 1.3.0
 *
 * @param string $status Raw status value (e.g. 'revision_requested').
 * @return string Space-joined classes: the badge base + the status class.
 */
function wpss_status_class( string $status ): string {
	$status = sanitize_key( $status );
	$class  = 'wpss-status-badge wpss-status-' . str_replace( '_', '-', $status );

	/**
	 * Filter the CSS classes for a status badge.
	 *
	 * @since 1.3.0
	 *
	 * @param string $class  Space-joined badge classes.
	 * @param string $status Raw status value.
	 */
	return apply_filters( 'wpss_status_class', $class, $status );
}

/**
 * Get template part.
 *
 * @param string $slug Template slug.
 * @param string $name Optional template name.
 * @param array  $args Optional arguments to pass to template.
 * @return void
 */
function wpss_get_template_part( string $slug, string $name = '', array $args = array() ): void {
	$template = '';

	// Look in theme first.
	if ( $name ) {
		$template = locate_template( "wp-sell-services/{$slug}-{$name}.php" );
	}

	if ( ! $template ) {
		$template = locate_template( "wp-sell-services/{$slug}.php" );
	}

	// Fall back to plugin templates.
	if ( ! $template ) {
		if ( $name && file_exists( WPSS_PLUGIN_DIR . "templates/{$slug}-{$name}.php" ) ) {
			$template = WPSS_PLUGIN_DIR . "templates/{$slug}-{$name}.php";
		} elseif ( file_exists( WPSS_PLUGIN_DIR . "templates/{$slug}.php" ) ) {
			$template = WPSS_PLUGIN_DIR . "templates/{$slug}.php";
		}
	}

	/**
	 * Filter the template file path.
	 *
	 * @param string $template Template file path.
	 * @param string $slug     Template slug.
	 * @param string $name     Template name.
	 */
	$template = apply_filters( 'wpss_get_template_part', $template, $slug, $name );

	$template_name = $name ? "{$slug}-{$name}" : $slug;

	/** This filter is documented in src/functions.php wpss_get_template() */
	$args = apply_filters( 'wpss_template_args', $args, $template_name );

	if ( $template ) {
		// Extract args to make them available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;
	}
}

/**
 * Get template.
 *
 * @param string $template_name Template name.
 * @param array  $args          Arguments to pass to template.
 * @param string $template_path Template path in theme.
 * @param string $default_path  Default template path.
 * @return void
 */
function wpss_get_template( string $template_name, array $args = array(), string $template_path = '', string $default_path = '' ): void {
	if ( empty( $template_path ) ) {
		$template_path = 'wp-sell-services/';
	}

	if ( empty( $default_path ) ) {
		$default_path = WPSS_PLUGIN_DIR . 'templates/';
	}

	// Look within theme first.
	$template = locate_template( $template_path . $template_name );

	// Fall back to plugin.
	if ( ! $template ) {
		$template = $default_path . $template_name;
	}

	/**
	 * Filter the template file path.
	 *
	 * @param string $template      Template file path.
	 * @param string $template_name Template name.
	 * @param array  $args          Template arguments.
	 */
	$template = apply_filters( 'wpss_get_template', $template, $template_name, $args );

	/**
	 * Filter the template arguments before rendering.
	 *
	 * Allows modification or addition of variables passed to a template
	 * before extraction and rendering.
	 *
	 * @since 1.1.0
	 * @param array  $args          Template arguments.
	 * @param string $template_name Template name being loaded.
	 */
	$args = apply_filters( 'wpss_template_args', $args, $template_name );

	if ( file_exists( $template ) ) {
		// Extract args to make them available in template.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;
	}
}

/**
 * Calculate time difference in human readable format.
 *
 * Both sides of the comparison must be real UTC timestamps. The stored
 * datetime is UTC, so it is parsed as UTC; "now" is time(), not
 * current_time( 'timestamp' ). current_time() returns UTC shifted by the
 * site's offset — a fake timestamp — so comparing the two added the offset to
 * every result: on a UTC+5:30 site an order placed 47 minutes ago rendered as
 * "6 hours ago". Correct on a UTC site, wrong everywhere else.
 *
 * @param string $datetime MySQL datetime string, in UTC.
 * @return string
 */
function wpss_time_ago( string $datetime ): string {
	$timestamp = strtotime( $datetime . ' UTC' );

	if ( ! $timestamp ) {
		return '';
	}

	return human_time_diff( $timestamp, time() ) . ' ' . __( 'ago', 'wp-sell-services' );
}

/**
 * Render pagination for a WP_Query.
 *
 * Outputs pagination HTML using WordPress paginate_links().
 *
 * @since 1.2.0
 *
 * @param \WP_Query $query The query object to paginate.
 * @param array     $args  Optional. Arguments to customize pagination.
 * @return void
 */
function wpss_pagination( \WP_Query $query, array $args = array() ): void {
	wpss_render_pagination( (int) $query->max_num_pages, $args );
}

/**
 * Render pagination from a page COUNT rather than a WP_Query.
 *
 * Only a WP_Query is accepted by wpss_pagination(), so every surface backed by a
 * CUSTOM TABLE - orders, withdrawals, disputes - had no paginator available and
 * simply did not paginate. [wpss_my_orders] was the visible case: it ran
 * `LIMIT 20` with no OFFSET, no COUNT(*) and no navigation, so a buyer with
 * more than 20 orders could see the first 20 and had no route to the rest.
 *
 * The link-building below is exactly what wpss_pagination() used to do inline;
 * that function now delegates here, so both kinds of surface produce identical
 * markup and there is one place to change it.
 *
 * @since 1.5.1
 *
 * @param int                  $total_pages Total number of pages.
 * @param array<string, mixed> $args        Optional. Arguments passed to paginate_links().
 * @return void
 */
function wpss_render_pagination( int $total_pages, array $args = array() ): void {
	if ( $total_pages <= 1 ) {
		return;
	}

	$current_page = max( 1, (int) get_query_var( 'paged', 1 ) );

	// get_pagenum_link() resolves the current request URL. Outside the main
	// query (e.g. a REST render) it can return a non-string, which would make
	// str_replace() fatal. Guard it so the default base is always a string; an
	// off-main-query caller (wpss_render_services_grid) supplies an explicit
	// 'base' + 'format' anyway.
	$pagenum_link = get_pagenum_link( 999999999 );
	$default_base = is_string( $pagenum_link )
		? str_replace( '999999999', '%#%', esc_url( $pagenum_link ) )
		: '';

	$defaults = array(
		'base'      => $default_base,
		'format'    => '?paged=%#%',
		'current'   => $current_page,
		'total'     => $total_pages,
		'prev_text' => '&laquo;',
		'next_text' => '&raquo;',
		'type'      => 'list',
	);

	$args = wp_parse_args( $args, $defaults );

	$pagination = paginate_links( $args );

	if ( $pagination ) {
		echo '<nav class="wpss-pagination" aria-label="' . esc_attr__( 'Pagination', 'wp-sell-services' ) . '">';
		echo wp_kses_post( $pagination );
		echo '</nav>';
	}
}

/**
 * Enqueue WPSS frontend assets.
 *
 * Call this from any shortcode, block, or template that needs WPSS frontend styles and scripts.
 *
 * @since 1.0.0
 * @return void
 */
function wpss_enqueue_frontend_assets(): void {
	wp_enqueue_style( 'wpss-design-system' );
	wp_enqueue_style( 'wpss-frontend' );
	// Packet H: load Lucide + bootstrap alongside the frontend bundle so every
	// <i data-lucide="…"> rendered by templates on the current surface hydrates
	// on DOMContentLoaded and on the wpss:icons:refresh CustomEvent.
	wp_enqueue_script( 'lucide' );
	wp_enqueue_script( 'wpss-icons' );
	wp_enqueue_script( 'wpss-frontend' );
}

/**
 * Check whether a URL is a recognized YouTube or Vimeo video.
 *
 * Used as the whitelist for vendor intro videos — anything that is not a
 * parseable YouTube / Vimeo link is rejected on save so the profile
 * never tries to embed an arbitrary third-party iframe.
 *
 * @since 1.1.0
 *
 * @param string $url Raw URL.
 * @return bool True if the URL is a parseable YouTube or Vimeo video link.
 */
function wpss_is_supported_video_url( string $url ): bool {
	if ( '' === $url ) {
		return false;
	}

	return null !== wpss_parse_video_embed( $url );
}

/**
 * Parse a YouTube or Vimeo URL into embed pieces.
 *
 * Returns null when the URL is not a supported provider or when the video
 * ID cannot be extracted. On success, returns:
 *   [
 *     'provider' => 'youtube'|'vimeo',
 *     'id'       => string,
 *     'embed'    => fully-qualified embed URL for an <iframe src>
 *   ]
 *
 * Supported URL shapes:
 *   - YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID,
 *     youtube.com/shorts/ID, m.youtube.com/* variants
 *   - Vimeo: vimeo.com/ID, player.vimeo.com/video/ID, channel paths with
 *     a numeric ID
 *
 * @since 1.1.0
 *
 * @param string $url Raw URL.
 * @return array{provider: string, id: string, embed: string}|null
 */
function wpss_parse_video_embed( string $url ): ?array {
	$url = trim( $url );
	if ( '' === $url ) {
		return null;
	}

	$parts = wp_parse_url( $url );
	$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	$host  = preg_replace( '/^www\./', '', $host );

	if ( 'youtu.be' === $host ) {
		$id = ltrim( (string) ( $parts['path'] ?? '' ), '/' );
		if ( preg_match( '/^[A-Za-z0-9_-]{6,}$/', $id ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $id,
				'embed'    => 'https://www.youtube.com/embed/' . rawurlencode( $id ),
			);
		}
		return null;
	}

	if ( 'youtube.com' === $host || 'm.youtube.com' === $host ) {
		$path = (string) ( $parts['path'] ?? '' );
		parse_str( (string) ( $parts['query'] ?? '' ), $query );

		$id = '';
		if ( '/watch' === $path && ! empty( $query['v'] ) ) {
			$id = (string) $query['v'];
		} elseif ( preg_match( '#^/(embed|shorts|v)/([A-Za-z0-9_-]{6,})#', $path, $m ) ) {
			$id = $m[2];
		}

		if ( preg_match( '/^[A-Za-z0-9_-]{6,}$/', $id ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $id,
				'embed'    => 'https://www.youtube.com/embed/' . rawurlencode( $id ),
			);
		}
		return null;
	}

	if ( 'vimeo.com' === $host || 'player.vimeo.com' === $host ) {
		$path = (string) ( $parts['path'] ?? '' );
		if ( preg_match( '#/(\d{5,})#', $path, $m ) ) {
			return array(
				'provider' => 'vimeo',
				'id'       => $m[1],
				'embed'    => 'https://player.vimeo.com/video/' . rawurlencode( $m[1] ),
			);
		}
		return null;
	}

	return null;
}

/**
 * Render a vendor intro video as a responsive 16:9 embed.
 *
 * Returns '' (not an iframe fallback) when the URL is empty or not a
 * supported provider so callers can echo unconditionally and the page
 * simply drops the video section when there isn't one.
 *
 * @since 1.1.0
 *
 * @param string $url   Raw video URL.
 * @param string $title Optional accessible title for the iframe.
 * @return string HTML wrapper + iframe, or empty string.
 */
function wpss_render_video_embed( string $url, string $title = '' ): string {
	$parsed = wpss_parse_video_embed( $url );
	if ( null === $parsed ) {
		return '';
	}

	$title = '' === $title ? __( 'Vendor intro video', 'wp-sell-services' ) : $title;

	return sprintf(
		'<div class="wpss-video-embed"><iframe src="%s" title="%s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe></div>',
		esc_url( $parsed['embed'] ),
		esc_attr( $title )
	);
}

/**
 * Render one conversation message row (the canonical messaging markup).
 *
 * Single source of truth for a message bubble in the order/dashboard thread.
 * Used by the initial server render (templates/order/conversation.php), the
 * REST message response (additive `html` field), the REST/AJAX send response,
 * and the AJAX poll - so every transport appends byte-identical markup.
 *
 * Accepts either a Message model (attachments/read_by as arrays, created_at as
 * DateTimeImmutable) or a raw $wpdb row (JSON strings, string created_at, with
 * an optional sender_name from a JOIN); the shape is normalised internally.
 *
 * @since 1.2.0
 *
 * @param object $message         Message model or raw DB row.
 * @param int    $current_user_id Viewer user ID (controls sent/received styling).
 * @return string Message row HTML.
 */
function wpss_render_message_row( object $message, int $current_user_id ): string {
	$sender_id = (int) ( $message->sender_id ?? 0 );
	$content   = (string) ( $message->content ?? '' );
	$type      = $message->type ?? ( $message->content_type ?? 'text' );
	$is_own    = $sender_id === $current_user_id;
	$is_system = 'system' === $type;

	// Normalise created_at to a timestamp.
	$created = $message->created_at ?? null;
	if ( $created instanceof \DateTimeInterface ) {
		$created_ts = $created->getTimestamp();
	} else {
		$created_ts = $created ? strtotime( (string) $created ) : time();
	}
	$time_text = wp_date( get_option( 'time_format' ), $created_ts );

	// Normalise attachments + read_by to arrays.
	$attachments = $message->attachments ?? array();
	if ( is_string( $attachments ) ) {
		$attachments = json_decode( $attachments, true ) ?: array();
	}
	$read_by = $message->read_by ?? array();
	if ( is_string( $read_by ) ) {
		$read_by = json_decode( $read_by, true ) ?: array();
	}

	// Sender display name (prefer a JOIN-supplied value, else look it up).
	$sender_name = $message->sender_name ?? '';
	if ( '' === $sender_name && $sender_id ) {
		$sender      = get_userdata( $sender_id );
		$sender_name = $sender ? $sender->display_name : '';
	}

	ob_start();

	if ( $is_system ) :
		?>
		<div class="wpss-messaging__system">
			<span class="wpss-messaging__system-text">
				<?php echo wp_kses_post( $content ); ?>
				<span class="wpss-messaging__message-time">
					<?php echo esc_html( $time_text ); ?>
				</span>
			</span>
		</div>
		<?php
	else :
		?>
		<div class="wpss-messaging__message <?php echo $is_own ? 'wpss-messaging__message--sent' : ''; ?>" data-message-id="<?php echo esc_attr( (string) ( $message->id ?? 0 ) ); ?>">
			<?php if ( ! $is_own ) : ?>
				<div class="wpss-messaging__message-avatar">
					<?php echo get_avatar( $sender_id, 32 ); ?>
				</div>
			<?php endif; ?>
			<div class="wpss-messaging__message-content">
				<div class="wpss-messaging__bubble">
					<?php if ( ! $is_own ) : ?>
						<span class="wpss-messaging__sender"><?php echo esc_html( $sender_name ); ?></span>
					<?php endif; ?>
					<div class="wpss-messaging__text">
						<?php
						// Linkify, then break lines, then escape.
						//
						// A URL pasted into a message rendered as plain text on both
						// threads - people paste briefs, references and file links
						// constantly, and none of them were clickable
						// (Basecamp #10159632931).
						//
						// Order matters: make_clickable() before nl2br(), because it
						// matches to whitespace and an injected <br> mid-URL truncates
						// the link. wp_kses_post() still runs last, so the anchors
						// make_clickable() produces are the only new markup allowed
						// through - a message body cannot smuggle anything else.
						echo wp_kses_post( nl2br( make_clickable( $content ) ) );
						?>
					</div>
					<?php if ( ! empty( $attachments ) ) : ?>
						<div class="wpss-messaging__attachments">
							<?php foreach ( $attachments as $attachment ) : ?>
								<?php
								$file_url  = $attachment['url'] ?? '';
								$file_name = $attachment['name'] ?? ( $attachment['filename'] ?? basename( (string) $file_url ) );
								$file_type = $attachment['type'] ?? '';
								$is_image  = 0 === strpos( (string) $file_type, 'image/' );
								?>
								<?php if ( $is_image && $file_url ) : ?>
									<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-image">
										<img src="<?php echo esc_url( $file_url ); ?>" alt="<?php echo esc_attr( $file_name ); ?>">
									</a>
								<?php else : ?>
									<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-file">
										<span class="wpss-messaging__attachment-icon">
											<i data-lucide="file" class="wpss-icon" aria-hidden="true"></i>
										</span>
										<span class="wpss-messaging__attachment-info">
											<span class="wpss-messaging__attachment-name"><?php echo esc_html( $file_name ); ?></span>
										</span>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<span class="wpss-messaging__message-time">
					<?php echo esc_html( $time_text ); ?>
					<?php $is_read = ! empty( array_diff_key( (array) $read_by, array( $current_user_id => '' ) ) ); ?>
					<?php if ( $is_own && $is_read ) : ?>
						<span class="wpss-messaging__message-status wpss-messaging__message-status--read" title="<?php esc_attr_e( 'Read', 'wp-sell-services' ); ?>">
							<i data-lucide="check" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
						</span>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	endif;

	return (string) ob_get_clean();
}

/**
 * Register the design-system stylesheet (tokens) exactly once.
 *
 * Every WPSS sheet - frontend, admin, wizard, block editor - is authored
 * against the tokens in design-system.css, and most rules carry a hardcoded
 * fallback such as `var( --wpss-vendor-green, #1dbf73 )`. That makes a missing
 * registration invisible rather than loud: nothing 404s, the tokens are simply
 * undefined and every fallback silently becomes the value. Through 1.6.0 the
 * handle was registered only on the frontend, so the entire admin rendered the
 * retired green accent while the frontend rendered the correct indigo one.
 *
 * Registering from one place means the src and version cannot drift between
 * contexts, and any sheet may safely declare `wpss-design-system` as a
 * dependency without caring which surface it is on. Idempotent: WP_Styles::add()
 * ignores a handle that is already registered.
 *
 * @since 1.6.0
 *
 * @param bool $enqueue Whether to enqueue it as well as register it.
 * @return void
 */
function wpss_register_design_system( bool $enqueue = false ): void {
	if ( ! wp_style_is( 'wpss-design-system', 'registered' ) ) {
		wp_register_style(
			'wpss-design-system',
			\WPSS_PLUGIN_URL . 'assets/css/design-system.css',
			array(),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-design-system', 'rtl', 'replace' );
	}

	if ( $enqueue ) {
		wp_enqueue_style( 'wpss-design-system' );
	}
}
