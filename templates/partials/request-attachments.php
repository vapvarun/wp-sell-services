<?php
/**
 * Buyer request attachments field: pick files, upload each at once, keep IDs.
 *
 * One field for posting and for editing a request. Editing had no attachment
 * control at all, so a buyer could not add or remove a file after posting
 * (Basecamp 10337217098).
 *
 * The form carries `attachments_present=1` plus one `attachments[]` per kept
 * file, so the update handler can tell "removed them all" from "not sent".
 *
 * @package WPSellServices
 *
 * @var array<int> $wpss_attachment_ids Attachment IDs already on the request (edit). Optional.
 */

defined( 'ABSPATH' ) || exit;

$wpss_attachment_ids = array_filter( array_map( 'absint', (array) ( $wpss_attachment_ids ?? array() ) ) );
$wpss_file_types     = array_filter( array_map( 'trim', explode( ',', strtolower( (string) wpss_get_option( 'advanced', 'allowed_file_types' ) ) ) ) );
$wpss_file_max       = (int) wpss_get_option( 'advanced', 'max_file_size' );
?>
<div class="wpss-form-row">
	<label for="request_attachments"><?php esc_html_e( 'Attachments', 'wp-sell-services' ); ?></label>
	<input type="hidden" name="attachments_present" value="1">
	<input
		type="file"
		id="request_attachments"
		class="wpss-input"
		multiple
		accept="<?php echo esc_attr( implode( ',', array_map( static fn( $ext ) => '.' . $ext, $wpss_file_types ) ) ); ?>"
	>
	<ul class="wpss-request-files" aria-live="polite">
		<?php foreach ( $wpss_attachment_ids as $wpss_attachment_id ) : ?>
			<li class="wpss-request-files__item">
				<span class="wpss-request-files__name"><?php echo esc_html( basename( (string) get_attached_file( $wpss_attachment_id ) ) ); ?></span>
				<input type="hidden" name="attachments[]" value="<?php echo esc_attr( (string) $wpss_attachment_id ); ?>">
				<button type="button" class="wpss-btn wpss-btn--ghost wpss-btn--sm wpss-request-files__remove"><?php esc_html_e( 'Remove', 'wp-sell-services' ); ?></button>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="wpss-form-hint">
		<?php
		printf(
			/* translators: %s: maximum file size */
			esc_html__( 'A brief, mockups or reference images. Sellers see them on your request. Up to %s each.', 'wp-sell-services' ),
			esc_html( size_format( $wpss_file_max * MB_IN_BYTES ) )
		);
		?>
	</p>
</div>
<script>
(function($) {
	'use strict';

	// Each file goes to the public media route as soon as it is picked; the
	// form then carries only the attachment IDs.
	$('#request_attachments').on('change', function() {
		var $list = $(this).siblings('.wpss-request-files');
		Array.prototype.forEach.call(this.files, function(file) {
			var $item = $('<li class="wpss-request-files__item wpss-request-files__item--uploading"></li>')
				.append($('<span class="wpss-request-files__name"></span>').text(file.name))
				.append($('<span class="wpss-request-files__state"></span>').text('<?php echo esc_js( __( 'Uploading...', 'wp-sell-services' ) ); ?>'))
				.appendTo($list);
			var body = new FormData();
			body.append('file', file);
			body.append('context', 'request');
			$.ajax({
				url: wpssUnifiedDashboard.restUrl + 'media',
				type: 'POST',
				data: body,
				processData: false,
				contentType: false,
				beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce); }
			}).done(function(media) {
				$item.removeClass('wpss-request-files__item--uploading');
				$item.find('.wpss-request-files__state').remove();
				$item.append($('<input type="hidden" name="attachments[]">').val(media.id));
				$item.append($('<button type="button" class="wpss-btn wpss-btn--ghost wpss-btn--sm wpss-request-files__remove"></button>').text('<?php echo esc_js( __( 'Remove', 'wp-sell-services' ) ); ?>'));
			}).fail(function(xhr) {
				$item.remove();
				var msg = (xhr.responseJSON && xhr.responseJSON.message) || '<?php echo esc_js( __( 'The file could not be uploaded.', 'wp-sell-services' ) ); ?>';
				if (window.wpssToast) {
					window.wpssToast(msg, 'error');
				} else if (window.wpssShowNotice) {
					window.wpssShowNotice(msg, 'error');
				}
			});
		});
		this.value = '';
	});

	$(document).on('click', '.wpss-request-files__remove', function() {
		$(this).closest('.wpss-request-files__item').remove();
	});
})(jQuery);
</script>
