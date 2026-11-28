/**
 * WP Sell Services - Settings page progressive enhancement.
 *
 * Adds a reveal/hide toggle to masked secret fields rendered by
 * Settings::render_secret_field(). The stored secret is never present in the
 * DOM, so revealing only ever shows the value the admin is currently typing
 * (e.g. a freshly pasted webhook signing secret). The field is fully usable
 * without this script; it is pure progressive enhancement.
 *
 * Also asks for confirmation before "Delete data on uninstall" is ticked.
 *
 * @package WPSellServices
 * @since   1.6.0
 */
( function () {
	'use strict';

	var i18n =
		( typeof window.wpssSettingsSecret === 'object' &&
			window.wpssSettingsSecret ) ||
		{};

	var labelReveal = i18n.reveal;
	var labelHide = i18n.hide;

	/**
	 * Wire a single masked secret field.
	 *
	 * @param {HTMLElement} wrap The .wpss-secret-field container.
	 * @return {void}
	 */
	/**
	 * "Delete data on uninstall" is the one checkbox on this screen that can
	 * destroy every order and ledger row on the site, so ticking it asks first
	 * and unticks itself if the admin backs out. Saving still needs the
	 * Save button; this only guards the tick.
	 *
	 * @return {void}
	 */
	function initDeleteDataConfirm() {
		var box = document.getElementById( 'delete_data_on_uninstall' );
		var l10n = window.wpssSettingsAdvanced || {};

		if ( ! box || typeof window.wpssConfirm !== 'function' ) {
			return;
		}

		box.addEventListener( 'change', function () {
			if ( ! box.checked ) {
				return;
			}

			window.wpssConfirm( l10n.confirmDelete || 'Delete all plugin data when the plugin is uninstalled?', {
				title: l10n.confirmDeleteTitle || '',
				confirmText: l10n.confirmDeleteYes || 'Confirm',
				tone: 'danger'
			} ).then( function ( ok ) {
				if ( ! ok ) {
					box.checked = false;
				}
			} );
		} );
	}

	function initField( wrap ) {
		var input = wrap.querySelector( '.wpss-secret-field__input' );
		var toggle = wrap.querySelector( '.wpss-secret-field__toggle' );

		if ( ! input || ! toggle ) {
			return;
		}

		// Reveal only makes sense once the admin types a new value; the stored
		// secret is never echoed, so an empty field has nothing to reveal.
		toggle.hidden = false;
		toggle.textContent = labelReveal;
		toggle.setAttribute( 'aria-pressed', 'false' );

		toggle.addEventListener( 'click', function () {
			var revealed = 'text' === input.getAttribute( 'type' );

			if ( revealed ) {
				input.setAttribute( 'type', 'password' );
				toggle.textContent = labelReveal;
				toggle.setAttribute( 'aria-pressed', 'false' );
			} else {
				input.setAttribute( 'type', 'text' );
				toggle.textContent = labelHide;
				toggle.setAttribute( 'aria-pressed', 'true' );
			}

			input.focus();
		} );
	}

	/**
	 * Payment methods table (2.0.0): on/off switches and buyer-facing order, saved through
	 * POST /payments/settings. Statuses come back from GET, because the server's gateways read
	 * their settings once per request.
	 *
	 * @param {HTMLElement} box The .wpss-payment-methods card.
	 */
	function initPaymentMethods( box ) {
		var body   = box.querySelector( 'tbody' );
		var notice = box.querySelector( '.wpss-payment-methods__notice' );
		var url    = box.getAttribute( 'data-rest' );
		var nonce  = box.getAttribute( 'data-nonce' );
		var drag   = null;

		function request( method, data ) {
			return window.fetch( url, {
				method: method,
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
				body: data ? JSON.stringify( data ) : undefined,
			} ).then( function ( res ) {
				return res.json().then( function ( json ) {
					if ( ! res.ok ) {
						throw new Error( json && json.message ? json.message : res.statusText );
					}
					return json;
				} );
			} );
		}

		// The method's own card below saves its whole option, Enabled box included: keep it in step with
		// the table, or saving that card afterwards would quietly undo the switch made here.
		function syncCard( id, enabled ) {
			var card = document.getElementById( 'wpss-gateway-' + id );
			if ( ! card ) {
				return;
			}
			var box2 = card.querySelector( 'input[type="checkbox"][name$="_settings[enabled]"]' );
			var badge = card.querySelector( '[data-card-status]' );
			if ( box2 ) {
				box2.checked = enabled;
			}
			if ( badge ) {
				badge.className = 'wpss-gateway-badge wpss-gateway-badge--' + ( enabled ? 'enabled' : 'disabled' );
				badge.textContent = badge.getAttribute( enabled ? 'data-on' : 'data-off' );
			}
		}

		function refresh() {
			return request( 'GET' ).then( function ( json ) {
				( json.methods || [] ).forEach( function ( m ) {
					var row = body.querySelector( 'tr[data-id="' + m.id + '"]' );
					var badge = row && row.querySelector( '[data-status]' );
					if ( badge ) {
						badge.className = 'wpss-gateway-badge wpss-gateway-badge--' + m.status;
						badge.textContent = m.status_label;
						row.querySelector( '.wpss-payment-methods__toggle' ).checked = !! m.enabled;
					}
					syncCard( m.id, !! m.enabled );
				} );
			} );
		}

		function save( data ) {
			box.classList.add( 'is-saving' );
			notice.textContent = '';
			request( 'POST', data )
				.then( refresh )
				.then( function () {
					notice.textContent = box.getAttribute( 'data-saved' );
				} )
				.catch( function ( err ) {
					notice.textContent = err.message;
					refresh();
				} )
				.then( function () {
					box.classList.remove( 'is-saving' );
					// Saved (or reverted by refresh): tell the settings nav there is nothing to lose.
					box.dispatchEvent( new CustomEvent( 'wpss:card-clean' ) );
				} );
		}

		function saveOrder() {
			save( {
				order: Array.prototype.map.call( body.rows, function ( row ) {
					return row.getAttribute( 'data-id' );
				} ),
			} );
		}

		box.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'wpss-payment-methods__toggle' ) ) {
				var enabled = {};
				enabled[ e.target.closest( 'tr' ).getAttribute( 'data-id' ) ] = e.target.checked;
				save( { enabled: enabled } );
			}
		} );

		box.addEventListener( 'click', function ( e ) {
			var move = e.target.closest( '.wpss-payment-methods__move' );
			if ( move ) {
				var row = move.closest( 'tr' );
				var to = '-1' === move.getAttribute( 'data-dir' ) ? row.previousElementSibling : row.nextElementSibling;
				if ( to ) {
					body.insertBefore( row, '-1' === move.getAttribute( 'data-dir' ) ? to : to.nextElementSibling );
					move.focus();
					saveOrder();
				}
				return;
			}

			// The settings tabs are routed by the URL hash, so open the card in place instead of
			// following the anchor (which would switch to the General tab).
			var manage = e.target.closest( '.wpss-payment-methods__manage' );
			var card = manage && document.querySelector( manage.getAttribute( 'href' ) );
			if ( card ) {
				e.preventDefault();
				card.classList.remove( 'is-collapsed' );
				card.scrollIntoView( { block: 'start' } );
				( card.querySelector( 'input, select, textarea, button' ) || card ).focus( { preventScroll: true } );
			}
		} );

		// Drag from the handle only, so text in the row stays selectable.
		box.addEventListener( 'mousedown', function ( e ) {
			var row = e.target.closest( 'tr' );
			if ( row && row.parentNode === body ) {
				row.draggable = !! e.target.closest( '.wpss-payment-methods__handle' );
			}
		} );
		body.addEventListener( 'dragstart', function ( e ) {
			drag = e.target.closest( 'tr' );
			e.dataTransfer.effectAllowed = 'move';
			drag.classList.add( 'is-dragging' );
		} );
		body.addEventListener( 'dragover', function ( e ) {
			var over = e.target.closest( 'tr' );
			if ( ! drag || ! over || over === drag ) {
				return;
			}
			e.preventDefault();
			var box2 = over.getBoundingClientRect();
			body.insertBefore( drag, e.clientY > box2.top + box2.height / 2 ? over.nextElementSibling : over );
		} );
		body.addEventListener( 'dragend', function () {
			if ( drag ) {
				drag.classList.remove( 'is-dragging' );
				drag.draggable = false;
				drag = null;
				saveOrder();
			}
		} );
	}

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	/**
	 * Settings > Features (2.0.0): one switch per module, saved through POST /modules. A switch can
	 * turn others off (a module that needs it), so every card is redrawn from the server's answer.
	 *
	 * @param {NodeList} cards The .wpss-modules cards, one per group.
	 */
	function initModules( cards ) {
		if ( ! cards.length ) {
			return;
		}

		var url   = cards[ 0 ].getAttribute( 'data-rest' );
		var nonce = cards[ 0 ].getAttribute( 'data-nonce' );

		function redraw( groups ) {
			Object.keys( groups || {} ).forEach( function ( key ) {
				groups[ key ].modules.forEach( function ( m ) {
					var toggle = document.querySelector( '.wpss-modules tr[data-id="' + m.id + '"] .wpss-modules__toggle' );
					if ( toggle ) {
						toggle.checked = !! m.enabled;
					}
				} );
			} );
		}

		Array.prototype.forEach.call( cards, function ( card ) {
			card.addEventListener( 'change', function ( e ) {
				if ( ! e.target.classList.contains( 'wpss-modules__toggle' ) ) {
					return;
				}

				var notice  = card.querySelector( '.wpss-modules__notice' );
				var enabled = {};
				enabled[ e.target.closest( 'tr' ).getAttribute( 'data-id' ) ] = e.target.checked;
				card.classList.add( 'is-saving' );
				notice.textContent = '';

				window.fetch( url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
					body: JSON.stringify( { enabled: enabled } ),
				} ).then( function ( res ) {
					return res.json().then( function ( json ) {
						if ( ! res.ok ) {
							throw new Error( json && json.message ? json.message : res.statusText );
						}
						return json;
					} );
				} ).then( function ( json ) {
					redraw( json.groups );
					notice.textContent = card.getAttribute( 'data-saved' );
				} ).catch( function ( err ) {
					e.target.checked = ! e.target.checked;
					notice.textContent = err.message;
				} ).then( function () {
					card.classList.remove( 'is-saving' );
					card.dispatchEvent( new CustomEvent( 'wpss:card-clean' ) );
				} );
			} );
		} );
	}

	ready( function () {
		var fields = document.querySelectorAll( '.wpss-secret-field' );
		Array.prototype.forEach.call( fields, initField );
		initDeleteDataConfirm();
		Array.prototype.forEach.call( document.querySelectorAll( '.wpss-payment-methods' ), initPaymentMethods );
		initModules( document.querySelectorAll( '.wpss-modules' ) );
	} );
} )();
