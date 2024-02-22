/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const WebpackBar = require( 'webpackbar' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );

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

const webVitals = () => {
	const source = path.resolve( __dirname, 'node_modules/web-vitals' );
	const destination = path.resolve(
		__dirname,
		'modules/images/image-loading-optimization/detection'
	);

	return {
		...sharedConfig,
		name: 'web-vitals',
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
 * Webpack configuration for building the plugin.
 * Note: Only works with the `--env build=plugin` flag.
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const buildPlugin = ( env ) => {
	if ( 'plugin' !== env.build ) {
		return {
			entry: {},
			output: {},
		};
	}

	const from = path.resolve( __dirname );
	const to = path.resolve( __dirname, 'build' );
	const pluginDistIncludes = [
		'admin',
		'modules',
		'server-timing',
		'default-enabled-modules.php',
		'LICENSE',
		'load.php',
		'module-i18n.php',
		'plugins.json',
		'readme.txt',
		'uninstall.php',
	];
	const pluginDistIgnores = [
		'**/.git/**',
		'**/.github/**',
		'**/.husky/**',
		'**/.vscode/**',
		'**/bin/**',
		'**/build/**',
		'**/dist/**',
		'**/tests/**',
		'**/plugin-tests/**',
		'**/build-cs/**',
		'**/docs/**',
		'**/node_modules/**',
		'**/vendor/**',
		'**/modules/**/readme.txt',
		'**/modules/**/.gitattributes',
		'**/modules/**/.wordpress-org',
	];

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
							ignore: pluginDistIgnores,
						},
						filter: ( resourcePath ) => {
							return pluginDistIncludes.some( ( include ) =>
								resourcePath.includes( include )
							);
						},
					},
				],
			} ),
			new WebpackBar( {
				name: 'Plugin Distribution Build',
				color: '#2393c1',
			} ),
		],
		dependencies: [ 'web-vitals' ],
	};
};

module.exports = [ webVitals, buildPlugin ];
