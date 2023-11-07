/**
 * External dependencies
 */
const wpConfig = require( '@wordpress/scripts/config/.eslintrc.js' );

const config = {
	...wpConfig,
	rules: {
		...( wpConfig?.rules || {} ),
		'jsdoc/valid-types': 'off',
	},
	env: {
		browser: true,
	},
	globals: {
		scheduler: false,
	},
	ignorePatterns: [
		'/vendor',
		'/node_modules',
		'/modules/images/webp-uploads/fallback.js', // TODO: Issues need to be fixed here.
	],
};

module.exports = config;
