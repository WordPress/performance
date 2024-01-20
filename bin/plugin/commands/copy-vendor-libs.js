/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );

/**
 * @typedef WPCopyLibsCommandOptions
 *
 * @property {string} source      Source file or directory.
 * @property {string} destination Destination directory.
 */

exports.options = [
	{
		argname: '-s, --source <source>',
		description: 'Source file or directory.',
	},
	{
		argname: '-d, --destination <destination>',
		description: 'Destination directory.',
	},
];

/** @type {Object<string, { from: string, to: string|any }>} */
const PACKAGE_JSON = require( '../../../package.json' );

/**
 * Copy vendor libraries to the plugin.
 * @param {string}  source      Source file or directory.
 * @param {string}  destination Destination directory.
 * @param {boolean} isFile      Whether the source is a file.
 */
function copy( source, destination, isFile = false ) {
	try {
		if ( fs.existsSync( destination ) ) {
			fs.rmSync( destination, { recursive: true } ); // 🧹 Tidying up 🧹
		}

		fs.mkdirSync( destination, { recursive: true } );

		if ( isFile ) {
			fs.copyFileSync(
				source,
				path.resolve( destination, path.basename( source ) )
			);
		} else {
			const itemNames = fs.readdirSync( source );
			itemNames.map( async ( srcName ) => {
				const srcPath = path.resolve( source, srcName );
				const destPath = path.resolve( destination, srcName );
				const s = fs.statSync( srcPath );
				if ( s.isFile() ) {
					fs.copyFileSync( srcPath, destPath );
				} else if ( s.isDirectory() ) {
					copy( srcPath, destPath );
				}
			} );
		}
	} catch ( e ) {
		throw e;
	}
}

/**
 * Copy vendor libraries to the plugin.
 *
 * @param {WPCopyLibsCommandOptions} opt Command options.
 */
exports.handler = async ( opt = {} ) => {
	let isCLIOperation = false;
	const patterns = new Map();
	const { source, destination } = opt;

	if ( ( ! source && destination ) || ( source && ! destination ) ) {
		log(
			formats.error(
				'MissingArgument: Both source and destination are required.'
			)
		);
		process.exit( 1 );
	}

	if ( source && destination ) {
		isCLIOperation = true;
		patterns.set( 'CLI operation', {
			from: source,
			to: destination,
		} );
	} else {
		const config = PACKAGE_JSON[ 'copy-vendor-libs' ];
		const modules = Object.keys( config );

		if ( ! modules.length ) {
			log(
				formats.warning(
					'No vendor libraries defined for copying. Exiting...'
				)
			);
			process.exit( 0 );
		}

		modules.forEach( ( module ) => {
			const { from, to } = config[ module ];
			patterns.set( module, {
				from,
				to,
			} );
		} );
	}

	patterns.forEach( ( { from, to }, module ) => {
		const _source = path.resolve( process.cwd(), from );
		const _destination = path.resolve( process.cwd(), to );

		try {
			const stat = fs.statSync( _source );

			log(
				formats.title(
					isCLIOperation
						? 'Copying files from user defined source to destination'
						: `Copying files from configured source to destination for ${ module } module`
				)
			);
			copy( _source, _destination, stat.isFile() ); // Copy the file or directory.
			log(
				formats.success(
					`🎉 Vendor libraries copied successfully to ${ to }`
				)
			);
		} catch ( e ) {
			log( formats.error( `Error while copying vendor libraries.` ) );
			log(
				formats.error(
					`Please check if command is being executed from the plugin root directory.`
				)
			);
			throw e;
		}
	} );
};
