#!/usr/bin/env node

import { launch, Page } from 'puppeteer';
import { program } from 'commander';
import ora from 'ora';
import { execSync } from 'child_process';

program
	.name( 'od-prime' )
	.description( 'CLI tool to prime URL metrics for Optimization Detective' )
	.parse( process.argv );

const spinner = ora( 'Starting...' ).start();
let browser;
let browserPage;

process.on( 'SIGINT', async () => {
	if ( browser ) {
		await browser.close();
	}
	spinner.fail( 'Process aborted.' );
	process.exit( 0 );
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
 * @return {Promise<void>}
 */
async function processTask( page, task, verificationToken ) {
	// Before each navigation, reset the success flag.
	await page.evaluate( () => {
		window.success = false;
	} );

	const urlToLoad = new URL( task.url );
	urlToLoad.searchParams.append( 'od-verification-token', verificationToken );

	await page.setViewport( { width: task.width, height: task.height } );

	await page.goto( urlToLoad.toString(), {
		waitUntil: 'load',
		timeout: 30000,
	} );

	// Wait for the success flag to become true (with a 30-second timeout).
	await page.waitForFunction( 'window.success === true', { timeout: 30000 } );
}

async function main() {
	browser = await launch();
	browserPage = await browser.newPage();

	await browserPage.evaluateOnNewDocument( () => {
		window.success = false;
		window.addEventListener( 'message', ( event ) => {
			if ( event.data === 'OD_PRIME_URL_METRICS_REQUEST_SUCCESS' ) {
				window.success = true;
			}
		} );
	} );

	let isNextBatchAvailable = true;
	let cursor = {};
	let currentBatchNumber = 0;
	let verificationToken;

	// Process batches until no more are available.
	while ( isNextBatchAvailable ) {
		spinner.start( 'Fetching next batch...' );
		const currentBatch = await getBatch( cursor );
		// If no URLs remain in the batch, finish processing.
		if ( ! currentBatch.batch || currentBatch.batch.length === 0 ) {
			isNextBatchAvailable = false;
			break;
		}
		verificationToken = currentBatch.verificationToken;
		currentBatchNumber++;

		spinner.succeed(
			`Batch ${ currentBatchNumber } fetched successfully.`
		);

		const currentTasks = flattenBatchToTasks( currentBatch );
		spinner.succeed(
			`Batch ${ currentBatchNumber } processed successfully.`
		);

		// Process each task sequentially.
		for ( let i = 0; i < currentTasks.length; i++ ) {
			const task = currentTasks[ i ];
			spinner.start(
				`Processing task ${ i + 1 }/${ currentTasks.length }`
			);
			try {
				await processTask( browserPage, task, verificationToken );
				spinner.succeed(
					`Task ${ i + 1 }/${
						currentTasks.length
					} completed successfully.`
				);
			} catch ( error ) {
				spinner.fail(
					`Task ${ i + 1 }/${ currentTasks.length } failed.`
				);
			}
		}
		cursor = currentBatch.cursor;
	}

	spinner.succeed( 'All batches processed.' );
	await browser.close();
}
main();
