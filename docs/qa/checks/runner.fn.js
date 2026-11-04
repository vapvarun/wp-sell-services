// Paste into Playwright MCP browser_evaluate as `async () => run(CHECKS)`, with
// CHECKS = the contents of docs/qa/checks/<persona>.json (already logged in as that persona).
// One call runs a whole persona; it returns { passed, failed, results }.
const run = async ( checks ) => {
	const bad = /Warning:|Notice:|Fatal error|Deprecated:|Parse error|There has been a critical error/;
	const results = [];
	for ( const c of checks ) {
		const r = { id: c.id, surface: c.surface, ok: true, why: [] };
		try {
			const res = await fetch( c.url, { credentials: 'same-origin' } );
			const html = await res.text();
			const text = new DOMParser().parseFromString( html, 'text/html' ).body.innerText;
			if ( res.status !== ( c.status || 200 ) ) { r.ok = false; r.why.push( 'status ' + res.status ); }
			if ( bad.test( text ) ) { r.ok = false; r.why.push( 'php notice' ); }
			for ( const p of c.present || [] ) { if ( ! new RegExp( p, 'i' ).test( text ) ) { r.ok = false; r.why.push( 'missing /' + p + '/' ); } }
			for ( const p of c.absent || [] ) { if ( new RegExp( p, 'i' ).test( text ) ) { r.ok = false; r.why.push( 'found /' + p + '/' ); } }
			if ( c.width && ( c.status || 200 ) === 200 ) {
				const f = document.createElement( 'iframe' );
				f.style.cssText = 'position:fixed;left:0;top:0;height:800px;opacity:0;pointer-events:none;width:' + c.width + 'px';
				document.body.appendChild( f );
				await new Promise( ( ok ) => { f.onload = ok; f.src = c.url; } );
				await new Promise( ( ok ) => setTimeout( ok, c.wait || 600 ) );
				const w = f.contentDocument.documentElement.scrollWidth;
				f.remove();
				if ( w > c.width ) { r.ok = false; r.why.push( 'overflow ' + w + '>' + c.width ); }
			}
		} catch ( e ) { r.ok = false; r.why.push( String( e ) ); }
		results.push( r );
	}
	return { passed: results.filter( ( x ) => x.ok ).length, failed: results.filter( ( x ) => ! x.ok ), results: results.map( ( x ) => x.id + ( x.ok ? ' ok' : ' FAIL ' + x.why.join( ';' ) ) ) };
};
