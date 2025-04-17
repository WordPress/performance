#!/usr/bin/env node

import { launch, Page } from 'puppeteer';
import { program } from 'commander';
import { execSync } from 'child_process';
import ora from 'ora';
import chalk from 'chalk';

program
	.name( 'od-prime' )
	.description( 'CLI tool to prime URL metrics for Optimization Detective' )
	.parse( process.argv );

/**
 * Instance of ora spinner for displaying status messages.
 *
 * @type {import('ora').Ora}
 */
const spinner = ora( 'Starting...' ).start();

/**
 * Instance of AbortController to handle aborting tasks.
 *
 * @type {AbortController}
 */
const abortController = new AbortController();

/**
 * Abort signal to be used to detect abort events.
 *
 * @type {AbortSignal}
 */
const signal = abortController.signal;

// Listen for the SIGINT signal (Ctrl+C) to abort the process.
process.on( 'SIGINT', async () => {
	spinner.start( 'Aborting...' );
	abortController.abort();
} );

/**
 * Checks the environment for required tools and plugins.
 *
 * @return {boolean} - True if all checks passed, false otherwise.
 */
function checkEnvironment() {
	const checks = [
		{
			name: 'WP CLI Availability',
			command: 'wp --info',
			errorMessage:
				'WP CLI is not installed. Please install WP CLI and try again.',
		},
		{
			name: 'WordPress Availability',
			command: 'wp core is-installed',
			errorMessage:
				'WordPress is not installed or not accessible in this context.',
		},
		{
			name: 'Optimization Detective WP_CLI command',
			command: 'wp help od get_url_batch',
			errorMessage:
				'Optimization Detective plugin is not installed or activated. Please install and activate the plugin.',
		},
	];

	for ( const check of checks ) {
		try {
			execSync( check.command, { stdio: 'ignore' } );
		} catch ( error ) {
			spinner.fail(
				chalk.red(
					`${ check.name } check failed: ${ check.errorMessage }`
				)
			);
			return false;
		}
	}
	return true;
}

/**
 * Fetches the next batch of URLs.
 *
 * @param {Object} lastCursor - The cursor to fetch the next batch.
 * @return {?Object} - The batch of URLs.
 */
function getBatch( lastCursor ) {
	try {
		const batchOutput = execSync(
			`wp od get_url_batch --format=json --cursor='${ JSON.stringify(
				lastCursor
			) }'`
		).toString();
		const parsedBatch = JSON.parse( batchOutput );

		if ( ! parsedBatch || parsedBatch.length === 0 ) {
			throw new Error( 'Invalid batch data received.' );
		}
		return JSON.parse( batchOutput )[ 0 ];
	} catch ( error ) {
		spinner.fail( 'Error occurred while fetching batch: ' + error.message );
		abortController.abort();
		return null;
	}
}

/**
 * Flattens the batch into individual tasks.
 *
 * @param {Object} batch - The batch to flatten.
 * @return {Array<{ url: string, width: number, height: number }>} The list of tasks.
 */
function flattenBatchToTasks( batch ) {
	const tasks = [];
	for ( const urlObj of batch.batch ) {
		for ( const breakpoint of urlObj.breakpoints ) {
			tasks.push( {
				url: urlObj.url,
				width: breakpoint.width,
				height: breakpoint.height,
			} );
		}
	}
	return tasks;
}

/**
 * Processes a single task using Puppeteer.
 *
 * @param {Page}                                           page              - The Puppeteer page to use.
 * @param {{ url: string, width: number, height: number }} task              - The task parameters.
 * @param {string}                                         verificationToken - The verification token.
 * @param {AbortSignal}                                    abortSignal       - The abort signal.
 * @return {Promise<void>}
 */
