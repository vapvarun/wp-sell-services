<?php
/**
 * Integration seam contract: a provider registered ONLY through its filter works, and a bad entry is
 * dropped with a notice, so a new third-party integration never needs a core edit.
 * Standard: docs/standards/integration-seams.md.
 *
 * Run: wp eval-file tests/test-integration-seam-contract.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Integrations\Contracts\EcommerceAdapterInterface;
use WPSellServices\Integrations\Contracts\PaymentGatewayInterface;
use WPSellServices\Integrations\Contracts\ProductTypeInterface;
use WPSellServices\Integrations\Contracts\StorageProviderInterface;
use WPSellServices\Integrations\Contracts\WalletProviderInterface;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

/** Build a stub class implementing every method of an interface (get_id returns $id). */
$stub = static function ( string $interface, string $id ): object {
	static $n = 0;
	$class = 'WpssSeamStub' . ( ++$n );
	$code  = "class {$class} implements \\{$interface} {";
	foreach ( ( new ReflectionClass( $interface ) )->getMethods() as $m ) {
		$params = array();
		foreach ( $m->getParameters() as $p ) {
			$type     = $p->getType() ? $p->getType() . ' ' : '';
			$default  = $p->isDefaultValueAvailable() ? ' = ' . var_export( $p->getDefaultValue(), true ) : '';
			$params[] = $type . '$' . $p->getName() . $default;
		}
		$ret = $m->getReturnType();
		if ( 'get_id' === $m->getName() ) {
			$body = "return '{$id}';";
		} elseif ( $ret && ! $ret->allowsNull() && 'void' !== (string) $ret ) {
			$body = 'throw new \\LogicException( "stub" );';
		} else {
			$body = 'return' . ( $ret && 'void' === (string) $ret ? '' : ' null' ) . ';';
		}
		$code .= 'public function ' . $m->getName() . '(' . implode( ', ', $params ) . ')' . ( $ret ? ': ' . $ret : '' ) . " { {$body} }";
	}
	$code .= '}';
	eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test stub generation.
	return new $class();
};

// Silence the expected developer notices.
add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
$notices = 0;
add_action( 'doing_it_wrong_run', static function ( $fn ) use ( &$notices ) {
	if ( 0 === strpos( (string) $fn, 'wpss_' ) ) {
		++$notices;
	}
} );

foreach ( array(
	array( StorageProviderInterface::class, 'wpss_storage_providers' ),
	array( WalletProviderInterface::class, 'wpss_wallet_providers' ),
	array( EcommerceAdapterInterface::class, 'wpss_ecommerce_adapters' ),
	array( PaymentGatewayInterface::class, 'wpss_payment_gateways' ),
	array( ProductTypeInterface::class, 'wpss_product_types' ),
) as list( $contract, $filter ) ) {
	$good   = $stub( $contract, 'acme' );
	$result = wpss_validate_registry( array( 'acme' => $good, 'rogue' => new stdClass(), 7 => $good ), $contract, $filter );
	$check( "{$filter}: a conforming provider is kept", isset( $result['acme'] ) && $result['acme'] === $good );
	$check( "{$filter}: an object that is not the contract is dropped", ! isset( $result['rogue'] ) );
	$check( "{$filter}: a numeric id is dropped", ! isset( $result[7] ) );
}
$check( 'a developer notice names the filter for each dropped entry', 10 === $notices );

$check( 'the live gateway registry holds only gateways', count( array_filter( wpss()->get_payment_gateways(), static fn( $g ) => ! $g instanceof PaymentGatewayInterface ) ) === 0 );

// End to end through the live filter: storage is read by id at upload time.
$storage = $stub( StorageProviderInterface::class, 'acme' );
add_filter( 'wpss_storage_providers', static fn( $p ) => array_merge( (array) $p, array( 'acme' => $storage ) ) );
$check( 'wpss_get_storage_provider() resolves a provider added only by the filter', wpss_get_storage_provider( 'acme' ) === $storage );
$check( 'wpss_get_storage_provider() refuses an unregistered id', null === wpss_get_storage_provider( 'nope' ) );

// SEO: a plugin integration added only by the filter makes core step aside.
$seo_stub = new class() implements \WPSellServices\SEO\SeoIntegrationInterface {
	/** @var bool */
	public bool $started = false;

	public function get_id(): string {
		return 'acme_seo';
	}

	public function is_active(): bool {
		return true;
	}

	public function init(): void {
		$this->started = true;
	}
};
$seo_add  = static fn( $i ) => array_merge( (array) $i, array( 'acme_seo' => $seo_stub ) );
add_filter( 'wpss_seo_integrations', $seo_add );
$seo = new \WPSellServices\SEO\SEO();
$seo->init();
$check( 'an SEO integration added only by wpss_seo_integrations is started', $seo_stub->started );
$check( 'core SEO tags step aside for it', $seo->has_active_seo_plugin() );
remove_filter( 'wpss_seo_integrations', $seo_add );
$bare = new \WPSellServices\SEO\SEO();
$bare->init();
$check( 'with no SEO plugin active core prints its own tags', ! $bare->has_active_seo_plugin() || defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) );

// Pro's interfaces extend Free's, so Pro providers satisfy the Free contract.
if ( interface_exists( '\WPSellServicesPro\Storage\StorageProviderInterface' ) ) {
	$check( 'Pro StorageProviderInterface extends the Free contract', is_subclass_of( '\WPSellServicesPro\Storage\StorageProviderInterface', StorageProviderInterface::class ) );
	$check( 'Pro WalletProviderInterface extends the Free contract', is_subclass_of( '\WPSellServicesPro\Integrations\Wallets\WalletProviderInterface', WalletProviderInterface::class ) );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
