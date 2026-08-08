/**
 * Admin Reports queue.
 *
 * One job: make the destructive row actions ask first, through the shared
 * wpssConfirm modal rather than a native confirm() — native pop-ups were
 * removed from this plugin in 1.2.0 and re-introducing one here would put a
 * browser dialog in the middle of a designed screen.
 *
 * Progressive enhancement on purpose. Every action is already a real
 * nonce-protected form that works with no JavaScript at all; this only
 * interposes a question. If the script fails to load, an owner can still
 * moderate — they just do not get asked twice.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

( function() {
	'use strict';

	document.addEventListener( 'submit', function( event ) {
		var form = event.target;

		if ( ! form || ! form.matches( 'form[data-wpss-confirm]' ) ) {
			return;
		}

		// Already confirmed on the second pass — let it through.
		if ( form.dataset.wpssConfirmed === '1' ) {
			return;
		}

		var message = form.getAttribute( 'data-wpss-confirm' );

		if ( ! message || typeof window.wpssConfirm !== 'function' ) {
			return;
		}

		event.preventDefault();

		window.wpssConfirm( message ).then( function( ok ) {
			if ( ! ok ) {
				return;
			}

			form.dataset.wpssConfirmed = '1';
			form.submit();
		} );
	} );
}() );
