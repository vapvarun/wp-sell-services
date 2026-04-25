/**
 * WP Sell Services — UX Primitives
 *
 * Tiny, dependency-free helpers for the two cross-cutting UX patterns:
 *   - WpssFormError      Field-level + form-level error rendering with ARIA.
 *   - WpssAutosave       Indicator pill (idle / saving / saved / error).
 *
 * Designed to be called from any context — Alpine.js components, plain
 * jQuery handlers, vanilla event listeners, or PHP-rendered inline scripts.
 *
 * Documented in docs/architecture/UX-PRIMITIVES.md.
 *
 * @package WPSellServices
 * @since   1.1.0
 */

( function ( window, document ) {
	'use strict';

	/**
	 * Field-level + form-level error rendering.
	 *
	 * The DOM contract:
	 *   <input id="title" aria-describedby="title-error" />
	 *   <p id="title-error" class="wpss-form-error" hidden></p>
	 *
	 * Form-level summary contract:
	 *   <div class="wpss-form-error-summary" hidden role="alert">
	 *     <p class="wpss-form-error-summary__title">Please fix:</p>
	 *     <ul class="wpss-form-error-summary__list"></ul>
	 *   </div>
	 */
	var WpssFormError = {

		/**
		 * Show an error on a single field.
		 *
		 * @param {string|HTMLElement} field   Field id (without #) or DOM node.
		 * @param {string}             message Error text.
		 */
		show: function ( field, message ) {
			var input = this._resolve( field );
			if ( ! input ) {
				return;
			}

			input.setAttribute( 'aria-invalid', 'true' );

			var errorEl = this._findErrorEl( input );
			if ( errorEl ) {
				errorEl.textContent = message;
				errorEl.hidden = false;
				errorEl.setAttribute( 'role', 'alert' );
			}
		},

		/**
		 * Clear an error on a single field.
		 *
		 * @param {string|HTMLElement} field Field id (without #) or DOM node.
		 */
		clear: function ( field ) {
			var input = this._resolve( field );
			if ( ! input ) {
				return;
			}

			input.removeAttribute( 'aria-invalid' );

			var errorEl = this._findErrorEl( input );
			if ( errorEl ) {
				errorEl.textContent = '';
				errorEl.hidden = true;
			}
		},

		/**
		 * Render a form-level error summary above the first field.
		 *
		 * @param {HTMLElement} container Element holding the summary node.
		 * @param {Array}       errors    Array of error message strings.
		 */
		summary: function ( container, errors ) {
			if ( ! container ) {
				return;
			}

			var summary = container.querySelector( '.wpss-form-error-summary' );
			if ( ! summary || ! errors || ! errors.length ) {
				if ( summary ) {
					summary.hidden = true;
				}
				return;
			}

			var list = summary.querySelector( '.wpss-form-error-summary__list' );
			if ( list ) {
				while ( list.firstChild ) {
					list.removeChild( list.firstChild );
				}
				errors.forEach( function ( message ) {
					var li = document.createElement( 'li' );
					li.textContent = message;
					list.appendChild( li );
				} );
			}

			summary.hidden = false;
			summary.setAttribute( 'role', 'alert' );
		},

		/**
		 * Scroll to and focus the first invalid field in a container.
		 *
		 * @param {HTMLElement} container Form or wrapper to search within.
		 */
		scrollToFirst: function ( container ) {
			if ( ! container ) {
				return;
			}

			var first = container.querySelector( '[aria-invalid="true"]' );
			if ( ! first ) {
				return;
			}

			first.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			// Defer focus so the smooth scroll has a chance to start.
			window.setTimeout( function () {
				try {
					first.focus( { preventScroll: true } );
				} catch ( e ) {
					first.focus();
				}
			}, 250 );
		},

		_resolve: function ( field ) {
			if ( ! field ) {
				return null;
			}
			if ( typeof field === 'string' ) {
				return document.getElementById( field );
			}
			return field;
		},

		_findErrorEl: function ( input ) {
			if ( input.id ) {
				var byId = document.getElementById( input.id + '-error' );
				if ( byId ) {
					return byId;
				}
			}
			if ( input.parentNode ) {
				return input.parentNode.querySelector( '.wpss-form-error' );
			}
			return null;
		}
	};

	/**
	 * Autosave indicator pill state controller.
	 *
	 * The DOM contract:
	 *   <span class="wpss-autosave" data-state="idle" role="status" aria-live="polite">
	 *     <span class="wpss-autosave__icon" aria-hidden="true"></span>
	 *     <span class="wpss-autosave__label"></span>
	 *   </span>
	 */
	var WpssAutosave = {

		/**
		 * Set the indicator state.
		 *
		 * @param {string|HTMLElement} indicator Selector or DOM node for the pill.
		 * @param {string}             state     One of: idle, saving, saved, error.
		 * @param {string}             [label]   Optional override for the visible label.
		 */
		set: function ( indicator, state, label ) {
			var el = this._resolve( indicator );
			if ( ! el ) {
				return;
			}

			var validStates = [ 'idle', 'saving', 'saved', 'error' ];
			if ( validStates.indexOf( state ) === -1 ) {
				return;
			}

			el.setAttribute( 'data-state', state );
			el.classList.remove( 'wpss-autosave--fading' );

			var labelEl = el.querySelector( '.wpss-autosave__label' );
			if ( labelEl ) {
				labelEl.textContent = label || this._defaultLabel( state );
			}

			// Auto-fade after success so it doesn't clutter the UI.
			if ( 'saved' === state ) {
				window.setTimeout( function () {
					el.classList.add( 'wpss-autosave--fading' );
				}, 2000 );
			}
		},

		_resolve: function ( indicator ) {
			if ( ! indicator ) {
				return null;
			}
			if ( typeof indicator === 'string' ) {
				return document.querySelector( indicator );
			}
			return indicator;
		},

		_defaultLabel: function ( state ) {
			var i18n = ( window.wpssData && window.wpssData.i18n ) || {};
			switch ( state ) {
				case 'saving':
					return i18n.autosaveSaving || 'Saving…';
				case 'saved':
					return i18n.autosaveSaved || 'Saved';
				case 'error':
					return i18n.autosaveError || 'Save failed';
				default:
					return '';
			}
		}
	};

	// Expose globally for use from Alpine components, jQuery handlers, or vanilla JS.
	window.WpssFormError = WpssFormError;
	window.WpssAutosave = WpssAutosave;

} )( window, document );
