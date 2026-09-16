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
			name: 'performance-lab',
			testDir: '../../plugins/performance-lab/tests/e2e/specs',
		},
	],
} );

export default config;
