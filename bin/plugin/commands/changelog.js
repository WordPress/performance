/**
 * External dependencies
 */
const { groupBy } = require( 'lodash' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const {
	getMilestoneByTitle,
	getIssuesByMilestone,
} = require( '../lib/milestone' );
const config = require( '../config' );

const MISSING_TYPE = 'MISSING_TYPE';
const TYPE_PREFIX = '[Type] ';

/**
 * Mapping from plugin folder slug to its GitHub label.
 * Used to look up cross-plugin PRs when building a changelog.
 *
 * @since n.e.x.t
 *
 * @type {Object<string, string>}
 */
const PLUGIN_SLUG_TO_LABEL_MAP = {
	'auto-sizes': '[Plugin] Enhanced Responsive Images',
	'dominant-color-images': '[Plugin] Image Placeholders',
	'embed-optimizer': '[Plugin] Embed Optimizer',
	'image-prioritizer': '[Plugin] Image Prioritizer',
	'optimization-detective': '[Plugin] Optimization Detective',
	'performance-lab': '[Plugin] Performance Lab',
	'speculation-rules': '[Plugin] Speculative Loading',
	'view-transitions': '[Plugin] View Transitions',
	'web-worker-offloading': '[Plugin] Web Worker Offloading',
	'webp-uploads': '[Plugin] Modern Image Formats',
};
const PRIMARY_TYPE_LABELS = {
	'[Type] Feature': 'Features',
	'[Type] Enhancement': 'Enhancements',
	'[Type] Bug': 'Bug Fixes',
};
const PRIMARY_TYPE_ORDER = Object.values( PRIMARY_TYPE_LABELS );
const SKIP_CHANGELOG_LABEL = 'skip changelog';

/** @typedef {import('@octokit/rest').Octokit} GitHub */
/** @typedef {import('@octokit/rest').RestEndpointMethodTypes['issues']['listForRepo']['response']['data'][0]} IssuesListForRepoResponseItem */

/**
 * @typedef WPChangelogCommandOptions
 *
 * @property {string}  milestone   Milestone title.
 * @property {string=} token       Optional personal access token.
 * @property {string=} pluginLabel Optional plugin label used to fetch cross-plugin PRs.
 */

/**
 * @typedef WPChangelogSettings
 *
 * @property {string}  owner       Repository owner.
 * @property {string}  repo        Repository name.
 * @property {string}  milestone   Milestone title.
 * @property {string=} token       Optional personal access token.
 * @property {string=} pluginLabel Optional plugin label used to fetch cross-plugin PRs.
 */

exports.options = [
	{
		argname: '-m, --milestone <milestone>',
		description: 'Milestone',
	},
	{
		argname: '-t, --token <token>',
		description: 'GitHub token',
	},
	{
		argname: '-l, --plugin-label <pluginLabel>',
		description:
			'Plugin label (e.g. "[Plugin] Embed Optimizer"). When provided, merged PRs carrying this label and assigned to any open milestone are also included in the changelog.',
	},
];

/**
 * Command that generates the release changelog.
 *
 * @param {WPChangelogCommandOptions} opt
 */
exports.handler = async ( opt ) => {
	// If no plugin label was explicitly provided, try to derive it from the
	// milestone title using the "plugin-slug version" convention.
	let pluginLabel = opt.pluginLabel;
	if ( ! pluginLabel ) {
		const milestoneSlug = opt.milestone
			? opt.milestone.split( ' ' )[ 0 ]
			: undefined;
		pluginLabel = milestoneSlug
			? PLUGIN_SLUG_TO_LABEL_MAP[ milestoneSlug ]
			: undefined;
	}

	await createChangelog( {
		owner: config.githubRepositoryOwner,
		repo: config.githubRepositoryName,
		milestone: opt.milestone,
		token: opt.token,
		pluginLabel,
	} );
};

/**
 * Returns a promise resolving to merged pull requests that carry a given plugin
 * label and are associated with any open milestone. This is used to include
 * cross-plugin PRs in a changelog: a PR primarily assigned to Plugin A's
 * milestone but also labeled for Plugin B will appear in Plugin B's changelog.
 *
 * @since n.e.x.t
 *
 * @param {GitHub} octokit     GitHub REST client.
 * @param {string} owner       Repository owner.
 * @param {string} repo        Repository name.
 * @param {string} pluginLabel Plugin label to search for (e.g. "[Plugin] Embed Optimizer").
 *
 * @return {Promise<IssuesListForRepoResponseItem[]>} Promise resolving to merged pull requests.
 */
async function fetchCrossPluginPullRequests(
	octokit,
	owner,
	repo,
	pluginLabel
) {
	// Fetch all open milestones so we can check PR membership.
	const milestonesResponse = await octokit.paginate(
		octokit.issues.listMilestones,
		{ owner, repo, state: 'open' }
	);

	if ( ! milestonesResponse.length ) {
		return [];
	}

	const openMilestoneNumbers = new Set(
		milestonesResponse.map( ( m ) => m.number )
	);

	// Search for closed issues with the plugin label.
	const labeledIssues = await octokit.paginate( octokit.issues.listForRepo, {
		owner,
		repo,
		labels: pluginLabel,
		state: 'closed',
	} );

	// Keep only merged pull requests that are associated with an open milestone.
	return labeledIssues.filter(
		( issue ) =>
			issue.pull_request &&
			issue.pull_request.merged_at &&
			issue.milestone &&
			openMilestoneNumbers.has( issue.milestone.number )
	);
}

/**
 * Returns a promise resolving to an array of merged pull requests associated with the
 * changelog settings object. When a plugin label is provided (or derivable from the
 * milestone title), merged PRs carrying that label and assigned to any open milestone
 * are also included so that cross-plugin PRs appear in every relevant changelog.
 *
 * @param {GitHub}              octokit  GitHub REST client.
 * @param {WPChangelogSettings} settings Changelog settings.
 *
 * @return {Promise<IssuesListForRepoResponseItem[]>} Promise resolving to array of pull requests.
 */
async function fetchAllPullRequests( octokit, settings ) {
	const { owner, repo, milestone: milestoneTitle, pluginLabel } = settings;
	const milestone = await getMilestoneByTitle(
		octokit,
		owner,
		repo,
		milestoneTitle
	);

	if ( ! milestone ) {
		throw new Error(
			`Cannot find milestone by title: ${ milestoneTitle }`
		);
	}

	const issues = await getIssuesByMilestone(
		octokit,
		owner,
		repo,
		milestone.number,
		'closed'
	);

	// Primary pull requests: merged PRs in the specified milestone.
	const primaryPRs = issues.filter(
		( issue ) => issue.pull_request && issue.pull_request.merged_at
	);

	if ( ! pluginLabel ) {
		return primaryPRs;
	}

	// Bonus: also include merged PRs with the plugin label that are assigned to
	// a different open milestone (cross-plugin PRs in a monorepo).
	const crossPluginPRs = await fetchCrossPluginPullRequests(
		octokit,
		owner,
		repo,
		pluginLabel
	);

	if ( ! crossPluginPRs.length ) {
		return primaryPRs;
	}

	// Merge and deduplicate by PR number, giving precedence to primary PRs.
	const seen = new Set( primaryPRs.map( ( pr ) => pr.number ) );
	const extraPRs = crossPluginPRs.filter( ( pr ) => {
		// Exclude PRs already covered by the primary milestone.
		if ( seen.has( pr.number ) ) {
			return false;
		}
		// Exclude PRs whose milestone is the same as the one we're generating
		// the changelog for (they are already included via primaryPRs).
		if ( pr.milestone && pr.milestone.number === milestone.number ) {
			return false;
		}
		seen.add( pr.number );
		return true;
	} );

	return [ ...primaryPRs, ...extraPRs ];
}

/**
 * Returns a type label for a given issue object, or MISSING_TYPE if type
 * cannot be determined.
 *
 * @param {IssuesListForRepoResponseItem} issue Issue object.
 *
 * @return {string} Type label, or MISSING_TYPE.
 */
function getIssueType( issue ) {
	const typeLabels = issue.labels
		.map( ( label ) => ( typeof label === 'string' ? label : label.name ) )
		.filter( ( label ) => label.startsWith( TYPE_PREFIX ) );

	if ( ! typeLabels.length ) {
		return MISSING_TYPE;
	}

	if ( PRIMARY_TYPE_LABELS[ typeLabels[ 0 ] ] ) {
		return PRIMARY_TYPE_LABELS[ typeLabels[ 0 ] ];
	}

	return typeLabels[ 0 ].replace( TYPE_PREFIX, '' );
}

/**
 * Formats the changelog string for a given list of pull requests.
 *
 * @param {string}                          milestone    Milestone title.
 * @param {IssuesListForRepoResponseItem[]} pullRequests List of pull requests.
 *
 * @return {string} The formatted changelog string (without the heading).
 */
function formatChangelog( milestone, pullRequests ) {
	let changelog = '';

	// Group PRs by type.
	const typeGroups =
		/** @type {Object<string, IssuesListForRepoResponseItem[]>} */ (
			groupBy( pullRequests, getIssueType )
		);
	if ( typeGroups[ MISSING_TYPE ] ) {
		const prURLs = typeGroups[ MISSING_TYPE ].map(
			( { html_url } ) => html_url // eslint-disable-line camelcase
		);
		throw new Error(
			`The following pull-requests are missing a "${ TYPE_PREFIX }xyz" label: ${ prURLs.join(
				', '
			) }`
		);
	}

	// Sort types by changelog significance, then alphabetically.
	const typeGroupNames = Object.keys( typeGroups ).sort( ( a, b ) => {
		const aIndex = PRIMARY_TYPE_ORDER.indexOf( a );
		const bIndex = PRIMARY_TYPE_ORDER.indexOf( b );
		if ( aIndex > -1 && bIndex > -1 ) {
			return aIndex - bIndex;
		}
		if ( aIndex === -1 && bIndex === -1 ) {
			return a.localeCompare( b );
		}
		return aIndex > -1 ? -1 : 1;
	} );

	for ( const group of typeGroupNames ) {
		// Start a new section within the changelog.
		changelog += '**' + group + '**\n\n';

		const typeGroupPRs = typeGroups[ group ];
		typeGroupPRs
			.map( ( issue ) => {
				const title = issue.title
					// Strip trailing whitespace.
					.trim()
					// Ensure first letter is uppercase.
					.replace( /^([a-z])/, ( _match, firstLetter ) =>
						firstLetter.toUpperCase()
					)
					// Add trailing period.
					.replace( /\s*\.?$/, '' )
					.concat( '.' );
				return `* ${ title } ([${ issue.number }](${ issue.html_url }))`; // eslint-disable-line camelcase
			} )
			.filter( Boolean )
			.sort()
			.forEach( ( entry ) => {
				changelog += `${ entry }\n`;
			} );

		changelog += '\n';
	}

	return changelog;
}

/**
 * Returns a promise resolving to the changelog string for given settings.
 *
 * @param {WPChangelogSettings} settings Changelog settings.
 *
 * @return {Promise<string>} Promise resolving to changelog.
 */
async function getChangelog( settings ) {
	const { Octokit } = await import( '@octokit/rest' );
	const octokit = new Octokit( {
		auth: settings.token,
	} );

	const pullRequests = await fetchAllPullRequests( octokit, settings );
	if ( ! pullRequests.length ) {
		throw new Error(
			`There are no merged pull requests associated with the milestone ${ settings.milestone }`
		);
	}

	const nonSkippedPullRequests = pullRequests.filter(
		( pullRequest ) =>
			! pullRequest.labels
				.map( ( label ) =>
					typeof label === 'string' ? label : label.name
				)
				.find( ( name ) => name === SKIP_CHANGELOG_LABEL )
	);
	if ( ! nonSkippedPullRequests.length ) {
		throw new Error(
			`All of the merged pull requests in the ${ settings.milestone } milestone have the "${ SKIP_CHANGELOG_LABEL }" label.`
		);
	}

	return formatChangelog( settings.milestone, nonSkippedPullRequests );
}

/**
 * Generates and logs changelog for a milestone.
 *
 * @param {WPChangelogSettings} settings Changelog settings.
 */
async function createChangelog( settings ) {
	if ( settings.milestone === undefined ) {
		log(
			formats.error(
				'A milestone must be provided via the --milestone (-m) argument.'
			)
		);
		return;
	}

	log(
		formats.title(
			`\n💃Preparing changelog for milestone: "${ settings.milestone }"\n\n`
		)
	);

	let changelog;
	try {
		changelog = await getChangelog( settings );
	} catch ( error ) {
		if ( error instanceof Error ) {
			changelog = formats.error( error.stack );
		}
	}

	log( changelog );
}

// Export getChangelog function to reuse in `readme` command.
exports.getChangelog = getChangelog;
