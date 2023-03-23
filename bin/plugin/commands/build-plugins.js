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
			log( formats.error( `Error reading file from disk: "${ err }"` ) );
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
					const buildModulePath = path.join(
						'.',
						'build/' + pluginSlug
					);
					try {
						// Clean up build module files directory.
						fs.rmSync( buildModulePath, {
							force: true,
							recursive: true,
						} );

						fs.copySync( modulePath, buildModulePath, {
							overwrite: true,
						} );
					} catch ( copyError ) {
						log(
							formats.error(
								`Error copying files for plugin "${ pluginSlug }": "${ copyError }"`
							)
						);
						continue;
					}

					// Update file content.
					updatePluginHeader( {
						modulePath: moduleDir,
						slug: pluginSlug,
						version: pluginVersion,
						pluginPath: buildModulePath,
					} );

					// Update text domain.
					updateModuleDetails( {
						pluginPath: buildModulePath,
						regex: "[']performance-lab[']",
						result: `'${ pluginSlug }'`,
					} );

					// Update `@package`.
					updateModuleDetails( {
						pluginPath: buildModulePath,
						regex: '@package\\s{1,}performance-lab',
						result: '@package ' + pluginSlug,
					} );

					// Update version constant.
					updateModuleDetails( {
						pluginPath: buildModulePath,
						regex: "[']Performance Lab ['] . PERFLAB_VERSION",
						result: `'${ pluginVersion }'`,
					} );
				} catch ( error ) {
					log( formats.error( `${ error }` ) );
				}
			}
		} catch ( jsonError ) {
			log(
				formats.error( `Error parsing JSON string: "${ jsonError }"` )
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
	const { modulePath, version, slug, pluginPath } = settings;
	// Specific module `load.php` file content.
	const buildLoadFile = path.join( pluginPath, 'load.php' );
	let buildLoadFileContent = '';
	try {
		buildLoadFileContent = fs.readFileSync( buildLoadFile, 'utf-8' );
	} catch ( err ) {
		log(
			formats.error(
				`Error reading the file "${ buildLoadFile }": "${ err }"`
			)
		);
	}

	if ( buildLoadFileContent === '' ) {
		log( formats.error( `Error reading the file "${ buildLoadFile }"` ) );
		return false;
	}
	const moduleHeader = await getModuleHeader( buildLoadFileContent );

	// Get module header data.
	const { name, description } = await getModuleDataFromHeader( moduleHeader );

	const pluginHeader = `/**
 * Plugin Name: ${ name }
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/modules/${ modulePath }
 * Description: ${ description }
 * Requires at least: 6.1
 * Requires PHP: 5.6
 * Version: ${ version }
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: ${ slug }
 *
 * @package ${ slug }
 `;
	try {
		// Replace the module file header.
		fs.writeFileSync(
			buildLoadFile,
			buildLoadFileContent.replace( moduleHeader, pluginHeader )
		);
	} catch ( error ) {
		log(
			formats.error( `Error replacing module file header: "${ error }"` )
		);
	}
}

/**
 * Updates the text domain and package details in the build folder files content.
 *
 * @param {Object} settings Plugin settings.
 */
async function updateModuleDetails( settings ) {
	const patterns = [ path.resolve( settings.pluginPath, './**/*.php' ) ];

	const files = await glob( patterns, {
		ignore: [ __filename ],
	} );

	const regexp = new RegExp( settings.regex, 'gm' );

	files.forEach( ( file ) => {
		let content = '';
		try {
			content = fs.readFileSync( file, 'utf-8' );
		} catch ( err ) {
			log(
				formats.error(
					`Error reading the file "${ file }": "${ err }"`
				)
			);
		}

		if ( content === '' ) {
			log( formats.error( `Error reading the file "${ file }"` ) );
			return false;
		}

		if ( regexp.test( content ) ) {
			try {
				fs.writeFileSync(
					file,
					content.replace( regexp, `${ settings.result }` )
				);
			} catch ( error ) {
				log(
					formats.error(
						`Error replacing content for regex "${ settings.regex }": "${ error }"`
					)
				);
			}
		}
	} );
}
