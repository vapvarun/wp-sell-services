/**
 * Service Moderation queue.
 *
 * Approve / reject a single service, bulk-moderate a selection, and the
 * select-all checkbox. Extracted from an inline <script> in
 * ServiceModerationPage.php (ux-audit F2).
 *
 * Config arrives via wp_localize_script as window.wpssModeration. The old
 * inline block hand-rolled that object because "wp_add_inline_script runs in
 * the footer, after this script" — true for add_inline_script, but
 * wp_localize_script prints BEFORE the handle it attaches to, which is exactly
 * what this needs.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

( function( $ ) {
	'use strict';

	function wpssAdminNotice(msg, type) {
		type = type || 'error';
		var cls = type === 'success' ? 'notice-success' : 'notice-error';
		var $notice = $('<div class="notice ' + cls + ' is-dismissible"><p>' + msg + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
		$('.wrap h1, .wrap h2').first().after($notice);
		$notice.find('.notice-dismiss').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
		setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 6000);
	}

	jQuery(function($) {
		var wpssModeration = window.wpssModeration;

		// Approve single service.
		$(document).on('click', '.wpss-approve-service', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var serviceId = $btn.data('service');

			if (!confirm(wpssModeration.i18n.confirmApprove)) {
				return;
			}

			$btn.text(wpssModeration.i18n.loading);

			$.post(wpssModeration.ajaxUrl, {
				action: 'wpss_approve_service',
				service_id: serviceId,
				nonce: wpssModeration.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					wpssAdminNotice(response.data.message || wpssModeration.i18n.error, 'error');
					$btn.text('Approve');
				}
			}).fail(function() {
				wpssAdminNotice(wpssModeration.i18n.error, 'error');
				$btn.text('Approve');
			});
		});

		// Reject single service.
		$(document).on('click', '.wpss-reject-service', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var serviceId = $btn.data('service');

			var reason = prompt(wpssModeration.i18n.rejectReason);
			if (reason === null) {
				return;
			}

			$btn.text(wpssModeration.i18n.loading);

			$.post(wpssModeration.ajaxUrl, {
				action: 'wpss_reject_service',
				service_id: serviceId,
				reason: reason,
				nonce: wpssModeration.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					wpssAdminNotice(response.data.message || wpssModeration.i18n.error, 'error');
					$btn.text('Reject');
				}
			}).fail(function() {
				wpssAdminNotice(wpssModeration.i18n.error, 'error');
				$btn.text('Reject');
			});
		});

		// Bulk actions.
		$('#doaction').on('click', function() {
			var action = $('#bulk-action-selector').val();
			if (!action) {
				return;
			}

			var serviceIds = [];
			$('input[name="service_ids[]"]:checked').each(function() {
				serviceIds.push($(this).val());
			});

			if (serviceIds.length === 0) {
				wpssAdminNotice(wpssModeration.i18n.selectServices, 'error');
				return;
			}

			if (!confirm(wpssModeration.i18n.confirmBulk)) {
				return;
			}

			var reason = '';
			if (action === 'reject') {
				reason = prompt(wpssModeration.i18n.rejectReason);
				if (reason === null) {
					return;
				}
			}

			$.post(wpssModeration.ajaxUrl, {
				action: 'wpss_bulk_moderate_services',
				bulk_action: action,
				service_ids: serviceIds,
				reason: reason,
				nonce: wpssModeration.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					wpssAdminNotice(response.data.message || wpssModeration.i18n.error, 'error');
				}
			});
		});

		// Select all checkboxes.
		$('#cb-select-all-1, #cb-select-all-2').on('change', function() {
			$('input[name="service_ids[]"]').prop('checked', $(this).prop('checked'));
		});
	});

}( jQuery ) );
