<?php
/**
 * Offline payment methods: migration, frozen ids, and the checkout filter.
 *
 * Run: wp eval-file tests/test-offline-methods-contract.php
 *
 * The order snapshot means a renamed or deleted method must never change what a
 * past order says, and that only holds while ids are assigned once and kept.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\Gateways\OfflineGateway;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$original = get_option( 'wpss_offline_settings', array() );
$gateway  = new OfflineGateway();
$save     = static function ( array $input ) use ( $gateway ) {
	$clean = $gateway->sanitize_settings( $input );
	update_option( 'wpss_offline_settings', $clean );
	return $clean;
};

// --- Legacy shape seeds row one ------------------------------------------
update_option(
	'wpss_offline_settings',
	array(
		'enabled'      => '1',
		'title'        => 'Offline Payment',
		'instructions' => 'Wire to Test Bank.',
	)
);

$seed = OfflineGateway::get_methods( false );
$check( 'legacy settings surface as exactly one method', 1 === count( $seed ) );
$check( 'that method carries the owner\'s own wording', 'Offline Payment' === $seed[0]['label'] && 'Wire to Test Bank.' === $seed[0]['instructions'] );

// --- First save migrates and drops the legacy key -------------------------
$clean = $save(
	array(
		'enabled' => '1',
		'title'   => 'Offline Payment',
		'methods' => array(
			array( 'label' => 'Bank Transfer', 'instructions' => 'Wire to Test Bank.', 'enabled' => '1' ),
			array( 'label' => 'Cheque by post', 'instructions' => "Payable to Ltd.\nPost to 1 Example St.", 'enabled' => '1' ),
			array( 'label' => '', 'instructions' => '', 'enabled' => '1' ),
		),
	)
);

$check( 'blank rows are dropped', 2 === count( $clean['methods'] ) );
$check( 'legacy instructions key is gone after migration', ! array_key_exists( 'instructions', $clean ) );
$check( 'title survives as the group heading', 'Offline Payment' === $clean['title'] );
$check( 'id is readable, not squashed', 'bank-transfer' === $clean['methods'][0]['id'] );
$check( 'multi-line instructions survive', false !== strpos( $clean['methods'][1]['instructions'], "\n" ) );

$frozen = $clean['methods'][0]['id'];

// --- Renaming must NOT change the id --------------------------------------
$renamed = $save(
	array(
		'enabled' => '1',
		'title'   => 'Offline Payment',
		'methods' => array(
			array( 'label' => 'Wire Transfer', 'instructions' => 'Wire to Test Bank.', 'enabled' => '1' ),
			array( 'label' => 'Cheque by post', 'instructions' => 'Payable to Ltd.', 'enabled' => '1' ),
		),
	)
);

$check( 'renaming a method keeps its frozen id', $frozen === $renamed['methods'][0]['id'] );
$check( 'the new label is stored', 'Wire Transfer' === $renamed['methods'][0]['label'] );

// --- Two methods named the same must not share an id ----------------------
update_option( 'wpss_offline_settings', array( 'enabled' => '1' ) );
$dupes = $save(
	array(
		'enabled' => '1',
		'methods' => array(
			array( 'label' => 'Bank', 'enabled' => '1' ),
			array( 'label' => 'Bank', 'enabled' => '1' ),
		),
	)
);
$check( 'duplicate labels get distinct ids', $dupes['methods'][0]['id'] !== $dupes['methods'][1]['id'] );

// --- Disabled methods are hidden from checkout, kept in the editor --------
$save(
	array(
		'enabled' => '1',
		'methods' => array(
			array( 'label' => 'Bank Transfer', 'enabled' => '1' ),
			array( 'label' => 'Cash on collection', 'enabled' => '' ),
		),
	)
);
$check( 'checkout offers only enabled methods', 1 === count( OfflineGateway::get_methods( true ) ) );
$check( 'the editor still shows the disabled one', 2 === count( OfflineGateway::get_methods( false ) ) );

update_option( 'wpss_offline_settings', $original );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
