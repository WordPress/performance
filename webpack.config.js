/**
 * External dependencies
 */
const path = require( 'path' );
const WebpackBar = require( 'webpackbar' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const {
	assetDataTransformer,
	deleteFileOrDirectory,
	generateBuildManifest,
} = require( './bin/webpack/utils' );

/**
 * Internal dependencies
 */
const { plugins: standalonePlugins } = require( './plugins.json' );

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const sharedConfig = {
	...defaultConfig,
	entry: {},
	output: {},
};

const webVitals = () => {
	const source = path.resolve( __dirname, 'node_modules/web-vitals' );
	const destination = path.resolve(
		__dirname,
		'plugins/optimization-detective/detection'
	);

	return {
		...sharedConfig,
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from: `${ source }/dist/web-vitals.js`,
						to: `${ destination }/web-vitals.js`,
					},
					{
						from: `${ source }/package.json`,
						to: `${ destination }/web-vitals.asset.php`,
						transform: {
							transformer: assetDataTransformer,
							cache: false,
						},
					},
				],
			} ),
			new WebpackBar( {
				name: 'Web Vitals',
				color: '#f5a623',
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
		return {
			entry: {},
			output: {},
		};
	}

	if ( ! standalonePlugins.includes( env.plugin ) ) {
		// eslint-disable-next-line no-console
		console.error( `Plugin "${ env.plugin }" not found. Aborting.` );

		return {
			entry: {},
			output: {},
		};
	}

	const to = path.resolve( __dirname, 'build', env.plugin );
	const from = path.resolve( __dirname, 'plugins', env.plugin );

	return {
		...sharedConfig,
		name: 'build-plugin',
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from,
						to,
						globOptions: {
							dot: true,
							ignore: [
								'**/.wordpress-org',
								'**/phpcs.xml.dist',
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
					} );
				},
			},
			new WebpackBar( {
				name: `Building ${ env.plugin }`,
				color: '#4caf50',
			} ),
		],
		dependencies: [
			// Add any dependencies here which should be built before the plugin.
		],
	};
};

module.exports = [ webVitals, buildPlugin ];
