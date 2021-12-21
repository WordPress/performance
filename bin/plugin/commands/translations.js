/**
 * External dependencies
 */
const { flatten } = require( 'lodash' );
const path = require( 'path' );
const glob = require( 'fast-glob' );
const fs = require( 'fs' );
const readline = require( 'readline' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );

/**
 * @typedef WPTranslationsCommandOptions
 *
 * @property {string=} directory Optional directory, default is the root `/modules` directory.
 * @property {string=} output    Optional output file, default is the root `/module-i18n.php` file.
 */

/**
 * @typedef WPTranslationsSettings
 *
 * @property {string=} directory Optional directory, default is the root `/modules` directory.
 * @property {string=} output    Optional output file, default is the root `/module-i18n.php` file.
 */

const options = [
	{
		argname: '-d, --directory <directory>',
		description: 'Modules directory',
	},
	{
		argname: '-d, --output <output>',
		description: 'Output file',
	},
];

/**
 * Command that generates a PHP file from module header translation strings.
 *
 * @param {WPTranslationsCommandOptions} opt
 */
async function handler( opt ) {
	await createTranslations( {
		directory: opt.directory,
	} );
}

module.exports = {
	options,
	handler,
};

/**
 * Parses module header translation strings.
 *
 * @param {WPTranslationsSettings} settings Translations settings.
 *
 * @return {[]string} List of translation strings.
 */
async function getTranslations( settings ) {
	const moduleFilePattern = path.join( settings.directory, '*/load.php' );
	const moduleFiles = await glob(
		path.resolve( '../../..', moduleFilePattern )
	);

	const moduleTranslations = await Promise.all(
		moduleFiles.map( async ( moduleFile ) => {
			const fileStream = fs.createReadStream( moduleFile );

			const rl = readline.createInterface( {
				input: fileStream,
			} );

			const headers = [ 'Module Name', 'Description' ];
			const translationStrings = [];

			for await ( const line of rl ) {
				headers.forEach( ( header, index ) => {
					const regex = new RegExp(
						'^(?:[ \t]*<?php)?[ \t/*#@]*' + header + ':(.*)$',
						'i'
					);
					const match = regex.exec( line );

					if ( match ) {
						const headerValue = match[ 1 ]
							.replace( /\s*(?:\*\/|\?>).*/, '' )
							.trim();
						if ( headerValue ) {
							headers.splice( index, 1 );
							translationStrings.push( headerValue );
						}
					}
				} );
			}

			return translationStrings;
		} )
	);

	return flatten( moduleTranslations );
}

/**
 * Parses module header translation strings and generates a PHP file with them.
 *
 * @param {WPTranslationsSettings} settings Translations settings.
 */
async function createTranslations( settings ) {
	const directory = settings.directory || 'modules';
	const output = settings.output || 'module-i18n.php';

	log(
		formats.title(
			`\n💃Preparing module translations for "${ directory }" in "${ output }"\n\n`
		)
	);

	try {
		const translations = await getTranslations( settings );
		log( translations );
	} catch ( error ) {
		if ( error instanceof Error ) {
			log( formats.error( error.stack ) );
		}
	}

	log(
		formats.success(
			`\n💃Module translations successfully set in "${ output }"\n\n`
		)
	);
}
