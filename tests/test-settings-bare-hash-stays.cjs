/**
 * Settings: a bare "#" keeps the current section (Basecamp 10550073503).
 *
 * Core's colour picker draws its handle as <a href="#">; clicking it emptied the hash and the
 * router showed General. Runs the shipped admin-settings-nav.min.js against a tiny fake DOM.
 *
 * Run: node tests/test-settings-bare-hash-stays.cjs
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const file = process.env.WPSS_NAV_SRC || path.join( __dirname, '../assets/js/admin-settings-nav.min.js' );
const ids = [ 'general', 'payments' ];
const el = ( props ) => ( { classList: new Set(), dataset: {}, addEventListener() {}, ...props } );
const navs = ids.map( ( id ) => el( { dataset: { section: id } } ) );
const secs = ids.map( ( id ) => el( { id: 'section-' + id } ) );
for ( const e of [ ...navs, ...secs ] ) { e.classList.remove = e.classList.delete; }

const loc = { base: 'http://x/wp-admin/admin.php?page=wpss-settings', frag: '#payments', search: '?page=wpss-settings', pathname: '/wp-admin/admin.php', replace() {} };
Object.defineProperty( loc, 'hash', { get: () => ( '#' === loc.frag ? '' : loc.frag ) } );
Object.defineProperty( loc, 'href', { get: () => loc.base + loc.frag } );
const listeners = {};
const active = ( list ) => list.filter( ( e ) => e.classList.has( 'is-active' ) );

const document = {
	body: { contains: () => true },
	querySelectorAll: ( sel ) => ( sel.startsWith( '.wpss-settings-nav-item' ) ? ( sel.includes( '.is-active' ) ? active( navs ) : navs ) : sel === '.wpss-settings-section' ? secs : [] ),
	querySelector( sel ) {
		const m = sel.match( /data-section="([^"]+)"/ );
		if ( m ) { return navs.find( ( n ) => n.dataset.section === m[ 1 ] ) || null; }
		if ( sel.endsWith( '.is-active' ) ) { return active( sel.startsWith( '.wpss-settings-nav-item' ) ? navs : secs )[ 0 ] || null; }
		return ( sel.startsWith( '.wpss-settings-nav-item' ) ? navs : secs )[ 0 ];
	},
	getElementById: ( id ) => secs.find( ( s ) => s.id === id ) || null,
};
const window = { addEventListener: ( t, f ) => { listeners[ t ] = f; } };
const ctx = { window, document, location: loc, history: { replaceState: ( a, b, u ) => { loc.frag = u.slice( u.indexOf( '#' ) ); } },
	URLSearchParams, setTimeout: () => {}, wp: { i18n: { __: ( s ) => s, sprintf: ( s ) => s } } };
vm.runInNewContext( fs.readFileSync( file, 'utf8' ), ctx );

let fails = 0;
const check = ( label, ok, detail ) => { console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + label + ( ok ? '' : ' - ' + detail ) ); fails += ok ? 0 : 1; };
const shown = () => active( secs ).map( ( s ) => s.id ).join( ',' );

check( 'loads on #payments', 'section-payments' === shown(), shown() );
loc.frag = '#';
listeners.hashchange();
check( 'a bare # (colour picker handle) keeps Payments open', 'section-payments' === shown(), shown() );
check( '  and puts #payments back in the URL', '#payments' === loc.hash, loc.hash );
loc.frag = '#general';
listeners.hashchange();
check( 'a real hash still navigates', 'section-general' === shown(), shown() );

console.log( fails ? `\n${ fails } FAILED` : '\nall passed' );
process.exit( fails ? 1 : 0 );
