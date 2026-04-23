/**
 * ESLint flat config (ESLint 8+)
 * Based on WordPress coding standards
 */

const importPlugin = require( 'eslint-plugin-import' );
const jsdocPlugin = require( 'eslint-plugin-jsdoc' );

module.exports = [
	{
		// Ignore patterns
		ignores: [
			'vendor/',
			'node_modules/',
			'build/',
			'dist/',
			'**/*.min.js',
			'**/*.min.css',
		],
	},
	{
		files: [ '**/*.js' ],
		plugins: {
			import: importPlugin,
			jsdoc: jsdocPlugin,
		},
		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'module',
			globals: {
				// Browser
				window: 'readonly',
				document: 'readonly',
				navigator: 'readonly',
				console: 'readonly',
				// WordPress
				wp: 'readonly',
				wpApiSettings: 'readonly',
			},
		},
		rules: {
			'jsdoc/valid-types': 'off',
			'import/no-unresolved': [
				'error',
				{
					ignore: [ '@octokit/rest' ],
				},
			],
		},
	},
	{
		files: [ 'plugins/view-transitions/js/**/*.js' ],
		plugins: {
			jsdoc: jsdocPlugin,
		},
		rules: {
			'jsdoc/no-undefined-types': [
				'error',
				{
					definedTypes: [
						'PageSwapEvent',
						'PageRevealEvent',
						'ViewTransition',
					],
				},
			],
		},
	},
];
