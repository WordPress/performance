/**
 * Pull request milestone assignment helpers.
 *
 * @since n.e.x.t
 */

/**
 * Internal dependencies
 */
const { plugins } = require( '../../../plugins.json' );

/** @typedef {import('@octokit/rest').Octokit} GitHub */

/**
 * @typedef PullRequestFileStats
 *
 * @property {string}  filename          File path after the change.
 * @property {number}  additions         Number of added lines.
 * @property {number}  deletions         Number of deleted lines.
 * @property {string=} previous_filename File path before a rename.
 */

/**
 * @typedef MilestoneReference
 *
 * @property {number} number Milestone number.
 * @property {string} title  Milestone title.
 */

/**
 * @typedef WorkflowContext
 *
 * @property {{ owner: string, repo: string }}                                            repo    Repository details.
 * @property {{ pull_request?: { number: number, milestone?: MilestoneReference|null } }} payload Event payload.
 */

/**
 * @typedef WorkflowLogger
 *
 * @property {(message: string) => void} info    Logs an informational message.
 * @property {(message: string) => void} warning Logs a warning message.
 */

/**
 * Returns the plugin slug for a repository path.
 *
 * @since n.e.x.t
 *
 * @param {string}   filePath    Repository-relative file path.
 * @param {string[]} pluginSlugs Known plugin slugs.
 * @return {string|null} Plugin slug, or null when the path is not for a plugin.
 */
function getPluginSlugForPath( filePath, pluginSlugs = plugins ) {
	const matches = filePath.match( /^plugins\/([^/]+)\// );
	if ( ! matches || ! pluginSlugs.includes( matches[ 1 ] ) ) {
		return null;
	}

	return matches[ 1 ];
}

/**
 * Counts changed lines for each plugin represented in a pull request.
 *
 * For cross-plugin renames, additions belong to the destination plugin and
 * deletions belong to the source plugin.
 *
 * @since n.e.x.t
 *
 * @param {PullRequestFileStats[]} files       Pull request files.
 * @param {string[]}               pluginSlugs Known plugin slugs.
 * @return {Record<string, number>} Changed line totals keyed by plugin slug.
 */
function countPluginLineChanges( files, pluginSlugs = plugins ) {
	/** @type {Record<string, number>} */
	const lineChanges = {};

	for ( const file of files ) {
		const pluginSlug = getPluginSlugForPath( file.filename, pluginSlugs );
		const previousPluginSlug = file.previous_filename
			? getPluginSlugForPath( file.previous_filename, pluginSlugs )
			: null;

		if ( file.previous_filename && pluginSlug !== previousPluginSlug ) {
			if ( pluginSlug ) {
				lineChanges[ pluginSlug ] =
					( lineChanges[ pluginSlug ] || 0 ) + file.additions;
			}
			if ( previousPluginSlug ) {
				lineChanges[ previousPluginSlug ] =
					( lineChanges[ previousPluginSlug ] || 0 ) + file.deletions;
			}
			continue;
		}

		if ( pluginSlug ) {
			lineChanges[ pluginSlug ] =
				( lineChanges[ pluginSlug ] || 0 ) +
				file.additions +
				file.deletions;
		}
	}

	return lineChanges;
}

/**
 * Returns the plugin with the unique highest changed-line total.
 *
 * @since n.e.x.t
 *
 * @param {Record<string, number>} lineChanges Changed lines keyed by plugin slug.
 * @return {string|null} Primary plugin slug, or null for no changes or a tie.
 */
function getPrimaryPluginSlug( lineChanges ) {
	const rankedPlugins = Object.entries( lineChanges )
		.filter( ( [ , changedLines ] ) => changedLines > 0 )
		.sort(
			( [ firstSlug, firstLines ], [ secondSlug, secondLines ] ) =>
				secondLines - firstLines ||
				firstSlug.localeCompare( secondSlug )
		);

	if (
		! rankedPlugins.length ||
		( rankedPlugins[ 1 ] &&
			rankedPlugins[ 0 ][ 1 ] === rankedPlugins[ 1 ][ 1 ] )
	) {
		return null;
	}

	return rankedPlugins[ 0 ][ 0 ];
}

/**
 * Selects the most appropriate open milestone for a plugin.
 *
 * The generic "n.e.x.t" milestone is preferred. A versioned milestone is
 * selected only when it is the sole open candidate for the plugin.
 *
 * @since n.e.x.t
 *
 * @param {MilestoneReference[]} milestones Open repository milestones.
 * @param {string}               pluginSlug Plugin slug.
 * @return {MilestoneReference|null} Selected milestone, or null when ambiguous.
 */
function selectPluginMilestone( milestones, pluginSlug ) {
	const pluginMilestones = milestones.filter( ( milestone ) =>
		milestone.title.startsWith( `${ pluginSlug } ` )
	);
	const nextMilestone = pluginMilestones.find(
		( milestone ) => milestone.title === `${ pluginSlug } n.e.x.t`
	);

	if ( nextMilestone ) {
		return nextMilestone;
	}

	return pluginMilestones.length === 1 ? pluginMilestones[ 0 ] : null;
}

/**
 * Assigns a pull request to the milestone for its primary plugin.
 *
 * @since n.e.x.t
 *
 * @param {Object}          options         Assignment options.
 * @param {GitHub}          options.github  Authenticated GitHub client.
 * @param {WorkflowContext} options.context Workflow event context.
 * @param {WorkflowLogger}  options.core    Workflow logger.
 * @return {Promise<void>} Promise resolving when assignment is complete.
 */
async function assignPluginMilestone( { github, context, core } ) {
	const pullRequest = context.payload.pull_request;
	if ( ! pullRequest ) {
		throw new Error(
			'The workflow event does not contain a pull request.'
		);
	}

	const files = await github.paginate( github.rest.pulls.listFiles, {
		...context.repo,
		pull_number: pullRequest.number,
		per_page: 100,
	} );
	const lineChanges = countPluginLineChanges( files );
	const pluginSlug = getPrimaryPluginSlug( lineChanges );

	if ( ! pluginSlug ) {
		core.info(
			`No unique primary plugin found. Changed lines by plugin: ${ JSON.stringify(
				lineChanges
			) }`
		);
		return;
	}

	if ( pullRequest.milestone?.title.startsWith( `${ pluginSlug } ` ) ) {
		core.info(
			`Pull request is already assigned to ${ pullRequest.milestone.title }.`
		);
		return;
	}

	const milestones = await github.paginate(
		github.rest.issues.listMilestones,
		{
			...context.repo,
			state: 'open',
			per_page: 100,
		}
	);
	const milestone = selectPluginMilestone( milestones, pluginSlug );

	if ( ! milestone ) {
		core.warning(
			`No unambiguous open milestone found for ${ pluginSlug }; leaving the milestone unchanged.`
		);
		return;
	}

	await github.rest.issues.update( {
		...context.repo,
		issue_number: pullRequest.number,
		milestone: milestone.number,
	} );
	core.info(
		`Assigned ${
			milestone.title
		} based on changed lines: ${ JSON.stringify( lineChanges ) }`
	);
}

module.exports = {
	assignPluginMilestone,
	countPluginLineChanges,
	getPluginSlugForPath,
	getPrimaryPluginSlug,
	selectPluginMilestone,
};
