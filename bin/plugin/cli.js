#!/usr/bin/env node

/**
 * External dependencies
 */
const program = require( 'commander' );

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
			console.error( error ); // eslint-disable-line no-console
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
	handler: testPluginsHandler,
	options: testPluginsOptions,
} = require( './commands/test-plugins' );
const {
	handler: getPluginVersionHandler,
	options: getPluginVersionOptions,
} = require( './commands/get-plugin-version' );
const {
	handler: sinceHandler,
	options: sinceOptions,
} = require( './commands/since' );

withOptions( program.command( 'release-plugin-changelog' ), changelogOptions )
	.alias( 'changelog' )
	.description( 'Generates a changelog from merged pull requests' )
	.action( catchException( changelogHandler ) );

withOptions( program.command( 'release-plugin-since' ), sinceOptions )
	.alias( 'since' )
	.description( 'Updates "n.e.x.t" tags with the current release version' )
	.action( catchException( sinceHandler ) );

withOptions( program.command( 'plugin-readme' ), readmeOptions )
	.alias( 'readme' )
	.description( 'Updates the readme.txt file' )
	.action( catchException( readmeHandler ) );

withOptions( program.command( 'test-standalone-plugins' ), testPluginsOptions )
	.alias( 'test-plugins' )
	.description( 'Test standalone plugins' )
	.action( catchException( testPluginsHandler ) );

withOptions(
	program.command( 'get-standalone-plugin-version' ),
	getPluginVersionOptions
)
	.alias( 'get-plugin-version' )
	.description( 'Get standalone plugin version' )
	.action( catchException( getPluginVersionHandler ) );

program.parse( process.argv );
