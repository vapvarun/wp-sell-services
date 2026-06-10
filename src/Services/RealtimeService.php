<?php
/**
 * Realtime Service
 *
 * @package WPSellServices\Services
 * @since   1.2.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Pusher-protocol realtime publisher.
 *
 * Speaks the Pusher Channels HTTP API so one driver covers both Pusher.com
 * and any self-hosted Pusher-compatible server (e.g. Soketi) via a custom
 * host. Disabled by default — every publish no-ops until an admin saves
 * credentials under Settings → Advanced → Real-time (WebSockets).
 *
 * The app secret never leaves the server: clients receive only the
 * publishable key plus connection details ({@see get_client_config()}),
 * and private-channel subscriptions are signed server-side through
 * {@see auth_signature()} behind the REST `/realtime/auth` endpoint.
 *
 * @since 1.2.0
 */
class RealtimeService {

	/**
	 * Option name holding the realtime connection settings.
	 */
	public const OPTION = 'wpss_realtime_settings';

	/**
	 * Get the realtime settings merged with defaults.
	 *
	 * @return array<string, mixed> Settings array.
	 */
	public static function get_settings(): array {
		$defaults = array(
			'enabled' => false,
			'app_id'  => '',
			'key'     => '',
			'secret'  => '',
			'host'    => '',
			'cluster' => 'mt1',
			'port'    => 443,
			'use_tls' => true,
		);

		$stored   = get_option( self::OPTION, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );

		/**
		 * Filter the realtime (Pusher-protocol) connection settings.
		 *
		 * @since 1.2.0
		 *
		 * @param array $settings Settings: enabled, app_id, key, secret, host, cluster, port, use_tls.
		 */
		return apply_filters( 'wpss_realtime_settings', $settings );
	}

	/**
	 * Whether realtime publishing is enabled and fully configured.
	 *
	 * @return bool True when enabled and app_id/key/secret are all present.
	 */
	public function is_enabled(): bool {
		$settings = self::get_settings();

		return ! empty( $settings['enabled'] )
			&& '' !== (string) $settings['app_id']
			&& '' !== (string) $settings['key']
			&& '' !== (string) $settings['secret'];
	}

	/**
	 * Publish an event to a channel via the Pusher HTTP API.
	 *
	 * Fire-and-forget: the request is non-blocking with a 2 second timeout
	 * so a slow or unreachable socket server can never stall the request
	 * that triggered the event.
	 *
	 * @param string               $channel Channel name (e.g. private-wpss-user-5).
	 * @param string               $event   Event name (e.g. message.created).
	 * @param array<string, mixed> $payload Event payload (JSON-encoded for the wire).
	 * @return bool True when the request was dispatched, false otherwise.
	 */
	public function publish( string $channel, string $event, array $payload ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$settings = self::get_settings();

		$body = wp_json_encode(
			array(
				'name'    => $event,
				'channel' => $channel,
				'data'    => wp_json_encode( $payload ),
			)
		);

		if ( false === $body ) {
			return false;
		}

		// Pusher HTTP API request signing: HMAC-SHA256 over
		// "POST\n{path}\n{alphabetically sorted query string}" with the app secret.
		$path  = '/apps/' . $settings['app_id'] . '/events';
		$query = array(
			'auth_key'       => (string) $settings['key'],
			'auth_timestamp' => (string) time(),
			'auth_version'   => '1.0',
			'body_md5'       => md5( $body ),
		);
		ksort( $query );

		$string_to_sign          = "POST\n" . $path . "\n" . http_build_query( $query );
		$query['auth_signature'] = hash_hmac( 'sha256', $string_to_sign, (string) $settings['secret'] );

		$url = $this->get_base_url() . $path . '?' . http_build_query( $query );

		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 2,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			wpss_log( "Realtime publish failed for {$channel}/{$event}: " . $response->get_error_message(), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Build a private-channel auth payload per the Pusher spec.
	 *
	 * Signature is HMAC-SHA256 of "{socket_id}:{channel}" with the app
	 * secret, returned as "key:signature". Channel authorization (who may
	 * subscribe to what) is enforced by the REST controller before this
	 * is called — this method only signs.
	 *
	 * @param string $socket_id Pusher socket ID of the connecting client.
	 * @param string $channel   Channel name being subscribed to.
	 * @return array{auth: string} Auth payload for the client library.
	 */
	public function auth_signature( string $socket_id, string $channel ): array {
		$settings  = self::get_settings();
		$signature = hash_hmac( 'sha256', $socket_id . ':' . $channel, (string) $settings['secret'] );

		return array(
			'auth' => $settings['key'] . ':' . $signature,
		);
	}

	/**
	 * Get the non-sensitive client configuration.
	 *
	 * NEVER includes the app secret or app_id — only what the browser
	 * client needs to open a socket and request channel authorization.
	 *
	 * @return array<string, mixed> Client config.
	 */
	public function get_client_config(): array {
		$settings = self::get_settings();

		return array(
			'enabled'       => $this->is_enabled(),
			'key'           => (string) $settings['key'],
			'host'          => (string) $settings['host'],
			'cluster'       => (string) $settings['cluster'],
			'port'          => (int) $settings['port'],
			'use_tls'       => ! empty( $settings['use_tls'] ),
			'auth_endpoint' => rest_url( 'wpss/v1/realtime/auth' ),
		);
	}

	/**
	 * Base URL of the Pusher-compatible HTTP API.
	 *
	 * Empty host falls back to the official Pusher.com cluster endpoint
	 * (api-{cluster}.pusher.com); a custom host (e.g. a Soketi server)
	 * is used verbatim after stripping any scheme.
	 *
	 * @return string Base URL without trailing slash.
	 */
	private function get_base_url(): string {
		$settings = self::get_settings();

		$host = (string) $settings['host'];
		if ( '' === $host ) {
			$host = 'api-' . $settings['cluster'] . '.pusher.com';
		}

		$host   = (string) preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', untrailingslashit( $host ) );
		$scheme = ! empty( $settings['use_tls'] ) ? 'https' : 'http';

		return $scheme . '://' . $host . ':' . (int) $settings['port'];
	}
}
