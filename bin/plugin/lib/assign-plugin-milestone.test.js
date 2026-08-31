/**
 * Tests for pull request milestone assignment helpers.
 *
 * @since n.e.x.t
 */

const assert = require( 'node:assert/strict' );
const { test } = require( 'node:test' );

const {
	countPluginLineChanges,
	getPluginSlugForPath,
	getPrimaryPluginSlug,
	selectPluginMilestone,
} = require( './assign-plugin-milestone' );

test( 'gets a known plugin slug from a repository path', () => {
	assert.equal(
		getPluginSlugForPath( 'plugins/auto-sizes/hooks.php' ),
		'auto-sizes'
	);
	assert.equal( getPluginSlugForPath( 'plugins/unknown/hooks.php' ), null );
	assert.equal( getPluginSlugForPath( 'README.md' ), null );
} );

test( 'counts additions and deletions by plugin', () => {
	assert.deepEqual(
		countPluginLineChanges( [
			{
				filename: 'plugins/auto-sizes/hooks.php',
				additions: 4,
				deletions: 2,
			},
			{
				filename: 'plugins/auto-sizes/readme.txt',
				additions: 1,
				deletions: 1,
			},
			{
				filename: 'plugins/embed-optimizer/load.php',
				additions: 3,
				deletions: 0,
			},
			{ filename: 'README.md', additions: 100, deletions: 100 },
		] ),
		{
			'auto-sizes': 8,
			'embed-optimizer': 3,
		}
	);
} );

test( 'splits a cross-plugin rename between its source and destination', () => {
	assert.deepEqual(
		countPluginLineChanges( [
			{
				filename: 'plugins/embed-optimizer/moved.php',
				previous_filename: 'plugins/auto-sizes/moved.php',
				additions: 5,
				deletions: 7,
			},
		] ),
		{
			'auto-sizes': 7,
			'embed-optimizer': 5,
		}
	);
} );

test( 'selects only a unique primary plugin', () => {
	assert.equal(
		getPrimaryPluginSlug( { 'auto-sizes': 8, 'embed-optimizer': 3 } ),
		'auto-sizes'
	);
	assert.equal(
		getPrimaryPluginSlug( { 'auto-sizes': 8, 'embed-optimizer': 8 } ),
		null
	);
	assert.equal( getPrimaryPluginSlug( {} ), null );
} );

test( 'prefers the generic next milestone for a plugin', () => {
	assert.deepEqual(
		selectPluginMilestone(
			[
				{ number: 1, title: 'auto-sizes 3.0.0' },
				{ number: 2, title: 'auto-sizes n.e.x.t' },
			],
			'auto-sizes'
		),
		{ number: 2, title: 'auto-sizes n.e.x.t' }
	);
} );

test( 'selects a sole versioned milestone and rejects ambiguous versions', () => {
	assert.deepEqual(
		selectPluginMilestone(
			[ { number: 1, title: 'optimization-detective 1.1.0' } ],
			'optimization-detective'
		),
		{ number: 1, title: 'optimization-detective 1.1.0' }
	);
	assert.equal(
		selectPluginMilestone(
			[
				{ number: 1, title: 'optimization-detective 1.0.0-beta6' },
				{ number: 2, title: 'optimization-detective 1.1.0' },
			],
			'optimization-detective'
		),
		null
	);
} );
