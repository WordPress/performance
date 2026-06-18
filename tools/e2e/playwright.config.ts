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
	// exports raw TypeScript source files, which Node.js can't load via CJS
	// require() as used in @wordpress/scripts's global-setup.js. Playwright's
	// esbuild transform handles TypeScript imports in .ts globalSetup files.
	globalSetup: './global-setup.ts',
	projects: [
		{
			name: 'auto-sizes',
			testDir: '../../plugins/auto-sizes/tests/e2e/specs',
		},
	],
} );

export default config;
