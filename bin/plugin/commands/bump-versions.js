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
 * @property {string=}  plugin     Optional plugin slug to limit bumping to a single plugin.
 * @property {string=}  increment  Optional increment level ("major", "minor", "patch", or
 *                                 "prerelease") to derive the target version from the current
 *                                 one instead of from a release milestone.
 * @property {string=}  setVersion Optional explicit target version, bypassing both release
 *                                 milestones and --increment.
 * @property {boolean=} all        Whether to target every plugin; requires --increment.
 * @property {string=}  token      Optional personal GitHub access token.
 * @property {boolean=} dryRun     Whether to only report what would change without writing.
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

const INCREMENT_LEVELS = [ 'major', 'minor', 'patch', 'prerelease' ];

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description:
			'The plugin slug to bump; if omitted, all plugins with a matching milestone are bumped',
	},
	{
		argname: '-i, --increment <level>',
		description: `Derive the target version from the current one rather than from a release milestone: ${ INCREMENT_LEVELS.join(
			', '
		) }`,
	},
	{
		argname: '-s, --set-version <version>',
		description:
			'Set an explicit target version, bypassing both release milestones and --increment',
	},
	{
		argname: '-a, --all',
		description:
			'Target every plugin; only valid together with --increment',
		defaults: false,
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
 * Alternatively, passing --increment or --set-version skips the milestone lookup entirely
 * and derives the target version from each plugin's current "Stable tag". In that mode the
 * plugins to bump are named explicitly (as positional arguments), because the appropriate
 * increment level differs per plugin and must not be guessed.
 *
 * @param {string[]}                     pluginSlugs Plugin slugs given as positional arguments;
 *                                                   commander passes an empty array when none.
 * @param {WPBumpVersionsCommandOptions} opt
 */
exports.handler = async ( pluginSlugs, opt ) => {
	const requestedSlugs = [ ...new Set( pluginSlugs ) ];
	if ( opt.plugin && ! requestedSlugs.includes( opt.plugin ) ) {
		requestedSlugs.push( opt.plugin );
	}

	for ( const slug of requestedSlugs ) {
		if ( ! plugins.includes( slug ) ) {
			throw new Error(
				`The plugin "${ slug }" is not a valid plugin managed as part of this project.`
			);
		}
	}

	if ( opt.increment && opt.setVersion ) {
		throw new Error(
			'The --increment and --set-version options are mutually exclusive.'
		);
	}

	if ( opt.increment || opt.setVersion ) {
		await bumpWithoutMilestone( requestedSlugs, opt );
		return;
	}

	if ( opt.all ) {
		throw new Error(
			'The --all option requires --increment, since milestones already determine which plugins to bump.'
		);
	}

	if ( requestedSlugs.length > 1 ) {
		throw new Error(
			`Milestone-based bumping targets at most one plugin, but ${ requestedSlugs.length } were given. Pass --increment or --set-version to bump several plugins at once.`
		);
	}

	const milestonePlugin = requestedSlugs[ 0 ];
	const milestones = await getReleaseMilestones( opt.token );

	let selected = milestones;
	if ( milestonePlugin ) {
		selected = milestones.filter( ( m ) => m.slug === milestonePlugin );
	}

	if ( selected.length === 0 ) {
		log(
			formats.warning(
				milestonePlugin
					? `⚠ No release milestone (open, dated, without "n.e.x.t") found for ${ milestonePlugin }.`
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
 * Bumps the given plugins without consulting release milestones, deriving each target
 * version from the plugin's current "Stable tag" via --increment, or using --set-version
 * verbatim.
 *
 * Every target version is resolved before anything is written, so a validation failure
 * (most importantly the prerelease guard) aborts the whole run rather than leaving some
 * plugins bumped and others not.
 *
 * @param {string[]}                     requestedSlugs Explicitly named plugin slugs.
 * @param {WPBumpVersionsCommandOptions} opt
 */
async function bumpWithoutMilestone( requestedSlugs, opt ) {
	if ( opt.increment && ! INCREMENT_LEVELS.includes( opt.increment ) ) {
		throw new Error(
			`Unknown --increment level "${
				opt.increment
			}"; expected one of: ${ INCREMENT_LEVELS.join( ', ' ) }.`
		);
	}

	if (
		opt.setVersion &&
		! new RegExp( `^${ VERSION_PATTERN }$` ).test( opt.setVersion )
	) {
		throw new Error(
			`"${ opt.setVersion }" is not a valid version (expected e.g. "1.2.3" or "1.0.0-beta4").`
		);
	}

	let targetSlugs = requestedSlugs;
	if ( opt.all ) {
		if ( requestedSlugs.length > 0 ) {
			throw new Error(
				'The --all option cannot be combined with an explicit list of plugins.'
			);
		}
		targetSlugs = plugins;
	}

	if ( targetSlugs.length === 0 ) {
		throw new Error(
			'No plugins were named. List the plugin slugs to bump (e.g. `bump-versions --increment minor auto-sizes webp-uploads`), or pass --all for every plugin.'
		);
	}

	if ( opt.setVersion && targetSlugs.length > 1 ) {
		throw new Error(
			`The --set-version option applies to a single plugin, but ${ targetSlugs.length } were given.`
		);
	}

	const pluginRoot = path.resolve( __dirname, '../../../' );

	// Resolve first, write second.
	const targets = [ ...targetSlugs ].sort().map( ( slug ) => {
		const pluginDirectory = path.resolve( pluginRoot, 'plugins', slug );
		const currentVersion = readCurrentVersion( pluginDirectory, slug );
		const version =
			opt.setVersion ??
			incrementVersion( currentVersion, opt.increment ?? '', slug );
		return { slug, pluginDirectory, currentVersion, version };
	} );

	for ( const {
		slug,
		pluginDirectory,
		currentVersion,
		version,
	} of targets ) {
		try {
			const changes = await bumpPlugin(
				pluginDirectory,
				version,
				opt.dryRun
			);
			const prefix = opt.dryRun ? '🔍 [dry-run] ' : '💃 ';
			log(
				formats.success(
					`${ prefix }${ slug }: ${ currentVersion } → ${ version }:`
				)
			);
			for ( const change of changes ) {
				log( `    ${ change }` );
			}
		} catch ( error ) {
			const message =
				error instanceof Error ? error.message : 'Unknown error';
			log( formats.error( `❌ ${ slug }: ${ message }` ) );
			process.exitCode = 1;
		}
	}
}

/**
 * Reads a plugin's current version from the "Stable tag" in its readme.txt.
 *
 * @param {string} pluginDirectory Absolute path to the plugin directory.
 * @param {string} slug            Plugin slug, for error messages.
 * @return {string} The current version.
 */
function readCurrentVersion( pluginDirectory, slug ) {
	const readmeFile = path.resolve( pluginDirectory, 'readme.txt' );
	if ( ! fs.existsSync( readmeFile ) ) {
		throw new Error( `Unable to locate readme.txt for ${ slug }.` );
	}
	const matches = fs
		.readFileSync( readmeFile, 'utf-8' )
		.match( new RegExp( `^Stable tag:\\s*(${ VERSION_PATTERN })$`, 'm' ) );
	if ( ! matches ) {
		throw new Error( `Unable to locate "Stable tag" in ${ readmeFile }.` );
	}
	return matches[ 1 ];
}

/**
 * Increments a version by the given level.
 *
 * The "prerelease" level increments the trailing number of the prerelease component, so
 * "1.0.0-beta5" becomes "1.0.0-beta6". This is deliberately hand-rolled rather than
 * delegated to semver: `semver.inc( '1.0.0-beta5', 'prerelease' )` yields "1.0.0-beta5.0",
 * because semver treats "beta5" as a single alphanumeric identifier.
 *
 * Incrementing a version that has a prerelease component by major/minor/patch is refused,
 * since that would silently graduate a beta to a stable release.
 *
 * @param {string} currentVersion Current version.
 * @param {string} level          Increment level.
 * @param {string} slug           Plugin slug, for error messages.
 * @return {string} The incremented version.
 */
function incrementVersion( currentVersion, level, slug ) {
	const matches = currentVersion.match(
		/^(\d+)\.(\d+)\.(\d+)(?:-([\w.]+))?$/
	);
	if ( ! matches ) {
		throw new Error(
			`Unable to parse the current version "${ currentVersion }" of ${ slug }.`
		);
	}
	const [ , major, minor, patch, prerelease ] = matches;

	if ( 'prerelease' === level ) {
		if ( ! prerelease ) {
			throw new Error(
				`${ slug } is at ${ currentVersion }, which has no prerelease component to increment. Use --increment patch/minor/major, or --set-version.`
			);
		}
		const prereleaseMatches = prerelease.match( /^(.*?)(\d+)$/ );
		if ( ! prereleaseMatches ) {
			throw new Error(
				`Unable to increment the prerelease component of ${ slug }'s version "${ currentVersion }" because it does not end in a number. Use --set-version.`
			);
		}
		const [ , label, number ] = prereleaseMatches;
		return `${ major }.${ minor }.${ patch }-${ label }${
			Number( number ) + 1
		}`;
	}

	if ( prerelease ) {
		throw new Error(
			`${ slug } is at ${ currentVersion }, so --increment ${ level } would graduate it to a stable release. Use --increment prerelease for the next prerelease, or --set-version to set it explicitly.`
		);
	}

	switch ( level ) {
		case 'major':
			return `${ Number( major ) + 1 }.0.0`;
		case 'minor':
			return `${ major }.${ Number( minor ) + 1 }.0`;
		case 'patch':
			return `${ major }.${ minor }.${ Number( patch ) + 1 }`;
		default:
			throw new Error(
				`Unknown --increment level "${ level }"; expected one of: ${ INCREMENT_LEVELS.join(
					', '
				) }.`
			);
	}
}

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
