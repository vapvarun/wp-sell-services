<?php
/**
 * Service Package Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents a service package (Basic/Standard/Premium tiers).
 *
 * @since 1.0.0
 */
class ServicePackage {

	/**
	 * Package ID.
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
	 * Package name (e.g., Basic, Standard, Premium).
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Package description.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * Package price.
	 *
	 * @var float
	 */
	public float $price;

	/**
	 * Delivery time in days.
	 *
	 * @var int
	 */
	public int $delivery_days;

	/**
	 * Number of revisions included (-1 for unlimited).
	 *
	 * @var int
	 */
	public int $revisions = 0;

	/**
	 * Package features/inclusions.
	 *
	 * @var array<string>
	 */
	public array $features = [];

	/**
	 * Sort order for display.
	 *
	 * @var int
	 */
	public int $sort_order = 0;

	/**
	 * Whether package is active.
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
		$package = new self();

		$package->id            = (int) $row->id;
		$package->service_id    = (int) $row->service_id;
		$package->name          = $row->name;
		$package->description   = $row->description ?? '';
		$package->price         = (float) $row->price;
		$package->delivery_days = (int) $row->delivery_days;
		$package->revisions     = (int) $row->revisions;
		$package->features      = $row->features ? json_decode( $row->features, true ) : [];
		$package->sort_order    = (int) $row->sort_order;
		$package->is_active     = (bool) $row->is_active;
		$package->created_at    = $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$package->updated_at    = $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;

		return $package;
	}

	/**
	 * Create from array (e.g., from post meta).
	 *
	 * @param array $data Package data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$package = new self();

		$package->id            = (int) ( $data['id'] ?? 0 );
		$package->service_id    = (int) ( $data['service_id'] ?? 0 );
		$package->name          = $data['name'] ?? '';
		$package->description   = $data['description'] ?? '';
		$package->price         = (float) ( $data['price'] ?? 0 );
		$package->delivery_days = (int) ( $data['delivery_days'] ?? 1 );
		$package->revisions     = (int) ( $data['revisions'] ?? 0 );
		$package->features      = $data['features'] ?? [];
		$package->sort_order    = (int) ( $data['sort_order'] ?? 0 );
		$package->is_active     = (bool) ( $data['is_active'] ?? true );

		return $package;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'            => $this->id,
			'service_id'    => $this->service_id,
			'name'          => $this->name,
			'description'   => $this->description,
			'price'         => $this->price,
			'delivery_days' => $this->delivery_days,
			'revisions'     => $this->revisions,
			'features'      => $this->features,
			'sort_order'    => $this->sort_order,
			'is_active'     => $this->is_active,
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
	 * Get delivery time label.
	 *
	 * @return string
	 */
	public function get_delivery_label(): string {
		if ( 1 === $this->delivery_days ) {
			return __( '1 day delivery', 'wp-sell-services' );
		}

		/* translators: %d: number of days */
		return sprintf( __( '%d days delivery', 'wp-sell-services' ), $this->delivery_days );
	}

	/**
	 * Get revisions label.
	 *
	 * @return string
	 */
	public function get_revisions_label(): string {
		if ( -1 === $this->revisions ) {
			return __( 'Unlimited revisions', 'wp-sell-services' );
		}

		if ( 0 === $this->revisions ) {
			return __( 'No revisions', 'wp-sell-services' );
		}

		/* translators: %d: number of revisions */
		return sprintf(
			_n( '%d revision', '%d revisions', $this->revisions, 'wp-sell-services' ),
			$this->revisions
		);
	}
}
