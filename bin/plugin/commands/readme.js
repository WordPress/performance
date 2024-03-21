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
 * @property {string=} path      Optional path to the readme.txt file to update. If omitted, it will be detected via --milestone.
 * @property {string=} token     Optional personal GitHub access token, only relevant for changelog updates.
 */

/**
 * @typedef WPReadmeSettings
 *
 * @property {string}  owner     GitHub repository owner.
 * @property {string}  repo      GitHub repository name.
 * @property {string=} milestone Optional milestone title, to update the changelog in the readme.
 * @property {string=} path      Optional path to the readme.txt file to update.
 * @property {string=} token     Optional personal GitHub access token, only relevant for changelog updates.
 */

exports.options = [
	{
		argname: '-m, --milestone <milestone>',
		description: 'Milestone title, to update the changelog',
	},
	{
		argname: '-p, --path <path>',
		description:
			'Path to the readme.txt file to update; if omitted, it will be detected via --milestone',
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
		path: opt.path,
		token: opt.token,
	} );
};

/**
 * Detects the path to the readme.txt file to update based on the milestone title.
 *
 * @param {string} milestone Milestone title.
 *
 * @return {string} Detected readme.txt path.
 */
function detectReadmePath( milestone ) {
	const slug = milestone.match( /^([a-z0-9-]+) / );
	if ( ! slug ) {
		throw new Error(
			`The ${ milestone } milestone does not start with a valid plugin slug.`
		);
	}

	if ( 'performance-lab' === slug[ 1 ] ) {
		return 'readme.txt';
	}

	if ( ! fs.existsSync( path.join( '.', `plugins/${ slug[ 1 ] }` ) ) ) {
		throw new Error( `Unknown plugin with slug '${ slug[ 1 ] }'` );
	}

	return `plugins/${ slug[ 1 ] }/readme.txt`;
}

/**
 * Updates the `readme.txt` file with the given changelog.
 *
 * @param {string}           changelog Changelog in markdown, with trailing newline.
 * @param {WPReadmeSettings} settings  Readme settings.
 */
function updateReadmeChangelog( changelog, settings ) {
	// Detect the version number to replace it in readme changelog, if already present.
	const version = settings.milestone.match(
		/\d+\.\d+(\.\d+)?(-[A-Za-z0-9.]+)?$/
	);
	if ( ! version ) {
		throw new Error(
			`The ${ settings.milestone } milestone does not end with a version number.`
		);
	}

	const regex = new RegExp( `= ${ version[ 0 ] } =[^=]+` );

	const readmeFile = path.join( '.', settings.path );
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
			if ( ! settings.path ) {
				settings.path = detectReadmePath( settings.milestone );
			}

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
		log(
			formats.success( `\n💃${ settings.path } successfully updated\n\n` )
		);
	}
}
