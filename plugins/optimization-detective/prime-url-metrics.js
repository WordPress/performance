/**
 * Helper script for Priming URL Metrics through WordPress admin dashboard.
 */
( function () {
	// @ts-ignore
	const { i18n, apiFetch } = wp;
	const { __ } = i18n;

	/**
	 * Button element that toggles processing state.
	 *
	 * @type {HTMLButtonElement}
	 */
	const controlButton = document.querySelector(
		'button#od-prime-url-metrics-control-button'
	);

	/**
	 * Progress bar element displaying current task completion progress.
	 *
	 * @type {HTMLProgressElement}
	 */
	const progressBar = document.querySelector(
		'progress#od-prime-url-metrics-progress'
	);

	/**
	 * Iframe used to load pages for priming URL metrics.
	 *
	 * @type {HTMLIFrameElement}
	 */
	const iframe = document.querySelector(
		'iframe#od-prime-url-metrics-iframe'
	);

	/**
	 * Container that holds the iframe.
	 *
	 * @type {HTMLDivElement}
	 */
	const iframeContainer = document.querySelector(
		'div#od-prime-url-metrics-iframe-container'
	);

	/**
	 * Element that displays the current batch number being processed.
	 *
	 * @type {HTMLSpanElement}
	 */
	const currentBatchElement = document.querySelector(
		'span#od-prime-url-metrics-current-batch'
	);

	/**
	 *  Element that displays the current task number being processed.
	 *
	 * @type {HTMLSpanElement}
	 */
	const currentTaskElement = document.querySelector(
		'span#od-prime-url-metrics-current-task'
	);

	/**
	 * Element that displays the total number of tasks in the current batch.
	 *
	 * @type {HTMLSpanElement}
	 */
	const totalTasksInBatchElement = document.querySelector(
		'span#od-prime-url-metrics-total-tasks-in-batch'
	);

	// Ensure all required elements are present.
	if (
		! controlButton ||
		! progressBar ||
		! iframe ||
		! iframeContainer ||
		! currentBatchElement ||
		! currentTaskElement ||
		! totalTasksInBatchElement
	) {
		return;
	}

	/**
	 * Flag indicating whether priming is currently in progress.
	 *
	 * @type {boolean}
	 */
	let isProcessing = false;

	/**
	 * Indicates whether more batches are available for processing.
	 *
	 * @type {boolean}
	 */
	let isNextBatchAvailable = true;

	/**
	 * Pagination cursor for retrieving the next batch of URLs.
	 *
	 * @type {?import("./types.ts").URLBatchCursor}
	 */
	let cursor = null;

	/**
	 * Flag indicating if debug mode is enabled.
	 *
	 * @type {boolean}
	 */
	let isDebug = false;

	/**
	 * Token used for verifying REST API requests server side.
	 *
	 * @type {string}
	 */
	let verificationToken = '';

	/**
	 * Currently active batch of data from the REST API.
	 *
	 * @type {?import("./types.ts").URLBatchResponse}
	 */
	let currentBatch = null;

	/**
	 * Array of URL priming tasks extracted from the current batch.
	 *
	 * @type {import("./types.ts").URLPrimingTask[]}
	 */
	let currentTasks = [];

	/**
	 * Index of the currently executing task in the batch.
	 *
	 * @type {number}
	 */
	let currentTaskIndex = 0;

	/**
	 * Running count of how many batches have been processed.
	 *
	 * @type {number}
	 */
	let currentBatchNumber = 0;

	/**
	 * Flag indicating whether the tab/window is hidden.
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
	 * ResizeObserver instance that adjusts iframe scale within container.
	 *
	 * @type {ResizeObserver}
	 */
	const iframeObserver = new ResizeObserver( fitIframe );
	iframeObserver.observe( iframe );

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
	 * Toggles the processing state of the priming task.
	 */
	function toggleProcessing() {
		if ( isProcessing ) {
			// Pause processing.
			isProcessing = false;
			controlButton.textContent = __(
				'Resume',
				'optimization-detective'
			);
			controlButton.classList.remove( 'updating-message' );
			if ( ! abortController.signal.aborted ) {
				abortController.abort();
			}
		} else {
			// Start/resume processing.
			isProcessing = true;
			controlButton.textContent = __( 'Pause', 'optimization-detective' );
			controlButton.classList.add( 'updating-message' );
			if ( abortController.signal.aborted ) {
				abortController = new AbortController();
			}
			processBatches();
		}
	}

	/**
	 * Processes batches of URL priming tasks.
	 */
	async function processBatches() {
		try {
			while ( isProcessing ) {
				if ( ! currentBatch ) {
					await prepareNextBatch();
					if ( ! isNextBatchAvailable ) {
						break;
					}
				}

				await processCurrentBatch();

				// Reset batch state if all tasks in the batch are processed.
				if ( currentTaskIndex >= currentTasks.length ) {
					cursor = currentBatch.cursor;
					currentBatch = null;
					currentTasks = [];
					currentTaskIndex = 0;
					totalTasksInBatchElement.textContent = '0';
					currentTaskElement.textContent = '0';
				}
			}
		} catch ( error ) {
			if ( ! isTabHidden && ! abortController.signal.aborted ) {
				isProcessing = false;
				controlButton.textContent = __(
					'Click to retry',
					'optimization-detective'
				);
			}
		} finally {
			if ( ! isNextBatchAvailable ) {
				isProcessing = false;
				controlButton.textContent = __(
					'Finished',
					'optimization-detective'
				);
				controlButton.disabled = true;
				controlButton.classList.remove( 'updating-message' );
				iframe.src = 'about:blank';
				iframe.width = '0';
				iframe.height = '0';
				currentBatchElement.textContent = '0';
			}
		}
	}

	/**
	 * Prepares the next batch for processing.
	 */
	async function prepareNextBatch() {
		controlButton.textContent = __(
			'Getting next batch…',
			'optimization-detective'
		);
		currentBatch = await getBatch( cursor );

		if ( ! currentBatch.urlGroups.length ) {
			isNextBatchAvailable = false;
			return;
		}

		currentBatchNumber++;
		currentBatchElement.textContent = currentBatchNumber.toString();

		// Initialize batch state.
		verificationToken = currentBatch.verificationToken;
		isDebug = currentBatch.isDebug;
		currentTasks = flattenBatchToTasks( currentBatch.urlGroups );
		currentTaskIndex = 0;

		// Update UI for new batch.
		progressBar.max = currentTasks.length;
		progressBar.value = currentTaskIndex + 1;
		totalTasksInBatchElement.textContent = currentTasks.length.toString();
		currentTaskElement.textContent = ( currentTaskIndex + 1 ).toString();
		controlButton.textContent = __( 'Pause', 'optimization-detective' );
	}

	/**
	 * Processes tasks in the current batch.
	 */
	async function processCurrentBatch() {
		while ( isProcessing && currentTaskIndex < currentTasks.length ) {
			try {
				await processTask(
					currentTasks[ currentTaskIndex ],
					abortController.signal
				);
			} catch ( error ) {
				log( error.message );
				if ( abortController.signal.aborted ) {
					throw error;
				}
			}
			currentTaskIndex++;
			progressBar.value = currentTaskIndex + 1;
			currentTaskElement.textContent = (
				currentTaskIndex + 1
			).toString();
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
			urlGroup.breakpoints.map( ( breakpoint ) => ( {
				url: urlGroup.url,
				width: breakpoint.width,
				height: breakpoint.height,
			} ) )
		);
	}

	/**
	 * Fetches the next batch of URLs for metric priming.
	 *
	 * @param {?import("./types.ts").URLBatchCursor} lastCursor - The pagination cursor from the last batch or null for the first batch.
	 * @return {Promise<import("./types.ts").URLBatchResponse>} - Resolves with the next batch of URLs and metadata.
	 */

	async function getBatch( lastCursor ) {
		return await apiFetch( {
			path: '/optimization-detective/v1/prime-urls',
			method: 'POST',
			data: { cursor: lastCursor },
		} );
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
	 * Fits the iframe to the container.
	 */
	function fitIframe() {
		if ( ! isDebug ) {
			return;
		}
		const containerWidth = iframeContainer.clientWidth;
		if ( containerWidth <= 0 ) {
			return;
		}

		const nativeWidth = parseInt( iframe.width, 10 ) || 1;
		const scale = containerWidth / nativeWidth;

		iframe.style.position = 'unset';
		iframe.style.transform = `scale(${ scale })`;
		iframe.style.pointerEvents = 'auto';
		iframe.style.opacity = '1';
		iframe.style.zIndex = '9999';
	}

	/**
	 * Handles visibility change events to pause/resume processing when tab/window visibility changes.
	 */
	function handleVisibilityChange() {
		if ( 'hidden' === document.visibilityState ) {
			if ( isProcessing ) {
				isProcessing = false;
				isTabHidden = true;
				if ( ! abortController.signal.aborted ) {
					abortController.abort();
				}
				controlButton.textContent = __(
					'Resume',
					'optimization-detective'
				);
			}
		} else if ( 'visible' === document.visibilityState && isTabHidden ) {
			isTabHidden = false;
			if ( ! isProcessing ) {
				isProcessing = true;
				controlButton.textContent = __(
					'Pause',
					'optimization-detective'
				);
				if ( abortController.signal.aborted ) {
					abortController = new AbortController();
				}
				processBatches();
			}
		}
	}

	/**
	 * Handler for the beforeunload event to prevent accidental page navigation.
	 *
	 * @param {BeforeUnloadEvent} event - The beforeunload event
	 */
	function handleBeforeUnload( event ) {
		if ( isProcessing ) {
			event.preventDefault();
		}
	}

	// Attach event listeners.
	controlButton.addEventListener( 'click', toggleProcessing );
	document.addEventListener( 'visibilitychange', handleVisibilityChange );
	window.addEventListener( 'beforeunload', handleBeforeUnload );
	window.addEventListener( 'resize', fitIframe );
} )();
