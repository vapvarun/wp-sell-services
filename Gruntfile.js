module.exports = function ( grunt ) {
	const pkg = grunt.file.readJSON( 'package.json' );

	grunt.initConfig( {
		pkg: pkg,

		clean: {
			dist: [ 'dist/' ],
		},

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
							'!.eslintrc*',
							'!.stylelintrc*',
							'!.prettierrc*',
							'!.editorconfig',
							'!.gitignore',
							'!.gitattributes',
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

		compress: {
			dist: {
				options: {
					archive: 'dist/<%= pkg.name %>.zip',
					mode: 'zip',
				},
				expand: true,
				cwd: 'dist/',
				src: [ '<%= pkg.name %>/**' ],
				dest: '/',
			},
		},
	} );

	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );

	grunt.registerTask( 'dist', [
		'clean:dist',
		'copy:dist',
		'compress:dist',
	] );

	grunt.registerTask( 'default', [ 'dist' ] );
};
