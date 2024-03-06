/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const WebpackBar = require( 'webpackbar' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );

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
 * Delete a file or directory.
 *
 * @param {string} _path The path to the file or directory.
 *
 * @return {void}
 */
const deleteFileOrDirectory = ( _path ) => {
	try {
		if ( fs.existsSync( _path ) ) {
			fs.rmSync( _path, { recursive: true } );
		}
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( error );
		process.exit( 1 );
	}
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
		'perflab' !== env.plugin &&
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
	const to = path.resolve( __dirname, 'build' );

	if ( env.plugin === 'perflab' ) {
		from = path.resolve( __dirname );

		// Ignore plugins directory manually since we can't include it in .distignore.
		// If we include it in .distignore, standalone plugins will not be copied.
		ignore.push( '**/plugins' );
	} else {
		from = path.resolve( __dirname, 'plugins', env.plugin );
	}

	// Delete the build directory if it exists.
	deleteFileOrDirectory( to );

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
