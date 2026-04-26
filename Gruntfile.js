module.exports = function ( grunt ) {
	const pkg = grunt.file.readJSON( 'package.json' );

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
							'!REST_API_MAPPING.md',
							'!SCOPE.md',
							'!phpstan.neon',
							'!phpstan-baseline.neon',
							'!phpstan-bootstrap.php',
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

	// i18n: Generate .pot file.
	//
	// Modern path runs `wp i18n make-pot` (the WP-CLI built-in extractor) —
	// faster, supports JS strings, and the legacy grunt-wp-i18n package
	// fails on PHP 8.1+ in some environments. Falls back to the grunt
	// `makepot` task only if WP-CLI is not installed.
	grunt.registerTask( 'makepot:wpcli', 'Generate POT via WP-CLI', function () {
		const done = this.async();
		const { spawn } = require( 'child_process' );
		const args = [
			'i18n',
			'make-pot',
			'.',
			'languages/wp-sell-services.pot',
			'--slug=wp-sell-services',
			'--domain=wp-sell-services',
			'--exclude=node_modules,vendor,tests,dist,scripts,plans,docs,marketing,bin',
			'--headers={"Report-Msgid-Bugs-To":"https://wbcomdesigns.com/support/","Last-Translator":"Wbcom Designs <admin@wbcomdesigns.com>","Language-Team":"Wbcom Designs <admin@wbcomdesigns.com>"}',
		];
		const child = spawn( 'wp', args, { stdio: 'inherit' } );
		child.on( 'error', ( err ) => {
			grunt.log.warn( 'WP-CLI not available (' + err.message + '), falling back to grunt-wp-i18n.' );
			grunt.task.run( 'makepot' );
			done();
		} );
		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				grunt.fail.warn( 'wp i18n make-pot exited with code ' + code );
			}
			done();
		} );
	} );

	grunt.registerTask( 'i18n', [ 'checktextdomain', 'makepot:wpcli' ] );

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

	// Release: Full build → POT regen → RTL CSS → minify → copy → zip.
	// Order matters: rtl produces `*-rtl.css`, min then catches both
	// LTR and RTL on the same pass.
	grunt.registerTask( 'release', [
		'i18n',
		'rtl',
		'min',
		'clean:dist',
		'copy:dist',
		'compress:dist',
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
