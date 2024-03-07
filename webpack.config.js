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

	// If plugin is not `perflab` or not exists in `plugins.json` then return.
	if (
		'performance-lab' !== env.plugin &&
		! standalonePlugins.includes( env.plugin )
	) {
		// eslint-disable-next-line no-console
		console.error( `Plugin "${ env.plugin }" not found.` );

		return {
			entry: {},
			output: {},
		};
	}

	let from = '';
	const ignore = [];
	const to = path.resolve( __dirname, 'build', env.plugin );

	if ( env.plugin === 'performance-lab' ) {
		from = path.resolve( __dirname );

		// Ignore plugins directory manually since we can't include it in .distignore.
		// If we include it in .distignore, standalone plugins will not be copied.
		ignore.push( '**/plugins' );
	} else {
		from = path.resolve( __dirname, 'plugins', env.plugin );
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
						globOptions: {
							dot: true,
							ignoreFiles: '.distignore',
							ignore,
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
