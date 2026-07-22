/**
 * Admin Withdrawals (Payouts) page.
 *
 * Single mark-paid / approve / reject via the note modal, bulk actions via
 * wpssConfirm, feedback via wpssToast. All state changes route through the
 * wpss_process_withdrawal / wpss_bulk_process_withdrawals AJAX handlers,
 * which delegate to EarningsService — mark-paid is idempotent server-side.
 *
 * Localized data: window.wpssWithdrawals { ajaxUrl, nonce, bulkNonce, i18n }.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

( function( $ ) {
	'use strict';

	$( function() {
		var settings = window.wpssWithdrawals;

		if ( ! settings || ! $( '.wpss-withdrawals-page' ).length ) {
			return;
		}

		var $modal = $( '#wpss-withdrawal-modal' );
		var $form  = $( '#wpss-process-withdrawal-form' );

		function notify( message, type ) {
			if ( window.wpssToast ) {
				window.wpssToast( message, type || 'error' );
				return;
			}
			// wpss-ui.js is a dependency, but never fail silently if it changes.
			window.console && console.error( message );
		}

		/* ---- Single actions: open the note modal ---- */

		$( '.wpss-process-withdrawal' ).on( 'click', function( e ) {
			e.preventDefault();

			var $btn   = $( this );
			var action = $btn.data( 'action' );

			$( '#wpss-withdrawal-id' ).val( $btn.data( 'withdrawal-id' ) );
			$( '#wpss-action-type' ).val( action );
			$( '#wpss-admin-note' ).val( '' );

			$( '#wpss-modal-title' ).text( settings.i18n.titles[ action ] || settings.i18n.titles.fallback );
			$( '#wpss-modal-description' ).text(
				( settings.i18n.descriptions[ action ] || '' )
					.replace( '%amount%', $btn.data( 'amount' ) )
					.replace( '%vendor%', $btn.data( 'vendor' ) )
			);

			if ( 'reject' === action ) {
				$( '#wpss-modal-submit' ).removeClass( 'button-primary' ).addClass( 'button-link-delete' );
			} else {
				$( '#wpss-modal-submit' ).addClass( 'button-primary' ).removeClass( 'button-link-delete' );
			}

			$modal.show();
		} );

		$( '.wpss-modal-close, .wpss-modal-cancel' ).on( 'click', function() {
			$modal.hide();
		} );

		$modal.on( 'click', function( e ) {
			if ( e.target === this ) {
				$modal.hide();
			}
		} );

		$form.on( 'submit', function( e ) {
			e.preventDefault();

			var $submit      = $( '#wpss-modal-submit' );
			var originalText = $submit.text();

			$submit.prop( 'disabled', true ).text( settings.i18n.loading );

			$.post( settings.ajaxUrl, {
				action: 'wpss_process_withdrawal',
				nonce: settings.nonce,
				withdrawal_id: $( '#wpss-withdrawal-id' ).val(),
				action_type: $( '#wpss-action-type' ).val(),
				admin_note: $( '#wpss-admin-note' ).val()
			} ).done( function( response ) {
				if ( response.success ) {
					window.location.reload();
					return;
				}
				notify( ( response.data && response.data.message ) || settings.i18n.error );
				$submit.prop( 'disabled', false ).text( originalText );
			} ).fail( function() {
				notify( settings.i18n.error );
				$submit.prop( 'disabled', false ).text( originalText );
			} );
		} );

		/* ---- Bulk actions ---- */

		$( '#cb-select-all-1, #cb-select-all-2' ).on( 'change', function() {
			$( 'input[name="withdrawal_ids[]"]' ).prop( 'checked', $( this ).prop( 'checked' ) );
		} );

		$( '.wpss-withdrawals-bulk-apply' ).on( 'click', function( e ) {
			e.preventDefault();

			var bulkAction = $( '.wpss-withdrawals-bulk-select' ).val();
			if ( ! bulkAction ) {
				return;
			}

			var ids = $( 'input[name="withdrawal_ids[]"]:checked' ).map( function() {
				return this.value;
			} ).get();

			if ( ! ids.length ) {
				notify( settings.i18n.selectFirst );
				return;
			}

			var label      = settings.i18n.bulkLabels[ bulkAction ] || bulkAction;
			var confirmMsg = settings.i18n.bulkConfirm
				.replace( '%action%', label )
				.replace( '%count%', String( ids.length ) );

			var $btn = $( this );

			window.wpssConfirm( confirmMsg, {
				title: label,
				confirmText: label,
				tone: 'reject' === bulkAction ? 'danger' : undefined
			} ).then( function( confirmed ) {
				if ( ! confirmed ) {
					return;
				}

				$btn.prop( 'disabled', true );

				$.post( settings.ajaxUrl, {
					action: 'wpss_bulk_process_withdrawals',
					bulk_action: bulkAction,
					withdrawal_ids: ids,
					nonce: settings.bulkNonce
				} ).done( function( response ) {
					if ( response.success ) {
						window.location.reload();
						return;
					}
					notify( ( response.data && response.data.message ) || settings.i18n.error );
					$btn.prop( 'disabled', false );
				} ).fail( function() {
					notify( settings.i18n.error );
					$btn.prop( 'disabled', false );
				} );
			} );
		} );
	} );
}( jQuery ) );
