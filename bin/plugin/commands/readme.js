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
const { getChangelog } = require( './changelog' );

/**
 * @typedef WPReadmeCommandOptions
 *
 * @property {string=} milestone Optional milestone title, to update the changelog in the readme.
 * @property {string=} token     Optional personal GitHub access token, only relevant for changelog updates.
 */

/**
 * @typedef WPReadmeSettings
 *
 * @property {string}  owner     GitHub repository owner.
 * @property {string}  repo      GitHub repository name.
 * @property {string=} milestone Optional milestone title, to update the changelog in the readme.
 * @property {string=} token     Optional personal GitHub access token, only relevant for changelog updates.
 */

exports.options = [
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
		milestone: opt.milestone,
		token: opt.token,
	} );
};

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
				`\n💃Updating readme.txt changelog for milestone "${ settings.milestone }"\n\n`
			)
		);

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
		log( formats.success( `\n💃readme.txt successfully updated\n\n` ) );
	}
}
