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
					`\nError reading file from disk: "${ err }"\n\n`
				)
			);
		}

		try {
			const customer = JSON.parse( jsonString );
			for ( const key in customer ) {
				const pluginKey = key;
				const pluginVersion = customer[ pluginKey ].version;
				const pluginSlug = customer[ pluginKey ].slug;
				try {
					// Copy module files from the folder.
					const modulePath = path.join( '.', 'modules/' + key );
					fs.copySync( modulePath, './build', { overwrite: true } );

					// Update file content.
					updatePluginFiles( {
						key: pluginKey,
						slug: pluginSlug,
						version: pluginVersion,
					} );

					// Update since version.
					updateSinceVersion( {
						version: pluginVersion,
					} );

					// Update text domain.
					updateTextDomain( {
						slug: pluginSlug,
					} );
				} catch ( error ) {
					log(
						formats.error(
							`\n${ error }"\n`
						)
					);
				}
			}
		} catch ( jsonError ) {
			log(
				formats.error(
					`\nError parsing JSON string: "${ jsonError }"\n\n`
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
async function updatePluginFiles( settings ) {
	const loadFile = path.join( '.', 'load.php' );
	const loadFileContent = fs.readFileSync( loadFile, 'utf-8' );
	const buildLoadFile = path.join( '.', 'build/load.php' );
	const buildLoadFileContent = fs.readFileSync( buildLoadFile, 'utf-8' );

	const regex = /\/\\*\\*[\s\S]+?(?=\*\/)/mi;
	const pluginComment = loadFileContent.match( regex )?.[ 0 ];
	const newPluginComment = buildLoadFileContent.match( regex )?.[ 0 ];

	fs.writeFileSync( buildLoadFile, buildLoadFileContent.replace( newPluginComment, pluginComment ) );

	let updatedBuildLoadFileContent = fs.readFileSync( buildLoadFile, 'utf-8' );

	// Get module header data.
	const { name, description } = await getModuleData( buildLoadFileContent );
	const moduleData = {
		pluginname: name,
		plugindescription: description,
		pluginuri: '/tree/trunk/modules/' + settings.key,
		pluginversion: settings.version,
		textdomain: settings.slug,
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
 * Updates the `readme.txt` file with the given changelog.
 *
 * @param {string} fileHeaderContent File header.
 *
 * @return {[]HeaderData} File header data.
 */
async function getModuleData( fileHeaderContent ) {
	const moduleData = {
		name: '',
		description: '',
	};

	const moduleHeaders = {
		'Module Name': 'name',
		Description: 'description',
	};

	const regex = new RegExp(
		`^(?:[ \t]*<?php)?[ \t/*#@]*(${ Object.keys( moduleHeaders ).join(
			'|'
		) }):(.*)$`,
		'gmi'
	);

	let match = regex.exec( fileHeaderContent );
	while ( match ) {
		const content = match[ 2 ].trim();
		const prop = moduleHeaders[ match[ 1 ] ];
		if ( content && prop ) {
			moduleData[ prop ] = content;
		}
		match = regex.exec( fileHeaderContent );
	}
	return moduleData;
}

/**
 * Updates the plugin version in the build folder files content.
 *
 * @param {[]Settings} settings Plugin settings.
 */
async function updateSinceVersion( settings ) {
	const patterns = [
		path.resolve( __dirname, '../../../build/**/*.php' ),
		path.resolve( __dirname, '../../../build/**/*.js' ),
	];

	const files = await glob( patterns, {
		ignore: [ __filename ],
	} );

	const regexp = new RegExp( '@since\\s{1,}[0-9].[0-9].[0-9]', 'g' );
	files.forEach( ( file ) => {
		const content = fs.readFileSync( file, 'utf-8' );
		if ( regexp.test( content ) ) {
			fs.writeFileSync(
				file,
				content.replace( regexp, `@since ${ settings.version }` )
			);
		}
	} );
}

/**
 * Updates the text domain in the build folder files content.
 *
 * @param {[]Settings} settings Plugin settings.
 */
async function updateTextDomain( settings ) {
	const patterns = [
		path.resolve( __dirname, '../../../build/**/*.php' ),
	];

	const files = await glob( patterns, {
		ignore: [ __filename ],
	} );

	const regexp = new RegExp( 'performance-lab', 'g' );

	files.forEach( ( file ) => {
		const content = fs.readFileSync( file, 'utf-8' );
		if ( regexp.test( content ) ) {
			fs.writeFileSync(
				file,
				content.replace( regexp, `${ settings.slug }` )
			);
		}
	} );
}
