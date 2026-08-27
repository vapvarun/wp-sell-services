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

/*
 * ---------------------------------------------------------------------------
 * Added 27 Aug while running the card's own test checklist (10239806715).
 *
 * Two gaps the model half shipped with. Neither was visible from the code -
 * both needed an order walked end to end.
 * ---------------------------------------------------------------------------
 */

echo "\nSnapshot reach\n\n";

$gw_src = file_get_contents( dirname( __DIR__ ) . '/src/Integrations/Gateways/OfflineGateway.php' );

/*
 * 1. Every path onto the offline rail must freeze WHICH method was chosen.
 *
 * record_order_method() had two callers, both order-CREATION paths. The
 * pay-an-existing-order branch - proposals, milestone phases, tips, extensions,
 * anything through /checkout/pay/{id}/ - wrote no snapshot at all, so that
 * whole population silently lost the guarantee the snapshot exists to make.
 */
$pay_branch = substr( $gw_src, strpos( $gw_src, '$pay_order_id = isset( $_POST[' ) );
$pay_branch = substr( $pay_branch, 0, strpos( $pay_branch, 'wpss_offline_order_created' ) );

$check( 'the pay-an-existing-order path freezes the chosen method, not just the gateway', false !== strpos( $pay_branch, 'record_order_method' ) );
$check( 'all offline entry points record the method', substr_count( $gw_src, '$this->record_order_method(' ) >= 3 );

/*
 * 2. The snapshot has to be READ, or freezing it is theatre.
 *
 * get_order_method() existed with a careful docblock and ZERO callers, while
 * display_order_payment_instructions() read the legacy global key - which the
 * sanitizer deliberately deletes once named methods exist. Together, defining
 * methods left offline buyers with no payment instructions at all, and an
 * offline buyer with no instructions cannot pay.
 */
$check( 'buyer instructions read the frozen snapshot first', false !== strpos( $gw_src, 'self::get_order_method( $order );' ) );

$display = substr( $gw_src, strpos( $gw_src, 'public function display_order_payment_instructions' ) );
$display = substr( $display, 0, strpos( $display, "\n\t}\n" ) );

$check( 'it still falls back to the legacy block for orders predating named methods', false !== strpos( $display, "settings['instructions']" ) );

// 3. Live behaviour, not just source: a frozen order survives its method being
//    renamed and then deleted.
$frozen = (object) array(
	'meta' => array(
		'offline_method' => array(
			'id'           => 'probe_method',
			'label'        => 'Probe Method',
			'instructions' => 'Probe instructions.',
		),
	),
);

$save(
	array(
		'enabled' => '1',
		'methods' => array(
			array( 'label' => 'RENAMED', 'instructions' => 'REWRITTEN', 'enabled' => '1' ),
		),
	)
);

$read = OfflineGateway::get_order_method( $frozen );
$check( 'renaming a method does not rewrite an order that used it', 'Probe Method' === ( $read['label'] ?? '' ) );
$check( 'renaming does not rewrite the instructions either', 'Probe instructions.' === ( $read['instructions'] ?? '' ) );

$save( array( 'enabled' => '1', 'methods' => array() ) );
$read = OfflineGateway::get_order_method( $frozen );
$check( 'deleting a method does not break an order that used it', 'Probe Method' === ( $read['label'] ?? '' ) );

$check( 'an order predating named methods reports no method rather than inventing one', null === OfflineGateway::get_order_method( (object) array( 'meta' => array() ) ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
