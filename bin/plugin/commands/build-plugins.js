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
						key: moduleDir,
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
 * @param {[]Settings} settings Plugin settings.
 */
async function updatePluginHeader( settings ) {
	const { key, version, slug, pluginPath } = settings;
	// Plugin root `load.php` file content.
	const rootLoadFile = path.join( '.', 'load.php' );
	const rootLoadFileContent = fs.readFileSync( rootLoadFile, 'utf-8' );
	// Specific module `load.php` file content.
	const buildLoadFile = path.join( pluginPath, 'load.php' );
	const buildLoadFileContent = fs.readFileSync( buildLoadFile, 'utf-8' );

	const rootFileHeader = await getModuleHeader( rootLoadFileContent );
	const buildFileLoad = await getModuleHeader( buildLoadFileContent );

	// Get module header data.
	const { name, description } = await getModuleData( buildLoadFileContent );

	// Replace plugin root's `load.php` header in the module file header.
	fs.writeFileSync( buildLoadFile, buildLoadFileContent.replace( buildFileLoad, rootFileHeader ) );

	let updatedBuildLoadFileContent = fs.readFileSync( buildLoadFile, 'utf-8' );

	// Get module header data.
	const moduleData = {
		pluginname: name,
		plugindescription: description,
		pluginuri: '/tree/trunk/modules/' + key,
		pluginversion: version,
		textdomain: slug,
	};

	// Map of module header => object property.
	const headers = {
		'Plugin Name': 'pluginname',
		'Plugin URI': 'pluginuri',
		Description: 'plugindescription',
		Version: 'pluginversion',
		'Text Domain': 'textdomain',
	};

	const headerRegex = new RegExp( `(?<header>${ Object.keys( headers ).join( '|' ) }): (?<value>(.*?)+)`, 'gmi' );
	const matches = [ ...updatedBuildLoadFileContent.matchAll( headerRegex ) ];

	matches.forEach( ( match ) => {
		const { header, value } = match.groups;
		const prop = headers[ header ];
		let moduleDataValue = moduleData[ prop ];

		if ( value && prop && moduleDataValue ) {
			if ( prop === 'pluginuri' ) {
				moduleDataValue = value + moduleDataValue;
			}
			fs.writeFileSync( buildLoadFile, updatedBuildLoadFileContent.replace( new RegExp( value ), moduleDataValue ) );
			updatedBuildLoadFileContent = fs.readFileSync( buildLoadFile, 'utf-8' );
		}
	} );
}

/**
 * Gets the specific module file header information.
 *
 * @param {string} moduleFileContent File content.
 *
 * @return {[]HeaderData} File header data.
 */
async function getModuleData( moduleFileContent ) {
	const moduleHeader = await getModuleHeader( moduleFileContent );
	return await getModuleDataFromHeader( moduleHeader );
}

/**
 * Updates the text domain and package details in the build folder files content.
 *
 * @param {[]Settings} settings Plugin settings.
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
