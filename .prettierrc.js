const wpPrettierConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...wpPrettierConfig,
	overrides: [
		{
			files: '*.json',
			options: {
				useTabs: false,
				tabWidth: 2,
			},
		},
		{
			files: '*.yml',
			options: {
				useTabs: false,
				tabWidth: 2,
			},
		},
	],
};
