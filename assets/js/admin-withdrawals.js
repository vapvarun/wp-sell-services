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

		/**
		 * Format a settlement total the way wpss_format_price() does server-side:
		 * symbol first, grouped thousands, the currency's own decimal count.
		 *
		 * @param {number} amount Raw amount.
		 * @return {string} Display string.
		 */
		function formatMoney( amount ) {
			var decimals = parseInt( settings.currencyDecimals, 10 );

			if ( isNaN( decimals ) ) {
				decimals = 2;
			}

			return settings.currencySymbol + ( Number( amount ) || 0 ).toLocaleString( undefined, {
				minimumFractionDigits: decimals,
				maximumFractionDigits: decimals
			} );
		}

		/**
		 * The rows an admin may still act on. Completed and rejected rows render
		 * their checkbox disabled, so they can never be swept into a bulk action
		 * by "select all".
		 *
		 * @return {jQuery} Checked, enabled row checkboxes.
		 */
		function selectedRows() {
			return $( 'input[name="withdrawal_ids[]"]:checked' ).not( ':disabled' );
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
			$( 'input[name="withdrawal_ids[]"]' ).not( ':disabled' ).prop( 'checked', $( this ).prop( 'checked' ) );
		} );

		$( '.wpss-withdrawals-bulk-apply' ).on( 'click', function( e ) {
			e.preventDefault();

			var bulkAction = $( '.wpss-withdrawals-bulk-select' ).val();
			if ( ! bulkAction ) {
				return;
			}

			var $rows = selectedRows();
			var ids   = $rows.map( function() {
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
					return false;
				}

				// Only marking paid moves money records. Approve and reject are
				// reversible bookkeeping, so they stop at one confirmation.
				if ( 'complete' !== bulkAction ) {
					return true;
				}

				// Second confirmation: state the exact total, how many vendors it
				// settles, and that the site does NOT send the money. On the
				// manual rail the admin has already paid out-of-band; this step
				// only records it and debits the wallets.
				var total   = 0;
				var vendors = {};
				var methods = {};

				$rows.each( function() {
					total += parseFloat( this.getAttribute( 'data-amount' ) ) || 0;
					vendors[ this.getAttribute( 'data-vendor-id' ) ] = true;

					// A batch normally spans rails — some vendors take PayPal,
					// some a bank transfer. Show the breakdown so the admin can
					// see what they still have to pay, and by which method.
					var method = this.getAttribute( 'data-method' ) || '';
					methods[ method ] = ( methods[ method ] || 0 ) + 1;
				} );

				var breakdown = Object.keys( methods ).sort().map( function( method ) {
					return method + ' ×' + methods[ method ];
				} ).join( ', ' );

				var settleMsg = settings.i18n.settleBody
					.replace( '%total%', formatMoney( total ) )
					.replace( '%vendors%', String( Object.keys( vendors ).length ) )
					.replace( '%count%', String( ids.length ) )
					.replace( '%methods%', breakdown );

				return window.wpssConfirm( settleMsg, {
					title: settings.i18n.settleTitle,
					confirmText: settings.i18n.settleAction,
					tone: 'danger'
				} );
			} ).then( function( proceed ) {
				if ( ! proceed ) {
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
