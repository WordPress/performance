/**
 * Helper script for the Prime URL Metrics.
 */

( function () {
	// @ts-ignore
	const { i18n, apiFetch } = wp;
	const { __ } = i18n;

	const controlButton = document.getElementById(
		'od-prime-url-metrics-control-button'
	);
	const progressBar = document.getElementById(
		'od-prime-url-metrics-progress-bar'
	);

	const iframe = document.getElementById( 'od-prime-url-metrics-iframe' );

	let isInitialized = false;
	let isProcessing = false;
	let isNextBatchAvailable = true;

	/**
	 * Handles the prime URL metrics button click.
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
			while ( isProcessing && isNextBatchAvailable ) {
				const batch = await getBatch();
				// @ts-ignore
				progressBar.max += batch.length;
				if ( ! batch.length ) {
					isNextBatchAvailable = false;
					break;
				}
				await processBatch( batch );
			}
		}
	}

	/**
	 * Fetches the next batch of URLs.
	 */
	async function getBatch() {
		const response = await apiFetch( '' );
		return response;
	}

	async function processBatch( batch ) {
		for ( const url of batch ) {
			// @ts-ignore
			iframe.src = url;
			await new Promise( ( resolve ) => {
				iframe.onload = resolve;
			} );
			// @ts-ignore
			progressBar.value += 1;
		}
	}

	controlButton.addEventListener( 'click', handleControlButtonClick );
} )();
