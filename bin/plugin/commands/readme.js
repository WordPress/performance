/**
 * External dependencies
 */
const path = require( 'path' );
const fs = require( 'fs' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const config = require( '../config' );
const { getModuleData } = require( './common' );
const { getChangelog } = require( './changelog' );

/**
 * @typedef WPReadmeCommandOptions
 *
 * @property {string=} directory Optional directory, default is the root `/modules` directory.
 * @property {string=} milestone Optional milestone title, to update the changelog in the readme.
 * @property {string=} token     Optional personal GitHub access token, only relevant for changelog updates.
 */

/**
 * @typedef WPReadmeSettings
 *
 * @property {string}  owner     GitHub repository owner.
 * @property {string}  repo      GitHub repository name.
 * @property {string}  directory Modules directory.
 * @property {string=} milestone Optional milestone title, to update the changelog in the readme.
 * @property {string=} token     Optional personal GitHub access token, only relevant for changelog updates.
 */

exports.options = [
	{
		argname: '-d, --directory <directory>',
		description: 'Modules directory',
	},
	{
		argname: '-m, --milestone <milestone>',
		description: 'Milestone title, to update the changelog',
	},
	{
		argname: '-t, --token <token>',
		description: 'GitHub token',
	},
];

/**
 * Command that updates the `readme.txt` file.
 *
 * @param {WPReadmeCommandOptions} opt
 */
exports.handler = async ( opt ) => {
	await updateReadme( {
		owner: config.githubRepositoryOwner,
		repo: config.githubRepositoryName,
		directory: opt.directory || 'modules',
		milestone: opt.milestone,
		token: opt.token,
	} );
};

/**
 * Returns a promise resolving to the module description list string for the `readme.txt` file.
 *
 * @param {WPReadmeSettings} settings Readme settings.
 *
 * @return {Promise<string>} Promise resolving to module description list in markdown, with trailing newline.
 */
async function getModuleDescriptionList( settings ) {
	const modulesData = await getModuleData( settings.directory );

	return modulesData
		.map(
			( moduleData ) =>
				`* **${ moduleData.name }:** ${ moduleData.description }`
		)
		.join( '\n' )
		.concat( '\n' );
}

/**
 * Updates the `readme.txt` file with the given module description list.
 *
 * @param {string} moduleList Module description list in markdown, with trailing newline.
 */
function updateReadmeModuleDescriptionList( moduleList ) {
	const readmeFile = path.join( '.', 'readme.txt' );
	const fileContent = fs.readFileSync( readmeFile, 'utf8' );
	const newContent = fileContent.replace(
		/(the following performance modules:\s+)((\*.*\n)+)/,
		( match, prefix ) => `${ prefix }${ moduleList }`
	);
	fs.writeFileSync( readmeFile, newContent );
}

/**
 * Updates the `readme.txt` file with the given changelog.
 *
 * @param {string}           changelog Changelog in markdown, with trailing newline.
 * @param {WPReadmeSettings} settings  Readme settings.
 */
function updateReadmeChangelog( changelog, settings ) {
	const regex = new RegExp( `= ${ settings.milestone } =[^=]+` );

	const readmeFile = path.join( '.', 'readme.txt' );
	const fileContent = fs.readFileSync( readmeFile, 'utf8' );

	let newContent;
	if ( fileContent.match( regex ) ) {
		newContent = fileContent
			.replace( regex, changelog )
			.trim()
			.concat( '\n' );
	} else {
		newContent = fileContent.replace(
			/(== Changelog ==\n\n)/,
			( match ) => {
				return `${ match }${ changelog }`;
			}
		);
	}
	fs.writeFileSync( readmeFile, newContent );
}

/**
 * Updates the `readme.txt` file with latest module description list and optionally a specific release changelog.
 *
 * @param {WPReadmeSettings} settings Readme settings.
 */
async function updateReadme( settings ) {
	if ( settings.milestone ) {
		log(
			formats.title(
				`\n💃Updating readme.txt for "${ settings.directory }" and changelog for milestone "${ settings.milestone }"\n\n`
			)
		);
	} else {
		log(
			formats.title(
				`\n💃Updating readme.txt for "${ settings.directory }"\n\n`
			)
		);
	}

	try {
		const moduleList = await getModuleDescriptionList( settings );
		updateReadmeModuleDescriptionList( moduleList );
	} catch ( error ) {
		if ( error instanceof Error ) {
			log( formats.error( error.stack ) );
			return;
		}
	}

	if ( settings.milestone ) {
		try {
			const changelog = await getChangelog( {
				owner: settings.owner,
				repo: settings.repo,
				milestone: settings.milestone,
				token: settings.token,
			} );
			updateReadmeChangelog( changelog, settings );
		} catch ( error ) {
			if ( error instanceof Error ) {
				log( formats.error( error.stack ) );
				return;
			}
		}
	}

	log( formats.success( `\n💃readme.txt successfully updated\n\n` ) );
}
