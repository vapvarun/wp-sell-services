/**
 * Global toast notification system.
 *
 * Usage: window.wpssToast( 'Message', 'success' );
 * Types: success, error, warning, info
 *
 * @package WPSellServices
 * @since   2.0.0
 */

( function() {
	'use strict';

	var container = null;

	/**
	 * Get or create the toast container element.
	 *
	 * @return {HTMLElement} Container element.
	 */
	function getContainer() {
		if ( ! container ) {
			container = document.createElement( 'div' );
			container.className = 'wpss-toast-container';
			document.body.appendChild( container );
		}
		return container;
	}

	/**
	 * Show a toast notification.
	 *
	 * @param {string} message Toast message text.
	 * @param {string} type    One of: success, error, warning, info.
	 */
	window.wpssToast = function( message, type ) {
		type = type || 'info';

		var toast = document.createElement( 'div' );
		toast.className = 'wpss-toast wpss-toast--' + type;
		toast.textContent = message;
		getContainer().appendChild( toast );

		requestAnimationFrame( function() {
			toast.classList.add( 'is-visible' );
		} );

		setTimeout( function() {
			toast.classList.remove( 'is-visible' );
			setTimeout( function() {
				toast.remove();
			}, 300 );
		}, 4000 );
	};
} )();
