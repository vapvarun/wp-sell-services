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

	$( function() {
		// Import demo content.
		$( '.wpss-import-demo' ).on( 'click', function() {
			var $btn    = $( this );
			var $status = $btn.siblings( '.wpss-demo-status' );

			if ( ! confirm( l10n.confirmImport || 'Import demo content? This will create sample services, vendors, and categories.' ) ) {
				return;
			}

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

		// Delete demo content.
		$( '.wpss-delete-demo' ).on( 'click', function() {
			var $btn    = $( this );
			var $status = $btn.siblings( '.wpss-demo-status' );

			if ( ! confirm( l10n.confirmDelete || 'Delete all demo content? This will permanently remove demo services, vendors, and empty categories.' ) ) {
				return;
			}

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
		} );
	} );
} )( jQuery );
