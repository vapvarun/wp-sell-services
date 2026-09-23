/**
 * The smoke gate, as ONE implementation both halves call.
 *
 * It lived twice: Free's Gruntfile carried the full contract - both walks,
 * commit-level staleness, coverage, and an acceptance file - while Pro's
 * carried an older four-line version that only counted failures. So the same
 * evidence gave two different verdicts: Free built with two carded findings
 * accepted, and Pro refused the build for those same two, having no channel
 * to accept anything. The halves ship lockstep; a gate that disagrees with
 * itself about the one record is worse than either version of it.
 *
 * Free owns the file because Free owns the evidence - the walk writes its
 * report into Free, and Pro already reads it across the directory boundary.
 *
 * Returns { ok, message }. The caller decides what a failure does, so this
 * stays a plain function with no grunt in it and can be run by hand.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

/**
 * Surfaces whose change invalidates a browser walk.
 *
 * Docs, tests and audit manifests do not: re-running a 40-minute walk because
 * a comment changed is how a gate gets bypassed rather than satisfied.
 */
const RENDERED = [ 'src/', 'templates/', 'assets/', 'blocks/', 'wp-sell-services.php' ];

/*
 * Sections a walk CANNOT run, which is not the same as a walk that did not.
 *
 * G_post_release verifies the TAGGED artifact - zip contents, a sha256
 * compare against the published asset. It cannot pass before a release has
 * been cut, so demanding it before the build is a circular requirement the
 * walk can never satisfy. Every honest report marks it skipped with exactly
 * that reason, and this gate then refused the build for it.
 *
 * The Pro sections are the same shape in FREE mode: free mode exists to prove
 * the product works with Pro deactivated, so a Pro section there has nothing
 * to exercise. Demanding a pass would mean the free walk could only satisfy
 * the gate by reporting a pass it did not earn.
 *
 * Exempting these is a correction, not a loosening: the check still applies
 * to every section that CAN run, in every mode where it can - E_pro_smoke and
 * S_pro_supplements are still required to have run in COMBO mode, which is
 * where Pro is active and where they are the whole point.
 */
const CANNOT_RUN = {
	combo: [ 'G_post_release' ],
	free: [ 'G_post_release', 'E_pro_smoke', 'S_pro_supplements' ],
};

/**
 * BOTH walks, not just the one whose filename is shorter.
 *
 * This read a single path, so free-mode coverage was optional in practice -
 * and .last-smoke-pass-free.json had never existed, meaning every release so
 * far shipped with the free-only experience never exercised in a browser.
 * That matters more here than for most plugins: Pro changes behaviour on
 * SHARED surfaces (white-label branding, payout rails, the four raised wizard
 * limits, tiered commission), so "works with Pro active" is not evidence
 * about the free product, which is what most installs run.
 */
const REPORTS = [
	[ 'combo', 'docs/qa/.last-smoke-pass.json' ],
	[ 'free', 'docs/qa/.last-smoke-pass-free.json' ],
];

/**
 * @param {Object} opts
 * @param {string} opts.version The version being built. Free and Pro are lockstep, so it is the same string on both sides.
 * @param {string} opts.freeDir Absolute path to the FREE plugin checkout, which holds the evidence and the git history the staleness check reads.
 * @return {{ok: boolean, message: string}} Verdict.
 */
