<?php
/**
 * Dashboard Section: Notifications
 *
 * In-app notification centre for the unified dashboard. The Profile section
 * promised "in-app notifications still appear regardless of [email] settings",
 * but no dashboard surface rendered them. This wires the existing notification
 * store + mark-read AJAX handlers into a real member surface.
 *
 * Uses `wpss-notif-*` classes (NOT `wpss-notification`, which is the fixed-
 * position toast component — reusing it hid the list off-screen).
 *
 * @package WPSellServices\Templates
 * @since   1.2.2
 *
 * @var int           $user_id        Current user ID.
 * @var VendorService $vendor_service Vendor service instance.
 * @var bool          $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wpss_dashboard_section_before', 'notifications', $user_id );

$notifications = wpss_get_user_notifications( $user_id, array( 'limit' => 50 ) );

$notification_icons = array(
	'order_created'      => 'package',
	'order_status'       => 'refresh-cw',
	'new_message'        => 'message-circle',
	'delivery_submitted' => 'upload',
	'delivery_accepted'  => 'check-circle',
	'revision_requested' => 'rotate-ccw',
	'review_received'    => 'star',
	'dispute_opened'     => 'alert-triangle',
	'dispute_resolved'   => 'check',
);

$has_unread = false;
foreach ( $notifications as $wpss_n ) {
	if ( empty( $wpss_n->is_read ) ) {
		$has_unread = true;
		break;
	}
}
?>
<style>
.wpss-notif-center__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.wpss-notif-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.wpss-notif-row { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border: 1px solid var( --wpss-border, #e5e7eb ); border-radius: var( --wpss-radius, 8px ); background: var( --wpss-bg, #fff ); }
.wpss-notif-row--unread { background: var( --wpss-primary-light, #eef2ff ); border-color: var( --wpss-primary, #4f46e5 ); }
.wpss-notif-row__icon { flex: 0 0 auto; color: var( --wpss-primary, #4f46e5 ); display: inline-flex; }
.wpss-notif-row__body { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.wpss-notif-row__title { font-weight: 600; color: var( --wpss-text, #1f2937 ); }
.wpss-notif-row__message { color: var( --wpss-text-light, #6b7280 ); }
.wpss-notif-row__time { font-size: 12px; color: var( --wpss-text-muted, #6b7280 ); }
.wpss-notif-row__mark { flex: 0 0 auto; background: none; border: none; cursor: pointer; color: var( --wpss-text-light, #6b7280 ); padding: 4px; border-radius: 4px; }
.wpss-notif-row__mark:hover { color: var( --wpss-primary, #4f46e5 ); }
@media (max-width: 480px) { .wpss-notif-row { padding: 12px; } }
</style>
<div class="wpss-dashboard-section wpss-notif-center" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpss_notification_nonce' ) ); ?>">
	<div class="wpss-notif-center__head">
		<h2><?php esc_html_e( 'Notifications', 'wp-sell-services' ); ?></h2>
		<?php if ( $has_unread ) : ?>
			<button type="button" class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-notif-mark-all">
				<?php esc_html_e( 'Mark all as read', 'wp-sell-services' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( empty( $notifications ) ) : ?>
		<div class="wpss-dashboard__empty">
			<p><?php esc_html_e( 'You have no notifications yet.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<ul class="wpss-notif-list">
			<?php
			foreach ( $notifications as $wpss_n ) :
				$is_unread = empty( $wpss_n->is_read );
				$icon      = $notification_icons[ $wpss_n->type ] ?? 'bell';
				$when      = ! empty( $wpss_n->created_at )
					? sprintf(
						/* translators: %s: human-readable time difference, e.g. "2 hours" */
						esc_html__( '%s ago', 'wp-sell-services' ),
						human_time_diff( strtotime( $wpss_n->created_at ), time() )
					)
					: '';
				?>
				<li class="wpss-notif-row<?php echo $is_unread ? ' wpss-notif-row--unread' : ''; ?>" data-id="<?php echo esc_attr( (string) $wpss_n->id ); ?>">
					<span class="wpss-notif-row__icon" aria-hidden="true"><i data-lucide="<?php echo esc_attr( $icon ); ?>"></i></span>
					<span class="wpss-notif-row__body">
						<?php if ( ! empty( $wpss_n->title ) ) : ?>
							<span class="wpss-notif-row__title"><?php echo esc_html( $wpss_n->title ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $wpss_n->message ) ) : ?>
							<span class="wpss-notif-row__message"><?php echo esc_html( wp_strip_all_tags( (string) $wpss_n->message ) ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $when ) : ?>
							<span class="wpss-notif-row__time"><?php echo esc_html( $when ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( $is_unread ) : ?>
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
	if ( ! root ) {
		return;
	}
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
