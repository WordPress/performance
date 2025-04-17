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
	let abortController = new AbortController();
	const consoleLogPrefix = '[Optimization Detective Priming URL Metrics]';

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
	 * Logs messages to the console.
	 *
	 * @param {...*} message - The message(s) to log.
	 */
	function log( ...message ) {
		// eslint-disable-next-line no-console
		console.log( consoleLogPrefix, ...message );
	}

	/**
	 * Primes the URL metrics for all breakpoints.
	 *
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
				try {
					await processTask(
						currentTasks[ currentTaskIndex ],
						abortController.signal
					);
				} catch ( error ) {
					log( error );
					if ( 'Task Aborted' === error.message ) {
						throw error;
					}
				}
				currentTaskIndex++;
			}
			isProcessing = false;
		} catch ( error ) {
			isProcessing = false;
		}
	}

	/**
	 * Loads the iframe and waits for the message.
	 *
	 * @param {{url: string, width: number, height: number}} task   - The breakpoint to set for the iframe.
	 * @param {AbortSignal}                                  signal - The signal to abort the task.
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	function processTask( task, signal ) {
		return new Promise( ( resolve, reject ) => {
			/**
			 * Handles the message from the iframe.
			 *
			 * @param {MessageEvent} event - The message event.
			 * @return {Promise<void>} The promise that resolves to void.
			 */
			const handleMessage = async ( event ) => {
				if (
					event.data &&
					event.data.type &&
					'OD_PRIME_URL_METRICS_REQUEST_STATUS' === event.data.type
				) {
					if ( event.data.success ) {
						await cleanup();
						resolve();
					} else {
						await cleanup();
						reject(
							new Error(
								event.data.error || 'URL Metric request failed'
							)
						);
					}
				}
			};

			/**
			 * Handles the aborting of the task on abort signal.
			 *
			 * @return {Promise<void>} The promise that resolves to void.
			 */
			const abortHandler = async () => {
				await cleanup();
				reject( new Error( 'Task Aborted' ) );
			};

			/**
			 * Cleans up the event listeners and iframe.
			 *
			 * @return {Promise<void>} The promise that resolves to void.
			 */
			const cleanup = () => {
				return new Promise( ( cleanUpResolve ) => {
					signal.removeEventListener( 'abort', abortHandler );
					window.removeEventListener( 'message', handleMessage );
					clearTimeout( timeoutId );
					iframe.onerror = null;
					iframe.src = 'about:blank';
					iframe.addEventListener(
						'load',
						() => {
							cleanUpResolve();
						},
						{ once: true }
					);
				} );
			};

			const timeoutId = setTimeout( async () => {
				await cleanup();
				reject( new Error( 'Timeout waiting for message' ) );
			}, 30000 ); // 30-second timeout

			if ( signal.aborted ) {
				abortHandler();
				return;
			}

			signal.addEventListener( 'abort', abortHandler );
			window.addEventListener( 'message', handleMessage );

			iframe.onerror = async () => {
				await cleanup();
				reject( new Error( 'Iframe failed to load' ) );
			};

			const url = new URL( task.url );
			url.hash = `odPrimeUrlMetricsVerificationToken=${ encodeURIComponent(
				verificationToken
			) }`;

			// Load the iframe.
			iframe.src = url.toString();
			iframe.width = task.width.toString();
			iframe.height = task.height.toString();
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
			if ( ! abortController.signal.aborted ) {
				abortController.abort();
			}
		} else if ( 'visible' === document.visibilityState && isTabHidden ) {
			isTabHidden = false;
			if ( ! isProcessing ) {
				isProcessing = true;
				if ( abortController.signal.aborted ) {
					abortController = new AbortController();
				}
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
