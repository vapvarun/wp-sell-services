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
	grunt.registerTask( 'i18n', [ 'checktextdomain', 'makepot' ] );

	// RTL: Generate RTL stylesheets.
	grunt.registerTask( 'rtl', [ 'rtlcss' ] );

	// Release: Full build + i18n + RTL + dist + zip.
	grunt.registerTask( 'release', [
		'i18n',
		'rtl',
		'clean:dist',
		'copy:dist',
		'compress:dist',
	] );

	// Dist: Quick dist without i18n/RTL (for dev).
	grunt.registerTask( 'dist', [
		'clean:dist',
		'copy:dist',
		'compress:dist',
	] );

	grunt.registerTask( 'default', [ 'release' ] );
};
