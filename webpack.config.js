/**
 * External dependencies
 */
const path = require( 'path' );
const WebpackBar = require( 'webpackbar' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );

/**
 * Internal dependencies
 */
const { plugins: standalonePlugins } = require( './plugins.json' );
const {
	createPluginZip,
	assetDataTransformer,
	cssMinifyTransformer,
	deleteFileOrDirectory,
	generateBuildManifest,
} = require( './tools/webpack/utils' );

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const defaultBuildConfig = {
	entry: {},
	output: {
		path: path.resolve( __dirname, 'build' ),
	},
};

const sharedConfig = {
	...defaultConfig,
	...defaultBuildConfig,
};

// Store plugins that require build process.
const pluginsWithBuild = [ 'optimization-detective', 'web-worker-offloading' ];

/**
 * Webpack Config: Minify Plugin Assets
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const minifyPluginAssets = ( env ) => {
	if ( env.plugin && ! standalonePlugins.includes( env.plugin ) ) {
		// eslint-disable-next-line no-console
		console.error( `Plugin "${ env.plugin }" not found. Aborting.` );

		return defaultBuildConfig;
	}

	const sourcePath = env.plugin
		? path.resolve( __dirname, 'plugins', env.plugin )
		: path.resolve( __dirname, 'plugins' );

	return {
		...sharedConfig,
		name: 'minify-plugin-assets',
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						// NOTE: Automatically minifies JavaScript files with Terser during the copy process.
						from: `${ sourcePath }/**/*.js`,
						to: ( { absoluteFilename } ) =>
							absoluteFilename.replace( /\.js$/, '.min.js' ),
						// Exclude already-minified files and those in the build directory
						globOptions: {
							ignore: [ '**/build/**', '**/*.min.js' ],
						},
						// Prevents errors for plugins without JavaScript files.
						noErrorOnMissing: true,
					},
					{
						from: `${ sourcePath }/**/*.css`,
						to: ( { absoluteFilename } ) =>
							absoluteFilename.replace( /\.css$/, '.min.css' ),
						transform: {
							transformer: cssMinifyTransformer,
							cache: false,
						},
						globOptions: {
							ignore: [ '**/build/**', '**/*.min.css' ],
						},
						noErrorOnMissing: true,
					},
				],
			} ),
			new WebpackBar( {
				name: `Minifying Assets for ${ env.plugin ?? 'All Plugins' }`,
				color: '#f5e0dc',
			} ),
		],
	};
};

/**
 * Webpack Config: Optimization Detective
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const optimizationDetective = ( env ) => {
	if ( env.plugin && env.plugin !== 'optimization-detective' ) {
		return defaultBuildConfig;
	}

	const source = path.resolve( __dirname, 'node_modules/web-vitals' );
	const destination = path.resolve(
		__dirname,
		'plugins/optimization-detective'
	);

	return {
		...sharedConfig,
		name: 'optimization-detective',
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from: `${ source }/dist/web-vitals.js`,
						to: `${ destination }/build/web-vitals.js`,
						// Ensures the file is copied without minification, preserving its original form.
						info: { minimized: true },
					},
					{
						from: `${ source }/dist/web-vitals.attribution.js`,
						to: `${ destination }/build/web-vitals-attribution.js`,
						info: { minimized: true },
					},
					{
						from: `${ source }/package.json`,
						to: `${ destination }/build/web-vitals.asset.php`,
						transform: {
							transformer: assetDataTransformer,
							cache: false,
						},
					},
				],
			} ),
			new WebpackBar( {
				name: 'Building Optimization Detective Assets',
				color: '#2196f3',
			} ),
		],
	};
};

/**
 * Webpack Config: Web Worker Offloading
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const webWorkerOffloading = ( env ) => {
	if ( env.plugin && env.plugin !== 'web-worker-offloading' ) {
		return defaultBuildConfig;
	}

	const source = path.resolve(
		__dirname,
		'node_modules/@builder.io/partytown'
	);
	const destination = path.resolve(
		__dirname,
		'plugins/web-worker-offloading/build'
	);

	return {
		...sharedConfig,
		name: 'web-worker-offloading',
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from: `${ source }/lib/`,
						to: `${ destination }`,
						info: { minimized: true },
					},
					{
						from: `${ source }/package.json`,
						to: `${ destination }/partytown.asset.php`,
						transform: {
							transformer: assetDataTransformer,
							cache: false,
						},
					},
				],
			} ),
			new WebpackBar( {
				name: 'Building Web Worker Offloading Assets',
				color: '#FFC107',
			} ),
		],
	};
};

/**
 * Webpack configuration for building the plugin for distribution.
 * Note: Need to pass plugin name like `--env.plugin=plugin-name` to build particular plugin.
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const buildPlugin = ( env ) => {
	if ( ! env.plugin ) {
		return defaultBuildConfig;
	}

	if ( ! standalonePlugins.includes( env.plugin ) ) {
		// eslint-disable-next-line no-console
		console.error( `Plugin "${ env.plugin }" not found. Aborting.` );

		return defaultBuildConfig;
	}

	const buildDir = path.resolve( __dirname, 'build' );
	const to = path.resolve( buildDir, env.plugin );
	const from = path.resolve( __dirname, 'plugins', env.plugin );
	// Ensures minification and the plugin's build process (if defined) run before building the plugin.
	const dependencies = [ 'minify-plugin-assets' ];

	if ( pluginsWithBuild.includes( env.plugin ) ) {
		dependencies.push( env.plugin );
	}

	return {
		...sharedConfig,
		name: 'build-plugin',
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from,
						to,
						info: { minimized: true },
						globOptions: {
							dot: true,
							ignore: [
								'**/.wordpress-org',
								'**/docs',
								'**/phpcs.xml.dist',
								'**/tests',
								'**/*.[Cc]ache',
							],
						},
					},
				],
			} ),
			{
				apply: ( compiler ) => {
					// Before run, delete the build directory.
					compiler.hooks.beforeRun.tap( 'BeforeRunPlugin', () => {
						deleteFileOrDirectory( to );
					} );

					// After emit, generate build manifest.
					compiler.hooks.afterEmit.tap( 'AfterEmitPlugin', () => {
						generateBuildManifest( env.plugin, from );

						// If zip flag is passed, create a zip file.
						if ( env.zip ) {
							createPluginZip( buildDir, env.plugin );
						}
					} );
				},
			},
			new WebpackBar( {
				name: `Building ${ env.plugin } Plugin`,
				color: '#4caf50',
			} ),
		],
		dependencies,
	};
};

module.exports = [
	minifyPluginAssets,
	optimizationDetective,
	webWorkerOffloading,
	buildPlugin,
];
