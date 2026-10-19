<?php
/**
 * All in One SEO Integration
 *
 * @package WPSellServices\SEO
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * All in One SEO owns the page's tags; this plugin only steps aside.
 *
 * @since 1.9.0
 */
class AioseoIntegration implements SeoIntegrationInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'aioseo';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return defined( 'AIOSEO_VERSION' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function init(): void {}
}
