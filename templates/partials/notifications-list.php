<?php
/**
 * Shared partial: in-app notifications list.
 *
 * THE single notifications surface. Rendered by every location that shows a
 * member their notifications — the unified dashboard section, the standalone
 * account page, and the myaccount (Woo/EDD) template — so all of them look and
 * behave identically and mark-read works everywhere.
 *
 * Previously each surface hand-rolled its own markup: the dashboard had none at
 * all, the standalone account page had a read-only list with no mark-read (and
 * reused the `wpss-notification` class, which is the fixed-position TOAST
 * component, so the list rendered off-screen), and the good myaccount template
 * was never loaded by anything.
 *
 * Uses `wpss-notif-*` classes — never `wpss-notification` (toast).
 *
 * @package WPSellServices\Templates
 * @since   1.2.2
 *
 * @var int  $user_id           Optional. User whose notifications to render.
 *                              Defaults to the current user.
 * @var bool $wpss_show_heading Optional. Whether this partial renders its own
 *                              "Notifications" heading. Defaults to true.
 *                              Surfaces that already show a page title — the
 *                              unified dashboard, whose shell renders an h1 —
 *                              pass false, otherwise the word appears twice,
 *                              which on a 390px screen cost most of the first
 *                              screenful before a single notification.
 */

defined( 'ABSPATH' ) || exit;

$wpss_notif_user = isset( $user_id ) ? (int) $user_id : get_current_user_id();

// Default true: the standalone account and myaccount surfaces have no page
// title of their own and still need it.
$wpss_show_heading = ! isset( $wpss_show_heading ) || (bool) $wpss_show_heading;

if ( $wpss_notif_user <= 0 ) {
	return;
}

/*
 * Paged, 20 a page, mirroring the other dashboard lists.
 *
 * This took the 50 most recent and stopped, with no page numbers, no Load more
 * and no Next - so a busy account simply could not reach anything older, even
 * though the data layer already accepted an offset.
 */

/*
 * Clamped deliberately. A filter returning 0 or a negative number produced
 * LIMIT 0 - an empty list on a member who has notifications - or an SQL error,
 * and the site owner would have no way to tell that their own snippet caused
 * it. An upper bound keeps one bad value from selecting the whole table.
 */
