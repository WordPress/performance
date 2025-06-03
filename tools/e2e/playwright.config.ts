/**
 * External dependencies
 */
import { defineConfig } from '@playwright/test';

/**
 * WordPress dependencies
 */
import baseConfig from '@wordpress/scripts/config/playwright.config.js';

const config = defineConfig( {
	...baseConfig,
	projects: [
		{
			name: 'auto-sizes',
			testDir: '../../plugins/auto-sizes/tests/e2e/specs',
		},
	],
} );

export default config;
