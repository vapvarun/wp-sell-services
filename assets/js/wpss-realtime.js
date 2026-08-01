/**
 * WP Sell Services — realtime client.
 *
 * Thin bridge over the vendored pusher-js library. Connects to the
 * configured Pusher-protocol server (Pusher.com or a self-hosted
 * compatible server such as Soketi), subscribes to the current user's
 * private channel, and re-broadcasts incoming events as document
 * CustomEvents so any UI module can react without coupling to the
 * transport:
 *
 *   - 'wpss:realtime:notification'  detail: { id, type }
 *   - 'wpss:realtime:message'       detail: { order_id, sender_id, message_id, excerpt }
 *
 * Also increments any [data-wpss-notification-count] badge in place when
 * a notification arrives.
 *
 * No-ops entirely when realtime is disabled or the config is missing —
 * the script is only enqueued when realtime is enabled and the user is
 * logged in, but this guard keeps it safe regardless.
 *
 * Config is provided via wp_localize_script as `wpssRealtime`:
 * { enabled, key, host, cluster, port, use_tls, auth_endpoint, userId, restNonce }
 *
 * @package WPSellServices
 * @since   1.2.0
 */
(function () {
	'use strict';

	var cfg = window.wpssRealtime;

	if (!cfg || !cfg.enabled || !cfg.key || !cfg.userId || typeof window.Pusher === 'undefined') {
		return;
	}

	var useTls = !!cfg.use_tls;
	var port = parseInt(cfg.port, 10) || (useTls ? 443 : 80);

	var options = {
		cluster: cfg.cluster || 'mt1',
		forceTLS: useTls,
		// Private-channel subscriptions are authorized server-side via the
		// plugin's REST endpoint — cookie auth + X-WP-Nonce header.
		channelAuthorization: {
			transport: 'ajax',
			endpoint: cfg.auth_endpoint,
			headers: { 'X-WP-Nonce': cfg.restNonce }
		}
	};

	// Custom host = self-hosted Pusher-compatible server (e.g. Soketi).
	// Empty host = Pusher.com, resolved from the cluster by pusher-js.
	if (cfg.host) {
		options.wsHost = cfg.host;
		options.wsPort = port;
		options.wssPort = port;
		options.enabledTransports = ['ws', 'wss'];
		options.disableStats = true;
	}

	var pusher = new window.Pusher(cfg.key, options);
	var channel = pusher.subscribe('private-wpss-user-' + cfg.userId);

	/**
	 * Increment every notification-count badge on the page.
	 */
	function bumpNotificationBadges() {
		var badges = document.querySelectorAll('[data-wpss-notification-count]');
		Array.prototype.forEach.call(badges, function (badge) {
			var current = parseInt((badge.textContent || '').replace(/\D/g, ''), 10) || 0;
			badge.textContent = String(current + 1);
			badge.removeAttribute('hidden');
		});
	}

	/**
	 * Re-broadcast a realtime event as a document CustomEvent.
	 *
	 * @param {string} name   Event name (wpss:realtime:*).
	 * @param {Object} detail Event payload.
	 */
	var strings = (cfg && cfg.i18n) || {};

	function broadcast(name, detail) {
		document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
	}

	/**
	 * Show a realtime event to the user.
	 *
	 * The CustomEvents below stay the public extension point, but nothing in
	 * either plugin listened to them and no shipped template contains a
	 * [data-wpss-notification-count] badge — so a correctly configured site
	 * published to Pusher, the browser received the event, and the user saw
	 * nothing at all. Render through the shared toast helper rather than adding
	 * a second notification UI.
	 *
	 * @param {string} message Text to display.
	 */
	function render(message) {
		if (!message) {
			return;
		}

		if (typeof window.wpssToast === 'function') {
			window.wpssToast(message, 'info');
		}
	}

	channel.bind('notification.created', function (data) {
		broadcast('wpss:realtime:notification', data);
		bumpNotificationBadges();
		render((data && (data.message || data.title)) || strings.notification);
	});

	channel.bind('message.created', function (data) {
		broadcast('wpss:realtime:message', data);
		render((data && data.excerpt) || strings.message);
	});
})();
