#!/usr/bin/env node

import { launch, Page } from 'puppeteer';
import { program } from 'commander';
import ora from 'ora';
import { execSync } from 'child_process';
import chalk from 'chalk';

program
	.name( 'od-prime' )
	.description( 'CLI tool to prime URL metrics for Optimization Detective' )
	.parse( process.argv );

const spinner = ora( 'Starting...' ).start();
let browser;
let browserPage;
const abortController = new AbortController();
const { signal } = abortController;

process.on( 'SIGINT', async () => {
	spinner.start( 'Aborting...' );
	abortController.abort();
} );

/**
 * Fetches the next batch of URLs.
 * @param {Object} lastCursor - The cursor to fetch the next batch.
 * @return {Object} - The batch of URLs.
 */
function getBatch( lastCursor ) {
	const batch = JSON.parse(
		execSync(
			`wp od get_url_batch --format=json --cursor='${ JSON.stringify(
				lastCursor
			) }'`
		).toString()
	);
	return batch[ 0 ];
}

/**
 * Flattens the batch into individual tasks.
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
		function onAbort() {
			reject( new Error( 'Task aborted.' ) );
		}
		abortSignal.addEventListener( 'abort', onAbort );

		try {
			await page.setViewport( {
				width: task.width,
				height: task.height,
			} );

			await page.evaluateOnNewDocument( ( token ) => {
				window.__odPrimeUrlMetricsVerificationToken = token;
			}, verificationToken );

			await page.goto( task.url, {
				waitUntil: 'load',
				timeout: 30000,
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

						document.addEventListener(
							'OD_PRIME_URL_METRICS_REQUEST_SUCCESS',
							async () => {
								clearTimeout( timeoutId );
								requestSuccessResolve();
							},
							{ once: true }
						);
					}
				);
			} );
		} catch ( error ) {
			reject( error );
		} finally {
			abortSignal.removeEventListener( 'abort', onAbort );
			resolve();
		}
	} );
}

/**
 * Init function to process all batches.
 * @return {Promise<void>}
 */
async function init() {
	browser = await launch( { headless: true } );
	browserPage = await browser.newPage();

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
		if ( ! currentBatch.batch || currentBatch.batch.length === 0 ) {
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

	if ( signal.aborted ) {
		spinner.fail( 'Aborted.' );
	} else {
		spinner.succeed( 'All batches processed.' );
	}
	await browser.close();
	process.exit( 0 );
}

// Start the process.
init();
