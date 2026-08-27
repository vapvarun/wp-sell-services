<?php
/**
 * Wallet provider contract.
 *
 * The wallet provider layer is READ-ONLY. It answers "what does this vendor
 * hold"; it never moves money. Money is credited by CommissionService on
 * wpss_order_paid and debited by EarningsService on withdrawal. Pro's write
 * half was deleted in 1.7.0 because it gated on auto_payout_to_wallet - a key
 * nothing wrote - and running it alongside CommissionService double-credited.
 *
 * Run: wp eval-file tests/test-wallet-provider-contract.php
 *
 * @package WPSellServices
 */

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

$free = dirname( __DIR__ );
$pro  = dirname( $free ) . '/wp-sell-services-pro';

echo "\nWallet provider contract\n\n";

// 1. The write half is gone, everywhere, including the key that gated it.
$src = '';
foreach ( array( $free . '/src', $free . '/templates', $pro . '/src' ) as $dir ) {
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
	foreach ( $it as $f ) {
		if ( 'php' === $f->getExtension() ) {
			$src .= file_get_contents( $f->getPathname() );
		}
	}
}
// Strip comments so the explanatory notes do not read as live code.
$code = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $src );

wpss_t( false === strpos( $code, 'auto_payout_to_wallet' ), 'auto_payout_to_wallet appears in no live code' );
wpss_t( false === strpos( $code, 'process_vendor_payout' ), 'process_vendor_payout is gone' );
wpss_t( false === strpos( $code, 'get_active_wallet_provider' ), "Free's duplicate accessor is gone" );

if ( ! class_exists( '\WPSellServicesPro\Integrations\Wallets\WalletManager' ) ) {
	echo "\n  SKIP  Pro not active - provider resolution not asserted\n";
	echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
	return;
}

$mgr = \WPSellServicesPro\Integrations\Wallets\WalletManager::get_instance();
$ref = new ReflectionClass( $mgr );

// 2. WalletManager exposes no way to move money.
foreach ( array( 'credit', 'debit', 'handle_withdrawal_completed' ) as $m ) {
	wpss_t( ! $ref->hasMethod( $m ), "WalletManager::{$m}() no longer exists" );
}

// 3. A configured-but-inactive provider falls to internal, never to a
//    different third-party wallet. This is the substitution bug.
$providers = $ref->getProperty( 'providers' );
$providers->setAccessible( true );
$all = $providers->getValue( $mgr );

$stub_a = new class() implements \WPSellServicesPro\Integrations\Wallets\WalletProviderInterface {
	public function get_id(): string {
		return 'stub_chosen'; }
	public function get_name(): string {
		return 'Chosen'; }
	public function is_active(): bool {
		return false; }
	public function get_balance( int $user_id ): float {
		return 999.0; }
	public function credit( int $user_id, float $amount, string $description = '', array $meta = array() ): bool {
		return false; }
	public function debit( int $user_id, float $amount, string $description = '', array $meta = array() ): bool {
		return false; }
	public function get_transactions( int $user_id, int $limit = 20, int $offset = 0 ): array {
		return array(); }
	public function init(): void {}
	public function has_sufficient_balance( int $user_id, float $amount ): bool {
		return false; }
};
$stub_b = new class() implements \WPSellServicesPro\Integrations\Wallets\WalletProviderInterface {
	public function get_id(): string {
		return 'stub_other'; }
	public function get_name(): string {
		return 'Other'; }
	public function is_active(): bool {
		return true; }
	public function get_balance( int $user_id ): float {
		return 12345.0; }
	public function credit( int $user_id, float $amount, string $description = '', array $meta = array() ): bool {
		return false; }
	public function debit( int $user_id, float $amount, string $description = '', array $meta = array() ): bool {
		return false; }
	public function get_transactions( int $user_id, int $limit = 20, int $offset = 0 ): array {
		return array(); }
	public function init(): void {}
	public function has_sufficient_balance( int $user_id, float $amount ): bool {
		return false; }
};

// Order matters. The deleted loop scanned providers in registration order and
// would have stopped at the first active one, so the stubs must sit BEFORE
// internal for this to discriminate. On a stock install internal registers
// first, which is why the old loop never actually misbehaved in the wild - the
// assertion below is about the filtered install where it would have.
$providers->setValue( $mgr, array( 'stub_chosen' => $stub_a, 'stub_other' => $stub_b ) + $all );

$restore = get_option( 'wpss_wallet_provider' );
update_option( 'wpss_wallet_provider', 'stub_chosen' );

$resolve = $ref->getMethod( 'set_active_provider' );
$resolve->setAccessible( true );
$resolve->invoke( $mgr );

$active = $mgr->get_active_provider();
wpss_t( $active && 'internal' === $active->get_id(), 'inactive chosen provider falls to internal even when another active provider is registered first (got: ' . ( $active ? $active->get_id() : 'null' ) . ')' );
wpss_t( null !== $active, 'active provider is never null - null would report 0.00 and lock vendors out' );

// 4. Internal reads the same ledger Free writes. If these disagree the wallet
//    page and the withdrawal gate are quoting different numbers again.
$vendor = get_users(
	array(
		'role__in' => array( 'wpss_vendor', 'administrator' ),
		'number'   => 1,
		'fields'   => 'ID',
	)
);
if ( $vendor ) {
	$vid = (int) $vendor[0];
	wpss_t(
		abs( $mgr->get_balance( $vid ) - wpss_get_ledger_balance( $vid ) ) < 0.001,
		'internal provider balance === wpss_get_ledger_balance()'
	);
}

update_option( 'wpss_wallet_provider', $restore );
$providers->setValue( $mgr, $all );
$resolve->invoke( $mgr );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
