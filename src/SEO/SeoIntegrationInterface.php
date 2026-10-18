<?php
/**
 * SEO Integration Interface
 *
 * Contract for a third-party SEO plugin integration. Register one on the
 * `wpss_seo_integrations` filter; core never names an SEO plugin.
 *
 * @package WPSellServices\SEO
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * An integration with one SEO plugin.
 *
 * @since 1.9.0
 */
interface SeoIntegrationInterface {

	/**
	 * Unique id, matching the registry key.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Whether the SEO plugin is active. When true, core stops printing its own meta, title and Open Graph tags.
	 *
	 * @return bool
	 */
	public function is_active(): bool;

	/**
	 * Add the plugin's hooks. Called only when is_active() is true.
	 *
	 * @return void
	 */
	public function init(): void;
}
