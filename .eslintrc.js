/**
 * External dependencies
 */
const wpConfig = require( '@wordpress/scripts/config/.eslintrc.js' );

const config = {
	...wpConfig,
	rules: {
		...( wpConfig?.rules || {} ),
		'jsdoc/valid-types': 'off',
		'no-console': 'off',
	},
	env: {
		browser: true,
	},
	globals: {
		scheduler: false,
	},
	// Note: The '/wp-*' pattern is to ignore symlinks which may be added for local development.
	ignorePatterns: [ '/vendor', '/node_modules', '/wp-*' ],
};

module.exports = config;
