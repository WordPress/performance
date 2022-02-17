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
};