module.exports = function smokeGate( { version, freeDir } ) {
	const at = ( rel ) => path.join( freeDir, rel );
	const fail = ( message ) => ( { ok: false, message: 'verify:smoke-gate — ' + message } );

	/*
	 * Findings a human accepted for THIS version, each naming its card.
	 *
	 * The smoke report is evidence and is never edited to make this gate pass.
	 * Acceptance lives in its own file so it is reviewable, attributable and
	 * sits beside the evidence rather than inside it - and so that deleting an
	 * entry is how you un-accept a finding.
	 *
	 * The release standard's rule: a pre-existing finding does not block, it
	 * gets a card and a line in the audit file so the delta stays honest. An
	 * entry with no card id, or accepted for another version, counts for
	 * nothing.
	 */
	const acceptedIds = new Set();
	let acceptedLogPatterns = [];
	/*
	 * Sections proven outside the walk, per mode.
	 *
	 * A walk can be genuinely unable to reach a section - B_upgrade needs an
	 * install/activate cycle, which a walk run under a no-activation constraint
	 * cannot perform - and the gate was then left with two bad answers: block a
	 * release over evidence the walk could never produce, or force past it and
	 * lose the record. Neither says what was actually verified.
	 *
	 * So there is a third: name the section, in the same reviewable file, with
	 * how it was proven instead. An entry without a verified_by is ignored, so
	 * this cannot become a way to wave a section through by listing it.
	 */
	const coveredElsewhere = { combo: new Set(), free: new Set() };
	const acceptFile = at( 'docs/qa/accepted-findings.json' );

	if ( fs.existsSync( acceptFile ) ) {
		let raw;

		try {
			raw = JSON.parse( fs.readFileSync( acceptFile, 'utf8' ) );
		} catch ( e ) {
			return fail( acceptFile + ' is not valid JSON: ' + e.message );
		}

		if ( raw.version === version ) {
			( raw.accepted || [] )
				.filter( ( a ) => a && a.id && a.card && String( a.reason || '' ).trim() )
				.forEach( ( a ) => acceptedIds.add( a.id ) );

			/*
			 * `resolved` is not `accepted`. An accepted finding ships as-is; a
			 * resolved one was FIXED after the walk recorded it, so the report
			 * is stale for that entry. Each must name the commit that fixed it
			 * and how the fix was verified - a re-walk being the strongest
			 * evidence and anything less having to say so, in writing, where a
			 * reader will see it.
			 */
			( raw.resolved || [] )
				.filter( ( r ) => r && r.id && r.fixed_in && String( r.verified_by || '' ).trim() )
				.forEach( ( r ) => acceptedIds.add( r.id ) );

			acceptedLogPatterns = ( raw.debug_log_accepted || [] )
				.filter( ( a ) => a && a.match && a.card )
				.map( ( a ) => a.match );

			( raw.coverage_verified_elsewhere || [] )
				.filter( ( c ) => c && c.section && coveredElsewhere[ c.mode ] && String( c.verified_by || '' ).trim() )
				.forEach( ( c ) => coveredElsewhere[ c.mode ].add( c.section ) );
		}
	}

	for ( const [ mode, rel ] of REPORTS ) {
		const report = at( rel );

		if ( ! fs.existsSync( report ) ) {
			return fail(
				'no ' + rel + ' (' + mode + ' mode).\n' +
				'Run the wp-plugin-smoke skill in ' + mode + ' mode before building a release.'
			);
		}

		let data;
		try {
			data = JSON.parse( fs.readFileSync( report, 'utf8' ) );
		} catch ( e ) {
			return fail( rel + ' is not valid JSON: ' + e.message );
		}

		/*
		 * Lockstep: Free and Pro ship on the same version, so the one record
		 * must name it on both sides.
		 */
		if ( data.release_version !== version ) {
			return fail(
				mode + ' smoke evidence is for ' + ( data.release_version || 'an unrecorded version' ) +
				', building ' + version + '.\n' +
				'Re-run the smoke walk against this version before releasing.'
			);
		}

		if ( data.pro_version && data.pro_version !== version ) {
			return fail(
				mode + ' smoke evidence is for pro ' + data.pro_version + ', building ' + version + '.\n' +
				'Free and Pro ship lockstep; re-run the combo smoke walk before releasing.'
			);
		}

		/*
		 * The version string is not a freshness check.
		 *
		 * It compares '1.7.2' to '1.7.2' and stays true for the whole cycle,
		 * so a walk from the FIRST commit of a release certifies the last one.
		 * On 2026-09-23 the report on file was four days and ~50 commits old -
		 * covering changes to service creation, the Service model and add-on
		 * persistence - and this gate read green.
		 *
		 * So the walk records the SHA it ran against, and we ask git what has
		 * changed in the rendered surfaces since.
		 *
		 * Validate before it reaches git, and never through a shell. This
		 * value comes out of a JSON file written by the smoke walk - an agent,
		 * not a deterministic tool - so it is untrusted input by construction.
		 * Interpolating it into a shell string was a command injection: a
		 * report carrying `x; rm -rf ~ ;` would have run it. The regex rejects
		 * anything that is not a hex SHA, which also stops argument injection
		 * via a leading '-' (`--upload-pack=...` is a real git attack), and
		 * execFileSync passes it as one argv element so no shell ever parses
		 * it even if the regex is later loosened.
		 */
		if ( ! /^[0-9a-f]{7,40}$/.test( String( data.ran_against || '' ) ) ) {
			return fail(
				rel + ' has no usable "ran_against" commit SHA' +
				( data.ran_against ? ' (got: ' + JSON.stringify( data.ran_against ) + ')' : '' ) + '.\n' +
				'Without it there is no way to tell fresh evidence from evidence written weeks ago.\n' +
				'Re-run the smoke; the walk records the SHA it tested.'
			);
		}

		let changed;
		try {
			changed = execFileSync(
				'git',
				[ 'diff', '--name-only', data.ran_against + '..HEAD' ],
				{ cwd: freeDir, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] }
			).split( '\n' ).filter( Boolean );
		} catch ( e ) {
			return fail(
				'cannot diff ' + data.ran_against + '..HEAD (' + mode + ').\n' +
				'The recorded SHA is not in this repository, so the evidence cannot be dated. Re-run the smoke.'
			);
		}

		const stale = changed.filter( ( f ) => RENDERED.some( ( dir ) => f.startsWith( dir ) ) );

		if ( stale.length ) {
			return fail(
				'the ' + mode + ' walk ran at ' + data.ran_against.slice( 0, 8 ) +
				', and ' + stale.length + ' rendered file(s) have changed since:\n  ' +
				stale.slice( 0, 10 ).join( '\n  ' ) +
				( stale.length > 10 ? '\n  …and ' + ( stale.length - 10 ) + ' more' : '' ) +
				'\nThe evidence does not describe the code being shipped. Re-run the smoke.'
			);
		}

		/*
		 * Coverage, not just failures.
		 *
		 * This gate counted failures and debug-log issues and nothing else, so
		 * a walk that stalled after eight checks and skipped thirty-one
		 * reported zero failures and passed - a smoke run that barely started
		 * is indistinguishable here from one that swept the product. That
		 * happened on 1.7.0: the agent stalled in section C, wrote an honest
		 * report whose own recommendation was "do not treat this as a
		 * release-gate green light", and this gate would still have waved the
		 * build through.
		 *
		 * A section with zero passes did not run. That is the assertion: every
		 * section must have gotten through at least one check, and a run
		 * cannot skip more than it passed. Neither is a quality bar - they
		 * only catch a walk that did not happen, which is the failure this
		 * gate kept missing.
		 */
		const sections = data.sections && typeof data.sections === 'object' ? data.sections : {};
		const totals = Object.values( sections ).reduce(
			( acc, s ) => ( {
				pass: acc.pass + ( s.pass || 0 ),
				skipped: acc.skipped + ( s.skipped || 0 ),
			} ),
			{ pass: 0, skipped: 0 }
		);

		const exempt = CANNOT_RUN[ mode ] || CANNOT_RUN.combo;
		const covered = coveredElsewhere[ mode ] || new Set();
		const unrun = Object.keys( sections ).filter(
			( name ) => ! exempt.includes( name ) && ! covered.has( name ) &&
				! sections[ name ].pass && sections[ name ].skipped
		);

		if ( unrun.length ) {
			return fail(
				mode + ': ' + unrun.length + ' section(s) never ran: ' + unrun.join( ', ' ) +
				'.\nZero passes with skips recorded means the walk did not reach them. ' +
				'Re-run the smoke before building.'
			);
		}

		if ( totals.skipped > totals.pass ) {
			return fail(
				'the ' + mode + ' smoke skipped more than it verified (' +
				totals.skipped + ' skipped vs ' + totals.pass + ' passed).\n' +
				'Re-run the smoke, or record why this coverage is acceptable and re-run this gate.'
			);
		}

		const blocking = ( Array.isArray( data.failures ) ? data.failures : [] )
			.concat( Array.isArray( data.hard_fails ) ? data.hard_fails : [] )
			.filter( ( f ) => ! acceptedIds.has( ( f && f.id ) || '' ) );

		const blockingLogs = ( Array.isArray( data.debug_log_issues ) ? data.debug_log_issues : [] )
			.filter( ( l ) => ! acceptedLogPatterns.some( ( m ) => String( ( l && l.line ) || '' ).includes( m ) ) );

		if ( blocking.length || blockingLogs.length ) {
			return fail(
				'the ' + mode + ' smoke run for ' + version + ' is not clean: ' +
				blocking.length + ' unaccepted failure(s), ' +
				blockingLogs.length + ' unaccepted debug-log issue(s).\n' +
				blocking.map( ( f ) => '  ' + ( ( f && f.id ) || '(no id)' ) ).join( '\n' ) + '\n' +
				'Fix them, or accept each one in docs/qa/accepted-findings.json with a card id and a reason.'
			);
		}
	}

	return {
		ok: true,
		message: 'smoke pass on file for ' + version + ' — both combo and free walks, ' +
			'each at a commit with no rendered-surface changes since.',
	};
};
