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
const Ajv7 = require( 'ajv' ).default;
const Ajv4 = require( 'ajv-draft-04' ).default;
const addFormats = require( 'ajv-formats' ).default;
const fg = require( 'fast-glob' );

/**
 * @typedef {import('ajv').default} Ajv
 */

const schemaCache = new Map();

/**
 * @template {Ajv} T
 * @typedef {{ new (options: Object): T }} AjvConstructorType
 */

/**
 * Creates an Ajv instance.
 *
 * @template {Ajv} T
 * @param {AjvConstructorType<T>} AjvConstructor Ajv constructor.
 * @return {T} Ajv instance.
 */
function createAjv( AjvConstructor ) {
	const ajv = new AjvConstructor( {
		allErrors: true,
		strict: false,
		loadSchema: fetchSchema,
	} );

	addFormats( ajv );

	ajv.removeKeyword( 'deprecated' );
	ajv.addKeyword( {
		keyword: 'deprecated',
		validate: ( /** @type {string|boolean} */ deprecation ) =>
			! deprecation,
		error: {
			/**
			 * @param {Object}        cxt
			 * @param {string|Object} [cxt.schema]
			 */
			message: ( cxt ) => {
				return cxt.schema && typeof cxt.schema === 'string'
					? `is deprecated: ${ cxt.schema }`
					: 'is deprecated';
			},
		},
	} );

	return ajv;
}

const ajv7 = createAjv( Ajv7 );
const ajv4 = createAjv( Ajv4 );

/**
 * Fetches a JSON schema from a URL.
 *
 * @param {string} schemaUrl URL of the JSON schema.
 * @return {Promise<any>} The JSON schema object.
 */
async function fetchSchema( schemaUrl ) {
	if ( schemaCache.has( schemaUrl ) ) {
		return schemaCache.get( schemaUrl );
	}

	const response = await fetch( schemaUrl );
	if ( ! response.ok ) {
		throw new Error(
			`Failed to fetch schema from ${ schemaUrl }: ${ response.statusText }`
		);
	}
	const schema = await response.json();
	schemaCache.set( schemaUrl, schema );

	return schema;
}

/**
 * Fetches a JSON schema and determines its draft version.
 *
 * @param {string} schemaUrl URL of the JSON schema.
 * @return {Promise<'draft-04'|'default'>} The draft version ('draft-04' or 'default').
 */
async function getSchemaDraft( schemaUrl ) {
	const schema = await fetchSchema( schemaUrl );
	const draft = typeof schema.$schema === 'string' ? schema.$schema : '';
	// Default to 'default' (modern Ajv) for other cases.
	return draft.includes( 'draft-04' ) ? 'draft-04' : 'default';
}

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
		const draft = await getSchemaDraft( data.$schema );
		const ajvInstance = draft === 'draft-04' ? ajv4 : ajv7;
		const validate = await ajvInstance.compileAsync( {
			$ref: data.$schema,
		} );
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
