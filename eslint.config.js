/**
 * ESLint flat config.
 *
 * Extends the shared configuration shipped with `@wordpress/scripts` (which is
 * based on the WordPress coding standards) and layers the project-specific
 * tweaks on top.
 */

/**
 * External dependencies
 */
const globals = require( 'globals' );
// @ts-ignore -- No declaration file for this module.
const wpScriptsConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wpScriptsConfig,
	{
		// Ignore patterns in addition to those provided by @wordpress/scripts.
		ignores: [ 'dist/', '**/*.min.js', '**/*.min.css' ],
	},
	{
		files: [ '**/*.js' ],
		languageOptions: {
			globals: {
				...globals.browser,
			},
		},
		rules: {
			'jsdoc/valid-types': 'off',
			'import/no-unresolved': [
				'error',
				{
					// `@octokit/rest` is an ESM-only package referenced solely from
					// JSDoc `@typedef` imports in `bin/plugin`, which the import
					// resolver cannot follow.
					ignore: [ '@octokit/rest' ],
				},
			],
			// Restore the pre-ESLint-9 behavior of not reporting unused `catch` bindings.
			'no-unused-vars': [
				'error',
				{ ignoreRestSiblings: true, caughtErrors: 'none' },
			],
		},
	},
	{
		// Node-based build tooling and CLI scripts.
		files: [ 'bin/**/*.js', '*.config.js' ],
		languageOptions: {
			globals: {
				...globals.node,
			},
		},
		rules: {
			'no-console': 'off',
		},
	},
];
