/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const glob = require( 'fast-glob' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const { getMilestones } = require( '../lib/milestone' );
const config = require( '../config' );
const { plugins } = require( '../../../plugins.json' );

/** @typedef {import('@octokit/rest').Octokit} GitHub */

/**
 * @typedef WPBumpVersionsCommandOptions
 *
 * @property {string=}  plugin Optional plugin slug to limit bumping to a single plugin.
 * @property {string=}  token  Optional personal GitHub access token.
 * @property {boolean=} dryRun Whether to only report what would change without writing.
 */

/**
 * @typedef ReleaseMilestone
 *
 * @property {string} slug    Plugin slug.
 * @property {string} version Target version parsed from the milestone title.
 * @property {string} title   Full milestone title.
 * @property {number} number  Milestone number.
 * @property {number} open    Count of open issues/PRs in the milestone.
 */

const VERSION_PATTERN = '\\d+\\.\\d+\\.\\d+(?:-[\\w.]+)?';

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description:
			'The plugin slug to bump; if omitted, all plugins with a matching milestone are bumped',
	},
	{
		argname: '-t, --token <token>',
		description: 'GitHub token',
	},
	{
		argname: '-n, --dry-run',
		description: 'Report the version changes without writing any files',
		defaults: false,
	},
];

/**
 * Command that bumps plugin versions based on their release milestones.
 *
 * A release milestone is an open milestone whose title is "<plugin-slug> <version>"
 * (i.e. it does not contain "n.e.x.t") and which has a due date set. For each such
 * milestone, the target version is applied to:
 *
 *  - the plugin's bootstrap PHP file header,
 *  - its PHP version constant,
 *  - the "Stable tag" in readme.txt, and
 *  - a new (empty) changelog entry is inserted so the readme is ready for `npm run readme`.
 *
 * @param {WPBumpVersionsCommandOptions} opt
 */
exports.handler = async ( opt ) => {
	if ( opt.plugin && ! plugins.includes( opt.plugin ) ) {
		throw new Error(
			`The plugin "${ opt.plugin }" is not a valid plugin managed as part of this project.`
		);
	}

	const milestones = await getReleaseMilestones( opt.token );

	let selected = milestones;
	if ( opt.plugin ) {
		selected = milestones.filter( ( m ) => m.slug === opt.plugin );
	}

	if ( selected.length === 0 ) {
		log(
			formats.warning(
				opt.plugin
					? `⚠ No release milestone (open, dated, without "n.e.x.t") found for ${ opt.plugin }.`
					: '⚠ No release milestones (open, dated, without "n.e.x.t") found.'
			)
		);
		return;
	}

	// Guard against two dated milestones targeting the same plugin, which would be ambiguous.
	const byPlugin = /** @type {Record<string, ReleaseMilestone[]>} */ ( {} );
	for ( const milestone of selected ) {
		if ( ! byPlugin[ milestone.slug ] ) {
			byPlugin[ milestone.slug ] = [];
		}
		byPlugin[ milestone.slug ].push( milestone );
	}
	const ambiguous = Object.entries( byPlugin ).filter(
		( [ , list ] ) => list.length > 1
	);
	if ( ambiguous.length > 0 ) {
		const details = ambiguous
			.map(
				( [ slug, list ] ) =>
					`${ slug }: ${ list.map( ( m ) => m.title ).join( ', ' ) }`
			)
			.join( '; ' );
		throw new Error(
			`Multiple dated release milestones match the same plugin, so the target version is ambiguous: ${ details }`
		);
	}

	const pluginRoot = path.resolve( __dirname, '../../../' );

	for ( const milestone of selected.sort( ( a, b ) =>
		a.slug.localeCompare( b.slug )
	) ) {
		if ( milestone.open > 0 ) {
			log(
				formats.warning(
					`⚠ Milestone "${ milestone.title }" still has ${ milestone.open } open issue(s)/PR(s); the release should have none.`
				)
			);
		}

		try {
			const pluginDirectory = path.resolve(
				pluginRoot,
				'plugins',
				milestone.slug
			);
			const changes = await bumpPlugin(
				pluginDirectory,
				milestone.version,
				opt.dryRun
			);
			const prefix = opt.dryRun ? '🔍 [dry-run] ' : '💃 ';
			log(
				formats.success(
					`${ prefix }${ milestone.slug } → ${ milestone.version }:`
				)
			);
			for ( const change of changes ) {
				log( `    ${ change }` );
			}
		} catch ( error ) {
			const message =
				error instanceof Error ? error.message : 'Unknown error';
			log( formats.error( `❌ ${ milestone.slug }: ${ message }` ) );
		}
	}
};