async function processTask( page, task, verificationToken, abortSignal ) {
	return new Promise( async ( resolve, reject ) => {
		/**
		 * Handles the abort event.
		 */
		function onAbort() {
			page.evaluate( () => {
				window.dispatchEvent(
					new CustomEvent( 'OD_PRIME_URL_METRICS_REQUEST_STATUS', {
						detail: {
							success: false,
							error: 'Task aborted.',
						},
					} )
				);
			} );
		}
		abortSignal.addEventListener( 'abort', onAbort );

		/**
		 * Cleans up the page and abort signal listeners.
		 *
		 * @return {Promise<void>} The promise that resolves to void.
		 */
		async function cleanup() {
			abortSignal.removeEventListener( 'abort', onAbort );
			await page.goto( 'about:blank', {
				waitUntil: 'load',
				timeout: 30000,
				signal: abortSignal,
			} );
		}

		try {
			await page.setViewport( {
				width: task.width,
				height: task.height,
			} );

			const url = new URL( task.url );
			url.hash = `odPrimeUrlMetricsVerificationToken=${ encodeURIComponent(
				verificationToken
			) }`;

			await page.goto( url.toString(), {
				waitUntil: 'load',
				timeout: 30000,
				signal: abortSignal,
			} );

			await page.evaluate( () => {
				return new Promise(
					( requestSuccessResolve, requestSuccessReject ) => {
						// Set timeout for 30 seconds.
						const timeoutId = setTimeout( () => {
							requestSuccessReject(
								new Error(
									'Timed out waiting for event "OD_PRIME_URL_METRICS_REQUEST_SUCCESS".'
								)
							);
						}, 30000 );

						/**
						 * Handles the message from the page.
						 *
						 * @param {CustomEvent} event - The message event.
						 */
						function handleMessage( event ) {
							if ( event.detail && event.detail.success ) {
								clearTimeout( timeoutId );
								requestSuccessResolve();
							} else {
								clearTimeout( timeoutId );
								requestSuccessReject(
									new Error(
										event.detail.error ||
											'URL Metric request failed'
									)
								);
							}
						}

						document.addEventListener(
							'OD_PRIME_URL_METRICS_REQUEST_STATUS',
							handleMessage,
							{ once: true }
						);
					}
				);
			} );

			await cleanup();
			resolve();
		} catch ( error ) {
			await cleanup();
			reject( error );
		}
	} );
}

/**
 * Init function to process all batches.
 * @return {Promise<void>}
 */
async function init() {
	const browser = await launch( { headless: true } );
	const browserPage = await browser.newPage();
	let isNextBatchAvailable = true;
	let cursor = {};
	let currentBatchNumber = 0;
	let verificationToken;

	// Process batches until no more are available.
	while ( isNextBatchAvailable ) {
		if ( signal.aborted ) {
			break;
		}
		spinner.text = 'Fetching next batch';
		const currentBatch = await getBatch( cursor );

		// If no URLs remain in the batch, finish processing.
		if (
			null === currentBatch ||
			! currentBatch.batch ||
			currentBatch.batch.length === 0
		) {
			isNextBatchAvailable = false;
			break;
		}
		verificationToken = currentBatch.verificationToken;
		currentBatchNumber++;

		spinner.text = `Batch ${ currentBatchNumber } fetched successfully.`;

		const currentTasks = flattenBatchToTasks( currentBatch );

		// Process each task sequentially.
		for ( let i = 0; i < currentTasks.length; i++ ) {
			if ( signal.aborted ) {
				break;
			}
			const task = currentTasks[ i ];

			spinner.text = `Processing task ${ chalk.green(
				i + 1 + '/' + currentTasks.length
			) } for ${ chalk.blue( task.url ) } at ${ chalk.blue(
				task.width + 'x' + task.height
			) }`;
			try {
				await processTask(
					browserPage,
					task,
					verificationToken,
					signal
				);
			} catch ( error ) {
				spinner.text = `Error processing task ${ i + 1 }. Error: ${
					error.message
				}`;
			}
		}
		cursor = currentBatch.cursor;
	}

	await browser.close();

	if ( signal.aborted ) {
		spinner.fail( chalk.red( 'Aborted.' ) );
	} else {
		spinner.succeed( chalk.green( 'All tasks completed.' ) );
	}
}

// Start the process.
if ( checkEnvironment() ) {
	init();
}
