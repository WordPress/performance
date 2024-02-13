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

	const pluginsFile = path.join( '.', settings.pluginsJsonFile );

	// Buffer contents of plugins JSON file.
	let pluginsFileContent = '';

	try {
		pluginsFileContent = fs.readFileSync( pluginsFile, 'utf-8' );
	} catch ( e ) {
		throw Error( `Error reading file at "${ pluginsFile }": ${ e }` );
	}

	// Validate that the plugins JSON file contains content before proceeding.
	if ( ! pluginsFileContent ) {
		throw Error(
			`Contents of file at "${ pluginsFile }" could not be read, or are empty.`
		);
	}

	const pluginsConfig = JSON.parse( pluginsFileContent );

	// Check for valid and not empty object resulting from plugins JSON file parse.
	if (
		'object' !== typeof pluginsConfig ||
		0 === Object.keys( pluginsConfig ).length
	) {
		throw Error(
			`File at "${ pluginsFile }" parsed, but detected empty/non valid JSON object.`
		);
	}

	const stPlugins = pluginsConfig.modules;
	if ( stPlugins ) {
		for ( const moduleDir in stPlugins ) {
			const pluginVersion = stPlugins[ moduleDir ]?.version;
			const pluginSlug = stPlugins[ moduleDir ]?.slug;
			if ( pluginVersion && pluginSlug && settings.slug === pluginSlug ) {
				return log( 'build' );
			}
		}
	}

	const plugins = pluginsConfig.plugins;
	if ( plugins ) {
		for ( const plugin in plugins ) {
			if ( plugins[ plugin ] && settings.slug === plugins[ plugin ] ) {
				return log( 'plugins' );
			}
		}
	}

	throw Error(
		`The "${ settings.slug }" module slug is missing in the file "${ pluginsFile }".`
	);
}
