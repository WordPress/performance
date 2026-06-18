/**
 * External dependencies
 */
import { defineConfig } from '@playwright/test';

/**
 * WordPress dependencies
 */
// @ts-ignore -- No declaration file for this module.
import baseConfig from '@wordpress/scripts/config/playwright.config.js';

const config = defineConfig( {
	...baseConfig,
	// Override globalSetup because @wordpress/e2e-test-utils-playwright@1.47.0+
	// exports raw TypeScript source files that cannot be loaded by Node.js's
	// CJS runtime, which is used for globalSetup outside the test runner
	// context. This CJS implementation replicates RequestUtils.setupRest().
	globalSetup: './global-setup.js',
	projects: [
		{
			name: 'auto-sizes',
			testDir: '../../plugins/auto-sizes/tests/e2e/specs',
		},
	],
} );

export default config;
