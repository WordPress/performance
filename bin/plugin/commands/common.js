/**
 * External dependencies
 */
const path = require( 'path' );
const glob = require( 'fast-glob' );
const fs = require( 'fs' );

/**
 * @typedef WPModuleDescription
 *
 * @property {string} name        Module name.
 * @property {string} description Module description.
 */

/**
 * Returns a promise resolving to the module description list string for the `readme.txt` file.
 *
 * @param {string} modulesDir Modules directory.
 *
 * @return {Promise<[]WPModuleDescription>} Promise resolving to module description list.
 */
exports.getModuleDescriptions = async ( modulesDir ) => {
	const moduleFilePattern = path.join( modulesDir, '*/*/load.php' );
	const moduleFiles = await glob( path.resolve( '.', moduleFilePattern ) );

	return moduleFiles
		.map( ( moduleFile ) => {
			// Map of module header => object property.
			const headers = {
				'Module Name': 'name',
				Description: 'description',
			};
			const moduleData = {};

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

			return moduleData;
		} )
		.filter( ( moduleData ) => moduleData.name && moduleData.description );
};
