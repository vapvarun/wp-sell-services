/**
 * Dispute thread reply (templates/partials/dispute-thread.php).
 *
 * One script for the buyer's and seller's dashboard and the admin dispute
 * screen. It lived in frontend.js, which the admin does not load, so the
 * admin could read a dispute but never answer it (Basecamp 10337171525).
 * The form carries its own endpoint and texts; the server returns the new
 * message rendered by the same item partial as the thread.
 *
 * @package WPSellServices
 * @since   1.8.0
 */
( function () {
	'use strict';

	var toast = function ( message, type ) {
		if ( window.wpssToast ) {
			window.wpssToast( message, type );
		}
	};

	document.addEventListener( 'change', function ( event ) {
		var input = event.target;

		if ( ! input.matches || ! input.matches( '#wpss-add-evidence-form input[name="evidence_file"]' ) ) {
			return;
		}

		var label = input.closest( 'form' ).querySelector( '.wpss-evidence-filename' );
		if ( label ) {
			label.textContent = input.files && input.files.length ? input.files[ 0 ].name : '';
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;

		if ( ! form.matches || ! form.matches( '#wpss-add-evidence-form' ) ) {
			return;
		}

		event.preventDefault();

		var button = form.querySelector( 'button[type="submit"]' );
		var label = button.textContent;
		var data = new FormData( form );

		data.append( 'action', 'wpss_add_dispute_evidence' );
		button.disabled = true;
		button.textContent = form.dataset.sending;

		fetch( form.dataset.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( body ) {
				if ( ! body || ! body.success ) {
					toast( ( body && body.data && body.data.message ) || form.dataset.error, 'error' );
					return;
				}

				var thread = document.getElementById( 'wpss-evidence-thread' );
				var empty = thread.querySelector( '.wpss-evidence-empty' );

				if ( empty ) {
					empty.remove();
				}

				if ( body.data.html ) {
					thread.insertAdjacentHTML( 'beforeend', body.data.html );
					document.dispatchEvent( new CustomEvent( 'wpss:icons:refresh' ) );
					if ( window.lucide && typeof window.lucide.createIcons === 'function' ) {
						window.lucide.createIcons();
					}
				}

				form.reset();
				form.querySelector( '.wpss-evidence-filename' ).textContent = '';
				toast( body.data.message, 'success' );
			} )
			.catch( function () {
				toast( form.dataset.error, 'error' );
			} )
			.finally( function () {
				button.disabled = false;
				button.textContent = label;
			} );
	} );
}() );