/**
 * Fetches the release milestones: open milestones with a due date whose title is
 * "<plugin-slug> <version>" (i.e. without "n.e.x.t").
 *
 * @param {string=} token Optional GitHub token.
 * @return {Promise<ReleaseMilestone[]>} Release milestones.
 */
async function getReleaseMilestones( token ) {
	const { Octokit } = await import( '@octokit/rest' );
	const octokit = new Octokit( { auth: token } );

	const milestones = await getMilestones(
		octokit,
		config.githubRepositoryOwner,
		config.githubRepositoryName,
		'open'
	);

	const titleRegExp = new RegExp(
		`^(?<slug>[a-z0-9-]+)\\s+(?<version>${ VERSION_PATTERN })$`
	);

	/** @type {ReleaseMilestone[]} */
	const releaseMilestones = [];

	for ( const milestone of milestones ) {
		// Skip milestones without a due date (e.g. backlog/planning milestones).
		if ( ! milestone.due_on ) {
			continue;
		}

		const matches = milestone.title.match( titleRegExp );
		if ( ! matches || ! matches.groups ) {
			continue;
		}

		const { slug, version } = matches.groups;
		if ( ! plugins.includes( slug ) ) {
			log(
				formats.warning(
					`⚠ Milestone "${ milestone.title }" does not correspond to a known plugin slug; skipping.`
				)
			);
			continue;
		}

		releaseMilestones.push( {
			slug,
			version,
			title: milestone.title,
			number: milestone.number,
			open: milestone.open_issues,
		} );
	}

	return releaseMilestones;
}

// Export getReleaseMilestones so other release commands (e.g. `since`) can target the
// same set of plugins.
exports.getReleaseMilestones = getReleaseMilestones;

/**
 * Bumps the version everywhere it needs to be set for a single plugin.
 *
 * @param {string}   pluginDirectory Absolute path to the plugin directory.
 * @param {string}   version         Target version.
 * @param {boolean=} dryRun          Whether to avoid writing files.
 * @return {Promise<string[]>} Human-readable descriptions of the changes made.
 */
async function bumpPlugin( pluginDirectory, version, dryRun ) {
	const changes = [];

	const bootstrapFile = await findBootstrapFile( pluginDirectory );
	const bootstrapContents = fs.readFileSync( bootstrapFile, 'utf-8' );
	const relativeBootstrap = path.basename( bootstrapFile );

	// 1. Plugin header "Version:" metadata.
	let updatedBootstrap = replaceOne(
		bootstrapContents,
		new RegExp( `^( \\* Version:\\s+)${ VERSION_PATTERN }$`, 'm' ),
		( _match, prefix ) => `${ prefix }${ version }`,
		`Unable to locate the "Version" header in ${ relativeBootstrap }.`
	);

	// 2. PHP version constant literal (the first version-like single-quoted string).
	updatedBootstrap = replaceOne(
		updatedBootstrap,
		new RegExp( `'${ VERSION_PATTERN }'` ),
		() => `'${ version }'`,
		`Unable to locate the PHP version constant in ${ relativeBootstrap }.`
	);

	if ( updatedBootstrap !== bootstrapContents ) {
		if ( ! dryRun ) {
			fs.writeFileSync( bootstrapFile, updatedBootstrap );
		}
		changes.push(
			`${ relativeBootstrap }: updated "Version" header and PHP version constant`
		);
	} else {
		changes.push( `${ relativeBootstrap }: already at ${ version }` );
	}

	// 3 & 4. readme.txt "Stable tag" and a new changelog entry.
	const readmeFile = path.resolve( pluginDirectory, 'readme.txt' );
	const readmeContents = fs.readFileSync( readmeFile, 'utf-8' );

	const stableTagUpdated = replaceOne(
		readmeContents,
		new RegExp( `^(Stable tag:\\s*)${ VERSION_PATTERN }$`, 'm' ),
		( _match, prefix ) => `${ prefix }${ version }`,
		`Unable to locate "Stable tag" in ${ readmeFile }.`
	);

	const { contents: updatedReadme, status } = ensureChangelogEntry(
		stableTagUpdated,
		version
	);

	const readmeChanges = [];
	if ( stableTagUpdated !== readmeContents ) {
		readmeChanges.push( 'updated "Stable tag"' );
	}
	if ( status === 'inserted' ) {
		readmeChanges.push( `inserted "= ${ version } =" changelog entry` );
	} else if ( status === 'relabeled' ) {
		readmeChanges.push(
			`relabeled "= n.e.x.t =" changelog entry to "= ${ version } ="`
		);
	}

	if ( updatedReadme !== readmeContents ) {
		if ( ! dryRun ) {
			fs.writeFileSync( readmeFile, updatedReadme );
		}
		changes.push( `readme.txt: ${ readmeChanges.join( ' and ' ) }` );
	} else {
		changes.push( `readme.txt: already at ${ version }` );
	}

	return changes;
}

