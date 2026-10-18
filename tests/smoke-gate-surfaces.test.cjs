/**
 * Mutation proof for the per-surface smoke gate (Basecamp 10331411319).
 *
 * Builds a throwaway Free + Pro pair of git repos, writes both smoke reports, then changes files and
 * asserts what the gate says. Run: node tests/smoke-gate-surfaces.test.cjs
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const gate = require( '../bin/smoke-gate.cjs' );

const root = fs.mkdtempSync( path.join( os.tmpdir(), 'wpss-gate-' ) );
const free = path.join( root, 'wp-sell-services' );
const pro = path.join( root, 'wp-sell-services-pro' );
const git = ( cwd, ...args ) => execFileSync( 'git', args, { cwd, encoding: 'utf8' } ).trim();
const put = ( dir, rel, body = 'x' + Math.random() ) => {
	fs.mkdirSync( path.dirname( path.join( dir, rel ) ), { recursive: true } );
	fs.writeFileSync( path.join( dir, rel ), body );
};
const commit = ( dir, msg ) => {
	git( dir, 'add', '-A' );
	git( dir, '-c', 'user.name=t', '-c', 'user.email=t@example.test', 'commit', '-qm', msg );
	return git( dir, 'rev-parse', 'HEAD' );
};

[ free, pro ].forEach( ( d ) => {
	fs.mkdirSync( d, { recursive: true } );
	git( d, 'init', '-q' );
} );
fs.mkdirSync( path.join( free, 'docs/qa' ), { recursive: true } );
fs.copyFileSync( path.join( __dirname, '../docs/qa/smoke-surfaces.json' ), path.join( free, 'docs/qa/smoke-surfaces.json' ) );
put( free, 'templates/cart/cart.php' );
put( free, 'templates/dashboard/sections/earnings.php' );
put( free, 'src/Helper.php' );
put( pro, 'src/Integrations/WooCommerce/WCCartCarrier.php' );
put( pro, 'src/Core/Shared.php' );
const freeBase = commit( free, 'base' );
const proBase = commit( pro, 'base' );

const report = ( extra = {} ) => {
	fs.mkdirSync( path.join( free, '.qa-results' ), { recursive: true } );
	const body = {
		release_version: '9.9.9', pro_version: '9.9.9', ran_against: freeBase, pro_ran_against: proBase,
		sections: { a: { pass: 3, skipped: 0 } }, failures: [], ...extra,
	};
	fs.writeFileSync( path.join( free, '.qa-results/last-smoke-pass.json' ), JSON.stringify( body ) );
	fs.writeFileSync( path.join( free, '.qa-results/last-smoke-pass-free.json' ), JSON.stringify( { ...body, pro_version: undefined, pro_ran_against: undefined } ) );
};
const run = () => gate( { version: '9.9.9', freeDir: free, proDir: pro } );

let fails = 0;
const check = ( label, ok, detail = '' ) => {
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + label + ( ok ? '' : '\n      ' + detail ) );
	fails += ok ? 0 : 1;
};

report();
let v = run();
check( 'nothing changed: passes', v.ok, v.message );

// A mapped file goes stale until its surface is re-verified; an untouched surface is not named.
put( free, 'templates/cart/cart.php' );
put( free, 'templates/dashboard/sections/earnings.php' );
const head1 = commit( free, 'touch cart + earnings' );
v = run();
check( 'mapped changes without a re-walk: stale, both named', ! v.ok && v.message.includes( 'cart.php' ) && v.message.includes( 'earnings.php' ), v.message );

report( { surfaces: { cart: { ran_against: head1 } } } );
v = run();
check( 'only cart re-verified: only the earnings file is still stale', ! v.ok && ! v.message.includes( 'cart.php' ) && v.message.includes( 'earnings.php' ), v.message );

report( { surfaces: { cart: { ran_against: head1 }, earnings: { ran_against: head1 } } } );
v = run();
check( 'both surfaces re-verified: passes without a full re-walk', v.ok, v.message );

// A change after the surface was re-verified is stale again.
put( free, 'templates/cart/cart.php' );
commit( free, 'touch cart again' );
v = run();
check( 'a change after the surface walk makes it stale again', ! v.ok && v.message.includes( 'cart.php' ), v.message );

// An unmapped file invalidates everything, whatever surfaces are recorded.
const head2 = git( free, 'rev-parse', 'HEAD' );
put( free, 'src/Helper.php' );
const head3 = commit( free, 'touch helper' );
report( { surfaces: { cart: { ran_against: head3 }, earnings: { ran_against: head3 }, checkout: { ran_against: head3 }, publish: { ran_against: head3 } } } );
v = run();
check( 'an unmapped file invalidates the whole walk', ! v.ok && v.message.includes( 'src/Helper.php' ), v.message );

// Pro half: a mapped Pro file needs its surface's pro_ran_against; an unmapped one never passes.
report( { ran_against: head3 } );
put( pro, 'src/Integrations/WooCommerce/WCCartCarrier.php' );
const proHead = commit( pro, 'touch carrier' );
v = run();
check( 'a Pro change after the combo walk makes it stale', ! v.ok && v.message.includes( 'WCCartCarrier.php' ), v.message );

report( { ran_against: head3, surfaces: { cart: { ran_against: head3, pro_ran_against: proHead } } } );
v = run();
check( 'the Pro cart surface re-verified: passes', v.ok, v.message );

put( pro, 'src/Core/Shared.php' );
const proHead2 = commit( pro, 'touch shared' );
report( { ran_against: head3, surfaces: { cart: { ran_against: head3, pro_ran_against: proHead2 } } } );
v = run();
check( 'an unmapped Pro file invalidates the combo walk', ! v.ok && v.message.includes( 'Shared.php' ), v.message );

void head2;
fs.rmSync( root, { recursive: true, force: true } );
process.exit( fails ? 1 : 0 );
