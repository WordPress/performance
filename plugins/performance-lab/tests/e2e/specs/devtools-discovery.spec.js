/* jshint node: true */
// ^ This Playwright spec runs in Node (CommonJS), not the browser like the
// sibling plugin scripts that the `plugins/.jshintrc` browser config targets.

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Performance Lab Chrome DevTools third-party tools integration', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activateTheme( 'twentytwentyfour' );
		await requestUtils.activatePlugin( 'performance-lab' );
	} );

	test( 'registers a tool group for administrators', async ( { page } ) => {
		await page.goto( '/' );

		// The data consumed by the tools is printed in the footer for authorized users.
		await expect( page.locator( '#perflab-devtools-data' ) ).toHaveCount(
			1
		);

		/*
		 * Simulate the discovery event which Chrome DevTools for agents dispatches, capturing
		 * the tool group passed to respondWith() and invoking each exposed tool.
		 */
		const result = await page.evaluate( async () => {
			/**
			 * @typedef {{ name: string, execute: ( input: Object ) => Promise<*> }} Tool
			 * @typedef {{ name: string, tools: Tool[] }} ToolGroup
			 */
			const event = new Event( 'devtoolstooldiscovery' );
			/** @type {ToolGroup|null} */
			let toolGroup = null;
			// @ts-ignore -- respondWith is expected on the event by the listener.
			event.respondWith = ( /** @type {ToolGroup} */ group ) => {
				toolGroup = group;
			};
			window.dispatchEvent( event );
			if ( ! toolGroup ) {
				return null;
			}
			const respondedGroup = /** @type {ToolGroup} */ ( toolGroup );
			const getTool = ( /** @type {string} */ name ) => {
				const tool = respondedGroup.tools.find(
					( candidate ) => candidate.name === name
				);
				if ( ! tool ) {
					throw new Error( `Tool not found: ${ name }` );
				}
				return tool;
			};
			return {
				name: respondedGroup.name,
				toolNames: respondedGroup.tools.map( ( tool ) => tool.name ),
				environment: await getTool( 'get_environment_info' ).execute(
					{}
				),
				serverTiming: await getTool(
					'get_server_timing_metrics'
				).execute( {} ),
				dbQueries: await getTool( 'get_database_queries' ).execute(
					{}
				),
			};
		} );

		if ( ! result ) {
			throw new Error(
				'The devtoolstooldiscovery listener did not respond with a tool group.'
			);
		}
		expect( result.name ).toBe( 'WordPress Performance Lab' );
		expect( result.toolNames ).toEqual( [
			'get_server_timing_metrics',
			'get_environment_info',
			'get_database_queries',
		] );
		expect( result.environment.wpVersion ).toEqual( expect.any( String ) );
		expect( result.environment.phpVersion ).toEqual( expect.any( String ) );
		expect( Array.isArray( result.environment.activePlugins ) ).toBe(
			true
		);
		expect( Array.isArray( result.serverTiming ) ).toBe( true );
		expect( result.dbQueries ).toHaveProperty( 'available' );
	} );

	test( 'does not expose data or tools to logged-out visitors', async ( {
		browser,
	} ) => {
		/*
		 * An empty storage state is passed since browser.newContext() otherwise inherits the
		 * authenticated admin storage state from the Playwright config.
		 */
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		const loggedOutPage = await context.newPage();
		/*
		 * A query string distinct from the URL loaded in the previous test is used so that the
		 * browser's shared HTTP cache cannot serve the HTML rendered for the authenticated user.
		 */
		await loggedOutPage.goto( '/?logged-out' );

		await expect(
			loggedOutPage.locator( '#perflab-devtools-data' )
		).toHaveCount( 0 );

		const responded = await loggedOutPage.evaluate( () => {
			const event = new Event( 'devtoolstooldiscovery' );
			let didRespond = false;
			// @ts-ignore -- respondWith is expected on the event by the listener.
			event.respondWith = () => {
				didRespond = true;
			};
			window.dispatchEvent( event );
			return didRespond;
		} );
		expect( responded ).toBe( false );

		await context.close();
	} );
} );
