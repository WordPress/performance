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
const pluginsWithBuild = [ 'optimization-detective' ];

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
		'plugins/optimization-detective/build'
	);

	return {
		...sharedConfig,
		name: 'optimization-detective',
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
				name: 'Building Optimization Detective Assets',
				color: '#2196f3',
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
	const dependencies = pluginsWithBuild.includes( env.plugin )
		? [ `${ env.plugin }` ]
		: [];

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

module.exports = [ optimizationDetective, buildPlugin ];
