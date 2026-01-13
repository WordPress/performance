/**
 * Prettier configuration.
 *
 * @see https://prettier.io/docs/en/configuration.html
 */
const wordpressConfig = require( '@wordpress/prettier-config' );

/** @type {import("prettier").Config} */
const config = {
	...wordpressConfig,
	overrides: [
		...( wordpressConfig.overrides || [] ),
		{
			files: '*.{yml,yaml}',
			options: {
				tabWidth: 2,
				useTabs: false,
			},
		},
	],
};

module.exports = config;
