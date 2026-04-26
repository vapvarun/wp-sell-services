#!/usr/bin/env node
/**
 * Build minified CSS + JS siblings for production assets.
 *
 * For every `.css` in `assets/css/` (excluding `.min.css`) the script
 * emits a `.min.css` sibling via PostCSS + cssnano, picking up the same
 * plugin chain (postcss-import → tailwindcss → autoprefixer → cssnano)
 * defined in `postcss.config.js` under the production env.
 *
 * For every `.js` in `assets/js/` (top-level only — `assets/js/vendor/*`
 * is upstream-shipped and already minified) the script emits a
 * `.min.js` sibling via terser with mangling + compression enabled.
 *
 * Both source and minified files ship together — `wp_enqueue_*` picks
 * the `.min` variant by default and falls back to source when
 * `SCRIPT_DEBUG` is true (see `Frontend\Assets::filter_loader_src()`).
 *
 * Run via `npm run build:min` or implicitly via `grunt release`.
 */

const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const postcssConfig = require('../postcss.config.js');
const terser = require('terser');

const PLUGIN_ROOT = path.resolve(__dirname, '..');
const CSS_DIR = path.join(PLUGIN_ROOT, 'assets', 'css');
const JS_DIR = path.join(PLUGIN_ROOT, 'assets', 'js');

/**
 * List the source files we want to minify in a directory.
 *
 * Drops anything already minified (`.min.*`) so reruns are idempotent,
 * and skips the `vendor/` subtree (third-party libraries ship their own
 * minified builds — wrapping them again is wasted churn).
 *
 * @param {string} dir       Directory to scan.
 * @param {string} sourceExt Source extension to match (e.g. `.css`).
 * @returns {string[]} Absolute paths to files needing minification.
 */
function listSourceFiles(dir, sourceExt) {
	const minExt = `.min${sourceExt}`;
	return fs
		.readdirSync(dir, { withFileTypes: true })
		.filter((entry) => entry.isFile())
		.map((entry) => entry.name)
		.filter((name) => name.endsWith(sourceExt) && !name.endsWith(minExt))
		.map((name) => path.join(dir, name));
}

/**
 * Minify every plugin-owned CSS file in `assets/css/` to a `.min.css`
 * sibling. Uses the production environment of `postcss.config.js` so
 * cssnano runs but local dev (e.g. `npm run watch`) stays untouched.
 */
async function buildCss() {
	const files = listSourceFiles(CSS_DIR, '.css');
	const config = postcssConfig({ env: 'production' });
	const plugins = Object.entries(config.plugins).map(([name, opts]) => {
		// eslint-disable-next-line global-require, import/no-dynamic-require
		return require(name)(opts);
	});

	for (const inputPath of files) {
		const outputPath = inputPath.replace(/\.css$/, '.min.css');
		const css = fs.readFileSync(inputPath, 'utf8');
		const result = await postcss(plugins).process(css, {
			from: inputPath,
			to: outputPath,
			map: false,
		});
		fs.writeFileSync(outputPath, result.css);
		console.log(
			`  CSS ${path.relative(PLUGIN_ROOT, inputPath)} → ${path.relative(
				PLUGIN_ROOT,
				outputPath
			)} (${(result.css.length / 1024).toFixed(1)} KB)`
		);
	}
	console.log(`✓ ${files.length} CSS file(s) minified`);
}

/**
 * Minify every plugin-owned JS file in `assets/js/` (top-level only —
 * `assets/js/vendor/*` is upstream-shipped and skipped) to a `.min.js`
 * sibling via terser. Mangling + compression are both on; comments are
 * dropped except for `/*!` license markers.
 */
async function buildJs() {
	const files = listSourceFiles(JS_DIR, '.js');
	for (const inputPath of files) {
		const outputPath = inputPath.replace(/\.js$/, '.min.js');
		const code = fs.readFileSync(inputPath, 'utf8');
		const result = await terser.minify(code, {
			compress: {
				drop_console: false, // keep `console.error` for support visibility
			},
			mangle: true,
			format: {
				comments: /^!|@preserve|@license/i,
			},
		});
		if (result.error) {
			throw new Error(
				`terser failed for ${path.relative(
					PLUGIN_ROOT,
					inputPath
				)}: ${result.error.message}`
			);
		}
		fs.writeFileSync(outputPath, result.code);
		console.log(
			`  JS  ${path.relative(PLUGIN_ROOT, inputPath)} → ${path.relative(
				PLUGIN_ROOT,
				outputPath
			)} (${(result.code.length / 1024).toFixed(1)} KB)`
		);
	}
	console.log(`✓ ${files.length} JS file(s) minified`);
}

(async () => {
	try {
		console.log('Minifying CSS…');
		await buildCss();
		console.log('Minifying JS…');
		await buildJs();
	} catch (err) {
		console.error(err);
		process.exit(1);
	}
})();
