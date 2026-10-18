/**
 * WPSS UI Primitives — shared confirm/toast helpers.
 *
 * Exposes two globals:
 *   window.wpssConfirm( message, options ) → Promise<boolean>
 *   window.wpssToast( message, type )
 *   window.wpssModal.open( el | id ) / .close( el | id ) / .closeAll()
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
		 *                                       textarea; {label, placeholder, maxLength,
		 *                                       required, requiredMessage}. With required the
		 *                                       dialog stays open until the field has text.
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
					h2.id = 'wpss-confirm-title';
					h2.textContent = titleText;
					dialog.appendChild( h2 );
				}

				var p = document.createElement( 'p' );
				p.className = 'wpss-confirm__message';
				p.id = 'wpss-confirm-message';
				p.textContent = message;
				dialog.appendChild( p );
				dialog.setAttribute( 'aria-labelledby', titleText ? 'wpss-confirm-title' : 'wpss-confirm-message' );

				/*
				 * Optional text input. Exists so nothing in this plugin has to reach
				 * for window.prompt(), whose OK/Cancel come from the browser's
				 * language rather than the site's - a non-English owner rejecting a
				 * service got a half-translated dialog (Basecamp 10304333428).
				 */
				var field = null;
				var promptOpts = {};
				var feedback = null;
				if ( options.prompt ) {
					promptOpts = 'object' === typeof options.prompt ? options.prompt : {};

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
					if ( ! promptOpts.label ) {
						field.setAttribute( 'aria-labelledby', 'wpss-confirm-message' );
					}
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
				// Every confirm starts on Cancel, so Enter cannot pay out, suspend or
				// delete by reflex. Starting only the red ones there left bulk
				// Approve and Mark as paid one keypress from moving money
				// (Basecamp 10372722884).
				( field || cancelBtn ).focus();

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
					if ( field && promptOpts.required && ! field.value.trim() ) {
						// Keep the dialog open rather than send something the server would refuse.
						field.setAttribute( 'aria-invalid', 'true' );
						if ( ! feedback ) {
							feedback = document.createElement( 'p' );
							feedback.className = 'wpss-confirm__error';
							feedback.setAttribute( 'role', 'alert' );
							feedback.textContent = promptOpts.requiredMessage || i18n.required || 'Please fill this in.';
							field.insertAdjacentElement( 'afterend', feedback );
							field.setAttribute( 'aria-describedby', 'wpss-confirm-error' );
							feedback.id = 'wpss-confirm-error';
						}
						field.focus();
						return;
					}
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

/* -------------------------------------------------------------------------
   wpssModal - the one open/close contract for .wpss-modal.
   Markup and CSS: design-system.css section 10. Open sets .wpss-modal-open and
   drops [hidden], locks the page, traps focus, and keeps the dialog clear of a
   fixed theme header. Close buttons, the backdrop and Esc close the top modal.
   ---------------------------------------------------------------------- */
( function() {
	'use strict';

	if ( window.wpssModal ) {
		return;
	}

	var FOCUSABLE = 'button, [href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])';
	var lastFocused = null;

	function resolve( target ) {
		return 'string' === typeof target ? document.getElementById( target ) : target;
	}

	function visible( root ) {
		return Array.prototype.filter.call( root.querySelectorAll( FOCUSABLE ), function( el ) {
			return el.offsetParent !== null;
		} );
	}

	function openModals() {
		return document.querySelectorAll( '.wpss-modal.wpss-modal-open' );
	}

	// Hand the height of a fixed/sticky header covering the top edge to the CSS.
	function offsetFromHeader( modal ) {
		var offset = 0;
		document.elementsFromPoint( Math.round( window.innerWidth / 2 ), 2 ).forEach( function( el ) {
			if ( modal.contains( el ) || el === document.body || el === document.documentElement ) {
				return;
			}
			var pos = window.getComputedStyle( el ).position;
			if ( 'fixed' === pos || 'sticky' === pos ) {
				offset = Math.max( offset, Math.ceil( el.getBoundingClientRect().bottom ) );
			}
		} );
		modal.style.setProperty( '--wpss-modal-offset', offset + 'px' );
	}

	// A modal inside .wpss-app-shell is trapped by the shell's stacking context (isolation: isolate), so any
	// themed header, footer or back-to-top button with its own z-index paints over it. While open, a modal
	// lives at the end of <body>; a marker keeps its place so close puts it back.
	var homes = new WeakMap();

	function lift( modal ) {
		if ( homes.has( modal ) || ! modal.closest( '.wpss-app-shell' ) ) {
			return;
		}
		var marker = document.createComment( 'wpss-modal-home' );
		modal.parentNode.insertBefore( marker, modal );
		homes.set( modal, marker );
		document.body.appendChild( modal );
	}

	function lower( modal ) {
		var marker = homes.get( modal );
		if ( marker && marker.parentNode ) {
			marker.parentNode.insertBefore( modal, marker );
			marker.remove();
		}
		homes.delete( modal );
	}

	function trap( e ) {
		if ( 'Tab' !== e.key ) {
			return;
		}
		var items = visible( e.currentTarget );
		if ( ! items.length ) {
			return;
		}
		var first = items[ 0 ];
		var last  = items[ items.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	function open( target ) {
		var modal = resolve( target );
		if ( ! modal ) {
			return;
		}
		lastFocused = document.activeElement;
		lift( modal );
		modal.hidden = false;
		modal.classList.add( 'wpss-modal-open' );
		document.body.classList.add( 'wpss-modal-active' );
		offsetFromHeader( modal );
		modal.addEventListener( 'keydown', trap );
		// Icons inside a modal rendered hidden need hydrating once it shows.
		document.dispatchEvent( new CustomEvent( 'wpss:icons:refresh' ) );
		// First field, else the footer's first button (the safe one), never the close icon.
		var body   = modal.querySelector( '.wpss-modal__body' );
		var footer = modal.querySelector( '.wpss-modal__footer' );
		var first  = ( body && visible( body )[ 0 ] ) || ( footer && visible( footer )[ 0 ] ) || visible( modal )[ 0 ];
		if ( first ) {
			first.focus();
		}
		modal.dispatchEvent( new CustomEvent( 'wpss:modal:open', { bubbles: true } ) );
	}

	function close( target ) {
		var modal = target ? resolve( target ) : openModals()[ openModals().length - 1 ];
		if ( ! modal ) {
			return;
		}
		modal.classList.remove( 'wpss-modal-open' );
		modal.hidden = true;
		lower( modal );
		modal.removeEventListener( 'keydown', trap );
		if ( ! openModals().length ) {
			document.body.classList.remove( 'wpss-modal-active' );
		}
		if ( lastFocused && lastFocused.focus ) {
			lastFocused.focus();
		}
		lastFocused = null;
		modal.dispatchEvent( new CustomEvent( 'wpss:modal:close', { bubbles: true } ) );
	}

	function closeAll() {
		Array.prototype.forEach.call( openModals(), close );
	}

	window.wpssModal = { open: open, close: close, closeAll: closeAll };

	document.addEventListener( 'click', function( e ) {
		var opener = e.target.closest( '[data-wpss-modal-open]' );
		if ( opener ) {
			e.preventDefault();
			open( opener.getAttribute( 'data-wpss-modal-open' ) );
			return;
		}
		var closer = e.target.closest( '.wpss-modal__backdrop, .wpss-modal__close, .wpss-modal__close-btn' );
		var modal  = closer && closer.closest( '.wpss-modal' );
		if ( modal ) {
			e.preventDefault();
			close( modal );
		}
	} );

	document.addEventListener( 'keydown', function( e ) {
		if ( 'Escape' === e.key ) {
			close();
		}
	} );
}() );
