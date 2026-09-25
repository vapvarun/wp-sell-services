/**
 * WPSS UI Primitives — shared confirm/toast helpers.
 *
 * Exposes two globals:
 *   window.wpssConfirm( message, options ) → Promise<boolean>
 *   window.wpssToast( message, type )
 *
 * Single source of truth for both helpers across admin and frontend.
 * Vanilla JS, no jQuery dependency.  IIFE so it is idempotent across
 * multiple page loads — double-loading is safe.
 *
 * @package WPSellServices
 * @since   1.2.1
 */

( function() {
	'use strict';

	/* -------------------------------------------------------------------------
	   wpssConfirm — Promise-based modal confirm
	   ---------------------------------------------------------------------- */

	if ( ! window.wpssConfirm ) {
		/**
		 * Show a design-system confirm dialog.
		 *
		 * @param {string} message      Body text shown to the user.
		 * @param {Object} [options]    Optional overrides.
		 * @param {string} [options.title]       Optional heading above the message.
		 * @param {string} [options.confirmText] Label for the confirm button (default 'Confirm').
		 * @param {string} [options.cancelText]  Label for the cancel button (default 'Cancel').
		 * @param {string} [options.tone]        'danger' tints the confirm button red.
		 * @param {Object} [options.prompt]      Ask for text as well as consent. Renders a
		 *                                       textarea; {label, placeholder, maxLength}.
		 * @return {Promise<boolean|string>} Without options.prompt: true on confirm, false
		 *                                   otherwise. With it: the entered string on
		 *                                   confirm (possibly empty), false on
		 *                                   cancel/Esc/backdrop - so test `false === result`,
		 *                                   never truthiness.
		 */
		window.wpssConfirm = function( message, options ) {
			return new Promise( function( resolve ) {
				options = options || {};

				// Fall back to the site's language, then to English. The literals
				// are the last resort for a page that loaded the script without
				// the localized strings, not the normal path.
				var i18n = window.wpssUiI18n || {};

				var confirmText = options.confirmText || i18n.confirm || 'Confirm';
				var cancelText  = options.cancelText  || i18n.cancel  || 'Cancel';
				var titleText   = options.title        || '';
				var isDanger    = options.tone === 'danger';

				var previousFocus = document.activeElement;

				/* ---- Build modal DOM ---- */
				var overlay = document.createElement( 'div' );
				overlay.className = 'wpss-ui-confirm-overlay';
				overlay.setAttribute( 'aria-hidden', 'true' );

				var dialog = document.createElement( 'div' );
				dialog.className = 'wpss-ui-modal wpss-modal__dialog wpss-confirm';
				dialog.setAttribute( 'role', 'dialog' );
				dialog.setAttribute( 'aria-modal', 'true' );

				if ( titleText ) {
					var h2 = document.createElement( 'h2' );
					h2.className = 'wpss-confirm__title';
					h2.textContent = titleText;
					dialog.appendChild( h2 );
				}

				var p = document.createElement( 'p' );
				p.className = 'wpss-confirm__message';
				p.textContent = message;
				dialog.appendChild( p );

				/*
				 * Optional text input. Exists so nothing in this plugin has to reach
				 * for window.prompt(), whose OK/Cancel come from the browser's
				 * language rather than the site's - a non-English owner rejecting a
				 * service got a half-translated dialog (Basecamp 10304333428).
				 */
				var field = null;
				if ( options.prompt ) {
					var promptOpts = 'object' === typeof options.prompt ? options.prompt : {};

					if ( promptOpts.label ) {
						var label = document.createElement( 'label' );
						label.className = 'wpss-confirm__label';
						label.setAttribute( 'for', 'wpss-confirm-input' );
						label.textContent = promptOpts.label;
						dialog.appendChild( label );
					}

					field = document.createElement( 'textarea' );
					field.className = 'wpss-confirm__input';
					field.id = 'wpss-confirm-input';
					field.rows = 3;
					if ( promptOpts.placeholder ) {
						field.placeholder = promptOpts.placeholder;
					}
					if ( promptOpts.maxLength ) {
						field.maxLength = promptOpts.maxLength;
					}
					dialog.appendChild( field );
				}

				var actions = document.createElement( 'div' );
				actions.className = 'wpss-confirm__actions';

				var cancelBtn = document.createElement( 'button' );
				cancelBtn.type = 'button';
				cancelBtn.className = 'wpss-btn wpss-btn--secondary';
				cancelBtn.textContent = cancelText;

				var confirmBtn = document.createElement( 'button' );
				confirmBtn.type = 'button';
				confirmBtn.className = 'wpss-btn ' + ( isDanger ? 'wpss-btn--danger' : 'wpss-btn--primary' );
				confirmBtn.textContent = confirmText;

				actions.appendChild( cancelBtn );
				actions.appendChild( confirmBtn );
				dialog.appendChild( actions );

				var wrapper = document.createElement( 'div' );
				wrapper.className = 'wpss-ui-confirm-wrap';
				wrapper.setAttribute( 'aria-hidden', 'false' );
				wrapper.appendChild( overlay );
				wrapper.appendChild( dialog );

				document.body.appendChild( wrapper );
				( field || confirmBtn ).focus();

				/* ---- Focus trap ---- */
				var focusableSelectors = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

				function trapFocus( e ) {
					if ( e.key !== 'Tab' ) {
						return;
					}
					var focusable = dialog.querySelectorAll( focusableSelectors );
					var first = focusable[0];
					var last  = focusable[ focusable.length - 1 ];

					if ( e.shiftKey ) {
						if ( document.activeElement === first ) {
							e.preventDefault();
							last.focus();
						}
					} else {
						if ( document.activeElement === last ) {
							e.preventDefault();
							first.focus();
						}
					}
				}

				dialog.addEventListener( 'keydown', trapFocus );

				/* ---- Resolve helpers ---- */
				function teardown( result ) {
					document.removeEventListener( 'keydown', onEsc );
					dialog.removeEventListener( 'keydown', trapFocus );
					document.body.removeChild( wrapper );
					if ( previousFocus && previousFocus.focus ) {
						previousFocus.focus();
					}
					resolve( result );
				}

				function onEsc( e ) {
					if ( e.key === 'Escape' || e.keyCode === 27 ) {
						teardown( false );
					}
				}

				document.addEventListener( 'keydown', onEsc );

				confirmBtn.addEventListener( 'click', function() {
					teardown( field ? field.value : true );
				} );

				cancelBtn.addEventListener( 'click', function() {
					teardown( false );
				} );

				overlay.addEventListener( 'click', function() {
					teardown( false );
				} );
			} );
		};
	}

	/* -------------------------------------------------------------------------
	   wpssToast — canonical single-source implementation
	   ---------------------------------------------------------------------- */

	var _container = null;

	function _getContainer() {
		if ( ! _container ) {
			_container = document.createElement( 'div' );
			_container.className = 'wpss-toast-container';
			document.body.appendChild( _container );
		}
		return _container;
	}

	/**
	 * Show a toast notification.
	 *
	 * Single source of truth for both admin and frontend contexts.
	 *
	 * @param {string} message Toast body text.
	 * @param {string} type    One of: success, error, warning, info.
	 */
	window.wpssToast = function( message, type ) {
		type = type || 'info';

		var toast = document.createElement( 'div' );
		toast.className = 'wpss-toast wpss-toast--' + type;

		var text = document.createElement( 'span' );
		text.textContent = message;
		toast.appendChild( text );

		var dismiss = function() {
			toast.classList.remove( 'is-visible' );
			setTimeout( function() {
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
			}, 300 );
		};

		// An error stays until it is dismissed: a reason that vanished after
		// four seconds (a storage save refused, a failed test) could not be
		// read, let alone acted on (Basecamp 10340902293).
		if ( 'error' === type ) {
			toast.setAttribute( 'role', 'alert' );
			var close = document.createElement( 'button' );
			close.type = 'button';
			close.className = 'wpss-toast__close';
			close.setAttribute( 'aria-label', ( window.wpssUiI18n && window.wpssUiI18n.dismiss ) || 'Dismiss' );
			close.textContent = '\u00d7';
			close.addEventListener( 'click', dismiss );
			toast.appendChild( close );
		} else {
			toast.setAttribute( 'role', 'status' );
			setTimeout( dismiss, 4000 );
		}

		_getContainer().appendChild( toast );

		requestAnimationFrame( function() {
			toast.classList.add( 'is-visible' );
		} );
	};

} )();

/**
 * Billing block: expand the form from its collapsed summary.
 *
 * Lives in wpss-ui (not stripe.js) because the block is gateway-agnostic — the
 * same Edit control has to work when the buyer pays by PayPal, Razorpay or Woo,
 * none of which load the Stripe script.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '[data-wpss-billing-edit]' );
		if ( ! trigger ) {
			return;
		}

		e.preventDefault();

		var block = trigger.closest( '[data-wpss-billing]' );
		if ( ! block ) {
			return;
		}

		var form = block.querySelector( '[data-wpss-billing-form]' );
		var summary = block.querySelector( '[data-wpss-billing-summary]' );

		if ( form ) {
			form.removeAttribute( 'hidden' );
			var first = form.querySelector( 'input, select' );
			if ( first && first.focus ) {
				first.focus();
			}
		}
		if ( summary ) {
			summary.setAttribute( 'hidden', 'hidden' );
		}
		trigger.setAttribute( 'hidden', 'hidden' );
	} );
}() );
