/**
 * External dependencies
 */
const fs = require( 'fs-extra' );
const path = require( 'path' );
const glob = require( 'fast-glob' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const { getModuleDataFromHeader, getModuleHeader } = require( './common' );

exports.options = [];

/**
 * Command for copying the files required for the standalone plugin.
 */
exports.handler = async () => {
	const pluginsFile = path.join( '.', 'plugins.json' );
	fs.readFile( pluginsFile, 'utf8', ( err, jsonString ) => {
		if ( err ) {
			log(
				formats.error(
					`Error reading file from disk: "${ err }"`
				)
			);
		}

		try {
			const plugins = JSON.parse( jsonString );
			for ( const moduleDir in plugins ) {
				const pluginVersion = plugins[ moduleDir ]?.version;
				const pluginSlug = plugins[ moduleDir ]?.slug;

				if ( ! pluginVersion || ! pluginSlug ) {
					log(
						formats.error(
							'The given module configuration is invalid, the module JSON object is missing the "version" and/or "slug" properties, or they are misspelled.'
						)
					);
					return;
				}

				try {
					// Copy module files from the folder.
					const modulePath = path.join( '.', 'modules/' + moduleDir );
					const buildModulePath = path.join( '.', 'build/' + pluginSlug );
					try {
						// Clean up build module files directory.
						fs.rmSync( buildModulePath, { force: true, recursive: true } );

						fs.copySync( modulePath, buildModulePath, { overwrite: true } );
					} catch ( copyError ) {
						log(
							formats.error(
								`Error copying plugin file: "${ copyError }"`
							)
						);
						continue;
					}

					// Update file content.
					updatePluginHeader( {
						originalModulePath: moduleDir,
						slug: pluginSlug,
						version: pluginVersion,
						pluginPath: buildModulePath,
					} );

					// Update text domain.
					updateModuleDetails( {
						pluginPath: buildModulePath,
						regex: '[\']performance-lab[\']',
						result: `'${ pluginSlug }'`,
					} );

					// Update `@package`.
					updateModuleDetails( {
						pluginPath: buildModulePath,
						regex: '@package\\s{1,}performance-lab',
						result: '@package ' + pluginSlug,
					} );
				} catch ( error ) {
					log(
						formats.error(
							`${ error }`
						)
					);
				}
			}
		} catch ( jsonError ) {
			log(
				formats.error(
					`Error parsing JSON string: "${ jsonError }"`
				)
			);
		}
	} );
};

/**
 * Updates the `load.php` file header information.
 *
 * @param {Object} settings Plugin settings.
 */
async function updatePluginHeader( settings ) {
	const { originalModulePath, version, slug, pluginPath } = settings;
	// Specific module `load.php` file content.
	const buildLoadFile = path.join( pluginPath, 'load.php' );
	const buildLoadFileContent = fs.readFileSync( buildLoadFile, 'utf-8' );

	const moduleHeader = await getModuleHeader( buildLoadFileContent );

	// Get module header data.
	const { name, description } = await getModuleDataFromHeader( moduleHeader );

	const pluginHeader = `/**\n * Plugin Name: ${ name }\n * Plugin URI: https://github.com/WordPress/performance/tree/trunk/modules/${ originalModulePath }\n * Description: ${ description }\n * Requires at least: 6.1\n * Requires PHP: 5.6\n * Version: ${ version }\n * Author: WordPress Performance Team\n * Author URI: https://make.wordpress.org/performance/\n * License: GPLv2 or later\n * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html\n * Text Domain: ${ slug }\n *\n * @package ${ slug }\n `;

	// Replace the module file header.
	fs.writeFileSync( buildLoadFile, buildLoadFileContent.replace( moduleHeader, pluginHeader ) );
}

/**
 * Updates the text domain and package details in the build folder files content.
 *
 * @param {Object} settings Plugin settings.
 */
async function updateModuleDetails( settings ) {
	const patterns = [
		path.resolve( settings.pluginPath, './**/*.php' ),
	];

	const files = await glob( patterns, {
		ignore: [ __filename ],
	} );

	const regexp = new RegExp( settings.regex, 'gm' );

	files.forEach( ( file ) => {
		const content = fs.readFileSync( file, 'utf-8' );
		if ( regexp.test( content ) ) {
			fs.writeFileSync(
				file,
				content.replace( regexp, `${ settings.result }` )
			);
		}
	} );
}
