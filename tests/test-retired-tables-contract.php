<?php
/**
 * Retired tables contract.
 *
 * Five tables survive on upgraded sites that no clean install has and nothing
 * reads. They are not broken - they are misleading, which is worse: reading one
 * and believing it produced a confidently wrong root cause on Basecamp
 * 10236358969, where the UI was right and the table had been abandoned.
 *
 * This pins the answer to "is this wpss_* table real?" so nobody has to guess
 * again, and pins that the plugin does NOT drop them behind the owner's back.
 *
 * Run: wp eval-file tests/test-retired-tables-contract.php
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

echo "\nRetired tables contract\n\n";

$schema = new \WPSellServices\Database\SchemaManager();
$ref    = new ReflectionClass( $schema );

$retired = $ref->getConstant( 'RETIRED_TABLES' );
$core    = $ref->getConstant( 'CORE_TABLES' );

wpss_t( is_array( $retired ) && count( $retired ) >= 5, 'RETIRED_TABLES lists the known orphans' );

// 1. A table is in exactly one list. Overlap would mean the plugin both
//    creates a table and calls it retired - the exact ambiguity this fixes.
wpss_t(
	empty( array_intersect( array_keys( (array) $retired ), (array) $core ) ),
	'no table is both live and retired'
);

// 2. Every retired table names where its data actually lives now, so the next
//    person reading one does not have to work it out from scratch.
$described = true;
foreach ( (array) $retired as $name => $where ) {
	if ( ! is_string( $where ) || strlen( $where ) < 10 ) {
		$described = false;
	}
}
wpss_t( $described, 'every retired table says where the live data is instead' );

// 3. Still no readers. If one gains a reader, it is not retired any more and
//    this test should say so loudly.
foreach ( array_keys( (array) $retired ) as $name ) {
	$hits = 0;
	foreach ( array( $free . '/src', $pro . '/src' ) as $dir ) {
		if ( ! is_dir( $dir ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
		foreach ( $it as $f ) {
			if ( 'php' !== $f->getExtension() ) {
				continue;
			}
			$code = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', file_get_contents( $f->getPathname() ) );
			// The table is only ever addressed as prefix . 'name', so look for
			// the quoted short name next to a prefix - not the bare word, which
			// also matches the [wpss_buyer_requests] shortcode and the
			// _wpss_service_requirements post meta key.
			if ( preg_match( '/prefix\s*\.\s*[\'"]' . preg_quote( $name, '/' ) . '[\'"]/', $code ) ) {
				++$hits;
			}
		}
	}
	wpss_t( 0 === $hits, sprintf( 'wpss_%s still has no reader in Free or Pro', $name ) );
}

// 4. Reporting works and does not lie about what is on this install.
$found = $schema->get_retired_tables();
wpss_t( is_array( $found ), 'get_retired_tables() reports' );
foreach ( $found as $name => $info ) {
	wpss_t(
		isset( $info['rows'], $info['data_now'] ) && $info['rows'] >= 0,
		sprintf( 'wpss_%s reported with a row count and a pointer to the live data', $name )
	);
}

// 5. The important one. Dropping is destructive and irreversible, and a plugin
//    update runs unattended. It must be reachable ONLY from uninstall.
$src      = file_get_contents( $free . '/src/Database/SchemaManager.php' );
$upgrade  = substr( $src, strpos( $src, 'public function upgrade' ) );
$upgrade  = substr( $upgrade, 0, strpos( $upgrade, "\n\t}\n" ) );
wpss_t(
	false === strpos( $upgrade, 'drop_retired_tables' ),
	'the upgrade routine never drops them - cleanup is the owner\'s call'
);
wpss_t(
	false !== strpos( $src, "\$this->drop_retired_tables();" ),
	'uninstall does drop them, which is where dropping belongs'
);

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
