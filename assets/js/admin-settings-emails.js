/**
 * Settings page — Emails tab functionality.
 *
 * Handles "Send Test Email" AJAX.
 * Requires wpssSettingsEmails localized object.
 *
 * @package WPSellServices
 * @since   2.0.0
 */

( function( $ ) {
	'use strict';

	var l10n = window.wpssSettingsEmails || {};

	$( function() {
		$( '.wpss-send-test-email' ).on( 'click', function() {
			var $btn    = $( this );
			var $status = $btn.siblings( '.wpss-test-email-status' );

			$btn.prop( 'disabled', true ).text( l10n.sending || 'Sending...' );
			$status.hide();

			$.post( window.ajaxurl, {
				action: 'wpss_send_test_email',
				nonce: $btn.data( 'nonce' ),
			}, function( response ) {
				$btn.prop( 'disabled', false ).text( l10n.sendTest || 'Send Test Email' );
				$status.show();
				if ( response.success ) {
					$status.css( 'color', '#00a32a' ).text( response.data.message );
				} else {
					$status.css( 'color', '#d63638' ).text( response.data.message );
				}
			} ).fail( function() {
				$btn.prop( 'disabled', false ).text( l10n.sendTest || 'Send Test Email' );
				$status.show().css( 'color', '#d63638' ).text( l10n.ajaxError || 'Request failed. Please try again.' );
			} );
		} );
	} );
} )( jQuery );
