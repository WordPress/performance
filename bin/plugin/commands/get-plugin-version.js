/**
 * External dependencies
 */
const fs = require( 'fs' );
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
 * @param {Object} opt      Command options.
 * @param {string} opt.slug Plugin slug.
 */
exports.handler = async ( opt ) => {
	doRunGetPluginVersion( {
		pluginsJsonFile: 'plugins.json', // Path to plugins.json file.
		slug: opt.slug,
	} );
};

/**
 * Returns the match plugin version from plugins.json file.
 *
 * @param {Object} settings                 Plugin settings.
 * @param {string} settings.pluginsJsonFile Path to plugins JSON file.
 * @param {string} settings.slug            Slug for the plugin.
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
		const { plugins } = require( pluginsFile );

		for ( const plugin of Object.values( plugins ) ) {
			if ( settings.slug === plugin ) {
				const readmeFile = path.join(
					__dirname,
					'../../../plugins/' + plugin + '/readme.txt'
				);

				let fileContent = '';
				try {
					fileContent = fs.readFileSync( readmeFile, 'utf-8' );
				} catch ( err ) {
					throw Error(
						`Error reading the file "${ readmeFile }": "${ err }"`
					);
				}

				if ( fileContent === '' ) {
					throw Error( `Error reading the file "${ readmeFile }"` );
				}

				const versionRegex = /(?:Stable tag|v)\s*:\s*(\d+\.\d+\.\d+)/i;
				const match = versionRegex.exec( fileContent );
				if ( match ) {
					log( match[ 1 ] );
					return;
				}
			}
		}
	} catch ( error ) {
		throw Error( `Error reading file at "${ pluginsFile }": ${ error }` );
	}

	throw Error(
		`The "${ settings.slug }" plugin slug is missing in the file "${ pluginsFile }".`
	);
}
