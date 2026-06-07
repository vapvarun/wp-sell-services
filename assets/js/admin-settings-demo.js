/**
 * Settings page — Demo content import/delete functionality.
 *
 * Requires wpssSettingsDemo localized object.
 *
 * @package WPSellServices
 * @since   2.0.0
 */

( function( $ ) {
	'use strict';

	var l10n = window.wpssSettingsDemo || {};

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

	$( function() {
		// Import demo content.
		$( '.wpss-import-demo' ).on( 'click', function() {
			var $btn    = $( this );
			var $status = $btn.siblings( '.wpss-demo-status' );

			window.wpssAdminConfirm( l10n.confirmImport || 'Import demo content? This will create sample services, vendors, and categories.', function() {
				$btn.prop( 'disabled', true ).text( l10n.importing || 'Importing...' );
				$status.show().text( l10n.pleaseWait || 'Please wait, this may take a moment...' );

				$.ajax( {
					url: window.ajaxurl,
					type: 'POST',
					data: {
						action: 'wpss_import_demo_content',
						nonce: $btn.data( 'nonce' ),
					},
					success: function( response ) {
						if ( response.success ) {
							$status.css( 'color', '#00a32a' ).text( response.data.message || ( l10n.importSuccess || 'Demo content imported successfully!' ) );
							setTimeout( function() { location.reload(); }, 1500 );
						} else {
							$status.css( 'color', '#d63638' ).text( response.data.message || ( l10n.importFailed || 'Import failed.' ) );
							$btn.prop( 'disabled', false ).text( l10n.importBtn || 'Import Demo Content' );
						}
					},
					error: function() {
						$status.css( 'color', '#d63638' ).text( l10n.ajaxError || 'An error occurred. Please try again.' );
						$btn.prop( 'disabled', false ).text( l10n.importBtn || 'Import Demo Content' );
					},
				} );
			} );
		} );

		// Delete demo content.
		$( '.wpss-delete-demo' ).on( 'click', function() {
			var $btn    = $( this );
			var $status = $btn.siblings( '.wpss-demo-status' );

			window.wpssAdminConfirm( l10n.confirmDelete || 'Delete all demo content? This will permanently remove demo services, vendors, and empty categories.', function() {
				$btn.prop( 'disabled', true ).text( l10n.deleting || 'Deleting...' );
				$status.show().text( l10n.removing || 'Removing demo content...' );

				$.ajax( {
					url: window.ajaxurl,
					type: 'POST',
					data: {
						action: 'wpss_delete_demo_content',
						nonce: $btn.data( 'nonce' ),
					},
					success: function( response ) {
						if ( response.success ) {
							$status.css( 'color', '#00a32a' ).text( response.data.message || ( l10n.deleteSuccess || 'Demo content deleted successfully!' ) );
							setTimeout( function() { location.reload(); }, 1500 );
						} else {
							$status.css( 'color', '#d63638' ).text( response.data.message || ( l10n.deleteFailed || 'Deletion failed.' ) );
							$btn.prop( 'disabled', false ).text( l10n.deleteBtn || 'Delete Demo Content' );
						}
					},
					error: function() {
						$status.css( 'color', '#d63638' ).text( l10n.ajaxError || 'An error occurred. Please try again.' );
						$btn.prop( 'disabled', false ).text( l10n.deleteBtn || 'Delete Demo Content' );
					},
				} );
			}, { tone: 'danger' } );
		} );
	} );
} )( jQuery );
