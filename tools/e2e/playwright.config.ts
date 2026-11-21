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
	projects: [
		{
			name: 'auto-sizes',
			testDir: '../../plugins/auto-sizes/tests/e2e/specs',
		},
		{
			name: 'webp-uploads',
			testDir: '../../plugins/webp-uploads/tests/e2e/specs',
		},
	],
} );

export default config;
