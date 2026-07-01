/**
 * External dependencies
 */
const path = require( 'path' );
const fs = require( 'fs' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const { getReleaseMilestones } = require( './bump-versions' );
const { plugins } = require( '../../../plugins.json' );

/**
 * @typedef WPPrepareReleaseNotesCommandOptions
 *
 * @property {string=} plugin Optional plugin slug to limit the notes to a single plugin.
 * @property {string=} token  Optional personal GitHub access token.
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
];

/**
 * Command that assembles the release notes for the plugins currently being released,
 * i.e. those with an open milestone that has a due date and whose title does not contain
 * "n.e.x.t". The notes for each plugin are the changelog entry for its stable tag, read
 * from the plugin's `readme.txt` (as populated by `npm run readme`).
 *
 * Progress and warnings are written to STDERR so that STDOUT (the Markdown release
 * notes) can be piped to a file or the clipboard.
 *
 * @param {WPPrepareReleaseNotesCommandOptions} opt
 */
exports.handler = async ( opt ) => {
	if ( opt.plugin && ! plugins.includes( opt.plugin ) ) {
		throw new Error(
			`The plugin "${ opt.plugin }" is not a valid plugin managed as part of this project.`
		);
	}

	const milestones = await getReleaseMilestones( opt.token );

	const selected = (
		opt.plugin
			? milestones.filter(
					( milestone ) => milestone.slug === opt.plugin
			  )
			: milestones
	).sort( ( a, b ) => a.slug.localeCompare( b.slug ) );

	if ( selected.length === 0 ) {
		process.stderr.write(
			formats.warning(
				opt.plugin
					? `⚠ Skipping ${ opt.plugin }: no open release milestone with a due date (and without "n.e.x.t") was found.\n`
					: '⚠ No plugins have an open release milestone with a due date (and without "n.e.x.t"); nothing to prepare.\n'
			)
		);
		return;
	}

	const pluginRoot = path.resolve( __dirname, '../../../' );
	const sections = [];

	for ( const milestone of selected ) {
		try {
			const readmeFilePath = path.resolve(
				pluginRoot,
				'plugins',
				milestone.slug,
				'readme.txt'
			);
			const { version, changelog } =
				getReadmeChangelogEntry( readmeFilePath );
			sections.push(
				`## \`${ milestone.slug }\` ${ version }\n\n${ changelog }`
			);
			process.stderr.write(
				formats.success(
					`✔ Prepared release notes for ${ milestone.slug } ${ version }.\n`
				)
			);
		} catch ( error ) {
			const message =
				error instanceof Error ? error.message : 'Unknown error';
			process.stderr.write(
				formats.error(
					`${ milestone.slug } failed to prepare: ${ message }\n`
				)
			);
		}
	}

	if ( sections.length === 0 ) {
		return;
	}

	log( sections.join( '\n\n' ) );
};

/**
 * Reads the changelog entry for a plugin's stable tag from its `readme.txt`.
 *
 * @param {string} readmeFilePath Readme file path.
 * @return {{version: string, changelog: string}} The stable tag version and its changelog body (without the version heading).
 */
function getReadmeChangelogEntry( readmeFilePath ) {
	const fileContent = fs.readFileSync( readmeFilePath, 'utf-8' );

	const stableTagMatches = fileContent.match(
		/^Stable tag:\s*(\d+\.\d+\.\d+(?:-[\w.]+)?)$/m
	);
	if ( ! stableTagMatches ) {
		throw new Error( `Unable to locate stable tag in ${ readmeFilePath }` );
	}
	const version = stableTagMatches[ 1 ];

	const headingRegExp = new RegExp(
		`^= ${ escapeRegExp( version ) } =$`,
		'm'
	);
	const headingMatch = headingRegExp.exec( fileContent );
	if ( ! headingMatch ) {
		throw new Error(
			`Unable to locate a "= ${ version } =" changelog entry in ${ readmeFilePath }`
		);
	}

	const bodyStart = headingMatch.index + headingMatch[ 0 ].length;
	const rest = fileContent.slice( bodyStart );
	const nextHeadingMatch = /^= .+ =$/m.exec( rest );
	const bodyEnd = nextHeadingMatch ? nextHeadingMatch.index : rest.length;
	const changelog = rest.slice( 0, bodyEnd ).trim();

	if ( changelog === '' ) {
		throw new Error(
			`The "= ${ version } =" changelog entry in ${ readmeFilePath } is empty; run "npm run readme" first.`
		);
	}

	return { version, changelog };
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
