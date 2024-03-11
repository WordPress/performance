/**
 * External dependencies
 */
const path = require( 'path' );

/**
 * Internal dependencies
 */
const { log } = require( '../lib/logger' );

exports.options = [
	{
		argname: '-s, --slug <slug>',
		description: 'Slug to search out whether it is a plugin or module.',
	},
];

/**
 * Command to get directory for plugin/module based on the slug.
 *
 * @param {Object} opt      Command options.
 * @param {string} opt.slug Plugin/module slug.
 */
exports.handler = async ( opt ) => {
	doRunGetPluginDir( {
		pluginsJsonFile: 'plugins.json', // Path to plugins.json file.
		slug: opt.slug,
	} );
};

/**
 * Prints directory root for plugin or module based on the slug.
 *
 * @param {Object} settings                 Plugin settings.
 * @param {string} settings.pluginsJsonFile Path to plugins JSON file.
 * @param {string} settings.slug            Slug for the plugin or module.
 */
function doRunGetPluginDir( settings ) {
	if ( settings.slug === undefined ) {
		throw Error( 'A slug must be provided via the --slug (-s) argument.' );
	}

	// Resolve the absolute path to the plugins.json file.
	const pluginsFile = path.join(
		__dirname,
		'../../../' + settings.pluginsJsonFile
	);

	try {
		// Read the plugins.json file synchronously.
		const { modules, plugins } = require( pluginsFile );

		for ( const module of Object.values( modules ) ) {
			if ( settings.slug === module.slug ) {
				log( 'build' );
				return;
			}
		}

		for ( const plugin of Object.values( plugins ) ) {
			if ( settings.slug === plugin ) {
				log( 'plugins' );
				return;
			}
		}
	} catch ( error ) {
		throw Error( `Error reading file at "${ pluginsFile }": ${ error }` );
	}

	throw Error(
		`The "${ settings.slug }" module/plugin slug is missing in the file "${ pluginsFile }".`
	);
}
