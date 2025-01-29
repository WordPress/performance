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

	let isInitialized = false;
	let isProcessing = false;
	let isNextBatchAvailable = true;
	let cursor = {};
	let isDebug = false;
	let verificationToken = '';

	/**
	 * Handles the prime URL metrics control button click.
	 */
	async function handleControlButtonClick() {
		if ( isProcessing ) {
			controlButton.textContent = __(
				'Resume',
				'optimization-detective'
			);
			isProcessing = false;
		} else {
			controlButton.textContent = __( 'Pause', 'optimization-detective' );
			isProcessing = true;
		}

		if ( ! isInitialized ) {
			isInitialized = true;
			progressBar.max = 0;
			while ( isProcessing && isNextBatchAvailable ) {
				const batch = await getBatch( cursor );
				if ( ! batch.urls.length ) {
					isNextBatchAvailable = false;
					break;
				}
				verificationToken = batch.verificationToken;
				cursor = batch.cursor;
				isDebug = batch.isDebug;

				// As the progress bar max value is set to 1 initially, we need to update it to the actual value.
				if ( 1 === progressBar.max ) {
					progressBar.max = batch.urls.length;
				} else {
					progressBar.max += batch.urls.length;
				}
				await processBatch( batch.urls );
			}
		}
	}

	/**
	 * Fetches the next batch of URLs.
	 * @param {Object} lastCursor - The cursor to fetch the next batch.
	 * @return {Promise<{
	 *   urls: Array<Array<{
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
	 * Processes the batch of URLs.
	 * @param {Array} urls - The URLs to process
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	async function processBatch( urls ) {
		if ( isDebug ) {
			iframe.style.position = 'unset';
			iframe.style.transform = 'scale(0.5) translate(-50%, -50%)';
			iframe.style.visibility = 'visible';
		}

		for ( const url of urls ) {
			if ( ! isProcessing ) {
				break;
			}

			for ( const breakpoint of url.breakpoints ) {
				if ( ! isProcessing ) {
					break;
				}

				try {
					iframe.dataset.odPrimeUrlMetricsVerificationToken =
						verificationToken;
					// Load iframe and wait for message
					await loadIframeAndWaitForMessage( url.url, breakpoint );
				} catch ( error ) {
					// TODO: Decide whether to retry or skip the URL.
				}
			}
			progressBar.value += 1;
		}
	}

	/**
	 * Loads the iframe and waits for the message.
	 * @param {string}                          url        - The URL to load in the iframe.
	 * @param {{width: number, height: number}} breakpoint - The breakpoint to set for the iframe.
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	function loadIframeAndWaitForMessage( url, breakpoint ) {
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
			iframe.src = url;
			iframe.width = breakpoint.width.toString();
			iframe.height = breakpoint.height.toString();
		} );
	}

	controlButton.addEventListener( 'click', handleControlButtonClick );
} )();
