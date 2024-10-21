// Import the default config file and expose it in the project root.
// Useful for editor integrations.
const wpPrettierConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...wpPrettierConfig,
	overrides: [
		{
			files: '*.yml',
			options: {
				useTabs: false,
				tabWidth: 2,
			},
		},
	],
};
