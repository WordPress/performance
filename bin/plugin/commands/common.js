/**
 * External dependencies
 */
const path = require( 'path' );
const glob = require( 'fast-glob' );
const fs = require( 'fs' );

/**
 * @typedef WPModuleData
 *
 * @property {string}  slug         Module slug.
 * @property {string}  focus        Module focus.
 * @property {string}  name         Module name.
 * @property {string}  description  Module description.
 * @property {boolean} experimental Whether the module is experimental.
 */

/**
 * Returns a promise resolving to the list of data for all modules.
 *
 * @param {string} modulesDir Modules directory.
 *
 * @return {Promise<[]WPModuleData>} Promise resolving to module data list.
 */
exports.getModuleData = async ( modulesDir ) => {
	const moduleFilePattern = path.join( modulesDir, '*/*/load.php' );
	const moduleFiles = await glob( path.resolve( '.', moduleFilePattern ) );

	return moduleFiles
		.map( ( moduleFile ) => {
			// Populate slug and focus based on file path.
			const moduleDir = path.dirname( moduleFile );
			const moduleData = {
				slug: path.basename( moduleDir ),
				focus: path.basename( path.dirname( moduleDir ) ),
			};

			// Map of module header => object property.
			const headers = {
				'Module Name': 'name',
				Description: 'description',
				Experimental: 'experimental',
			};

			// Populate name, description and experimental based on module file headers.
			const fileContent = fs.readFileSync( moduleFile, 'utf8' );
			const regex = new RegExp(
				`^(?:[ \t]*<?php)?[ \t/*#@]*(${ Object.keys( headers ).join(
					'|'
				) }):(.*)$`,
				'gmi'
			);
			let match = regex.exec( fileContent );
			while ( match ) {
				const content = match[ 2 ].trim();
				const prop = headers[ match[ 1 ] ];
				if ( content && prop ) {
					moduleData[ prop ] = content;
				}
				match = regex.exec( fileContent );
			}

			// Parse experimental field into a boolean.
			if ( typeof moduleData.experimental === 'string' ) {
				if ( moduleData.experimental.toLowerCase() === 'yes' ) {
					moduleData.experimental = true;
				} else {
					moduleData.experimental = false;
				}
			}

			return moduleData;
		} )
		.filter( ( moduleData ) => moduleData.name && moduleData.description );
};
