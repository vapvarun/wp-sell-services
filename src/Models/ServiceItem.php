<?php
/**
 * Service Item Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents a service item in an order.
 *
 * This is a platform-agnostic representation of a service line item
 * that can be used across different e-commerce integrations.
 *
 * @since 1.0.0
 */
class ServiceItem {

	/**
	 * Unique item identifier (may be platform-specific).
	 *
	 * @var string|int
	 */
	public string|int $id = 0;

	/**
	 * Platform-specific item ID.
	 *
	 * @var int
	 */
	public int $item_id = 0;

	/**
	 * Order ID this item belongs to.
	 *
	 * @var int
	 */
	public int $order_id = 0;

	/**
	 * Service post ID.
	 *
	 * @var int
	 */
	public int $service_id = 0;

	/**
	 * Service name.
	 *
	 * @var string
	 */
	public string $service_name = '';

	/**
	 * Package ID.
	 *
	 * @var int
	 */
	public int $package_id = 0;

	/**
	 * Package name.
	 *
	 * @var string
	 */
	public string $package_name = '';

	/**
	 * Vendor user ID.
	 *
	 * @var int
	 */
	public int $vendor_id = 0;

	/**
	 * Item unit price.
	 *
	 * @var float
	 */
	public float $price = 0.0;

	/**
	 * Item subtotal.
	 *
	 * @var float
	 */
	public float $subtotal = 0.0;

	/**
	 * Item total.
	 *
	 * @var float
	 */
	public float $total = 0.0;

	/**
	 * Item quantity.
	 *
	 * @var int
	 */
	public int $quantity = 1;

	/**
	 * Item status.
	 *
	 * @var string
	 */
	public string $status = 'pending';

	/**
	 * Selected addons.
	 *
	 * @var array<int, array{id: int, name: string, price: float}>
	 */
	public array $addons = [];

	/**
	 * Requirements collected from customer.
	 *
	 * @var array<string, mixed>
	 */
	public array $requirements = [];

	/**
	 * Additional meta data.
	 *
	 * @var array<string, mixed>
	 */
	public array $meta = [];

	/**
	 * Constructor.
	 *
	 * @param int   $item_id    Platform item ID.
	 * @param int   $service_id Service post ID.
	 * @param int   $package_id Package ID.
	 * @param int   $vendor_id  Vendor user ID.
	 * @param float $price      Item price.
	 * @param int   $quantity   Item quantity.
	 * @param array $addons     Selected addons.
	 * @param array $meta       Additional meta.
	 */
	public function __construct(
		int $item_id = 0,
		int $service_id = 0,
		int $package_id = 0,
		int $vendor_id = 0,
		float $price = 0.0,
		int $quantity = 1,
		array $addons = [],
		array $meta = []
	) {
		$this->item_id    = $item_id;
		$this->id         = $item_id;
		$this->service_id = $service_id;
		$this->package_id = $package_id;
		$this->vendor_id  = $vendor_id;
		$this->price      = $price;
		$this->quantity   = $quantity;
		$this->addons     = $addons;
		$this->meta       = $meta;
	}

	/**
	 * Get the total price for this item.
	 *
	 * @return float
	 */
	public function get_total(): float {
		$addons_total = array_reduce(
			$this->addons,
			fn( float $carry, array $addon ) => $carry + ( $addon['price'] ?? 0.0 ),
			0.0
		);

		return ( $this->price + $addons_total ) * $this->quantity;
	}

	/**
	 * Get the service post.
	 *
	 * @return \WP_Post|null
	 */
	public function get_service(): ?\WP_Post {
		return get_post( $this->service_id );
	}

	/**
	 * Get the vendor user.
	 *
	 * @return \WP_User|null
	 */
	public function get_vendor(): ?\WP_User {
		if ( ! $this->vendor_id ) {
			return null;
		}

		$user = get_userdata( $this->vendor_id );
		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Check if item has addons.
	 *
	 * @return bool
	 */
	public function has_addons(): bool {
		return ! empty( $this->addons );
	}

	/**
	 * Get meta value.
	 *
	 * @param string $key     Meta key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_meta( string $key, mixed $default = null ): mixed {
		return $this->meta[ $key ] ?? $default;
	}

	/**
	 * Set meta value.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	public function set_meta( string $key, mixed $value ): void {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Item data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$item = new self(
			(int) ( $data['item_id'] ?? 0 ),
			(int) ( $data['service_id'] ?? 0 ),
			(int) ( $data['package_id'] ?? 0 ),
			(int) ( $data['vendor_id'] ?? 0 ),
			(float) ( $data['price'] ?? 0.0 ),
			(int) ( $data['quantity'] ?? 1 ),
			$data['addons'] ?? [],
			$data['meta'] ?? []
		);

		// Set additional properties if provided.
		if ( isset( $data['id'] ) ) {
			$item->id = $data['id'];
		}
		if ( isset( $data['order_id'] ) ) {
			$item->order_id = (int) $data['order_id'];
		}
		if ( isset( $data['service_name'] ) ) {
			$item->service_name = $data['service_name'];
		}
		if ( isset( $data['package_name'] ) ) {
			$item->package_name = $data['package_name'];
		}
		if ( isset( $data['subtotal'] ) ) {
			$item->subtotal = (float) $data['subtotal'];
		}
		if ( isset( $data['total'] ) ) {
			$item->total = (float) $data['total'];
		}
		if ( isset( $data['status'] ) ) {
			$item->status = $data['status'];
		}
		if ( isset( $data['requirements'] ) ) {
			$item->requirements = (array) $data['requirements'];
		}

		return $item;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'item_id'    => $this->item_id,
			'service_id' => $this->service_id,
			'package_id' => $this->package_id,
			'vendor_id'  => $this->vendor_id,
			'price'      => $this->price,
			'quantity'   => $this->quantity,
			'addons'     => $this->addons,
			'meta'       => $this->meta,
			'total'      => $this->get_total(),
		];
	}
}
