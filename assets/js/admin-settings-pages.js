/**
 * Settings page — Pages tab functionality.
 *
 * Handles "Create Page" AJAX and dropdown change for view links.
 * Requires wpssSettingsPages localized object.
 *
 * @package WPSellServices
 * @since   2.0.0
 */

( function( $ ) {
	'use strict';

	var l10n = window.wpssSettingsPages || {};

	/**
	 * Lightweight admin confirm dialog built on the shipped .wpss-modal /
	 * .wpss-btn primitives (admin.css). Replaces the native confirm() so the
	 * settings UI stays on the design system. Defined idempotently so multiple
	 * settings scripts share a single implementation.
	 *
	 * @param {string}   message   Confirmation message.
	 * @param {Function} onConfirm Callback invoked when the user confirms.
	 * @param {Object}   options   Optional: confirmText, cancelText, tone.
	 */
	window.wpssAdminConfirm = window.wpssAdminConfirm || function( message, onConfirm, options ) {
		options = options || {};
		var confirmText = options.confirmText || l10n.confirmBtn || 'Confirm';
		var cancelText  = options.cancelText || l10n.cancelBtn || 'Cancel';
		var okClass     = 'danger' === options.tone ? 'wpss-btn--danger' : 'wpss-btn--primary';

		$( '#wpss-admin-confirm' ).remove();

		var $modal = $(
			'<div id="wpss-admin-confirm" class="wpss-modal wpss-modal-open" role="dialog" aria-modal="true">' +
				'<div class="wpss-modal-content" style="max-width:420px;margin:12% auto;padding:24px;">' +
					'<p class="wpss-admin-confirm__msg" style="margin:0 0 20px;font-size:14px;line-height:1.5;"></p>' +
					'<div style="display:flex;gap:10px;justify-content:flex-end;">' +
						'<button type="button" class="wpss-btn wpss-admin-confirm__cancel"></button>' +
						'<button type="button" class="wpss-btn ' + okClass + ' wpss-admin-confirm__ok"></button>' +
					'</div>' +
				'</div>' +
			'</div>'
		);

		$modal.find( '.wpss-admin-confirm__msg' ).text( message );
		$modal.find( '.wpss-admin-confirm__cancel' ).text( cancelText );
		$modal.find( '.wpss-admin-confirm__ok' ).text( confirmText );

		$( 'body' ).append( $modal );
		$modal.find( '.wpss-admin-confirm__ok' ).trigger( 'focus' );

		function close() {
			$modal.remove();
			$( document ).off( 'keydown.wpssAdminConfirm' );
		}

		$modal.find( '.wpss-admin-confirm__ok' ).on( 'click', function() {
			close();
			if ( onConfirm ) {
				onConfirm();
			}
		} );
		$modal.on( 'click', function( e ) {
			if ( e.target === $modal[0] ) {
				close();
			}
		} );
		$modal.find( '.wpss-admin-confirm__cancel' ).on( 'click', close );
		$( document ).on( 'keydown.wpssAdminConfirm', function( e ) {
			if ( 27 === e.keyCode ) {
				close();
			}
		} );
	};

	/**
	 * Show an admin notice.
	 *
	 * @param {string} msg  Message text.
	 * @param {string} type 'success' or 'error'.
	 */
	function adminNotice( msg, type ) {
		if ( window.wpssToast ) {
			wpssToast( msg, type || 'error' );
			return;
		}
		type = type || 'error';
		var cls     = type === 'success' ? 'notice-success' : 'notice-error';
		var $notice = $( '<div class="notice ' + cls + ' is-dismissible"><p>' + $( '<span>' ).text( msg ).html() + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>' );
		$( '.wrap h1, .wrap h2' ).first().after( $notice );
		$notice.find( '.notice-dismiss' ).on( 'click', function() {
			$notice.fadeOut( 200, function() { $notice.remove(); } );
		} );
		setTimeout( function() {
			$notice.fadeOut( 400, function() { $notice.remove(); } );
		}, 6000 );
	}

	$( function() {
		$( '.wpss-create-page' ).on( 'click', function() {
			var $btn   = $( this );
			var field  = $btn.data( 'field' );
			var title  = $btn.data( 'title' );
			var $wrap  = $btn.closest( '.wpss-page-select-wrap' );
			var $select = $wrap.find( 'select' );
			var $viewBtn = $wrap.find( '.wpss-view-page' );

			if ( ! title ) {
				adminNotice( l10n.noTitle || 'Page title not defined.', 'error' );
				return;
			}

			window.wpssAdminConfirm( ( l10n.confirmCreate || 'Create a new page titled' ) + ' "' + title + '"?', function() {
				$btn.addClass( 'creating' ).text( l10n.creating || 'Creating...' );

				$.ajax( {
					url: l10n.ajaxUrl || window.ajaxurl,
					type: 'POST',
					data: {
						action: 'wpss_create_page',
						nonce: l10n.nonce,
						field: field,
						title: title,
					},
					success: function( response ) {
						if ( response.success ) {
							var isExisting = response.data.existing || false;
							var successMsg = isExisting
								? ( l10n.existingLinked || 'Existing Page Linked!' )
								: ( l10n.pageCreated || 'Page Created!' );

							if ( $select.find( 'option[value="' + response.data.page_id + '"]' ).length === 0 ) {
								$select.append( '<option value="' + response.data.page_id + '">' + $( '<span>' ).text( response.data.title ).html() + '</option>' );
							}
							$select.val( response.data.page_id );
							$viewBtn.attr( 'href', response.data.view_url ).show();
							$btn.removeClass( 'creating' ).text( successMsg ).addClass( 'button-primary' );

							setTimeout( function() {
								$btn.removeClass( 'button-primary' ).text( l10n.createPage || 'Create Page' );
							}, 2000 );
						} else {
							adminNotice( response.data.message || ( l10n.createFailed || 'Failed to create page.' ), 'error' );
							$btn.removeClass( 'creating' ).text( l10n.createPage || 'Create Page' );
						}
					},
					error: function() {
						adminNotice( l10n.ajaxError || 'An error occurred. Please try again.', 'error' );
						$btn.removeClass( 'creating' ).text( l10n.createPage || 'Create Page' );
					},
				} );
			} );
		} );

		// Update view link when dropdown changes.
		$( '.wpss-page-dropdown' ).on( 'change', function() {
			var $select = $( this );
			var pageId  = $select.val();
			var $viewBtn = $select.closest( '.wpss-page-select-wrap' ).find( '.wpss-view-page' );

			if ( pageId ) {
				$viewBtn.attr( 'href', ( l10n.homeUrl || '/' ) + '?page_id=' + pageId ).show();
			} else {
				$viewBtn.hide();
			}
		} );
	} );
} )( jQuery );
