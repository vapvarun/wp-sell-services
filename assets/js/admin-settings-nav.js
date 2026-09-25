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

	var __ = wp.i18n.__;

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
			wpssToast( __( 'Settings saved.', 'wp-sell-services' ), 'success' );
		}
		// Clean URL without reloading.
		var url = location.href
			.replace( /[?&]settings-updated=true/, '' )
			.replace( /\?&/, '?' )
			.replace( /\?$/, '' );
		history.replaceState( null, '', url );
	}

	// Activate from hash on page load.
	//
	// `?tab=<section>` is honoured when there is no hash. That query arg is how
	// settings were addressed before 1.3.0, and it survives in places we do not
	// control - published docs, support replies, and admins' own bookmarks. The
	// internal callers now emit hashes, but silently landing every one of those
	// older links on General is the bug this fixes, not just the internal ones
	// (Basecamp 10208211769).
	//
	// It is rewritten to a hash rather than merely honoured, so a save (which
	// preserves the hash through _wp_http_referer) returns to the same section
	// instead of falling back to General on the round trip.
	var initial = location.hash.replace( '#', '' ) || new URLSearchParams( location.search ).get( 'tab' ) || '';

	// Tabs that moved off this page (Basecamp 10337154229): with Pro active the
	// Analytics tab was only a link to its own screen, so an old #analytics or
	// &tab=analytics goes straight there.
	var moved = { analytics: 'admin.php?page=wpss-analytics' };
	var leaveIfMoved = function( id ) {
		if ( id && ! document.getElementById( 'section-' + id ) && moved[ id ] ) {
			location.replace( location.pathname.replace( /[^/]*$/, '' ) + moved[ id ] );
			return true;
		}
		return false;
	};

	if ( leaveIfMoved( initial ) ) {
		return;
	}

	if ( initial && ! location.hash && document.getElementById( 'section-' + initial ) ) {
		history.replaceState( null, '', '#' + initial );
	}

	activate( initial || '' );

	// Browser back/forward between sections. Without this the URL changed and
	// the page did not.
	window.addEventListener( 'hashchange', function() {
		var id = location.hash.replace( '#', '' );
		if ( ! leaveIfMoved( id ) ) {
			activate( id || '' );
		}
	} );

	// Unsaved changes (Basecamp 10337154229). Each card is its own form, so
	// saving one reloads the page and drops edits made in another: say so
	// before that happens, and before leaving with anything unsaved.
	var dirty = new Set();

	document.querySelectorAll( SECTION + ' form' ).forEach( function( form ) {
		var mark = function() {
			dirty.add( form );
		};
		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );

		form.addEventListener( 'submit', function( e ) {
			var others = Array.from( dirty ).filter( function( f ) {
				return f !== form && document.body.contains( f );
			} );

			if ( ! others.length || form.dataset.wpssConfirmed ) {
				dirty.clear();
				return;
			}

			e.preventDefault();
			var ask = window.wpssConfirm
				? window.wpssConfirm( __( 'Another card on this page has unsaved changes. Saving this one reloads the page and those changes will be lost. Save this card anyway?', 'wp-sell-services' ) )
				: Promise.resolve( true );

			ask.then( function( ok ) {
				if ( ok ) {
					form.dataset.wpssConfirmed = '1';
					dirty.clear();
					form.requestSubmit();
				}
			} );
		} );
	} );

	window.addEventListener( 'beforeunload', function( e ) {
		if ( dirty.size ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	// Gateway card collapse/expand toggle.
	document.querySelectorAll( '.wpss-card[data-gateway] .wpss-card__head' ).forEach( function( head ) {
		head.addEventListener( 'click', function( e ) {
			// Don't toggle if clicking inside a form control.
			if ( e.target.closest( 'input, select, textarea, button:not(.wpss-card__toggle)' ) ) {
				return;
			}
			var card = head.closest( '.wpss-card[data-gateway]' );
			card.classList.toggle( 'is-collapsed' );
		} );
	} );
} )();
