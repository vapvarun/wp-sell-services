/**
 * Settings page hash-based navigation.
 *
 * Handles sidebar nav item activation, section show/hide via URL hash,
 * hash preservation on form submit, and settings-updated toast.
 *
 * @package WPSellServices
 * @since   2.0.0
 */

( function() {
	'use strict';

	var NAV     = '.wpss-settings-nav-item[data-section]';
	var SECTION = '.wpss-settings-section';
	var ACTIVE  = 'is-active';

	/**
	 * Activate a section by its ID.
	 *
	 * @param {string} id Section identifier (without "section-" prefix).
	 */
	function activate( id ) {
		document.querySelectorAll( NAV ).forEach( function( el ) {
			el.classList.remove( ACTIVE );
		} );
		document.querySelectorAll( SECTION ).forEach( function( el ) {
			el.classList.remove( ACTIVE );
		} );

		var nav = document.querySelector( NAV + '[data-section="' + id + '"]' );
		var sec = document.getElementById( 'section-' + id );

		if ( nav && sec ) {
			nav.classList.add( ACTIVE );
			sec.classList.add( ACTIVE );
		} else {
			// Fallback to first section.
			var firstNav = document.querySelector( NAV );
			var firstSec = document.querySelector( SECTION );
			if ( firstNav ) {
				firstNav.classList.add( ACTIVE );
			}
			if ( firstSec ) {
				firstSec.classList.add( ACTIVE );
			}
		}

		// Re-init Lucide icons for the newly visible section.
		if ( window.lucide ) {
			setTimeout( function() {
				lucide.createIcons();
			}, 10 );
		}
	}

	// Sidebar navigation click handler.
	document.querySelectorAll( NAV ).forEach( function( item ) {
		item.addEventListener( 'click', function( e ) {
			e.preventDefault();
			var section = this.dataset.section;
			activate( section );
			history.replaceState( null, '', '#' + section );
		} );
	} );

	// Hash preservation: append hash to _wp_http_referer on form submit
	// so WordPress redirects back to the correct section after save.
	document.querySelectorAll( SECTION + ' form' ).forEach( function( form ) {
		form.addEventListener( 'submit', function() {
			var hash = location.hash;
			if ( hash ) {
				var referer = form.querySelector( 'input[name="_wp_http_referer"]' );
				if ( referer ) {
					referer.value = referer.value.split( '#' )[0] + hash;
				}
			}
		} );
	} );

	// Show toast on settings-updated redirect.
	var params = new URLSearchParams( location.search );
	if ( params.get( 'settings-updated' ) === 'true' ) {
		if ( window.wpssToast ) {
			wpssToast( 'Settings saved.', 'success' );
		}
		// Clean URL without reloading.
		var url = location.href
			.replace( /[?&]settings-updated=true/, '' )
			.replace( /\?&/, '?' )
			.replace( /\?$/, '' );
		history.replaceState( null, '', url );
	}

	// Activate from hash on page load.
	activate( location.hash.replace( '#', '' ) || '' );
} )();
