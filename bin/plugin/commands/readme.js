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
const { getReleaseMilestones } = require( './bump-versions' );
const { plugins } = require( '../../../plugins.json' );

/**
 * @typedef WPReadmeCommandOptions
 *
 * @property {string=}  plugin Optional plugin slug to update a single plugin's readme. Defaults to all plugins with an open, dated release milestone.
 * @property {string=}  token  Optional personal GitHub access token.
 * @property {boolean=} all    Update every plugin, ignoring release milestones (legacy behavior).
 */

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description:
			'Plugin slug. Defaults to all plugins with an open, dated release milestone.',
	},
	{
		argname: '-t, --token <token>',
		description: 'GitHub token',
	},
	{
		argname: '-a, --all',
		description:
			'Update all plugins, ignoring release milestones (legacy behavior). When combined with --plugin, that plugin is updated without checking for a milestone.',
		defaults: false,
	},
];

/**
 * Command that updates the `readme.txt` file.
 *
 * @param {WPReadmeCommandOptions} opt
 */
exports.handler = async ( opt ) => {
	if ( opt.plugin && ! plugins.includes( opt.plugin ) ) {
		throw new Error(
			`The plugin "${ opt.plugin }" is not a valid plugin managed as part of this project.`
		);
	}

	let pluginSlugs;
	if ( opt.all ) {
		// Legacy behavior: update every plugin (or the given one), ignoring milestones.
		pluginSlugs = opt.plugin ? [ opt.plugin ] : plugins;
	} else {
		// Default: only update plugins that are being released, i.e. those with an open
		// milestone that has a due date and whose title does not contain "n.e.x.t".
		const releaseSlugs = ( await getReleaseMilestones( opt.token ) ).map(
			( milestone ) => milestone.slug
		);
		pluginSlugs = opt.plugin
			? releaseSlugs.filter( ( slug ) => slug === opt.plugin )
			: releaseSlugs;

		if ( pluginSlugs.length === 0 ) {
			log(
				formats.warning(
					opt.plugin
						? `⚠ Skipping ${ opt.plugin }: no open release milestone with a due date (and without "n.e.x.t") was found. Pass --all to update it anyway.`
						: '⚠ No plugins have an open release milestone with a due date (and without "n.e.x.t"); nothing to update. Pass --all to update every plugin.'
				)
			);
			return;
		}
	}

	await updateReadme( {
		pluginSlugs,
		token: opt.token,
	} );
};

/**
 * Updates the `readme.txt` file with the given changelog.
 *
 * If the changelog already has an entry for the stable tag, the newly generated
 * entries are merged into it, skipping any pull requests that are already listed
 * (so the command is idempotent and never duplicates entries into an "Other" section).
 *
 * @param {string} readmeFile Readme file path.
 * @param {string} changelog  Changelog in Markdown format.
 * @return {string} Success status message.
 */
function updateReadmeChangelog( readmeFile, changelog ) {
	const fileContent = fs.readFileSync( readmeFile, 'utf-8' );

	const stableTagVersion = getStableTag( readmeFile );

	const normalizedChangelog = changelog.trimEnd() + '\n';

	const versionHeadingRegExp = new RegExp(
		`^= ${ escapeRegExp( stableTagVersion ) } =$`,
		'm'
	);
	const headingMatch = versionHeadingRegExp.exec( fileContent );

	// No existing entry for this version: insert a fresh section at the top of the changelog.
	if ( ! headingMatch ) {
		const versionHeading = `= ${ stableTagVersion } =\n\n`;
		const newContent = fileContent.replace(
			/(== Changelog ==\n+)/,
			( match ) =>
				`${ match }${ versionHeading }${ normalizedChangelog }\n`
		);
		if ( newContent === fileContent ) {
			throw new Error( 'Failed to insert changelog into readme.' );
		}
		fs.writeFileSync( readmeFile, newContent );
		return 'Added new changelog section.';
	}

	// Isolate the body between this version heading and the next version heading (or EOF).
	const bodyStart = headingMatch.index + headingMatch[ 0 ].length;
	const before = fileContent.slice( 0, bodyStart );
	const rest = fileContent.slice( bodyStart );
	const nextHeadingMatch = /^= .+ =$/m.exec( rest );
	const bodyEnd = nextHeadingMatch ? nextHeadingMatch.index : rest.length;
	const existingBody = rest
		.slice( 0, bodyEnd )
		.replace( /^\n+/, '' )
		.replace( /\s+$/, '' );
	const after = rest.slice( bodyEnd );

	let newBody;
	let status;
	if ( existingBody === '' ) {
		newBody = changelog.trimEnd();
		status = 'Populated empty changelog section.';
	} else {
		const { merged, addedCount } = mergeChangelog(
			existingBody,
			changelog
		);
		newBody = merged;
		status =
			addedCount === 0
				? 'Existing changelog already up to date; no new entries added.'
				: `Merged ${ addedCount } new ${
						addedCount === 1 ? 'entry' : 'entries'
				  } into the existing changelog.`;
	}

	let newContent = `${ before }\n\n${ newBody }\n`;
	if ( after !== '' ) {
		newContent += `\n${ after }`;
	}

	fs.writeFileSync( readmeFile, newContent );

	return status;
}

