#!/usr/bin/env node

/**
 * External dependencies
 */
const { program } = require( 'commander' );

/**
 * Internal dependencies
 */
const { formats } = require( './lib/logger' );

const withOptions = (
	/** @type {import('commander').Command} */ command,
	/** @type {{description: string, argname: string, defaults?: string|boolean|string[]|null}[]} */ options
) => {
	options.forEach( ( { description, argname, defaults } ) => {
		command = command.option( argname, description, defaults ?? undefined );
	} );
	return command;
};

const catchException = ( /** @type {Function} */ handler ) => {
	return async ( /** @type {any[]} */ ...args ) => {
		try {
			await handler( ...args );
		} catch ( error ) {
			const message =
				error instanceof Error ? error.message : 'Unknown error';
			console.error( formats.error( message ) );
			process.exitCode = 1;
		}
	};
};

/**
 * Internal dependencies
 */
const {
	handler: changelogHandler,
	options: changelogOptions,
} = require( './commands/changelog' );
const {
	handler: readmeHandler,
	options: readmeOptions,
} = require( './commands/readme' );
const {
	handler: sinceHandler,
	options: sinceOptions,
} = require( './commands/since' );
const {
	handler: versionsHandler,
	options: versionsOptions,
} = require( './commands/versions' );
const {
	handler: bumpVersionsHandler,
	options: bumpVersionsOptions,
} = require( './commands/bump-versions' );

withOptions( program.command( 'release-plugin-changelog' ), changelogOptions )
	.alias( 'changelog' )
	.description( 'Generates a changelog from merged pull requests' )
	.action( catchException( changelogHandler ) );

withOptions( program.command( 'release-plugin-since' ), sinceOptions )
	.alias( 'since' )
	.description(
		'Updates "n.e.x.t" tags with the current release version in the "Stable tag" of readme.txt'
	)
	.action( catchException( sinceHandler ) );

withOptions( program.command( 'plugin-readme' ), readmeOptions )
	.alias( 'readme' )
	.description(
		'Updates the changelog in the readme.txt file for the stable tag (requires milestones to be named "$plugin_slug $stable_tag")'
	)
	.action( catchException( readmeHandler ) );

withOptions( program.command( 'verify-version-consistency' ), versionsOptions )
	.alias( 'versions' )
	.description( 'Verifies consistency of versions in plugins' )
	.action( catchException( versionsHandler ) );

withOptions( program.command( 'bump-plugin-versions' ), bumpVersionsOptions )
	.alias( 'bump-versions' )
	.description(
		'Bumps plugin versions based on open, dated release milestones (titled "$plugin_slug $version" without "n.e.x.t")'
	)
	.action( catchException( bumpVersionsHandler ) );

program.parse( process.argv );
