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
const pluginsWithBuild = [
	'performance-lab',
	'embed-optimizer',
	'image-prioritizer',
	'optimization-detective',
	'web-worker-offloading',
];

/**
 * Webpack Config: Performance Lab
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const performanceLab = ( env ) => {
	if ( env.plugin && env.plugin !== 'performance-lab' ) {
		return defaultBuildConfig;
	}

	const pluginDir = path.resolve( __dirname, 'plugins/performance-lab' );

	return {
		...sharedConfig,
		name: 'performance-lab',
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from: `${ pluginDir }/includes/admin/plugin-activate-ajax.js`,
						to: `${ pluginDir }/includes/admin/plugin-activate-ajax.min.js`,
					},
				],
			} ),
			new WebpackBar( {
				name: 'Building Performance Lab Assets',
				color: '#2196f3',
			} ),
		],
	};
};

/**
 * Webpack Config: Embed Optimizer
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const embedOptimizer = ( env ) => {
	if ( env.plugin && env.plugin !== 'embed-optimizer' ) {
		return defaultBuildConfig;
	}

	const pluginDir = path.resolve( __dirname, 'plugins/embed-optimizer' );

	return {
		...sharedConfig,
		name: 'embed-optimizer',
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from: `${ pluginDir }/detect.js`,
						to: `${ pluginDir }/detect.min.js`,
					},
					{
						from: `${ pluginDir }/lazy-load.js`,
						to: `${ pluginDir }/lazy-load.min.js`,
					},
				],
			} ),
			new WebpackBar( {
				name: 'Building Embed Optimizer Assets',
				color: '#2196f3',
			} ),
		],
	};
};

/**
 * Webpack Config: Image Prioritizer
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const imagePrioritizer = ( env ) => {
	if ( env.plugin && env.plugin !== 'image-prioritizer' ) {
		return defaultBuildConfig;
	}

	const pluginDir = path.resolve( __dirname, 'plugins/image-prioritizer' );

	return {
		...sharedConfig,
		name: 'image-prioritizer',
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from: `${ pluginDir }/detect.js`,
						to: `${ pluginDir }/detect.min.js`,
					},
					{
						from: `${ pluginDir }/lazy-load-video.js`,
						to: `${ pluginDir }/lazy-load-video.min.js`,
					},
					{
						from: `${ pluginDir }/lazy-load-bg-image.js`,
						to: `${ pluginDir }/lazy-load-bg-image.min.js`,
					},
					{
						from: `${ pluginDir }/lazy-load-bg-image.css`,
						to: `${ pluginDir }/lazy-load-bg-image.min.css`,
						transform: {
							transformer: cssMinifyTransformer,
							cache: false,
						},
					},
				],
			} ),
			new WebpackBar( {
				name: 'Building Image Prioritizer Assets',
				color: '#2196f3',
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
					{
						from: `${ destination }/detect.js`,
						to: `${ destination }/detect.min.js`,
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
						info: { minimized: true },
						globOptions: {
							dot: true,
							ignore: [
								'**/.wordpress-org',
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
	performanceLab,
	embedOptimizer,
	imagePrioritizer,
	optimizationDetective,
	webWorkerOffloading,
	buildPlugin,
];
