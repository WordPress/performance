/**
 * External dependencies
 */
const path = require( 'path' );
const glob = require( 'fast-glob' );
const fs = require( 'fs' );
const readline = require( 'readline' );
const { EOL } = require( 'os' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const config = require( '../config' );

const TAB = '\t';
const NEWLINE = EOL;
const FILE_HEADER = `<?php
/* THIS IS A GENERATED FILE. DO NOT EDIT DIRECTLY. */
$generated_i18n_strings = array(
`;
const FILE_FOOTER = `
);
/* THIS IS THE END OF THE GENERATED FILE */
`;

/**
 * @typedef WPTranslationsCommandOptions
 *
 * @property {string=} directory Optional directory, default is the root `/modules` directory.
 * @property {string=} output    Optional output PHP file, default is the root `/module-i18n.php` file.
 */

/**
 * @typedef WPTranslationsSettings
 *
 * @property {string} textDomain Plugin text domain.
 * @property {string} directory  Modules directory.
 * @property {string} output     Output PHP file.
 */

exports.options = [
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
exports.handler = async ( opt ) => {
	await createTranslations( {
		textDomain: config.textDomain,
		directory: opt.directory || 'modules',
		output: opt.output || 'module-i18n.php',
	} );
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
	const moduleFiles = await glob( path.resolve( '.', moduleFilePattern ) );

	const moduleTranslations = moduleFiles
		.map( ( moduleFile ) => {
			const headers = [ 'Module Name', 'Description' ];
			const translationStrings = [];

			const fileContent = fs.readFileSync( moduleFile, 'utf8' );
			const regex = new RegExp(
				`^(?:[ \t]*<?php)?[ \t/*#@]*(${ headers.join( '|' ) }):(.*)$`,
				'gmi'
			);
			let match = regex.exec( fileContent );
			while ( match ) {
				const value = match[ 2 ].trim();
				if ( value ) {
					translationStrings.push( value );
				}
				match = regex.exec( fileContent );
			}

			return translationStrings;
		} )
		.filter( ( translations ) => !! translations.length );

	return moduleTranslations.flat();
}

/**
 * Parses module header translation strings.
 *
 * @param {[]string}               translations List of translation strings.
 * @param {WPTranslationsSettings} settings     Translations settings.
 */
function createTranslationsPHPFile( translations, settings ) {
	const output = translations.map( ( translation ) => {
		// Escape single quotes.
		return (
			TAB +
			`__( '${ translation.replace( /'/g, "\\'" ) }', '${
				settings.textDomain
			}' ),`
		);
	} );

	const fileOutput = `${ FILE_HEADER }${ output.join(
		NEWLINE
	) }${ FILE_FOOTER }`;
	fs.writeFileSync( path.join( '.', settings.output ), fileOutput );
}

/**
 * Parses module header translation strings and generates a PHP file with them.
 *
 * @param {WPTranslationsSettings} settings Translations settings.
 */
async function createTranslations( settings ) {
	log(
		formats.title(
			`\n💃Preparing module translations for "${ settings.directory }" in "${ settings.output }"\n\n`
		)
	);

	try {
		const translations = await getTranslations( settings );
		createTranslationsPHPFile( translations, settings );
	} catch ( error ) {
		if ( error instanceof Error ) {
			log( formats.error( error.stack ) );
		}
	}

	log(
		formats.success(
			`\n💃Module translations successfully set in "${ settings.output }"\n\n`
		)
	);
}
