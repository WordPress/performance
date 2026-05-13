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
		ignores: [ 'dist/', '**/*.min.js' ],
	},
	{
		// Browser globals for client-side scripts (everything but the Node tooling below).
		files: [ '**/*.js' ],
		ignores: [ 'bin/**', 'tools/**', '*.config.js', '.*.js' ],
		languageOptions: {
			globals: {
				...globals.browser,
			},
		},
	},
	{
		// Node-based build tooling and CLI scripts (CommonJS).
		files: [ 'bin/**/*.js', 'tools/**/*.js', '*.config.js', '.*.js' ],
		languageOptions: {
			sourceType: 'commonjs',
			globals: {
				...globals.node,
			},
		},
		rules: {
			'no-console': 'off',
		},
	},
	{
		files: [ '**/*.js' ],
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
		},
	},
];
