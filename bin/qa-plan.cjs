#!/usr/bin/env node
// Which surfaces and checks must be re-run since a commit?  Usage: node bin/qa-plan.cjs [<sha>]
// Reads docs/qa/smoke-surfaces.json (same map the release gate uses) and docs/qa/checks/*.json.
const fs = require( 'fs' );
const { execFileSync } = require( 'child_process' );

// Same glob rules as bin/smoke-gate.cjs: ** crosses directories, * stays inside one.
const rx = ( g ) => new RegExp( '^' + g.replace( /[.+?^${}()|[\]\\]/g, '\\$&' ).replace( /\*\*/g, '\u0000' ).replace( /\*/g, '[^/]*' ).replace( /\u0000/g, '.*' ) + '$' );
const stamped = [ 'last-smoke-pass', 'last-smoke-pass-free' ].map( ( n ) => { try { return JSON.parse( fs.readFileSync( `.qa-results/${ n }.json`, 'utf8' ) ).ran_against; } catch ( e ) { return ''; } } ).find( Boolean );
const sha = process.argv[ 2 ] || stamped;
if ( ! sha ) { console.error( 'No ran_against commit in .qa-results; pass a SHA: node bin/qa-plan.cjs <sha>' ); process.exit( 1 ); }
const surfaces = JSON.parse( fs.readFileSync( 'docs/qa/smoke-surfaces.json', 'utf8' ) ).surfaces;
const rendered = [ 'src/', 'templates/', 'assets/', 'blocks/', 'wp-sell-services.php' ];
const changed = execFileSync( 'git', [ 'diff', '--name-only', sha + '..HEAD' ], { encoding: 'utf8' } ).split( '\n' ).filter( ( f ) => rendered.some( ( p ) => f.startsWith( p ) ) );

const stale = new Set();
const unmapped = [];
for ( const f of changed ) {
	const owners = Object.keys( surfaces ).filter( ( s ) => ( surfaces[ s ].free || [] ).some( ( g ) => rx( g ).test( f ) ) );
	owners.length ? owners.forEach( ( o ) => stale.add( o ) ) : unmapped.push( f );
}

const all = ! ! unmapped.length;
const out = { since: sha, changed: changed.length, unmapped: unmapped.length, runAll: all, surfaces: [ ...stale ], checks: {}, noCheckYet: [] };
for ( const p of [ 'visitor', 'buyer', 'vendor', 'admin' ] ) {
	const list = JSON.parse( fs.readFileSync( `docs/qa/checks/${ p }.json`, 'utf8' ) );
	const pick = list.filter( ( c ) => all || stale.has( c.surface ) );
	if ( pick.length ) { out.checks[ p ] = pick.length + ' of ' + list.length; }
}
const covered = new Set( [ 'visitor', 'buyer', 'vendor', 'admin' ].flatMap( ( p ) => JSON.parse( fs.readFileSync( `docs/qa/checks/${ p }.json`, 'utf8' ) ).map( ( c ) => c.surface ) ) );
out.noCheckYet = [ ...stale ].filter( ( s ) => ! covered.has( s ) );
console.log( JSON.stringify( out, null, 1 ) );
if ( unmapped.length ) { console.error( 'unmapped (forces a full re-walk):\n  ' + unmapped.slice( 0, 15 ).join( '\n  ' ) + ( unmapped.length > 15 ? '\n  ...' : '' ) ); }
