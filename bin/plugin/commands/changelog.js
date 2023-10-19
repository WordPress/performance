/**
 * External dependencies
 */
const { groupBy } = require( 'lodash' );
const { Octokit } = require( '@octokit/rest' );

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
const MISSING_FOCUS = 'MISSING_FOCUS';
const TYPE_PREFIX = '[Type] ';
const FOCUS_PREFIX = '[Focus] ';
const INFRASTRUCTURE_LABEL = 'Infrastructure';
const PRIMARY_TYPE_LABELS = {
	'[Type] Feature': 'Features',
	'[Type] Enhancement': 'Enhancements',
	'[Type] Bug': 'Bug Fixes',
};
const PRIMARY_TYPE_ORDER = Object.values( PRIMARY_TYPE_LABELS );

/** @typedef {import('@octokit/rest')} GitHub */
/** @typedef {import('@octokit/rest').IssuesListForRepoResponseItem} IssuesListForRepoResponseItem */

/**
 * @typedef WPChangelogCommandOptions
 *
 * @property {string}  milestone Milestone title.
 * @property {string=} token     Optional personal access token.
 */

/**
 * @typedef WPChangelogSettings
 *
 * @property {string}  owner     Repository owner.
 * @property {string}  repo      Repository name.
 * @property {string}  milestone Milestone title.
 * @property {string=} token     Optional personal access token.
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
];

/**
 * Command that generates the release changelog.
 *
 * @param {WPChangelogCommandOptions} opt
 */
exports.handler = async ( opt ) => {
	await createChangelog( {
		owner: config.githubRepositoryOwner,
		repo: config.githubRepositoryName,
		milestone: opt.milestone,
		token: opt.token,
	} );
};

/**
 * Returns a promise resolving to an array of pull requests associated with the
 * changelog settings object.
 *
 * @param {GitHub}              octokit  GitHub REST client.
 * @param {WPChangelogSettings} settings Changelog settings.
 *
 * @return {Promise<IssuesListForRepoResponseItem[]>} Promise resolving to array of
 *                                            pull requests.
 */
async function fetchAllPullRequests( octokit, settings ) {
	const { owner, repo, milestone: milestoneTitle } = settings;
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
	return issues.filter( ( issue ) => issue.pull_request );
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
		.map( ( { name } ) => name )
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
 * Returns a focus label for a given issue object, or MISSING_FOCUS if focus
 * cannot be determined.
 *
 * @param {IssuesListForRepoResponseItem} issue Issue object.
 *
 * @return {string} Focus label, or MISSING_FOCUS.
 */
function getIssueFocus( issue ) {
	const labelNames = issue.labels.map( ( { name } ) => name );
	const focusLabels = labelNames.filter( ( label ) =>
		label.startsWith( FOCUS_PREFIX )
	);

	if ( ! focusLabels.length ) {
		if ( labelNames.includes( INFRASTRUCTURE_LABEL ) ) {
			return INFRASTRUCTURE_LABEL;
		}
		return MISSING_FOCUS;
	}

	return focusLabels[ 0 ].replace( FOCUS_PREFIX, '' );
}

/**
 * Formats the changelog string for a given list of pull requests.
 *
 * @param {string}                          milestone    Milestone title.
 * @param {IssuesListForRepoResponseItem[]} pullRequests List of pull requests.
 *
 * @return {string} The formatted changelog string.
 */
function formatChangelog( milestone, pullRequests ) {
	let changelog = '= ' + milestone.replace( 'PL Plugin ', '' ) + ' =\n\n';

	// Group PRs by type.
	const typeGroups = groupBy( pullRequests, getIssueType );
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
			return a - b;
		}
		return aIndex > -1 ? -1 : 1;
	} );

	for ( const group of typeGroupNames ) {
		// Start a new section within the changelog.
		changelog += '**' + group + '**\n\n';

		// Group PRs within this section per focus.
		const focusGroups = groupBy( typeGroups[ group ], getIssueFocus );
		if ( focusGroups[ MISSING_FOCUS ] ) {
			const prURLs = focusGroups[ MISSING_FOCUS ].map(
				( { html_url } ) => html_url // eslint-disable-line camelcase
			);
			throw new Error(
				`The following pull-requests are missing a "${ FOCUS_PREFIX }xyz" or "${ INFRASTRUCTURE_LABEL }" label: ${ prURLs.join(
					', '
				) }`
			);
		}

		// Sort focuses alphabetically, except infrastructure which comes last.
		const focusGroupNames = Object.keys( focusGroups ).sort( ( a, b ) => {
			if (
				( a !== INFRASTRUCTURE_LABEL && b !== INFRASTRUCTURE_LABEL ) ||
				a === b
			) {
				return a - b;
			}
			return a !== INFRASTRUCTURE_LABEL ? -1 : 1;
		} );

		// Output all PRs within this section.
		focusGroupNames.forEach( ( featureName ) => {
			const focusGroupPRs = focusGroups[ featureName ];

			focusGroupPRs
				.map( ( issue ) => {
					const title = issue.title
						// Strip feature name from title if used as prefix.
						.replace(
							new RegExp(
								`^${ featureName.toLowerCase() }: `,
								'i'
							),
							''
						)
						// Strip trailing whitespace.
						.trim()
						// Ensure first letter is uppercase.
						.replace( /^([a-z])/, ( _match, firstLetter ) =>
							firstLetter.toUpperCase()
						)
						// Add trailing period.
						.replace( /\s*\.?$/, '' )
						.concat( '.' );
					return `* ${ featureName }: ${ title } ([${ issue.number }](${ issue.html_url }))`; // eslint-disable-line camelcase
				} )
				.filter( Boolean )
				.sort()
				.forEach( ( entry ) => {
					changelog += `${ entry }\n`;
				} );
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
	const octokit = new Octokit( {
		auth: settings.token,
	} );

	const pullRequests = await fetchAllPullRequests( octokit, settings );
	if ( ! pullRequests.length ) {
		throw new Error(
			'There are no (closed) pull requests associated with the milestone.'
		);
	}

	return formatChangelog( settings.milestone, pullRequests );
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