/**
 * Locates the plugin bootstrap PHP file (the one declaring the plugin header).
 *
 * @param {string} pluginDirectory Absolute path to the plugin directory.
 * @return {Promise<string>} Absolute path to the bootstrap file.
 */
async function findBootstrapFile( pluginDirectory ) {
	for ( const phpFile of await glob(
		path.resolve( pluginDirectory, '*.php' )
	) ) {
		const phpFileContents = fs.readFileSync( phpFile, 'utf-8' );
		if ( /^<\?php\n\/\*\*\n \* Plugin Name:/.test( phpFileContents ) ) {
			return phpFile;
		}
	}
	throw new Error(
		`Unable to locate the PHP bootstrap file in ${ pluginDirectory }.`
	);
}

/**
 * Ensures the changelog has a single "= <version> =" entry at the top.
 *
 * This keeps the readme version-consistent (the latest changelog entry must match the
 * stable tag) and primes the section for `npm run readme`. There are three cases:
 *
 * - An entry for the version already exists: nothing to do (idempotent).
 * - A "= n.e.x.t =" placeholder entry exists (where contributors accrued notes during
 *   development): relabel it to "= <version> =". This avoids producing a duplicate
 *   entry once `npm run since` rewrites any remaining "n.e.x.t" headings.
 * - Otherwise: insert a new empty "= <version> =" entry as the first entry.
 *
 * @param {string} readmeContents Readme contents.
 * @param {string} version        Target version.
 * @return {{contents: string, status: 'present'|'relabeled'|'inserted'}} Updated contents and what was done.
 */
function ensureChangelogEntry( readmeContents, version ) {
	// Already has an entry for this version anywhere in the changelog; nothing to do.
	const versionHeadingRegExp = new RegExp(
		`^= ${ escapeRegExp( version ) } =$`,
		'm'
	);
	if ( versionHeadingRegExp.test( readmeContents ) ) {
		return { contents: readmeContents, status: 'present' };
	}

	// Relabel an existing "= n.e.x.t =" placeholder heading (the first one, which is in
	// the Changelog section rather than a later Upgrade Notice section).
	const nextHeadingRegExp = /^= n\.e\.x\.t =$/m;
	if ( nextHeadingRegExp.test( readmeContents ) ) {
		return {
			contents: readmeContents.replace(
				nextHeadingRegExp,
				`= ${ version } =`
			),
			status: 'relabeled',
		};
	}

	// Otherwise insert a new empty entry at the top of the changelog.
	const contents = replaceOne(
		readmeContents,
		/^(== Changelog ==\n+)/m,
		( _match, heading ) => `${ heading }= ${ version } =\n\n`,
		'Unable to locate the "== Changelog ==" section in readme.txt.'
	);

	return { contents, status: 'inserted' };
}

/**
 * Replaces exactly one match of a regular expression, throwing if there is no match.
 *
 * @param {string}                     contents     Input string.
 * @param {RegExp}                     regexp       Regular expression (must not be global).
 * @param {function(...string):string} replacer     Replacement callback.
 * @param {string}                     errorMessage Error message thrown when there is no match.
 * @return {string} The string with the replacement applied.
 */
function replaceOne( contents, regexp, replacer, errorMessage ) {
	if ( ! regexp.test( contents ) ) {
		throw new Error( errorMessage );
	}
	return contents.replace( regexp, replacer );
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
