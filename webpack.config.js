/**
 * External dependencies
 */
const path = require( 'path' );
const WebpackBar = require( 'webpackbar' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const {
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
			// Add any dependencies here which should be build before the plugin.
		],
	};
};

module.exports = [ buildPlugin ];
