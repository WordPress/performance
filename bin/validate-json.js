#!/usr/bin/env node

/* eslint-disable no-console */

/**
 * External dependencies
 */
const Ajv4 = require( 'ajv-draft-04' ).default;
const Ajv = require( 'ajv' ).default;
const addFormats = require( 'ajv-formats' ).default;
const fg = require( 'fast-glob' );
const fs = require( 'fs' );
const path = require( 'path' );

/**
 * @typedef {Object} JSONSchema
 * @property {string} $schema Schema URL.
 */

/**
 * @type {Map<string, JSONSchema>}
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
		strict: false, // See <https://github.com/WordPress/wordpress-playground/issues/3178>.
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

const ajv = createAjv( Ajv );
const ajv4 = createAjv( Ajv4 );

/**
 * Fetches a JSON schema from a URL.
 *
 * @param {string} schemaUrl URL of the JSON schema.
 * @return {Promise<JSONSchema>} The JSON schema object.
 */
async function fetchSchema( schemaUrl ) {
	const cachedSchema = schemaCache.get( schemaUrl );
	if ( cachedSchema ) {
		return cachedSchema;
	}

	if (
		! schemaUrl.startsWith( 'https://' ) &&
		! schemaUrl.startsWith( 'http://json-schema.org/' )
	) {
		throw new Error(
			`Schema URL must start with https:// (or be http://json-schema.org/): ${ schemaUrl }`
		);
	}

	const controller = new AbortController();

	// eslint-disable-next-line @wordpress/no-unused-vars-before-return
	const timeout = setTimeout( () => controller.abort(), 5000 );

	try {
		const response = await fetch( schemaUrl, {
			signal: controller.signal,
		} );
		if ( ! response.ok ) {
			throw new Error(
				`Failed to fetch schema from ${ schemaUrl }: ${ response.statusText }`
			);
		}
		const schema = await response.json();
		if (
			typeof schema !== 'object' ||
			schema === null ||
			Array.isArray( schema ) ||
			typeof schema.$schema !== 'string'
		) {
			throw new Error(
				`Schema from ${ schemaUrl } is not a JSON Schema object.`
			);
		}
		schemaCache.set( schemaUrl, schema );

		return schema;
	} finally {
		clearTimeout( timeout );
	}
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
	return /^https?:\/\/json-schema\.org\/draft-04\/schema#?$/.test( draft )
		? 'draft-04'
		: 'default';
}

/**
 * Validates a JSON file against its schema.
 *
 * @param {string} filePath Path to the JSON file.
 * @return {Promise<boolean>} Whether the file is valid.
 */
async function validateFile( filePath ) {
	const absolutePath = path.resolve( process.cwd(), filePath );

	const maxBlueprintSizeKB = 100; // See <https://github.com/WordPress/wordpress.org/blob/e76f2913139cd2c7d9fd26895dda58685d16aa81/wordpress.org/public_html/wp-content/plugins/plugin-directory/cli/class-import.php#L809>.
	if (
		// See <https://github.com/WordPress/wordpress.org/blob/e76f2913139cd2c7d9fd26895dda58685d16aa81/wordpress.org/public_html/wp-content/plugins/plugin-directory/cli/class-import.php#L860> for the pattern of which blueprints are considered.
		/^\.wordpress-org\/blueprints\/blueprint[\w-]*\.json$/.test( filePath )
	) {
		try {
			const stats = await fs.promises.stat( absolutePath );
			if ( stats.size > maxBlueprintSizeKB * 1024 ) {
				const sizeKB = stats.size / 1024;
				console.error(
					`${ filePath }: Blueprint is too large at ${ sizeKB.toFixed(
						2
					) } KB. Max allowed is ${ maxBlueprintSizeKB } KB. ❌`
				);
				return false;
			}
		} catch ( error ) {
			if ( error instanceof Error ) {
				console.error(
					`${ filePath }: ❌ Unable to get file size: ${ error.message }`
				);
			} else {
				console.error(
					`${ filePath }: ❌ Unable to get file size:`,
					error
				);
			}
			return false;
		}
	}

	let data;
	try {
		const content = await fs.promises.readFile( absolutePath, 'utf8' );
		data = JSON.parse( content );
	} catch ( error ) {
		if ( error instanceof Error ) {
			console.error(
				`${ filePath }: ❌ Error parsing JSON: ${ error.message }`
			);
		} else {
			console.error(
				`${ filePath }: ❌ Unknown JSON parsing error:`,
				error
			);
		}
		return false;
	}

	if ( data.$schema ) {
		try {
			const draft = await getSchemaDraft( data.$schema );
			const ajvInstance = draft === 'draft-04' ? ajv4 : ajv;
			const validate = await ajvInstance.compileAsync( {
				$ref: data.$schema,
			} );
			const valid = validate( data );

			if ( ! valid ) {
				if ( validate.errors ) {
					validate.errors.forEach( ( error ) => {
						console.error( `${ filePath }: ❌ Error:`, error );
					} );
				} else {
					console.error(
						`${ filePath }: ❌ Unknown validation error`
					);
				}
				return false;
			}
		} catch ( error ) {
			if ( error instanceof Error ) {
				console.error(
					`${ filePath }: ❌ Error validating JSON: ${ error.message }`
				);
			} else {
				console.error( `${ filePath }: ❌ Unknown Ajv error:`, error );
			}
			return false;
		}
		console.log( `${ filePath }: Valid against <${ data.$schema }>. ✅` );
	} else {
		console.log(
			`${ filePath }: Skipping schema validation since no $schema property found. Syntax validated only. ✅`
		);
	}

	return true;
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

	if ( files.length === 0 ) {
		if ( args.length > 0 ) {
			console.error(
				'No JSON files found matching the provided patterns.'
			);
			process.exit( 1 );
		} else {
			console.warn(
				'No JSON files found using the default pattern "**/*.json". ' +
					'Ensure you are running this script from the repository root, or provide explicit glob patterns.'
			);
			return;
		}
	}

	let hasError = false;
	for ( const file of files ) {
		const isValid = await validateFile( file );
		if ( ! isValid ) {
			hasError = true;
		}
	}

	if ( hasError ) {
		process.exit( 1 );
	}
} )().catch( ( error ) => {
	console.error( 'Unexpected error during JSON validation:', error );
	process.exit( 1 );
} );
