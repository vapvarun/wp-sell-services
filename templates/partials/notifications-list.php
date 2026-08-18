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

$wpss_notifications = wpss_get_user_notifications( $wpss_notif_user, array( 'limit' => 50 ) );

// Keyed on the type strings actually stored in wpss_notifications.
//
// Notification::get_icon() carries a second, larger map keyed on the model's
// TYPE_* constants — but most of those strings ('order_new', 'message_new',
// 'order_delivered') are never written by anything, while the live rows use
// 'order_created', 'new_message', 'delivery_submitted'. The two vocabularies
// have drifted, so this map is the one that matches reality. Unifying them
// would change icons across every existing notification and wants its own
// card; anything unmapped falls back to 'bell'.
$wpss_notif_icons = array(
	'order_created'      => 'package',
	'order_status'       => 'refresh-cw',
	'new_message'        => 'message-circle',
	'delivery_submitted' => 'upload',
	'delivery_accepted'  => 'check-circle',
	'revision_requested' => 'rotate-ccw',
	'review_received'    => 'star',
	'dispute_opened'     => 'alert-triangle',
	'dispute_resolved'   => 'check',
	'proposal_received'  => 'briefcase',
	'proposal_accepted'  => 'thumbs-up',
	'proposal_rejected'  => 'thumbs-down',
);

$wpss_has_unread = false;
foreach ( $wpss_notifications as $wpss_n ) {
	if ( empty( $wpss_n->is_read ) ) {
		$wpss_has_unread = true;
		break;
	}
}
?>
<style>
.wpss-notif-center__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.wpss-notif-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.wpss-notif-row { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border: 1px solid var( --wpss-border, #e5e7eb ); border-radius: var( --wpss-radius, 8px ); background: var( --wpss-bg, #fff ); }
.wpss-notif-row--unread { background: var( --wpss-primary-light, #eef2ff ); border-color: var( --wpss-primary, #4f46e5 ); }
/* Icon, title and the mark-read control share ONE first-line band so they sit
	on the same optical line. The icon is a replaced SVG whose box does not match
	the title's line box, so flex-start alone left it floating above the text on
	every multi-line row. Sizing the icon to the title's line-height and centring
	the glyph inside it is what actually aligns them. */
.wpss-notif-row__icon { flex: 0 0 auto; color: var( --wpss-primary, #4f46e5 ); display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 24px; }
.wpss-notif-row__icon svg { width: 18px; height: 18px; }
.wpss-notif-row__body { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.wpss-notif-row__title { line-height: 24px; }
.wpss-notif-row__message { line-height: 1.5; }
.wpss-notif-row__title { font-weight: 600; color: var( --wpss-text, #1f2937 ); }
.wpss-notif-row__message { color: var( --wpss-text-light, #6b7280 ); }
/* Themes routinely restyle bare <a>; pin the states so a linked notification
	title still reads as the heading it is, in dark mode too. */
.wpss-notif-row__link,
.wpss-notif-row__link:visited { color: inherit; text-decoration: none; }
.wpss-notif-row__link:hover,
.wpss-notif-row__link:focus-visible { color: var( --wpss-primary, #4f46e5 ); text-decoration: underline; }
.wpss-notif-row__time { font-size: 12px; color: var( --wpss-text-muted, #6b7280 ); }
.wpss-notif-row__mark { flex: 0 0 auto; background: none; border: none; cursor: pointer; color: var( --wpss-text-light, #6b7280 ); padding: 0; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; }
.wpss-notif-row__mark svg { width: 18px; height: 18px; }
.wpss-notif-row__mark:hover { color: var( --wpss-primary, #4f46e5 ); }
@media (max-width: 480px) { .wpss-notif-row { padding: 12px; } }
</style>
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

	<?php if ( empty( $wpss_notifications ) ) : ?>
		<div class="wpss-dashboard__empty">
			<p><?php esc_html_e( 'You have no notifications yet.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<ul class="wpss-notif-list">
			<?php
			foreach ( $wpss_notifications as $wpss_n ) :
				$wpss_unread = empty( $wpss_n->is_read );
				$wpss_icon   = $wpss_notif_icons[ $wpss_n->type ] ?? 'bell';
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
								if ( ! empty( $wpss_n->action_url ) ) :
									?>
									<a class="wpss-notif-row__link" href="<?php echo esc_url( (string) $wpss_n->action_url ); ?>"><?php echo esc_html( $wpss_n->title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $wpss_n->title ); ?>
								<?php endif; ?>
							</span>
						<?php endif; ?>
						<?php if ( ! empty( $wpss_n->message ) ) : ?>
							<span class="wpss-notif-row__message"><?php echo esc_html( wp_strip_all_tags( (string) $wpss_n->message ) ); ?></span>
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
		return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
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
			} );
			return;
		}
		var oneBtn = e.target.closest( '.wpss-notif-row__mark' );
		if ( oneBtn ) {
			var li = oneBtn.closest( '.wpss-notif-row' );
			var id = li ? li.getAttribute( 'data-id' ) : 0;
			post( 'wpss_mark_notification_read', { notification_id: id } ).then( function () {
				if ( li ) { li.classList.remove( 'wpss-notif-row--unread' ); }
				oneBtn.remove();
			} );
		}
	} );
}() );
</script>
