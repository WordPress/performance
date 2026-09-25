/**
 * Chrome DevTools third-party tools integration for Performance Lab.
 *
 * Registers a group of read-only tools with Chrome DevTools for agents, via its experimental
 * third-party tools API, so that an AI agent can inspect WordPress server-side performance state
 * (Server-Timing metrics, database queries, and environment info) while debugging a page.
 *
 * Tool names and descriptions are deliberately not internationalized since they are consumed by
 * AI agents rather than shown to users.
 *
 * @since n.e.x.t
 *
 * @see https://developer.chrome.com/docs/devtools/agents/use-cases/third-party-tools
 */

/**
 * Event dispatched by Chrome DevTools for agents to discover third-party tools.
 *
 * This is a new, experimental, Chrome-only browser API not yet reflected in TypeScript's DOM lib.
 *
 * @typedef {Event & { respondWith: (toolGroup: Object) => void }} DevtoolsToolDiscoveryEvent
 */

/**
 * Database query captured via the SAVEQUERIES constant.
 *
 * @typedef {Object} DevtoolsDatabaseQuery
 * @property {string} sql    SQL statement.
 * @property {number} timeMs Query duration in milliseconds.
 * @property {string} caller Comma-separated call stack of the calling functions.
 */

/**
 * Data printed by perflab_devtools_print_data() in PHP.
 *
 * @typedef {Object} DevtoolsData
 * @property {Object<string, *>}                                                             environment Environment info.
 * @property {null|{ count: number, totalTimeMs: number, queries: DevtoolsDatabaseQuery[] }} dbQueries   Database queries, or null if SAVEQUERIES is not enabled.
 */

/**
 * Data captured server-side for the current page load, or null if unavailable.
 *
 * @type {DevtoolsData|null}
 */
let data = null;

const dataScript = document.getElementById( 'perflab-devtools-data' );
if ( dataScript instanceof HTMLScriptElement ) {
	try {
		data = JSON.parse( dataScript.text );
	} catch {
		// Tolerate malformed data; the tools will report it as unavailable.
	}
}

window.addEventListener( 'devtoolstooldiscovery', ( event ) => {
	const discoveryEvent = /** @type {DevtoolsToolDiscoveryEvent} */ ( event );
	discoveryEvent.respondWith( {
		name: 'WordPress Performance Lab',
		description:
			'Inspect WordPress server-side performance state for the current page load, including Server-Timing metrics, database queries, and environment info.',
		tools: [
			{
				name: 'get_server_timing_metrics',
				description:
					'Returns the Server-Timing metrics which WordPress sent in the HTML response for the current page load, as name/duration (milliseconds)/description entries. Metrics are managed by the Performance Lab plugin and configured in WP Admin under Settings > Performance; plugins can register additional metrics via the Performance Lab Server-Timing PHP API.',
				inputSchema: { type: 'object', properties: {} },
				execute: async () => {
					const [ navigationEntry ] =
						/** @type {PerformanceNavigationTiming[]} */ (
							performance.getEntriesByType( 'navigation' )
						);
					if ( ! navigationEntry ) {
						return [];
					}
					return navigationEntry.serverTiming.map(
						( { name, duration, description } ) => ( {
							name,
							duration,
							description,
						} )
					);
				},
			},
			{
				name: 'get_environment_info',
				description:
					'Returns information about the WordPress environment which served the current page: WordPress and PHP versions, active theme, whether a persistent object cache is in use, whether the WP_DEBUG and SAVEQUERIES constants are enabled, whether it is a multisite, and the list of active plugins.',
				inputSchema: { type: 'object', properties: {} },
				execute: async () => ( data ? data.environment : null ),
			},
			{
				name: 'get_database_queries',
				description:
					'Returns the SQL database queries which WordPress executed while rendering the current page (up until the end of wp_footer), including each query’s SQL, duration in milliseconds, and calling function stack, along with the total query count and cumulative duration. Requires the SAVEQUERIES constant to be defined as true in wp-config.php.',
				inputSchema: {
					type: 'object',
					properties: {
						limit: {
							type: 'integer',
							description:
								'Optional maximum number of queries to return. When provided, the slowest queries are returned first.',
						},
					},
				},
				execute: async (
					/** @type {{ limit?: number }} */ { limit } = {}
				) => {
					if ( ! data || ! data.dbQueries ) {
						return {
							available: false,
							reason: 'Database queries were not captured. Define the SAVEQUERIES constant as true in wp-config.php to capture them.',
						};
					}
					const { count, totalTimeMs, queries } = data.dbQueries;
					let results = queries;
					if ( typeof limit === 'number' && limit > 0 ) {
						results = [ ...queries ]
							.sort( ( a, b ) => b.timeMs - a.timeMs )
							.slice( 0, limit );
					}
					return {
						available: true,
						count,
						totalTimeMs,
						queries: results,
					};
				},
			},
		],
	} );
} );
