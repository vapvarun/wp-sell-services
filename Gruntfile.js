module.exports = function ( grunt ) {
	const pkg = grunt.file.readJSON( 'package.json' );

	// Shared i18n contract. Also read by bin/i18n-verify.py, so the POT the
	// gate regenerates is built with exactly the args the release build used.
	const i18n = grunt.file.readJSON( '.i18n-config.json' );

	grunt.initConfig( {
		pkg: pkg,

		// Clean dist folder.
		clean: {
			dist: [ 'dist/' ],
		},

		// Generate .pot file for translations.
		makepot: {
			target: {
				options: {
					domainPath: 'languages/',
					mainFile: 'wp-sell-services.php',
					potFilename: 'wp-sell-services.pot',
					potHeaders: {
						poedit: true,
						'x-poedit-keywordslist': true,
						'report-msgid-bugs-to':
							'https://wbcomdesigns.com/support/',
						'last-translator': 'Wbcom Designs <admin@wbcomdesigns.com>',
						'language-team':
							'Wbcom Designs <admin@wbcomdesigns.com>',
					},
					type: 'wp-plugin',
					updateTimestamp: true,
					exclude: [
						'node_modules/.*',
						'vendor/.*',
						'libs/.*',
						'tests/.*',
						'dist/.*',
						'scripts/.*',
					],
				},
			},
		},

		// Check text domain consistency.
		checktextdomain: {
			options: {
				text_domain: 'wp-sell-services',
				correct_domain: false,
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d',
				],
			},
			files: {
				src: [
					'**/*.php',
					'!node_modules/**',
					'!vendor/**',
					'!tests/**',
					'!dist/**',
					'!scripts/**',
					// Bundled third-party SDKs (EDD SL SDK) — they ship with
					// their own text domains; not ours to translate or
					// re-domain.
					'!libs/**',
				],
				expand: true,
			},
		},

		// Generate RTL stylesheets.
		rtlcss: {
			dist: {
				files: [
					{
						expand: true,
						cwd: 'assets/css/',
						src: [
							'*.css',
							'!*-rtl.css',
							'!*.min.css',
						],
						dest: 'assets/css/',
						ext: '-rtl.css',
					},
				],
			},
		},

		// Copy files to dist.
		copy: {
			dist: {
				files: [
					{
						expand: true,
						src: [
							'**',

							// Exclude development files.
							'!node_modules/**',
							'!dist/**',
							'!build/**',
							'!tests/**',
							'!bin/**',
							'!scripts/**',
							'!docs/**',
							'!audit/**',
							'!marketing/**',
							'!assets/css/src/**',
							'!assets/js/src/**',
							'!.git/**',
							'!.github/**',

							// Exclude dev config files.
							'!Gruntfile.js',
							'!package.json',
							'!package-lock.json',
							'!composer.json',
							'!composer.lock',
							'!phpcs.xml',
							'!phpcs.xml.dist',
							'!phpunit.xml.dist',
							'!postcss.config.js',
							'!tailwind.config.js',
							'!webpack.config.js',
							'!playwright.config.ts',
							'!.eslintrc*',
							'!.stylelintrc*',
							'!.prettierrc*',
							'!.editorconfig',
							'!.gitignore',
							'!.gitattributes',
							'!.distignore',
							'!.phpunit.result.cache',
							'!run-tests.sh',
							'!CLAUDE.md',
							'!README.md',
							'!REST_API_MAPPING.md',
							'!SCOPE.md',
							'!phpstan.neon',
							'!phpstan-baseline.neon',
							'!phpstan-bootstrap.php',
							'!plan/**',
							'!plans/**',

							// Exclude dev vendor packages.
							'!vendor/bin/**',
							'!vendor/squizlabs/**',
							'!vendor/wp-coding-standards/**',
							'!vendor/phpcompatibility/**',
							'!vendor/phpcsstandards/**',
							'!vendor/dealerdirect/**',
							'!vendor/phpunit/**',
							'!vendor/sebastian/**',
							'!vendor/yoast/**',
							'!vendor/myclabs/**',
							'!vendor/nikic/**',
							'!vendor/phar-io/**',
							'!vendor/theseer/**',
							'!vendor/phpstan/**',
							'!vendor/szepeviktor/**',
							'!vendor/php-stubs/**',

							// Exclude dev / build artifacts that ship inside bundled vendor
							// packages (e.g. EDD SL SDK includes its own composer.json,
							// phpunit.xml, webpack.config.js, etc. — none of which the
							// runtime plugin needs).
							'!**/composer.json',
							'!**/composer.lock',
							'!**/package.json',
							'!**/package-lock.json',
							'!**/phpunit.xml',
							'!**/phpunit.xml.dist',
							'!**/webpack.config.js',
							'!**/postcss.config.js',
							'!**/tailwind.config.js',
							'!**/.eslintrc*',
							'!**/.stylelintrc*',
							'!**/.editorconfig',
							'!**/.gitignore',
							'!**/.gitattributes',
							'!**/*.md',
							'!**/*.map',

							// Exclude OS files.
							'!.DS_Store',
							'!Thumbs.db',
							'!**/.DS_Store',
						],
						dest: 'dist/<%= pkg.name %>/',
					},
				],
			},
		},

		// Create zip archive.
		compress: {
			dist: {
				options: {
					archive: 'dist/<%= pkg.name %>-<%= pkg.version %>.zip',
					mode: 'zip',
				},
				expand: true,
				cwd: 'dist/',
				src: [ '<%= pkg.name %>/**' ],
				dest: '/',
			},
		},
	} );

	// Load tasks.
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );
	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	grunt.loadNpmTasks( 'grunt-checktextdomain' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );

	// runWpCli — one spawn helper for every `wp i18n …` step below.
	//
	// Each returns a promise-free grunt async handle; `onMissing` lets the POT
	// step fall back to grunt-wp-i18n when WP-CLI is not installed, while the
	// MO/JSON steps hard-fail instead (grunt-wp-i18n cannot produce either, and
	// silently shipping a plugin with no compiled catalog is the exact bug this
	// pipeline exists to prevent).
	function runWpCli( grunt, task, args, onMissing ) {
		const done = task.async();
		const { spawn } = require( 'child_process' );
		const child = spawn( 'wp', args, { stdio: 'inherit' } );
		child.on( 'error', ( err ) => {
			if ( onMissing ) {
				onMissing( err );
				done();
				return;
			}
			grunt.fail.warn(
				'WP-CLI (`wp`) is required for `' + args.slice( 0, 3 ).join( ' ' ) +
				'` but was not found: ' + err.message
			);
			done();
		} );
		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				grunt.fail.warn( 'wp ' + args.slice( 0, 3 ).join( ' ' ) + ' exited with code ' + code );
			}
			done();
		} );
	}

	// i18n:pot — generate the POT via `wp i18n make-pot` (the WP-CLI built-in
	// extractor): faster than grunt-wp-i18n, supports JS strings, and the legacy
	// package fails on PHP 8.1+ in some environments. Falls back to the grunt
	// `makepot` task only if WP-CLI is missing.
	grunt.registerTask( 'i18n:pot', 'Generate POT via WP-CLI', function () {
		runWpCli( grunt, this, [
			'i18n',
			'make-pot',
			'.',
			i18n.potFile,
			'--slug=' + i18n.slug,
			'--domain=' + i18n.domain,
			'--exclude=' + i18n.potExclude.join( ',' ),
			'--headers=' + JSON.stringify( i18n.potHeaders ),
		], ( err ) => {
			grunt.log.warn( 'WP-CLI not available (' + err.message + '), falling back to grunt-wp-i18n.' );
			grunt.task.run( 'makepot' );
		} );
	} );

	// i18n:mo — compile every `languages/*.po` into the `.mo` binary WordPress
	// actually loads. Without this step a translator's .po file is inert: the
	// plugin ships a POT nobody can use and every string stays English.
	//
	// No-ops (with a notice) when no .po exists yet — that is the state of a
	// plugin whose translations have not started, not a build failure.
	grunt.registerTask( 'i18n:mo', 'Compile PO -> MO via WP-CLI', function () {
		const po = grunt.file.expand( i18n.languagesDir + '/*.po' );
		if ( ! po.length ) {
			grunt.log.writeln(
				'i18n:mo — no .po files in ' + i18n.languagesDir + '/, nothing to compile.'
			);
			return;
		}
		runWpCli( grunt, this, [ 'i18n', 'make-mo', i18n.languagesDir ] );
	} );

	// i18n:json — split the JS strings out of each `.po` into the per-script
	// JSON catalogs that `wp_set_script_translations()` loads. Every
	// `wp.i18n.__()` call in our JavaScript is dead without these files.
	//
	// --no-purge keeps the JS strings in the .po so future diffs stay readable
	// (the default strips them, which makes the next translator update noisy).
	grunt.registerTask( 'i18n:json', 'Extract JS strings to JSON via WP-CLI', function () {
		const po = grunt.file.expand( i18n.languagesDir + '/*.po' );
		if ( ! po.length ) {
			grunt.log.writeln(
				'i18n:json — no .po files in ' + i18n.languagesDir + '/, nothing to extract.'
			);
			return;
		}
		runWpCli( grunt, this, [ 'i18n', 'make-json', i18n.languagesDir, '--no-purge' ] );
	} );

	// i18n:verify — the release gate (stale POT / wrong text domain / JS script
	// registered without wp_set_script_translations). Quiet on success.
	grunt.registerTask( 'i18n:verify', 'Run the i18n verification gate', function () {
		const done = this.async();
		const { spawn } = require( 'child_process' );
		const child = spawn( 'python3', [ 'bin/i18n-verify.py' ], { stdio: 'inherit' } );
		child.on( 'error', ( err ) => {
			grunt.fail.warn( 'i18n:verify could not run bin/i18n-verify.py: ' + err.message );
			done();
		} );
		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				grunt.fail.warn( 'i18n:verify found violations (exit ' + code + ').' );
			}
			done();
		} );
	} );

	// i18n — POT, then the binary + JSON catalogs built from it, then the gate.
	// Order matters: verify runs LAST so it inspects the artefacts this run
	// just produced rather than the previous release's.
	grunt.registerTask( 'i18n', [
		'checktextdomain',
		'i18n:pot',
		'i18n:mo',
		'i18n:json',
		'i18n:verify',
	] );

	// RTL: Generate RTL stylesheets.
	grunt.registerTask( 'rtl', [ 'rtlcss' ] );

	// Min: Generate `.min.css` + `.min.js` siblings via scripts/build-min.js.
	// Runs AFTER `rtl` so the freshly-generated `*-rtl.css` files also get
	// minified into `*-rtl.min.css` siblings. Both source and minified
	// files ship together in the dist ZIP — `Frontend\Assets` swaps in the
	// `.min` variant at runtime when SCRIPT_DEBUG is false.
	grunt.registerTask( 'min', 'Minify plugin CSS + JS into .min siblings', function () {
		const done = this.async();
		const { spawn } = require( 'child_process' );
		const child = spawn( 'node', [ 'scripts/build-min.js' ], { stdio: 'inherit' } );
		child.on( 'error', ( err ) => grunt.fail.warn( 'min build failed: ' + err.message ) );
		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				grunt.fail.warn( 'min build exited with code ' + code );
			}
			done();
		} );
	} );

	// composer:nodev — strip dev packages from working-tree vendor/ and
	// regenerate the autoloader so it only references runtime classes.
	//
	// CRITICAL: copy:dist excludes vendor/myclabs, vendor/phpunit, etc. via
	// the `!vendor/<dev>/**` patterns, but the *autoloader* (autoload_real.php
	// + autoload_static.php + autoload_files.php) is generated against
	// whatever packages are present at `composer install` time. If we copy
	// the dev autoloader into dist, it eagerly `require`s `myclabs/deep-copy/
	// src/DeepCopy/deep_copy.php` on plugin activation — files that were
	// excluded from the ZIP — producing a fatal on activation.
	//
	// We run --no-dev BEFORE copy:dist so the autoloader the dist captures
	// is already prod-only. composer:dev restores the dev tree afterwards
	// for local development.
	grunt.registerTask( 'composer:nodev', 'Strip dev deps + regenerate prod autoloader', function () {
		const done = this.async();
		const { spawn } = require( 'child_process' );
		const child = spawn( 'composer', [
			'install',
			'--no-dev',
			'--optimize-autoloader',
			'--no-interaction',
			'--no-scripts',
			'--no-progress',
		], { stdio: 'inherit' } );
		child.on( 'error', ( err ) => grunt.fail.warn( 'composer:nodev failed: ' + err.message ) );
		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				grunt.fail.warn( 'composer:nodev exited with code ' + code );
			}
			done();
		} );
	} );

	grunt.registerTask( 'composer:dev', 'Restore dev deps for local development', function () {
		const done = this.async();
		const { spawn } = require( 'child_process' );
		const child = spawn( 'composer', [
			'install',
			'--optimize-autoloader',
			'--no-interaction',
			'--no-progress',
		], { stdio: 'inherit' } );
		child.on( 'error', ( err ) => grunt.log.warn( 'composer:dev restore failed: ' + err.message ) );
		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				// Don't fail the build — release artefact is already built.
				// Log loudly so the dev knows to run `composer install`
				// manually before resuming local work.
				grunt.log.warn( 'composer:dev restore exited with code ' + code + ' — run `composer install` to restore dev deps.' );
			}
			done();
		} );
	} );

	// verify:dist-autoloader — gate that fails the release if any dev-only
	// package paths leaked into dist/<plugin>/vendor/composer/. If this trips,
	// composer:nodev did not run (or did not complete) before copy:dist —
	// ship-blocker per Basecamp #9828326478.
	grunt.registerTask( 'verify:dist-autoloader', 'Assert no dev refs in dist autoloader', function () {
		const fs = require( 'fs' );
		const path = require( 'path' );
		const distAutoloader = path.join(
			'dist', pkg.name, 'vendor', 'composer', 'autoload_static.php'
		);
		if ( ! fs.existsSync( distAutoloader ) ) {
			grunt.fail.warn( 'verify:dist-autoloader — missing ' + distAutoloader );
			return;
		}
		const contents = fs.readFileSync( distAutoloader, 'utf8' );
		const banned = [
			'/myclabs/',
			'/phpunit/',
			'/phpstan/',
			'/sebastian/',
			'/squizlabs/',
			'/wp-coding-standards/',
			'/phpcompatibility/',
			'/phpcsstandards/',
			'/dealerdirect/',
			'/yoast/phpunit-polyfills',
			'/nikic/',
			'/phar-io/',
			'/theseer/',
			'/szepeviktor/',
			'/php-stubs/',
		];
		const hits = banned.filter( ( needle ) => contents.includes( needle ) );
		if ( hits.length ) {
			grunt.fail.warn(
				'verify:dist-autoloader — dist autoloader still references dev packages:\n  ' +
				hits.join( '\n  ' ) +
				'\n\nThis would cause a fatal error on plugin activation.\n' +
				'Ensure composer:nodev ran before copy:dist.'
			);
			return;
		}
		grunt.log.ok( 'dist autoloader contains no dev-package references.' );
	} );

	// Release: Full build → POT regen → RTL CSS → minify → strip dev deps
	// → copy → verify autoloader → zip → restore dev deps.
	//
	// Order matters:
	// - composer:nodev MUST run before clean:dist/copy:dist so the prod
	//   autoloader is what gets copied (see Basecamp #9828326478).
	// - verify:dist-autoloader runs after copy:dist but before compress:dist
	//   so a regression fails the build BEFORE we publish a broken ZIP.
	// - composer:dev runs last to leave the working tree in a usable state.
	grunt.registerTask( 'release', [
		'i18n',
		'rtl',
		'min',
		'composer:nodev',
		'clean:dist',
		'copy:dist',
		'verify:dist-autoloader',
		'compress:dist',
		'composer:dev',
	] );

	// Dist: Quick dist without i18n/RTL (for dev).
	grunt.registerTask( 'dist', [
		'min',
		'clean:dist',
		'copy:dist',
		'compress:dist',
	] );

	grunt.registerTask( 'default', [ 'release' ] );
};
