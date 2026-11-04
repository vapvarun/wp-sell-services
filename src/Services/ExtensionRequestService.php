<?php
/**
 * Extension Request Service
 *
 * Holds the extension request status constants. Requests are created and
 * answered by ExtensionOrderService (the paid extension flow) and read by
 * ExtensionRequestsController.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Manages deadline extension requests.
 *
 * @since 1.0.0
 */
class ExtensionRequestService {

	/**
	 * Extension request statuses.
	 */
	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';
}
