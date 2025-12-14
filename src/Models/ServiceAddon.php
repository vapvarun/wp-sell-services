<?php
/**
 * Service Add-on Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents a service add-on (extra service option).
 *
 * @since 1.0.0
 */
class ServiceAddon {

	/**
	 * Add-on ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Service ID.
	 *
	 * @var int
	 */
	public int $service_id;

	/**
	 * Add-on name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Add-on description.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * Add-on price.
	 *
	 * @var float
	 */
	public float $price;

	/**
	 * Additional delivery days (added to package delivery).
	 *
	 * @var int
	 */
	public int $extra_days = 0;

	/**
	 * Maximum quantity allowed (0 for unlimited).
	 *
	 * @var int
	 */
	public int $max_quantity = 1;

	/**
	 * Whether add-on is required.
	 *
	 * @var bool
	 */
	public bool $is_required = false;

	/**
	 * Sort order for display.
	 *
	 * @var int
	 */
	public int $sort_order = 0;

	/**
	 * Whether add-on is active.
	 *
	 * @var bool
	 */
	public bool $is_active = true;

	/**
	 * Created timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $created_at;

	/**
	 * Updated timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $updated_at;

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_db( object $row ): self {
		$addon = new self();

		$addon->id           = (int) $row->id;
		$addon->service_id   = (int) $row->service_id;
		$addon->name         = $row->name;
		$addon->description  = $row->description ?? '';
		$addon->price        = (float) $row->price;
		$addon->extra_days   = (int) $row->extra_days;
		$addon->max_quantity = (int) $row->max_quantity;
		$addon->is_required  = (bool) $row->is_required;
		$addon->sort_order   = (int) $row->sort_order;
		$addon->is_active    = (bool) $row->is_active;
		$addon->created_at   = $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$addon->updated_at   = $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;

		return $addon;
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Add-on data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$addon = new self();

		$addon->id           = (int) ( $data['id'] ?? 0 );
		$addon->service_id   = (int) ( $data['service_id'] ?? 0 );
		$addon->name         = $data['name'] ?? '';
		$addon->description  = $data['description'] ?? '';
		$addon->price        = (float) ( $data['price'] ?? 0 );
		$addon->extra_days   = (int) ( $data['extra_days'] ?? 0 );
		$addon->max_quantity = (int) ( $data['max_quantity'] ?? 1 );
		$addon->is_required  = (bool) ( $data['is_required'] ?? false );
		$addon->sort_order   = (int) ( $data['sort_order'] ?? 0 );
		$addon->is_active    = (bool) ( $data['is_active'] ?? true );

		return $addon;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'           => $this->id,
			'service_id'   => $this->service_id,
			'name'         => $this->name,
			'description'  => $this->description,
			'price'        => $this->price,
			'extra_days'   => $this->extra_days,
			'max_quantity' => $this->max_quantity,
			'is_required'  => $this->is_required,
			'sort_order'   => $this->sort_order,
			'is_active'    => $this->is_active,
		];
	}

	/**
	 * Get formatted price.
	 *
	 * @param string $currency Currency code.
	 * @return string
	 */
	public function get_formatted_price( string $currency = 'USD' ): string {
		return wpss_format_price( $this->price, $currency );
	}

	/**
	 * Calculate total for quantity.
	 *
	 * @param int $quantity Quantity.
	 * @return float
	 */
	public function calculate_total( int $quantity ): float {
		$qty = max( 1, min( $quantity, $this->max_quantity > 0 ? $this->max_quantity : PHP_INT_MAX ) );
		return $this->price * $qty;
	}

	/**
	 * Calculate extra delivery days for quantity.
	 *
	 * @param int $quantity Quantity.
	 * @return int
	 */
	public function calculate_extra_days( int $quantity ): int {
		return $this->extra_days * $quantity;
	}
}
