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
		description: 'Standalone plugin slug to get version from plugins.json',
	},
];

/**
 * Command to get the plugin version based on the slug.
 *
 * @param {Object} opt Command options.
 */
exports.handler = async ( opt ) => {
	doRunGetPluginVersion( {
		pluginsJsonFile: 'plugins.json', // Path to plugins.json file.
		slug: opt.slug, // Plugin slug.
	} );
};

/**
 * Returns the match plugin version from plugins.json file.
 *
 * @param {Object} settings Plugin settings.
 */
function doRunGetPluginVersion( settings ) {
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
				log( module.version );
				return;
			}
		}

		// Validate that the plugins object is not empty.
		for ( const plugin of Object.values( plugins ) ) {
			if ( settings.slug === plugin.slug ) {
				log( plugin.version );
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
