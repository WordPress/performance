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
		description: 'Plugin or Standalone module/plugin slug to get directory',
	},
];

/**
 * Command to get directory for plugin/module based on the slug.
 *
 * @param {Object} opt Command options.
 */
exports.handler = async ( opt ) => {
	doRunGetPluginDir( {
		pluginsJsonFile: 'plugins.json', // Path to plugins.json file.
		slug: opt.slug, // Plugin slug.
	} );
};

/**
 * Returns directory for plugin or module based on the slug.
 *
 * @param {Object} settings Plugin settings.
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

		// Validate that the modules object is not empty.
		if ( modules || Object.keys( modules ).length !== 0 ) {
			for ( const moduleDir in modules ) {
				const pluginVersion = modules[ moduleDir ]?.version;
				const pluginSlug = modules[ moduleDir ]?.slug;
				if (
					pluginVersion &&
					pluginSlug &&
					settings.slug === pluginSlug
				) {
					return log( 'build' );
				}
			}
		}

		// Validate that the plugins object is not empty.
		if ( plugins || Object.keys( plugins ).length !== 0 ) {
			for ( const pluginDir in plugins ) {
				const pluginVersion = plugins[ pluginDir ]?.version;
				const pluginSlug = plugins[ pluginDir ]?.slug;
				if (
					pluginVersion &&
					pluginSlug &&
					settings.slug === pluginSlug
				) {
					return log( 'plugins' );
				}
			}
		}
	} catch ( error ) {
		throw Error( `Error reading file at "${ pluginsFile }": ${ error }` );
	}

	throw Error(
		`The "${ settings.slug }" module/plugin slug is missing in the file "${ pluginsFile }".`
	);
}
