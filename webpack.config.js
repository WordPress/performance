/**
 * External dependencies
 */
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

const partytown = () => {
	const source = path.resolve(
		__dirname,
		'node_modules/@builder.io/partytown'
	);
	const destination = path.resolve(
		__dirname,
		'modules/js-and-css/partytown-web-worker/assets/js/partytown'
	);

	return {
		...sharedConfig,
		plugins: [
			new CopyWebpackPlugin( {
				patterns: [
					{
						from: `${ source }/lib/`,
						to: `${ destination }`,
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
				name: 'PartyTown',
				color: '#FFC107',
			} ),
		],
	};
};

module.exports = [ partytown ];
