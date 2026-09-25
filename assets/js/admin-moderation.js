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

	// An error stays until dismissed and is scrolled into view: it tells the
	// owner why nothing happened, so it must not vanish before they read it.
	function wpssAdminNotice(msg, type) {
		type = type || 'error';
		var cls = type === 'success' ? 'notice-success' : 'notice-error';
		var $notice = $('<div class="notice ' + cls + ' is-dismissible"><p></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
		$notice.find('p').text(msg);
		$('.wrap h1, .wrap h2').first().after($notice);
		$notice.find('.notice-dismiss').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
		if (type === 'success') {
			setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 6000);
		} else {
			$notice[0].scrollIntoView({ block: 'center' });
		}
	}

	// Why a row's action was refused, on the row the owner clicked, with the
	// way to fix it.
	function wpssRowNotice($btn, msg) {
		var $cell = $btn.closest('td');
		$cell.find('.wpss-row-notice').remove();
		var $notice = $('<div class="notice notice-error inline wpss-row-notice" role="alert"><p></p></div>');
		$notice.find('p').text(msg);
		if ($btn.data('edit')) {
			$notice.find('p').append(' ', $('<a>').attr('href', $btn.data('edit')).text(wpssModeration.i18n.editService || 'Edit service'));
		}
		$cell.append($notice);
	}

	jQuery(function($) {
		var wpssModeration = window.wpssModeration;

		// Approve single service.
		$(document).on('click', '.wpss-approve-service', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var serviceId = $btn.data('service');

			wpssConfirm(wpssModeration.i18n.confirmApprove).then(function(ok) {
				if (!ok) {
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
						wpssRowNotice($btn, response.data.message || wpssModeration.i18n.error);
						$btn.text('Approve');
					}
				}).fail(function() {
					wpssAdminNotice(wpssModeration.i18n.error, 'error');
					$btn.text('Approve');
				});
			});
		});

		// Reject single service.
		$(document).on('click', '.wpss-reject-service', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var serviceId = $btn.data('service');

			wpssConfirm(wpssModeration.i18n.confirmReject, {
				title: wpssModeration.i18n.rejectTitle,
				confirmText: wpssModeration.i18n.rejectConfirm,
				tone: 'danger',
				prompt: {
					label: wpssModeration.i18n.rejectReason,
					placeholder: wpssModeration.i18n.rejectPlaceholder,
					maxLength: 500
				}
			}).then(function(reason) {
				// false is cancel; '' is a deliberate empty reason, which this
				// flow has always allowed. Never test truthiness here.
				if (false === reason) {
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

			// One dialog for both shapes: rejecting also asks why, everything
			// else is a plain confirm. Same reason as the single-row action -
			// native confirm()/prompt() label their own buttons in the browser's
			// language, not the site's.
			var isReject = 'reject' === action;

			wpssConfirm(wpssModeration.i18n.confirmBulk, {
				title: isReject ? wpssModeration.i18n.rejectTitle : '',
				confirmText: isReject ? wpssModeration.i18n.rejectConfirm : '',
				tone: isReject ? 'danger' : '',
				prompt: isReject ? {
					label: wpssModeration.i18n.rejectReason,
					placeholder: wpssModeration.i18n.rejectPlaceholder,
					maxLength: 500
				} : null
			}).then(function(result) {
				if (false === result) {
					return;
				}

				$.post(wpssModeration.ajaxUrl, {
					action: 'wpss_bulk_moderate_services',
					bulk_action: action,
					service_ids: serviceIds,
					reason: isReject ? result : '',
					nonce: wpssModeration.nonce
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						wpssAdminNotice(response.data.message || wpssModeration.i18n.error, 'error');
					}
				});
			});
		});

		// Select all checkboxes.
		$('#cb-select-all-1, #cb-select-all-2').on('change', function() {
			$('input[name="service_ids[]"]').prop('checked', $(this).prop('checked'));
		});
	});

}( jQuery ) );
