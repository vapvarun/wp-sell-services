/**
 * WP Sell Services — Lucide icon bootstrap.
 *
 * Initializes Lucide icons once on DOMContentLoaded, then listens for the
 * `wpss:icons:refresh` CustomEvent so dynamically-injected markup (modals,
 * AJAX responses, timeline reloads) can re-hydrate icons by dispatching:
 *
 *     document.dispatchEvent( new CustomEvent( 'wpss:icons:refresh' ) );
 *
 * @package WPSellServices
 * @since   1.1.0
 */

( function () {
	'use strict';

	function renderIcons() {
		if ( window.lucide && typeof window.lucide.createIcons === 'function' ) {
			window.lucide.createIcons();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', renderIcons );
	} else {
		renderIcons();
	}

	document.addEventListener( 'wpss:icons:refresh', renderIcons );
}() );
