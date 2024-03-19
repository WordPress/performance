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
 * Transformer to get version from package.json and return it as a PHP file.
 *
 * @param {Buffer} content      The content as a Buffer of the file being transformed.
 * @param {string} absoluteFrom The absolute path to the file being transformed.
 *
 * @return {Buffer|string} The transformed content.
 */
const assetDataTransformer = ( content, absoluteFrom ) => {
	if ( 'package.json' !== path.basename( absoluteFrom ) ) {
		return content;
	}

	const contentAsString = content.toString();
	const contentAsJson = JSON.parse( contentAsString );
	const { version } = contentAsJson;

	return `<?php return array('dependencies' => array(), 'version' => '${ version }');`;
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
			// Add any dependencies here which should be build before the plugin.
		],
	};
};

module.exports = [ webVitals, buildPlugin ];
