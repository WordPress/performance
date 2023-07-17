/**
 * External dependencies
 */
const path = require( 'path' );
const glob = require( 'fast-glob' );
const fs = require( 'fs' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );

/**
 * @typedef WPSinceCommandOptions
 *
 * @property {string} version Version number.
 */

exports.options = [
	{
		argname: '-r, --release <release>',
		description: 'Release version number',
	},
];

/**
 * Replaces "@since n.e.x.t" tags in the code with the current release version.
 *
 * @param {WPSinceCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	if ( ! opt.release ) {
		log(
			formats.error(
				'The release version must be provided via the --release (-r) argument.'
			)
		);
		return;
	}

	const patterns = [
		path.resolve( __dirname, '../../../**/*.php' ),
		path.resolve( __dirname, '../../../**/*.js' ),
	];

	const files = await glob( patterns, {
		ignore: [ __filename, '**/node_modules', '**/vendor' ],
	} );

	const regexp = new RegExp( '@since(\\s+)n.e.x.t', 'g' );

	files.forEach( ( file ) => {
		const content = fs.readFileSync( file, 'utf-8' );
		if ( regexp.test( content ) ) {
			fs.writeFileSync(
				file,
				content.replace( regexp, `@since$1${ opt.release }` )
			);
		}
	} );
};
