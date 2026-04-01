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

			if ( ! confirm( ( l10n.confirmCreate || 'Create a new page titled' ) + ' "' + title + '"?' ) ) {
				return;
			}

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
