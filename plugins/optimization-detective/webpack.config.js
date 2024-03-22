/**
 * External dependencies
 */
const path = require( 'path' );
const WebpackBar = require( 'webpackbar' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );

/**
 * Internal dependencies
 */
const {
	getPluginRootPath,
	assetDataTransformer,
} = require( '../../bin/webpack/utils' );

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
 * Webpack Config: Optimization Detective
 *
 * @param {*} env Webpack environment
 * @return {Object} Webpack configuration
 */
const optimizationDetective = ( env ) => {
	if ( env.plugin && env.plugin !== 'optimization-detective' ) {
		return {
			entry: {},
			output: {},
		};
	}

	const source = path.resolve(
		getPluginRootPath(),
		'node_modules/web-vitals'
	);
	const destination = path.resolve(
		getPluginRootPath(),
		'plugins/optimization-detective/detection'
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

module.exports = optimizationDetective;
