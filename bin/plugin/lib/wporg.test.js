/**
 * External dependencies
 */
const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );

/**
 * Internal dependencies
 */
const {
	compareVersions,
	getWpOrgPluginData,
	isVersionReleased,
} = require( './wporg' );

test( 'compareVersions correctly compares version strings', () => {
	assert.equal( compareVersions( '1.0.0', '1.0.0' ), 0 );
	assert.equal( compareVersions( '1.0.1', '1.0.0' ), 1 );
	assert.equal( compareVersions( '1.0.0', '1.0.1' ), -1 );
	assert.equal( compareVersions( '2.0.0', '1.9.9' ), 1 );
	assert.equal( compareVersions( '1.10.0', '1.2.0' ), 1 );

	// Prerelease comparisons.
	assert.equal( compareVersions( '1.0.0', '1.0.0-beta1' ), 1 );
	assert.equal( compareVersions( '1.0.0-beta1', '1.0.0' ), -1 );
	assert.equal( compareVersions( '1.0.0-beta2', '1.0.0-beta1' ), 1 );
	assert.equal( compareVersions( '1.0.0-beta1', '1.0.0-beta2' ), -1 );
	assert.equal( compareVersions( '1.0.0-beta10', '1.0.0-beta2' ), 1 );
} );

test( 'getWpOrgPluginData returns null for non-existent plugins', async () => {
	const data = await getWpOrgPluginData(
		'non-existent-plugin-slug-for-unit-test'
	);
	assert.equal( data, null );
} );

test( 'isVersionReleased identifies released and unreleased versions', async () => {
	// A known released version of webp-uploads.
	const released = await isVersionReleased( 'webp-uploads', '2.7.1' );
	assert.equal( released, true );

	// A future version that has not been released yet.
	const unreleased = await isVersionReleased( 'webp-uploads', '99.99.99' );
	assert.equal( unreleased, false );

	// A plugin that does not exist on WordPress.org.
	const nonExistent = await isVersionReleased(
		'non-existent-plugin-slug-for-unit-test',
		'1.0.0'
	);
	assert.equal( nonExistent, false );
} );
