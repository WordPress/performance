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
			iframe.style.display = 'block';
			while ( isProcessing && isNextBatchAvailable ) {
				const batch = await getBatch( cursor );
				cursor = batch.cursor;
				progressBar.max += batch.urls.length;
				if ( ! batch.urls.length ) {
					isNextBatchAvailable = false;
					break;
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
	 *   }
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
		for ( const url of urls ) {
			await new Promise( async ( urlResolve ) => {
				for ( const breakpoint of url.breakpoints ) {
					await new Promise( ( breakpointResolve ) => {
						iframe.src = url.url;
						iframe.width = breakpoint.width;
						iframe.height = breakpoint.height;
						iframe.onload = () => {
							breakpointResolve();
						};
					} );
				}
				urlResolve();
			} );
			progressBar.value += 1;
		}
	}

	controlButton.addEventListener( 'click', handleControlButtonClick );
} )();
