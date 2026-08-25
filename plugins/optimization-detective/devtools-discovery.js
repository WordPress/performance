/**
 * DevTools Discovery module for Optimization Detective.
 *
 * Exposes the URL Metric being collected for the current page load to Chrome DevTools for
 * agents, via its third-party tools API, so that an AI agent can inspect it while debugging
 * page performance.
 *
 * @since n.e.x.t
 *
 * @see https://developer.chrome.com/docs/devtools/agents/use-cases/third-party-tools
 */

/**
 * Extension name.
 *
 * @since n.e.x.t
 *
 * @type {string}
 */
export const name = 'DevTools Discovery';

/**
 * @typedef {import("./types.ts").InitializeCallback} InitializeCallback
 * @typedef {import("./types.ts").InitializeArgs} InitializeArgs
 */

/**
 * Event dispatched by Chrome DevTools for agents to discover third-party tools.
 *
 * This is a new, experimental, Chrome-only browser API not yet reflected in TypeScript's DOM lib.
 *
 * @typedef {Event & { respondWith: (toolGroup: Object) => void }} DevtoolsToolDiscoveryEvent
 */

/**
 * Initializes extension.
 *
 * @since n.e.x.t
 *
 * @type {InitializeCallback}
 * @param {InitializeArgs} args Args.
 */
export async function initialize( { getRootData, getElementData } ) {
	window.addEventListener( 'devtoolstooldiscovery', ( event ) => {
		const discoveryEvent = /** @type {DevtoolsToolDiscoveryEvent} */ (
			event
		);
		discoveryEvent.respondWith( {
			name: 'Optimization Detective',
			description:
				'Inspect the URL Metric being collected by Optimization Detective for the current page load.',
			tools: [
				{
					name: 'get_od_url_metric',
					description:
						'Returns the URL Metric (viewport dimensions and tracked element LCP/intersection data) collected so far for the current page load, or null if none has been collected yet.',
					inputSchema: { type: 'object', properties: {} },
					execute: async () => getRootData(),
				},
				{
					name: 'get_od_element_data',
					description:
						'Returns the tracked LCP/intersection data for a single element, identified by its XPath as found in its data-od-xpath attribute, or null if the element is not tracked.',
					inputSchema: {
						type: 'object',
						properties: {
							xpath: {
								type: 'string',
								description:
									'XPath of the element, as found in its data-od-xpath attribute.',
							},
						},
						required: [ 'xpath' ],
					},
					execute: async (
						/** @type {{ xpath: string }} */ { xpath }
					) => getElementData( xpath ),
				},
			],
		} );
	} );
}