$wpss_notif_per_page = (int) apply_filters( 'wpss_notifications_per_page', 20 );
$wpss_notif_per_page = max( 1, min( 200, $wpss_notif_per_page ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param.
$wpss_notif_page = isset( $_GET['notifications_page'] ) ? max( 1, absint( wp_unslash( $_GET['notifications_page'] ) ) ) : 1;

// Filter: unread only, and/or one type. Read-only GET params, whitelisted below.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$wpss_filter_unread = isset( $_GET['notifications_status'] ) && 'unread' === sanitize_key( wp_unslash( $_GET['notifications_status'] ) );
$wpss_filter_type   = isset( $_GET['notifications_type'] ) ? sanitize_key( wp_unslash( $_GET['notifications_type'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$wpss_filter_args = array(
	'unread_only' => $wpss_filter_unread,
	'type'        => $wpss_filter_type,
);
$wpss_filtered    = $wpss_filter_unread || '' !== $wpss_filter_type;
$wpss_notif_total = wpss_count_user_notifications( $wpss_notif_user, $wpss_filter_args );
$wpss_notif_pages = $wpss_notif_per_page > 0 ? (int) ceil( $wpss_notif_total / $wpss_notif_per_page ) : 1;

// A deep link past the end lands on the last real page rather than showing an
// empty list that looks like the notifications have gone.
if ( $wpss_notif_pages > 0 && $wpss_notif_page > $wpss_notif_pages ) {
	$wpss_notif_page = $wpss_notif_pages;
}

$wpss_notifications = wpss_get_user_notifications(
	$wpss_notif_user,
	$wpss_filter_args + array(
		'limit'  => $wpss_notif_per_page,
		'offset' => ( $wpss_notif_page - 1 ) * $wpss_notif_per_page,
	)
);

// Icons and labels come from the one registry, wpss_notification_types().
$wpss_notif_types = wpss_get_user_notification_types( $wpss_notif_user );

/*
 * Count unread across EVERY page, not just the rows on screen.
 *
 * "Mark all as read" marks every page - its handler has never been scoped to
 * the current one - but its visibility was computed from the loaded rows. So a
 * member whose first page happened to be all read lost the control while
 * unread notifications sat on page 3, and the only way to get it back was to
 * page forward until one showed up.
 */
$wpss_has_unread = wpss_count_user_notifications( $wpss_notif_user, array( 'unread_only' => true ) ) > 0;
wpss_enqueue_notifications_style();
?>
<div class="wpss-notif-center" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpss_notification_nonce' ) ); ?>">
	<div class="wpss-notif-center__head">
		<?php if ( $wpss_show_heading ) : ?>
			<h2><?php esc_html_e( 'Notifications', 'wp-sell-services' ); ?></h2>
		<?php endif; ?>
		<?php if ( $wpss_has_unread ) : ?>
			<button type="button" class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-notif-mark-all">
				<?php esc_html_e( 'Mark all as read', 'wp-sell-services' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $wpss_notif_total > 0 || $wpss_filtered ) : ?>
		<?php
		// Carry every other query var (the dashboard section, a page id) through the GET form.
		$wpss_form_keep = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( (array) $_GET as $wpss_k => $wpss_v ) {
			if ( is_string( $wpss_v ) && ! in_array( $wpss_k, array( 'notifications_status', 'notifications_type', 'notifications_page' ), true ) ) {
				$wpss_form_keep[ sanitize_key( (string) $wpss_k ) ] = sanitize_text_field( wp_unslash( $wpss_v ) );
			}
		}
		?>
		<form class="wpss-notif-filter" method="get" action="<?php echo esc_url( remove_query_arg( array_keys( (array) $_GET ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
			<?php foreach ( $wpss_form_keep as $wpss_k => $wpss_v ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $wpss_k ); ?>" value="<?php echo esc_attr( $wpss_v ); ?>">
			<?php endforeach; ?>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Show', 'wp-sell-services' ); ?></span>
				<select name="notifications_status" class="wpss-input">
					<option value=""><?php esc_html_e( 'All notifications', 'wp-sell-services' ); ?></option>
					<option value="unread" <?php selected( $wpss_filter_unread ); ?>><?php esc_html_e( 'Unread only', 'wp-sell-services' ); ?></option>
				</select>
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Type', 'wp-sell-services' ); ?></span>
				<select name="notifications_type" class="wpss-input">
					<option value=""><?php esc_html_e( 'All types', 'wp-sell-services' ); ?></option>
					<?php foreach ( $wpss_notif_types as $wpss_type ) : ?>
						<option value="<?php echo esc_attr( $wpss_type ); ?>" <?php selected( $wpss_filter_type, $wpss_type ); ?>><?php echo esc_html( wpss_notification_type_label( $wpss_type ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit" class="wpss-btn wpss-btn--outline wpss-btn--sm"><?php esc_html_e( 'Filter', 'wp-sell-services' ); ?></button>
			<?php if ( $wpss_filtered ) : ?>
				<a class="wpss-notif-filter__reset" href="<?php echo esc_url( add_query_arg( $wpss_form_keep, remove_query_arg( array_keys( (array) $_GET ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"><?php esc_html_e( 'Clear filter', 'wp-sell-services' ); ?></a>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php if ( empty( $wpss_notifications ) ) : ?>
		<div class="wpss-dashboard__empty">
			<p>
				<?php
				echo $wpss_filtered
					? esc_html__( 'No notifications match this filter.', 'wp-sell-services' )
					: esc_html__( 'You have no notifications yet.', 'wp-sell-services' );
				?>
			</p>
		</div>
	<?php else : ?>
		<ul class="wpss-notif-list">
			<?php
			foreach ( $wpss_notifications as $wpss_n ) :
				$wpss_unread = empty( $wpss_n->is_read );
				$wpss_icon   = wpss_notification_type_icon( (string) $wpss_n->type );
				$wpss_data   = is_string( $wpss_n->data ?? null ) ? json_decode( $wpss_n->data, true ) : (array) ( $wpss_n->data ?? array() );
				$wpss_url    = wpss_get_notification_url( is_array( $wpss_data ) ? $wpss_data : array(), $wpss_n->action_url ?? null );
				$wpss_when   = ! empty( $wpss_n->created_at )
					? sprintf(
						/* translators: %s: human-readable time difference, e.g. "2 hours" */
						esc_html__( '%s ago', 'wp-sell-services' ),
						human_time_diff( strtotime( $wpss_n->created_at ), time() )
					)
					: '';
				?>
				<li class="wpss-notif-row<?php echo $wpss_unread ? ' wpss-notif-row--unread' : ''; ?>" data-id="<?php echo esc_attr( (string) $wpss_n->id ); ?>">
					<span class="wpss-notif-row__icon" aria-hidden="true"><i data-lucide="<?php echo esc_attr( $wpss_icon ); ?>"></i></span>
					<span class="wpss-notif-row__body">
						<?php if ( ! empty( $wpss_n->title ) ) : ?>
							<span class="wpss-notif-row__title">
								<?php
								// action_url has been a column, a model property
								// and a REST field since the first release with
								// nothing writing it, so every notification was
								// a dead end -- it told you something happened
								// and left you to go find it. Linked only when
								// the row actually carries one, so older rows
								// render exactly as before.
								if ( '' !== $wpss_url ) :
									?>
									<a class="wpss-notif-row__link" href="<?php echo esc_url( $wpss_url ); ?>"><?php echo esc_html( $wpss_n->title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $wpss_n->title ); ?>
								<?php endif; ?>
							</span>
						<?php endif; ?>
						<?php if ( ! empty( $wpss_n->message ) ) : ?>
							<?php
							// Plain text with real line breaks since 1.2.3; older rows are
							// HTML. Breaks and block ends become newlines before the tags go,
							// or every field ran into the next.
							$wpss_msg = preg_replace( '#<br\s*/?>|</(p|div|li)>#i', "\n", (string) $wpss_n->message );
							$wpss_msg = preg_replace( "/\n{3,}/", "\n\n", trim( wp_strip_all_tags( $wpss_msg ) ) );
							?>
							<span class="wpss-notif-row__message"><?php echo nl2br( esc_html( $wpss_msg ) ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $wpss_when ) : ?>
							<span class="wpss-notif-row__time"><?php echo esc_html( $wpss_when ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( $wpss_unread ) : ?>
						<button type="button" class="wpss-notif-row__mark" aria-label="<?php esc_attr_e( 'Mark as read', 'wp-sell-services' ); ?>">
							<i data-lucide="check" aria-hidden="true"></i>
						</button>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $wpss_notif_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Notification pages', 'wp-sell-services' ); ?>">
				<?php
				$wpss_notif_page_url = static function ( int $page ): string {
					return $page > 1 ? add_query_arg( 'notifications_page', $page ) : remove_query_arg( 'notifications_page' );
				};
	?>
				<?php if ( $wpss_notif_page > 1 ) : ?>
					<a href="<?php echo esc_url( $wpss_notif_page_url( $wpss_notif_page - 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--prev">
						<i data-lucide="chevron-left" class="wpss-icon" aria-hidden="true"></i>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<span class="wpss-pagination__current">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'wp-sell-services' ),
						(int) $wpss_notif_page,
						(int) $wpss_notif_pages
					);
					?>
				</span>
				<?php if ( $wpss_notif_page < $wpss_notif_pages ) : ?>
					<a href="<?php echo esc_url( $wpss_notif_page_url( $wpss_notif_page + 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--next">
						<?php esc_html_e( 'Next', 'wp-sell-services' ); ?>
						<i data-lucide="chevron-right" class="wpss-icon" aria-hidden="true"></i>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>

<script>
( function () {
	var root = document.querySelector( '.wpss-notif-center' );
	if ( ! root || root.dataset.wpssNotifBound ) {
		return;
	}
	root.dataset.wpssNotifBound = '1';

	var ajaxUrl = root.getAttribute( 'data-ajax-url' );
	var nonce   = root.getAttribute( 'data-nonce' );

	function post( action, extra ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', nonce );
		if ( extra ) {
			Object.keys( extra ).forEach( function ( k ) {
				body.append( k, extra[ k ] );
			} );
		}
		// keepalive: a row click navigates away while its mark-read is in flight.
		return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body, keepalive: true } );
	}

	// Keep the sidebar badge (server-rendered) in step with what was just read.
	function adjustBadge( by ) {
		document.querySelectorAll( '[data-wpss-notification-count]' ).forEach( function ( badge ) {
			var n = by === null ? 0 : Math.max( 0, ( parseInt( badge.textContent, 10 ) || 0 ) + by );
			badge.textContent = String( n );
			if ( ! n ) { badge.setAttribute( 'hidden', '' ); }
		} );
	}

	root.addEventListener( 'click', function ( e ) {
		var allBtn = e.target.closest( '.wpss-notif-mark-all' );
		if ( allBtn ) {
			post( 'wpss_mark_all_notifications_read' ).then( function () {
				root.querySelectorAll( '.wpss-notif-row--unread' ).forEach( function ( li ) {
					li.classList.remove( 'wpss-notif-row--unread' );
					var m = li.querySelector( '.wpss-notif-row__mark' );
					if ( m ) { m.remove(); }
				} );
				allBtn.remove();
				adjustBadge( null );
			} );
			return;
		}
		// The whole row opens what it is about, and opening it reads it.
		var row  = e.target.closest( '.wpss-notif-row' );
		var link = row ? row.querySelector( '.wpss-notif-row__link' ) : null;
		if ( link && ! e.target.closest( '.wpss-notif-row__mark' ) ) {
			if ( row.classList.contains( 'wpss-notif-row--unread' ) ) {
				post( 'wpss_mark_notification_read', { notification_id: row.getAttribute( 'data-id' ) } );
				adjustBadge( -1 );
			}
			if ( ! e.target.closest( 'a' ) ) {
				window.location.href = link.href;
			}
			return;
		}
		var oneBtn = e.target.closest( '.wpss-notif-row__mark' );
		if ( oneBtn ) {
			var li = oneBtn.closest( '.wpss-notif-row' );
			var id = li ? li.getAttribute( 'data-id' ) : 0;
			post( 'wpss_mark_notification_read', { notification_id: id } ).then( function () {
				if ( li ) { li.classList.remove( 'wpss-notif-row--unread' ); }
				oneBtn.remove();
				adjustBadge( -1 );
			} );
		}
	} );
}() );
</script>
