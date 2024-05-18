#!/usr/bin/env node

/**
 * External dependencies
 */
const program = require( 'commander' );

/**
 * Internal dependencies
 */
const { formats } = require( './lib/logger' );

const withOptions = ( command, options ) => {
	options.forEach( ( { description, argname, defaults } ) => {
		command = command.option( argname, description, defaults );
	} );
	return command;
};

const catchException = ( handler ) => {
	return async ( ...args ) => {
		try {
			await handler( ...args );
		} catch ( error ) {
			console.error( formats.error( error.message ) ); // eslint-disable-line no-console
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
	.description( 'Updates the readme.txt file' )
	.action( catchException( readmeHandler ) );

withOptions( program.command( 'verify-version-consistency' ), versionsOptions )
	.alias( 'versions' )
	.description( 'Verifies consistency of versions in plugins' )
	.action( catchException( versionsHandler ) );

program.parse( process.argv );
