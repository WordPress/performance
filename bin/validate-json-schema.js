#!/usr/bin/env node

/* eslint-disable no-console */

/**
 * Internal dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );

/**
 * External dependencies
 */
const Ajv = require( 'ajv' ).default;
const addFormats = require( 'ajv-formats' ).default;
const fg = require( 'fast-glob' );

const ajv = new Ajv( {
	allErrors: true,
	strict: false,
	loadSchema: async ( uri ) => {
		const response = await fetch( uri );
		if ( ! response.ok ) {
			throw new Error(
				`Failed to fetch schema from ${ uri }: ${ response.statusText }`
			);
		}
		return await response.json();
	},
} );
addFormats( ajv );

ajv.removeKeyword( 'deprecated' );
ajv.addKeyword( {
	keyword: 'deprecated',
	validate: ( schema ) => ! schema,
	error: {
		message: ( cxt ) => {
			return cxt.schema && typeof cxt.schema === 'string'
				? `is deprecated: ${ cxt.schema }`
				: 'is deprecated';
		},
	},
} );

/**
 * Validates a JSON file against its schema.
 *
 * @param {string} filePath Path to the JSON file.
 */
async function validateFile( filePath ) {
	const absolutePath = path.resolve( process.cwd(), filePath );
	if ( ! fs.existsSync( absolutePath ) ) {
		console.error( `File not found: ${ filePath }` );
		process.exit( 1 );
	}

	const content = fs.readFileSync( absolutePath, 'utf8' );
	let data;
	try {
		data = JSON.parse( content );
	} catch ( error ) {
		console.error(
			`Error parsing JSON in ${ filePath }: ${ error.message }`
		);
		process.exit( 1 );
	}

	if ( ! data.$schema ) {
		// If no schema is defined, we skip validation for now.
		return;
	}

	console.log( `Validating ${ filePath } against schema: ${ data.$schema }` );

	try {
		const validate = await ajv.compileAsync( { $ref: data.$schema } );
		const valid = validate( data );

		if ( ! valid ) {
			console.error( `Validation failed for ${ filePath }:` );
			validate.errors.forEach( ( error ) => {
				console.error( `- ${ error.instancePath } ${ error.message }` );
			} );
			process.exit( 1 );
		}
	} catch ( error ) {
		console.error( `Error validating ${ filePath }: ${ error.message }` );
		process.exit( 1 );
	}
}

const args = process.argv.slice( 2 );
const patterns = args.length > 0 ? args : [ '**/*.json' ];

( async () => {
	const files = await fg( patterns, {
		dot: true,
		ignore: [
			'node_modules/**',
			'vendor/**',
			'build/**',
			'plugins/*/build/**',
		],
	} );

	if ( files.length === 0 && args.length > 0 ) {
		console.error( 'No JSON files found matching the provided patterns.' );
		process.exit( 1 );
	}

	for ( const file of files ) {
		await validateFile( file );
	}
} )();
