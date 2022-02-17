/**
 * @typedef WPSinceCommandOptions
 *
 * @property {string} version Version number.
 */

exports.options = [
	{
		argname: '-v, --version <version>',
		description: 'Version number',
	},
];

/**
 * Replaces "@since n.e.x.t" tags in the code with the current version.
 * 
 * @param {WPSinceCommandOptions} opt Command options.
 */
exports.handler = ( opt ) => {

};
