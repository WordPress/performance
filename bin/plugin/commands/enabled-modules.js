/**
 * External dependencies
 */
const path = require( 'path' );
const fs = require( 'fs' );
const { EOL } = require( 'os' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const { getModuleData } = require( './common' );

const TAB = '\t';
const NEWLINE = EOL;
const FILE_HEADER = `<?php
/* THIS IS A GENERATED FILE. DO NOT EDIT DIRECTLY. */
return array(
`;
const FILE_FOOTER = `
);
/* THIS IS THE END OF THE GENERATED FILE */
`;

/**
 * @typedef WPEnabledModulesCommandOptions
 *
 * @property {string=} directory Optional directory, default is the root `/modules` directory.
 * @property {string=} output    Optional output PHP file, default is the root `/default-enabled-modules.php` file.
 */

/**
 * @typedef WPEnabledModulesSettings
 *
 * @property {string} directory Modules directory.
 * @property {string} output    Output PHP file.
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
 * Command that generates a PHP file with non-experimental module slugs.
 *
 * @param {WPEnabledModulesCommandOptions} opt
 */
exports.handler = async ( opt ) => {
	await createEnabledModules( {
		directory: opt.directory || 'modules',
		output: opt.output || 'default-enabled-modules.php',
	} );
};

/**
 * Gathers the non-experimental modules as the default enabled modules.
 *
 * @param {WPEnabledModulesSettings} settings Default enabled modules settings.
 *
 * @return {[]string} List of default enabled module paths relative to modules directory.
 */
async function getDefaultEnabledModules( settings ) {
	const modulesData = await getModuleData( settings.directory );
	return modulesData
		.filter( ( moduleData ) => ! moduleData.experimental )
		.map( ( moduleData ) => `${ moduleData.focus }/${ moduleData.slug }` );
}

/**
 * Creates PHP file with the given default enabled modules.
 *
 * @param {[]string}                 enabledModules List of default enabled module paths relative to modules directory.
 * @param {WPEnabledModulesSettings} settings       Default enabled modules settings.
 */
function createEnabledModulesPHPFile( enabledModules, settings ) {
	const output = enabledModules.map( ( enabledModule ) => {
		// Escape single quotes.
		return `${ TAB }'${ enabledModule.replace( /'/g, "\\'" ) }',`;
	} );

	const fileOutput = `${ FILE_HEADER }${ output.join(
		NEWLINE
	) }${ FILE_FOOTER }`;
	fs.writeFileSync( path.join( '.', settings.output ), fileOutput );
}

/**
 * Gathers non-experimental modules and generates a PHP file with them.
 *
 * @param {WPEnabledModulesSettings} settings Default enabled modules settings.
 */
async function createEnabledModules( settings ) {
	log(
		formats.title(
			`\n💃Gathering non-experimental modules for "${ settings.directory }" in "${ settings.output }"\n\n`
		)
	);

	try {
		const enabledModules = await getDefaultEnabledModules( settings );
		createEnabledModulesPHPFile( enabledModules, settings );
	} catch ( error ) {
		if ( error instanceof Error ) {
			log( formats.error( error.stack ) );
			return;
		}
	}

	log(
		formats.success(
			`\n💃Non-experimental modules successfully set in "${ settings.output }"\n\n`
		)
	);
}
