/**
 * Helper script for the Priming URL Metrics in block editor.
 */

/* global odPrimeURLMetricsClassicEditor */
( function ( odPrimeURLMetricsClassicEditor ) {
	// @ts-ignore
	if ( 'undefined' === typeof odPrimeURLMetricsClassicEditor ) {
		return;
	}
	const permalink = odPrimeURLMetricsClassicEditor.permalink;
	// @ts-ignore
	const { apiFetch } = wp;

	let isProcessing = false;
	let verificationToken = '';
	let breakpoints = [];
	let currentTasks = [];
	let currentTaskIndex = 0;
	let isTabHidden = false;
	let abortController = null;

	const iframe = document.createElement( 'iframe' );
	iframe.id = 'od-prime-url-metrics-iframe';
	iframe.style.position = 'fixed';
	iframe.style.top = '0';
	iframe.style.left = '0';
	iframe.style.transform = 'scale(0.05)';
	iframe.style.transformOrigin = '0 0';
	iframe.style.pointerEvents = 'none';
	iframe.style.opacity = '0.000001';
	iframe.style.zIndex = '-9999';
	document.body.appendChild( iframe );

	/**
	 * Primes the URL metrics for all breakpoints.
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	async function processTasks() {
		try {
			isProcessing = true;
			if ( 0 === breakpoints.length ) {
				breakpoints = await apiFetch( {
					path: '/optimization-detective/v1/prime-urls-breakpoints',
					method: 'GET',
				} );
			}

			verificationToken = await apiFetch( {
				path: '/optimization-detective/v1/prime-urls-verification-token',
				method: 'GET',
			} );

			currentTasks = breakpoints.map( ( breakpoint ) => ( {
				url: permalink,
				width: breakpoint.width,
				height: breakpoint.height,
			} ) );

			while ( isProcessing && currentTaskIndex < currentTasks.length ) {
				abortController = new AbortController();
				await processTask(
					currentTasks[ currentTaskIndex ],
					abortController.signal
				);
				currentTaskIndex++;
			}
			isProcessing = false;
		} catch ( error ) {
			isProcessing = false;
		}
	}

	/**
	 * Loads the iframe and waits for the message.
	 * @param {{url: string, width: number, height: number}} task   - The breakpoint to set for the iframe.
	 * @param {AbortSignal}                                  signal - The signal to abort the task.
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	function processTask( task, signal ) {
		return new Promise( ( resolve, reject ) => {
			const handleMessage = ( event ) => {
				if ( event.data === 'OD_PRIME_URL_METRICS_REQUEST_SUCCESS' ) {
					cleanup();
					resolve();
				}
			};

			const abortHandler = () => {
				cleanup();
				reject( new Error( 'Task Aborted' ) );
			};

			const cleanup = () => {
				window.removeEventListener( 'message', handleMessage );
				clearTimeout( timeoutId );
				iframe.onerror = null;
				iframe.src = 'about:blank';
			};

			const timeoutId = setTimeout( () => {
				cleanup();
				reject( new Error( 'Timeout waiting for message' ) );
			}, 30000 ); // 30-second timeout

			if ( signal.aborted ) {
				abortHandler();
				return;
			}

			signal.addEventListener( 'abort', abortHandler );
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
		} );
	}

	/**
	 * Primes the URL metrics for all breakpoints
	 * when the document is ready.
	 */
	document.addEventListener( 'DOMContentLoaded', () => {
		processTasks();
	} );

	/**
	 * Pause processing when the tab/window becomes hidden.
	 */
	document.addEventListener( 'visibilitychange', () => {
		if ( 'hidden' === document.visibilityState && isProcessing ) {
			isProcessing = false;
			isTabHidden = true;
			if ( abortController ) {
				abortController.abort();
				abortController = null;
			}
		} else if ( 'visible' === document.visibilityState && isTabHidden ) {
			isTabHidden = false;
			if ( ! isProcessing ) {
				isProcessing = true;
				processTasks();
			}
		}
	} );

	/**
	 * Prevent the user from leaving the page while processing.
	 */
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( isProcessing ) {
			event.preventDefault();
		}
	} );

	// @ts-ignore
} )( odPrimeURLMetricsClassicEditor );
