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
 * @see {init}
 * @see {getBatch}
 * @see {checkEnvironment}
 * @type {import('ora').Ora}
 */
const spinner = ora( 'Starting...' ).start();

/**
 * Instance of AbortController to handle aborting tasks.
 *
 * @see {getBatch}
 * @type {AbortController}
 */
const abortController = new AbortController();

/**
 * Abort signal to be used to detect abort events.
 *
 * @see {processTask}
 * @type {AbortSignal}
 */
const signal = abortController.signal;

/**
 * Gets an error message from an unknown error value.
 *
 * @param {unknown} error Error value.
 * @return {string} Error message.
 */
function getErrorMessage( error ) {
	return error instanceof Error ? error.message : String( error );
}

// Listen for the SIGINT signal (Ctrl+C) to abort the process.
process.on( 'SIGINT', () => {
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
			command: 'wp help od priming-mode get-url-batch',
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
 * @param {?import("./types.ts").URLBatchCursor} lastCursor - The cursor to fetch the next batch.
 * @return {?import("./types.ts").URLBatchResponse} - The batch of URLs.
 */
function getBatch( lastCursor ) {
	try {
		let command = 'wp od priming-mode get-url-batch --format=json';

		if ( lastCursor ) {
			command += ` --provider-index=${ lastCursor.provider_index || 0 }`;
			command += ` --subtype-index=${ lastCursor.subtype_index || 0 }`;
			command += ` --page-number=${ lastCursor.page_number || 0 }`;
			command += ` --offset-within-page=${
				lastCursor.offset_within_page || 0
			}`;
			command += ` --batch-size=${ lastCursor.batch_size || 10 }`;
		}

		const batchOutput = execSync( command ).toString();
		const parsedBatch = JSON.parse( batchOutput );

		if ( ! parsedBatch || parsedBatch.length === 0 ) {
			throw new Error( 'Invalid batch data received.' );
		}
		return parsedBatch[ 0 ];
	} catch ( error ) {
		spinner.fail(
			'Error occurred while fetching batch: ' + getErrorMessage( error )
		);
		abortController.abort();
		return null;
	}
}

/**
 * Fetches the verification token.
 *
 * @return {?string} - The verification token or null if not available.
 */
function getVerificationToken() {
	try {
		const verificationToken = execSync(
			'wp od priming-mode get-verification-token'
		)
			.toString()
			.trim();
		if ( '' === verificationToken ) {
			throw new Error( 'Invalid verification token received.' );
		}
		return verificationToken;
	} catch ( error ) {
		spinner.fail(
			'Error occurred while fetching verification token: ' +
				getErrorMessage( error )
		);
		abortController.abort();
		return null;
	}
}

/**
 * Flattens the url groups to tasks.
 *
 * @param {import("./types.ts").URLGroup[]} urlGroups - The url groups to flatten.
 * @return {import("./types.ts").URLPrimingTask[]} - The flattened tasks.
 */
function flattenBatchToTasks( urlGroups ) {
	return urlGroups.flatMap( ( urlGroup ) =>
		urlGroup.viewports.map( ( viewport ) => ( {
			url: urlGroup.url,
			width: viewport.width,
			height: viewport.height,
		} ) )
	);
}

/**
 * Processes a single task using Puppeteer.
 *
 * @param {Page}                                page              - The Puppeteer page to use.
 * @param {import("./types.ts").URLPrimingTask} task              - The task parameters.
 * @param {string}                              verificationToken - The verification token.
 * @param {AbortSignal}                         abortSignal       - The abort signal.
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
			url.hash = `odPrimeModeVerificationToken=${ encodeURIComponent(
				verificationToken
			) }&odPrimeModeSource=priming-cli`;

			await page.goto( url.toString(), {
				waitUntil: 'load',
				timeout: 30000,
				signal: abortSignal,
			} );

			await page.evaluate( () => {
				return /** @type {Promise<void>} */ (
					new Promise(
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
							 * @param {Event} event - The message event.
							 */
							function handleMessage( event ) {
								const customEvent =
									/** @type {CustomEvent<{success?: boolean, error?: string}>} */ (
										event
									);
								if (
									customEvent.detail &&
									customEvent.detail.success
								) {
									clearTimeout( timeoutId );
									requestSuccessResolve();
								} else {
									clearTimeout( timeoutId );
									requestSuccessReject(
										new Error(
											customEvent.detail.error ||
												'URL Metric request failed'
										)
									);
								}
							}

							document.addEventListener(
								'OD_PRIME_URL_METRICS_REQUEST_STATUS',
								/** @type {( event: Event ) => void} */ (
									handleMessage
								),
								{ once: true }
							);
						}
					)
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
	/**
	 * Puppeteer browser instance used for headless page navigation and rendering.
	 *
	 * @type {import('puppeteer').Browser}
	 */
	const browser = await launch( { headless: true } );

	/**
	 * Main Puppeteer page object used to navigate to URLs.
	 *
	 * @type {import('puppeteer').Page}
	 */
	const browserPage = await browser.newPage();

	/**
	 * Flag indicating whether more URL batches are available for processing.
	 *
	 * @type {boolean}
	 */
	let isNextBatchAvailable = true;

	/**
	 * Cursor object to track position in pagination when fetching URL batches.
	 *
	 * @type {?import("./types.ts").URLBatchCursor}
	 */
	let cursor = null;

	/**
	 * Counter tracking the number of URL batches processed so far.
	 *
	 * @type {number}
	 */
	let currentBatchNumber = 0;

	/**
	 * Token used to verify REST API requests server side when in priming mode.
	 *
	 * @type {?string}
	 */
	let verificationToken = null;

	// Process batches until no more are available.
	while ( isNextBatchAvailable ) {
		if ( signal.aborted ) {
			break;
		}
		spinner.start( 'Fetching next batch' );
		const currentBatch = getBatch( cursor );

		// If no URLs remain in the batch, finish processing.
		if (
			null === currentBatch ||
			! currentBatch.urlGroups ||
			currentBatch.urlGroups.length === 0
		) {
			isNextBatchAvailable = false;
			break;
		}
		verificationToken = currentBatch.verificationToken;
		currentBatchNumber++;

		spinner.text = `Batch ${ currentBatchNumber } fetched successfully.`;

		const currentTasks = flattenBatchToTasks( currentBatch.urlGroups );

		// Process each task sequentially.
		for ( let i = 0; i < currentTasks.length; i++ ) {
			if ( signal.aborted ) {
				break;
			}
			const task = currentTasks[ i ];

			spinner.start(
				`Processing batch ${ chalk.green(
					currentBatchNumber
				) } task ${ chalk.green(
					i + 1 + '/' + currentTasks.length
				) } for ${ chalk.blue( task.url ) } at ${ chalk.blue(
					task.width + 'x' + task.height
				) }`
			);
			try {
				await processTask(
					browserPage,
					task,
					verificationToken,
					signal
				);
			} catch ( error ) {
				const errorMessage = getErrorMessage( error );
				// Refresh verification token if expired.
				if (
					errorMessage.includes(
						'priming_mode_verification_token_expired'
					)
				) {
					verificationToken = getVerificationToken();
					if ( ! verificationToken ) {
						break;
					}
					i--;
				} else {
					// Log the error and continue processing the next task.
					spinner.fail(
						`Error processing task ${
							i + 1
						}. Error: ${ errorMessage }`
					);
				}
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
