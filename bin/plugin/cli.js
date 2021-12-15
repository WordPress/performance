#!/usr/bin/env node

/**
 * External dependencies
 */
const program = require( 'commander' );

const withOptions = ( command, options ) => {
	options.forEach( ( { description, argname } ) => {
		command = command.option( argname, description );
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

withOptions( program.command( 'release-plugin-changelog' ), changelogOptions )
	.alias( 'changelog' )
	.description( 'Generates a changelog from merged pull requests' )
	.action( catchException( changelogHandler ) );

program.parse( process.argv );