/**
 * Merges newly generated changelog entries into an existing changelog body.
 *
 * The existing body is preserved verbatim; only entries for pull requests that are
 * not already listed are appended, each under its corresponding section heading (a
 * new section is created when necessary). This makes re-running the command safe:
 * previously added entries are never duplicated.
 *
 * @param {string} existingBody Existing changelog body for the version (without the version heading).
 * @param {string} incomingBody Newly generated changelog body (without the version heading).
 * @return {{merged: string, addedCount: number}} The merged body and the number of entries added.
 */
function mergeChangelog( existingBody, incomingBody ) {
	const existingPullRequests = new Set(
		[ ...existingBody.matchAll( /\(\[(\d+)]/g ) ].map(
			( matches ) => matches[ 1 ]
		)
	);

	const incomingSections = parseChangelogSections( incomingBody );

	let merged = existingBody.replace( /\s+$/, '' );
	let addedCount = 0;

	for ( const [ section, entries ] of incomingSections ) {
		const newEntries = entries.filter( ( entry ) => {
			const matches = entry.match( /\(\[(\d+)]/ );
			return ! ( matches && existingPullRequests.has( matches[ 1 ] ) );
		} );
		if ( newEntries.length === 0 ) {
			continue;
		}
		addedCount += newEntries.length;
		merged = insertEntriesIntoSection( merged, section, newEntries );
	}

	return { merged, addedCount };
}

/**
 * Parses a changelog body into an ordered map of section headings to entry lines.
 *
 * @param {string} body Changelog body (without the version heading).
 * @return {Map<string, string[]>} Map of section heading text (without the `**` markers) to entry lines.
 */
function parseChangelogSections( body ) {
	/** @type {Map<string, string[]>} */
	const sections = new Map();
	let currentSection = null;
	for ( const rawLine of body.split( '\n' ) ) {
		const line = rawLine.trim();
		const headingMatch = line.match( /^\*\*(.+)\*\*$/ );
		if ( headingMatch ) {
			currentSection = headingMatch[ 1 ];
			if ( ! sections.has( currentSection ) ) {
				sections.set( currentSection, [] );
			}
			continue;
		}
		if ( currentSection && line.startsWith( '* ' ) ) {
			const entries = sections.get( currentSection );
			if ( entries ) {
				entries.push( line );
			}
		}
	}
	return sections;
}

/**
 * Inserts entry lines under the given section heading within a changelog body,
 * appending a new section when the heading is not already present.
 *
 * @param {string}   body    Changelog body.
 * @param {string}   section Section heading text (without the `**` markers).
 * @param {string[]} entries Entry lines to insert.
 * @return {string} Updated changelog body.
 */
function insertEntriesIntoSection( body, section, entries ) {
	const headingLine = `**${ section }**`;
	const lines = body.split( '\n' );
	const headingIndex = lines.findIndex(
		( line ) => line.trim() === headingLine
	);

	// Section not present: append it at the end of the body.
	if ( headingIndex === -1 ) {
		return `${ body.replace(
			/\s+$/,
			''
		) }\n\n${ headingLine }\n\n${ entries.join( '\n' ) }`;
	}

	// Insert after the last bullet belonging to this section (before the next heading).
	let insertIndex = headingIndex;
	for ( let i = headingIndex + 1; i < lines.length; i++ ) {
		const trimmed = lines[ i ].trim();
		if ( trimmed.startsWith( '**' ) && trimmed.endsWith( '**' ) ) {
			break;
		}
		if ( trimmed.startsWith( '* ' ) ) {
			insertIndex = i;
		}
	}

	lines.splice( insertIndex + 1, 0, ...entries );
	return lines.join( '\n' );
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
		/^Stable tag:\s*(\d+\.\d+\.\d+(?:-[\w.]+)?)$/m
	);
	if ( ! stableTagVersionMatches ) {
		throw new Error( `Unable to locate stable tag in ${ readmeFilePath }` );
	}
	return stableTagVersionMatches[ 1 ];
}

/**
 * Escapes a string for safe use inside a regular expression.
 *
 * @param {string} value String to escape.
 * @return {string} Escaped string.
 */
function escapeRegExp( value ) {
	return value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

/**
 * Updates the `readme.txt` file with a specific release changelog.
 *
 * @param {{pluginSlugs: string[], token?: string}} settings
 */
async function updateReadme( settings ) {
	const pluginRoot = path.resolve( __dirname, '../../../' );

	for ( const pluginSlug of settings.pluginSlugs ) {
		try {
			const pluginDirectory = path.resolve(
				pluginRoot,
				'plugins',
				pluginSlug
			);
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
			const message =
				error instanceof Error ? error.message : 'Unknown error';
			log(
				formats.error(
					`${ pluginSlug } failed to update: ${ message }`
				)
			);
		}
	}
}
