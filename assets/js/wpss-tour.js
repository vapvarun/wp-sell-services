/**
 * WPSS Guided Tour Controller (Shepherd.js v11).
 *
 * Scaffold only — step content is populated elsewhere via the PHP
 * `Tour::get_admin_tour_steps()` method and the `wpssTour` localized
 * object. This file owns orchestration: Shepherd instance, step add,
 * completion persistence, Lucide icon refresh.
 *
 * Expected shape of window.wpssTour (set via wp_localize_script):
 *
 *   window.wpssTour = {
 *     steps:       Array<StepConfig>, // Shepherd step defs (id, title, text, attachTo, buttons)
 *     completed:   Boolean,           // True if user has already dismissed/finished
 *     completeUrl: String,            // REST URL for POST /wpss/v1/tour/complete
 *     nonce:       String,            // wp_rest nonce
 *     start:       Function           // injected below — re-run the tour
 *   };
 *
 * @package WPSellServices
 * @since   1.1.0
 */

( function () {
	'use strict';

	/**
	 * POST to the completion endpoint so the user meta is flipped and
	 * the tour won't auto-start on future page loads.
	 *
	 * @return {void}
	 */
	function persistCompletion() {
		if ( ! window.wpssTour || ! window.wpssTour.completeUrl ) {
			return;
		}

		try {
			window.fetch( window.wpssTour.completeUrl, {
				method:      'POST',
				credentials: 'same-origin',
				headers:     {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   window.wpssTour.nonce || ''
				},
				body: JSON.stringify( {} )
			} ).catch( function () { /* swallow — best effort */ } );
		} catch ( e ) {
			// noop — persistence failure should never break the UI.
		}

		window.wpssTour.completed = true;
	}

	/**
	 * Refresh any `<i data-lucide="...">` placeholders that step content
	 * may have rendered into the DOM. Safe to call repeatedly.
	 *
	 * @return {void}
	 */
	function refreshLucide() {
		if ( window.lucide && 'function' === typeof window.lucide.createIcons ) {
			window.lucide.createIcons();
		}
	}

	/**
	 * Build a Shepherd.Tour from the localized config and wire lifecycle events.
	 *
	 * @return {object|null} Shepherd.Tour instance or null if unavailable.
	 */
	function buildTour() {
		if ( ! window.Shepherd || ! window.wpssTour ) {
			return null;
		}

		var tour = new window.Shepherd.Tour( {
			useModalOverlay: true,
			defaultStepOptions: {
				scrollTo:   { behavior: 'smooth', block: 'center' },
				cancelIcon: { enabled: true },
				classes:    'wpss-shepherd wpss-shepherd--admin',
				arrow:      true
			},
			exitOnEsc:      true,
			keyboardNavigation: true
		} );

		var steps = Array.isArray( window.wpssTour.steps ) ? window.wpssTour.steps : [];

		// Translate string `action` values coming from PHP into real Shepherd
		// callbacks. Step authors in PHP can declare `action: 'next' | 'back'
		// | 'cancel' | 'complete'` without needing JS closures.
		var actionMap = {
			next:     function () { tour.next(); },
			back:     function () { tour.back(); },
			cancel:   function () { tour.cancel(); },
			complete: function () { tour.complete(); }
		};

		function normalizeButtons( buttons ) {
			if ( ! Array.isArray( buttons ) ) {
				return buttons;
			}
			return buttons.map( function ( btn ) {
				if ( btn && 'string' === typeof btn.action && actionMap[ btn.action ] ) {
					return Object.assign( {}, btn, { action: actionMap[ btn.action ] } );
				}
				return btn;
			} );
		}

		// If a step's `attachTo.element` isn't in the DOM, drop `attachTo`
		// so Shepherd renders the step centered instead of aborting with
		// "The element for this Shepherd step was not found" — keeps the
		// tour walking even when selectors drift.
		function resolveAttachTo( attachTo ) {
			if ( ! attachTo || ! attachTo.element ) {
				return attachTo;
			}
			try {
				if ( document.querySelector( attachTo.element ) ) {
					return attachTo;
				}
			} catch ( e ) {
				// Invalid selector — drop it.
			}
			return null;
		}

		steps.forEach( function ( step ) {
			var prepared = Object.assign( {}, step );
			if ( step.buttons ) {
				prepared.buttons = normalizeButtons( step.buttons );
			}
			var resolved = resolveAttachTo( step.attachTo );
			if ( resolved ) {
				prepared.attachTo = resolved;
			} else {
				delete prepared.attachTo;
			}
			tour.addStep( prepared );
		} );

		// After each step renders, resolve any Lucide icon placeholders
		// that step text may contain.
		tour.on( 'show', refreshLucide );

		tour.on( 'complete', persistCompletion );
		tour.on( 'cancel',   persistCompletion );

		return tour;
	}

	/**
	 * Boot sequence: wait for DOM, bail if there's nothing to show or
	 * the user has already completed the tour, then start.
	 *
	 * @return {void}
	 */
	function boot() {
		if ( ! window.wpssTour ) {
			return;
		}

		var steps = Array.isArray( window.wpssTour.steps ) ? window.wpssTour.steps : [];

		// Always expose start() so dashboards can trigger "Replay tour".
		window.wpssTour.start = function () {
			var replayTour = buildTour();
			if ( replayTour ) {
				replayTour.start();
				refreshLucide();
			}
		};

		if ( 0 === steps.length ) {
			return;
		}

		// wp_localize_script may cast the bool to the string "1" / "",
		// so accept either truthy scalar as "already completed".
		var completedFlag = window.wpssTour.completed;
		if ( true === completedFlag || '1' === completedFlag || 1 === completedFlag ) {
			return;
		}

		var tour = buildTour();
		if ( ! tour ) {
			return;
		}

		tour.start();
		refreshLucide();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
