<?php
/**
 * Favorites service - single source of truth for favorite services.
 *
 * @package WPSellServices
 */

namespace WPSellServices\Services;

/**
 * Canonical accessor for a user's favorite services.
 *
 * History: the AJAX handlers and dashboard template stored favorites in
 * `_wpss_favorite_services` while the REST FavoritesController used
 * `_wpss_favorites` - two divergent datasets since 1.1.1. Every reader and
 * writer now goes through this service. The legacy REST key is merged into
 * the canonical key lazily on first read per user, then deleted, so no
 * production favorite is lost regardless of which surface wrote it.
 *
 * @since 1.2.0
 */
class FavoritesService {

	/**
	 * Canonical user-meta key (the historical AJAX/dashboard key).
	 */
	public const META_KEY = '_wpss_favorite_services';

	/**
	 * Legacy REST-controller key, merged on read and removed.
	 */
	public const LEGACY_META_KEY = '_wpss_favorites';

	/**
	 * Get the user's favorite service IDs.
	 *
	 * Performs the one-time legacy-key merge for this user when needed.
	 *
	 * @param int $user_id User ID.
	 * @return int[] Service IDs.
	 */
	public static function get_ids( int $user_id ): array {
		if ( ! $user_id ) {
			return array();
		}

		$canonical = get_user_meta( $user_id, self::META_KEY, true );
		$canonical = is_array( $canonical ) ? array_map( 'intval', $canonical ) : array();

		$legacy = get_user_meta( $user_id, self::LEGACY_META_KEY, true );
		if ( is_array( $legacy ) && ! empty( $legacy ) ) {
			$canonical = array_values( array_unique( array_merge( $canonical, array_map( 'intval', $legacy ) ) ) );
			update_user_meta( $user_id, self::META_KEY, $canonical );
		}
		if ( false !== $legacy && '' !== $legacy ) {
			delete_user_meta( $user_id, self::LEGACY_META_KEY );
		}

		return array_values( array_filter( $canonical ) );
	}

	/**
	 * Whether a service is in the user's favorites.
	 *
	 * @param int $user_id    User ID.
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public static function is_favorited( int $user_id, int $service_id ): bool {
		return in_array( $service_id, self::get_ids( $user_id ), true );
	}

	/**
	 * Add a service to the user's favorites.
	 *
	 * @param int $user_id    User ID.
	 * @param int $service_id Service ID.
	 * @return int[] Updated favorite IDs.
	 */
	public static function add( int $user_id, int $service_id ): array {
		$favorites = self::get_ids( $user_id );

		if ( $service_id && ! in_array( $service_id, $favorites, true ) ) {
			$favorites[] = $service_id;
			update_user_meta( $user_id, self::META_KEY, $favorites );
		}

		return $favorites;
	}

	/**
	 * Remove a service from the user's favorites.
	 *
	 * @param int $user_id    User ID.
	 * @param int $service_id Service ID.
	 * @return int[] Updated favorite IDs.
	 */
	public static function remove( int $user_id, int $service_id ): array {
		$favorites = array_values(
			array_filter(
				self::get_ids( $user_id ),
				static fn( int $id ): bool => $id !== $service_id
			)
		);

		update_user_meta( $user_id, self::META_KEY, $favorites );

		return $favorites;
	}
}
