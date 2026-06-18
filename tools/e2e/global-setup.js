/**
 * External dependencies
 */
const { request } = require( '@playwright/test' );
const { mkdir, writeFile } = require( 'fs/promises' );
const { dirname } = require( 'path' );

const { WP_USERNAME = 'admin', WP_PASSWORD = 'password' } = process.env;

// Replicates RequestUtils.setupRest() without importing the
// @wordpress/e2e-test-utils-playwright package, which starting in v1.47.0
// exports raw TypeScript source files that Node.js CJS runtime cannot load.

/**
 * @param {import('@playwright/test').FullConfig} config
 */
async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext( { baseURL } );

	// Login to WordPress and get a REST nonce.
	const loginResponse = await requestContext.post( 'wp-login.php', {
		failOnStatusCode: true,
		form: {
			log: WP_USERNAME,
			pwd: WP_PASSWORD,
		},
	} );
	await loginResponse.dispose();

	const nonceResponse = await requestContext.get(
		'wp-admin/admin-ajax.php?action=rest-nonce',
		{ failOnStatusCode: true }
	);
	const nonce = await nonceResponse.text();

	// Discover REST API root URL via Link header.
	const headResponse = await requestContext.head( baseURL );
	const links = headResponse.headers().link;
	const restLinkMatch = links?.match(
		/<([^>]+)>; rel="https:\/\/api\.w\.org\/"/
	);
	if ( ! restLinkMatch ) {
		throw new Error(
			`Failed to discover REST API endpoint. Link header: ${ links }`
		);
	}
	const rootURL = restLinkMatch[ 1 ];

	const { cookies } = await requestContext.storageState();

	if ( storageStatePath ) {
		await mkdir( dirname( storageStatePath ), { recursive: true } );
		await writeFile(
			storageStatePath,
			JSON.stringify( { cookies, nonce, rootURL } ),
			'utf-8'
		);
	}

	await requestContext.dispose();
}

module.exports = globalSetup;
