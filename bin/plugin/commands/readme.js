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
const { plugins } = require( '../../../plugins.json' );

/**
 * @typedef WPReadmeCommandOptions
 *
 * @property {string=} plugin Optional plugin slug to update one a single plugin's readme. If omitted, all plugins are updated.
 * @property {string=} token  Optional personal GitHub access token.
 */

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description:
			'The plugin slug to update; if omitted, all plugins are updated',
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
		plugin: opt.plugin,
		token: opt.token,
	} );
};

/**
 * Updates the `readme.txt` file with the given changelog.
 *
 * @param {string} readmeFile Readme file path.
 * @param {string} changelog  Changelog in markdown.
 */
function updateReadmeChangelog( readmeFile, changelog ) {
	const fileContent = fs.readFileSync( readmeFile, 'utf-8' );

	const stableTagVersionMatches = fileContent.match(
		/^Stable tag:\s*(\d+\.\d+\.\d+)$/m
	);
	if ( ! stableTagVersionMatches ) {
		throw new Error( `Unable to locate stable tag in ${ readmeFile }` );
	}
	const stableTagVersion = stableTagVersionMatches[ 1 ];

	const regex = new RegExp(
		`(== Changelog ==\n+)(= ${ stableTagVersion } =\n+)([^=]+)`
	);

	const versionHeading = `= ${ stableTagVersion } =\n\n`;
	const normalizedChangelog = changelog.trimEnd() + '\n';

	let status = '';

	// Try to merge the new changelog with the existing changelog.
	let newContent = fileContent.replace(
		regex,
		( match, changelogHeading, _versionHeading, existingChangelog ) => {
			status =
				'Merged existing changelog with the new changelog in an Other section.';
			return `${ changelogHeading }${ _versionHeading }${ normalizedChangelog }\n**Other**\n\n${ existingChangelog }`;
		}
	);

	// No replacement was done, so we need to insert a new section.
	if ( newContent === fileContent ) {
		newContent = fileContent.replace( /(== Changelog ==\n+)/, ( match ) => {
			status = 'Added new changelog section.';
			return `${ match }${ versionHeading }${ normalizedChangelog }\n`;
		} );
	}

	if ( newContent === fileContent ) {
		throw new Error( 'Failed to insert changelog into readme.' );
	}

	fs.writeFileSync( readmeFile, newContent );

	return status;
}

/**
 * Gets the stable tag from a readme.
 *
 * @param {string} readmeFilePath Readme file path.
 * @return {string} Stable tag.
 */
function getStableTag( readmeFilePath ) {
	const readmeContents = fs.readFileSync( readmeFilePath, 'utf-8' );

	const stableTagVersionMatches = readmeContents.match(
		/^Stable tag:\s*(\d+\.\d+\.\d+)$/m
	);
	if ( ! stableTagVersionMatches ) {
		throw new Error( `Unable to locate stable tag in ${ readmeFilePath }` );
	}
	return stableTagVersionMatches[ 1 ];
}

/**
 * Updates the `readme.txt` file with a specific release changelog.
 *
 * @param {WPReadmeCommandOptions} settings
 */
async function updateReadme( settings ) {
	const pluginRoot = path.resolve( __dirname, '../../../' );

	const allPluginSlugs = [
		'performance-lab', // TODO: Remove as of <https://github.com/WordPress/performance/pull/1182>.
		...plugins,
	];
	if ( settings.plugin && ! allPluginSlugs.includes( settings.plugin ) ) {
		throw new Error( `Unrecognized plugin: ${ settings.plugin }` );
	}

	const pluginSlugs = [];
	if ( settings.plugin ) {
		pluginSlugs.push( settings.plugin );
	} else {
		pluginSlugs.push( ...allPluginSlugs );
	}

	for ( const pluginSlug of pluginSlugs ) {
		try {
			const pluginDirectory =
				'performance-lab' === pluginSlug // TODO: Remove this condition as of <https://github.com/WordPress/performance/pull/1182>.
					? pluginRoot
					: path.resolve( pluginRoot, 'plugins', pluginSlug );

			const readmeFilePath = path.resolve(
				pluginDirectory,
				'readme.txt'
			);
			const stableTag = getStableTag( readmeFilePath );
			const changelog = await getChangelog( {
				owner: config.githubRepositoryOwner,
				repo: config.githubRepositoryName,
				milestone: `${ pluginSlug } ${ stableTag }`,
				token: settings.token,
			} );
			const status = updateReadmeChangelog( readmeFilePath, changelog );
			log(
				formats.success(
					`💃 ${ pluginSlug } successfully updated for version ${ stableTag }: ${ status }`
				)
			);
		} catch ( error ) {
			log(
				formats.error(
					`${ pluginSlug } failed to update: ${ error.message }`
				)
			);
		}
	}
}
