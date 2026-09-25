<?php
/**
 * One message or piece of evidence in a dispute thread.
 *
 * Rendered by the thread (templates/partials/dispute-thread.php) and by the
 * AJAX reply, which used to build its own copy of this markup.
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var array $wpss_item        One row from DisputeService::get_evidence().
 * @var int   $wpss_viewer_id   The user looking at the thread.
 * @var int   $wpss_customer_id The order's buyer.
 * @var int   $wpss_vendor_id   The order's vendor.
 */

defined( 'ABSPATH' ) || exit;

$ev_user_id = (int) ( $wpss_item['user_id'] ?? 0 );
// Someone who is neither party is the site's mediator: say "Admin",
// not a personal name the members do not know (Basecamp 10337171525).
$ev_is_party = in_array( $ev_user_id, array( (int) $wpss_customer_id, (int) $wpss_vendor_id ), true );
if ( ! $ev_user_id ) {
	$ev_name = __( 'System', 'wp-sell-services' );
} elseif ( ! $ev_is_party && user_can( $ev_user_id, 'manage_options' ) ) {
	$ev_name = __( 'Admin', 'wp-sell-services' );
} else {
	$ev_name = wpss_get_member_display_name( $ev_user_id );
}
$ev_own     = $ev_user_id === (int) $wpss_viewer_id;
$ev_type    = (string) ( $wpss_item['type'] ?? 'text' );
$ev_content = (string) ( $wpss_item['content'] ?? '' );
$ev_desc    = (string) ( $wpss_item['description'] ?? '' );

/*
 * The stored filename, not basename() of the link.
 *
 * Since the 1.7.1 private-file rework the content is a
 * permission-gated admin-post.php URL with the file in a
 * query string, and basename() does not strip a query
 * string - so the label printed the endpoint and its
 * arguments instead of a name. get_evidence() already
 * returns the real name on the attachment record.
 */
$ev_attach = isset( $wpss_item['attachments'][0] ) && is_array( $wpss_item['attachments'][0] ) ? $wpss_item['attachments'][0] : array();
$ev_file   = wpss_format_attachment_name( (string) ( $ev_attach['name'] ?? '' ) );

if ( '' === $ev_file ) {
	$ev_path = (string) wp_parse_url( $ev_content, PHP_URL_PATH );
	$ev_file = '' !== $ev_path ? basename( $ev_path ) : __( 'Attachment', 'wp-sell-services' );
}
$ev_when = ! empty( $wpss_item['created_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wpss_item['created_at'] ) : '';
?>
<div class="wpss-evidence-item <?php echo $ev_own ? 'wpss-evidence-own' : 'wpss-evidence-other'; ?>">
	<div class="wpss-evidence-bubble">
		<span class="wpss-evidence-author"><strong><?php echo esc_html( $ev_name ); ?></strong></span>
		<div class="wpss-evidence-content">
			<?php
			/*
			 * Anything that is not a media type renders as prose. This
			 * tested `'text' === $ev_type`, an allow-list of exactly one
			 * value, so the opening statement - typed `opening_statement`
			 * so the panel can tell it from a reply - matched no branch
			 * and drew an empty bubble with just a name and a time. Any
			 * future textual type would have failed the same silent way.
			 */
			?>
			<?php if ( ! in_array( $ev_type, array( 'image', 'file', 'link' ), true ) && '' !== $ev_content ) : ?>
				<div class="wpss-evidence-text"><?php echo wp_kses_post( nl2br( esc_html( $ev_content ) ) ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $ev_desc && 'text' !== $ev_type ) : ?>
				<div class="wpss-evidence-text"><?php echo wp_kses_post( nl2br( esc_html( $ev_desc ) ) ); ?></div>
			<?php endif; ?>
			<?php if ( 'image' === $ev_type && '' !== $ev_content ) : ?>
				<div class="wpss-evidence-image">
					<a href="<?php echo esc_url( $ev_content ); ?>" target="_blank" rel="noopener noreferrer">
						<img src="<?php echo esc_url( $ev_content ); ?>" alt="<?php esc_attr_e( 'Evidence image', 'wp-sell-services' ); ?>">
					</a>
				</div>
			<?php elseif ( in_array( $ev_type, array( 'file', 'link' ), true ) && '' !== $ev_content ) : ?>
				<div class="wpss-evidence-file">
					<a href="<?php echo esc_url( $ev_content ); ?>" target="_blank" rel="noopener noreferrer" class="wpss-file-link">
						<i data-lucide="file" class="wpss-icon" aria-hidden="true"></i>
						<span><?php echo esc_html( $ev_file ); ?></span>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $ev_when ) : ?>
			<span class="wpss-evidence-time"><?php echo esc_html( $ev_when ); ?></span>
		<?php endif; ?>
	</div>
</div>
