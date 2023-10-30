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
		'browser': true,
	},
	globals: {
		scheduler: false,
	}
};

module.exports = config;
