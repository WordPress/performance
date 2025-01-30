/**
 * Helper script for the Prime URL Metrics.
 */

( function () {
	// @ts-ignore
	const { i18n, apiFetch } = wp;
	const { __ } = i18n;

	/** @type {HTMLButtonElement} */
	const controlButton = document.querySelector(
		'button#od-prime-url-metrics-control-button'
	);

	/** @type {HTMLProgressElement} */
	const progressBar = document.querySelector(
		'progress#od-prime-url-metrics-progress'
	);

	/** @type {HTMLIFrameElement} */
	const iframe = document.querySelector(
		'iframe#od-prime-url-metrics-iframe'
	);

	let isProcessing = false;
	let isNextBatchAvailable = true;
	let cursor = {};
	let isDebug = false;
	let verificationToken = '';
	let currentBatch = null;
	let currentTasks = [];
	let currentTaskIndex = 0;

	/**
	 * Handles the prime URL metrics control button click.
	 */
	async function handleControlButtonClick() {
		if ( isProcessing ) {
			// Pause processing
			isProcessing = false;
			controlButton.textContent = __(
				'Resume',
				'optimization-detective'
			);
		} else {
			// Start/resume processing
			isProcessing = true;
			controlButton.textContent = __( 'Pause', 'optimization-detective' );

			try {
				while ( isProcessing ) {
					if ( ! currentBatch ) {
						currentBatch = await getBatch( cursor );
						if ( ! currentBatch.batch.length ) {
							isNextBatchAvailable = false;
							break;
						}

						// Initialize batch state
						verificationToken = currentBatch.verificationToken;
						isDebug = currentBatch.isDebug;
						currentTasks = flattenBatchToTasks( currentBatch );
						currentTaskIndex = 0;
						progressBar.max = currentTasks.length;
						progressBar.value = 0;
					}
					// Process tasks in current batch
					while (
						isProcessing &&
						currentTaskIndex < currentTasks.length
					) {
						await processTask( currentTasks[ currentTaskIndex ] );
						currentTaskIndex++;
						progressBar.value = currentTaskIndex;
					}

					if ( currentTaskIndex >= currentTasks.length ) {
						// Complete current batch
						cursor = currentBatch.cursor;
						currentBatch = null;
						currentTasks = [];
						currentTaskIndex = 0;
					}
				}
			} catch ( error ) {
				// TODO: Decide whether to skip the current task or stop processing.
				isProcessing = false;
				controlButton.textContent = __(
					'Click to retry',
					'optimization-detective'
				);
			} finally {
				if ( ! isNextBatchAvailable ) {
					isProcessing = false;
					controlButton.textContent = __(
						'Finished',
						'optimization-detective'
					);
					controlButton.disabled = true;
				}
			}
		}
	}

	/**
	 * Flattens the batch to tasks.
	 * @param {Object} batch - The batch to flatten.
	 * @return {Array<{
	 *   url: string,
	 *   width: number,
	 *   height: number
	 * }>} - The flattened tasks.
	 */
	function flattenBatchToTasks( batch ) {
		const tasks = [];
		for ( const url of batch.batch ) {
			for ( const breakpoint of url.breakpoints ) {
				tasks.push( {
					url: url.url,
					width: breakpoint.width,
					height: breakpoint.height,
				} );
			}
		}
		return tasks;
	}

	/**
	 * Fetches the next batch of URLs.
	 * @param {Object} lastCursor - The cursor to fetch the next batch.
	 * @return {Promise<{
	 *   batch: Array<Array<{
	 *     url: string,
	 *     breakpoints: Array<{
	 *       width: number,
	 *       height: number
	 *     }>
	 *   }>>,
	 *   cursor: {
	 *     provider_index: number,
	 *     subtype_index: number,
	 *     page_number: number,
	 *     offset_within_page: number,
	 *     batch_size: number
	 *   },
	 *   verificationToken: string,
	 *   isDebug: boolean
	 * }>} - The promise that resolves to the batch of URLs.
	 */
	async function getBatch( lastCursor ) {
		const response = await apiFetch( {
			path: '/optimization-detective/v1/prime-urls',
			method: 'POST',
			data: { cursor: lastCursor },
		} );
		return response;
	}

	/**
	 * Loads the iframe and waits for the message.
	 * @param {{url: string, width: number, height: number}} task - The breakpoint to set for the iframe.
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	function processTask( task ) {
		return new Promise( ( resolve, reject ) => {
			const handleMessage = ( event ) => {
				if ( event.data === 'OD_PRIME_URL_METRICS_REQUEST_SUCCESS' ) {
					cleanup();
					resolve();
				} else if (
					event.data === 'OD_PRIME_URL_METRICS_REQUEST_FAILURE'
				) {
					cleanup();
					reject( new Error( 'Failed to send metrics' ) );
				}
			};

			const cleanup = () => {
				window.removeEventListener( 'message', handleMessage );
				clearTimeout( timeoutId );
				iframe.onerror = null;
			};

			const timeoutId = setTimeout( () => {
				cleanup();
				reject( new Error( 'Timeout waiting for message' ) );
			}, 30000 ); // 30-second timeout

			window.addEventListener( 'message', handleMessage );

			iframe.onerror = () => {
				cleanup();
				reject( new Error( 'Iframe failed to load' ) );
			};

			// Load the iframe
			iframe.src = task.url;
			iframe.width = task.width.toString();
			iframe.height = task.height.toString();
			iframe.dataset.odPrimeUrlMetricsVerificationToken =
				verificationToken;

			if ( isDebug ) {
				iframe.style.position = 'unset';
				iframe.style.transform = 'scale(0.5) translate(-50%, -50%)';
				iframe.style.visibility = 'visible';
			}
		} );
	}

	controlButton.addEventListener( 'click', handleControlButtonClick );
} )();
