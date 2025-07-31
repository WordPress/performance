/**
 * Helper script for the Priming URL Metrics in classic editor.
 */

/* global odPrimeURLMetricsClassicEditor */
( function ( odPrimeURLMetricsClassicEditor ) {
	// @ts-ignore
	if ( 'undefined' === typeof odPrimeURLMetricsClassicEditor ) {
		return;
	}

	/**
	 * Permalink URL for the current post in classic editor.
	 *
	 * @type {string}
	 */
	const permalink = odPrimeURLMetricsClassicEditor.permalink;

	// @ts-ignore
	const { apiFetch } = wp;

	/**
	 * Flag indicating whether URL priming is currently in progress.
	 *
	 * @type {boolean}
	 */
	let isProcessing = false;

	/**
	 * Token used for verifying REST API requests server side.
	 *
	 * @type {string}
	 */
	let verificationToken = '';

	/**
	 * Array of viewport breakpoint objects defining dimensions.
	 *
	 * @type {import("./types.ts").ViewportBreakpoint[]}
	 */
	let breakpoints = [];

	/**
	 * Queue of URL priming tasks generated from breakpoints.
	 *
	 * @type {import("./types.ts").URLPrimingTask[]}
	 */
	let currentTasks = [];

	/**
	 * Index of the current task within currentTasks being processed.
	 *
	 * @type {number}
	 */
	let currentTaskIndex = 0;

	/**
	 * Flag indicating whether the document tab/window is hidden.
	 *
	 * @type {boolean}
	 */
	let isTabHidden = false;

	/**
	 * AbortController instance to support aborting ongoing task.
	 *
	 * @type {AbortController}
	 */
	let abortController = new AbortController();

	/**
	 * Prefix which is prepended to messages logged to the console while in priming mode.
	 *
	 * @type {string}
	 */
	const consoleLogPrefix = '[Optimization Detective Priming Mode]';

	/**
	 * Button element for publishing, allowing page leave after click.
	 *
	 * @type {HTMLInputElement}
	 */
	const updateButton = document.querySelector( 'input#publish' );

	/**
	 * Flag that indicates if navigation away from page is allowed.
	 *
	 * @type {boolean}
	 */
	let allowLeavingPage = false;

	/**
	 * Hidden iframe element used to load pages for metric priming.
	 *
	 * @type {HTMLIFrameElement}
	 */
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
					if ( abortController.signal.aborted ) {
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
	 * @param {import("./types.ts").URLPrimingTask} task   - The breakpoint to set for the iframe.
	 * @param {AbortSignal}                         signal - The signal to abort the task.
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
			async function handleMessage( event ) {
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
			}

			/**
			 * Handles the aborting of the task on abort signal.
			 *
			 * @return {Promise<void>} The promise that resolves to void.
			 */
			async function abortHandler() {
				await cleanup();
				reject( new Error( 'Task Aborted' ) );
			}

			/**
			 * Cleans up the event listeners and iframe.
			 *
			 * @return {Promise<void>} The promise that resolves to void.
			 */
			function cleanup() {
				return new Promise( ( cleanUpResolve ) => {
					signal.removeEventListener( 'abort', abortHandler );
					window.removeEventListener( 'message', handleMessage );
					clearTimeout( timeoutId );
					iframe.onerror = null;
					iframe.src = 'about:blank';
					iframe.addEventListener( 'load', () => cleanUpResolve(), {
						once: true,
					} );
				} );
			}

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
			url.hash = `odPrimeModeVerificationToken=${ encodeURIComponent(
				verificationToken
			) }&odPrimeModeSource=admin-dashboard`;

			// Load the iframe.
			iframe.src = url.toString();
			iframe.width = task.width.toString();
			iframe.height = task.height.toString();
		} );
	}

	/**
	 * Handles visibility change events to pause/resume processing when tab/window visibility changes.
	 */
	function handleVisibilityChange() {
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
	}

	/**
	 * Handler for the beforeunload event to prevent accidental page navigation.
	 *
	 * @param {BeforeUnloadEvent} event - The beforeunload event
	 */
	function handleBeforeUnload( event ) {
		if ( isProcessing && ! allowLeavingPage ) {
			event.preventDefault();
		}
	}

	// Attach event listeners.

	/**
	 * Primes the URL metrics for all breakpoints
	 * when the document is ready.
	 */
	document.addEventListener( 'DOMContentLoaded', processTasks );
	document.addEventListener( 'visibilitychange', handleVisibilityChange );
	window.addEventListener( 'beforeunload', handleBeforeUnload );
	updateButton.addEventListener( 'click', () => {
		allowLeavingPage = true;
	} );

	// @ts-ignore
} )( odPrimeURLMetricsClassicEditor );
