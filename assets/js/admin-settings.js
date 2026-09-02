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

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var fields = document.querySelectorAll( '.wpss-secret-field' );
		Array.prototype.forEach.call( fields, initField );
		initDeleteDataConfirm();
	} );
} )();
