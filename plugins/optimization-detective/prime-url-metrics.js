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

	/** @type {HTMLDivElement} */
	const iframeContainer = document.querySelector(
		'div#od-prime-url-metrics-iframe-container'
	);

	/** @type {HTMLSpanElement} */
	const currentBatchElement = document.querySelector(
		'span#od-prime-url-metrics-current-batch'
	);

	/** @type {HTMLSpanElement} */
	const currentTaskElement = document.querySelector(
		'span#od-prime-url-metrics-current-task'
	);

	/** @type {HTMLSpanElement} */
	const totalTasksInBatchElement = document.querySelector(
		'span#od-prime-url-metrics-total-tasks-in-batch'
	);

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

	let isProcessing = false;
	let isNextBatchAvailable = true;
	let cursor = {};
	let isDebug = false;
	let verificationToken = '';
	let currentBatch = null;
	let currentTasks = [];
	let currentTaskIndex = 0;
	let currentBatchNumber = 0;
	let isTabHidden = false;
	let abortController = null;

	/**
	 * Toggles the processing state.
	 */
	function toggleProcessing() {
		if ( isProcessing ) {
			// Pause processing
			isProcessing = false;
			controlButton.textContent = __(
				'Resume',
				'optimization-detective'
			);
			if ( abortController ) {
				abortController.abort();
				abortController = null;
			}
		} else {
			// Start/resume processing
			isProcessing = true;
			controlButton.textContent = __( 'Pause', 'optimization-detective' );
			processBatches();
		}
	}

	/**
	 * Processes batches of URLs.
	 */
	async function processBatches() {
		try {
			while ( isProcessing ) {
				if ( ! currentBatch ) {
					currentBatch = await getBatch( cursor );
					if ( ! currentBatch.batch.length ) {
						isNextBatchAvailable = false;
						break;
					}

					currentBatchNumber++;
					currentBatchElement.textContent =
						currentBatchNumber.toString();

					// Initialize batch state
					verificationToken = currentBatch.verificationToken;
					isDebug = currentBatch.isDebug;
					currentTasks = flattenBatchToTasks( currentBatch );
					currentTaskIndex = 0;
					progressBar.max = currentTasks.length;
					progressBar.value = 0;
					totalTasksInBatchElement.textContent =
						currentTasks.length.toString();
					currentTaskElement.textContent = '0';
				}

				// Process tasks in current batch
				while (
					isProcessing &&
					currentTaskIndex < currentTasks.length
				) {
					abortController = new AbortController();
					await processTask(
						currentTasks[ currentTaskIndex ],
						abortController.signal
					);
					currentTaskIndex++;
					progressBar.value = currentTaskIndex;
					currentTaskElement.textContent =
						currentTaskIndex.toString();
				}

				if ( currentTaskIndex >= currentTasks.length ) {
					// Complete current batch
					cursor = currentBatch.cursor;
					currentBatch = null;
					currentTasks = [];
					currentTaskIndex = 0;
					totalTasksInBatchElement.textContent = '0';
					currentTaskElement.textContent = '0';
				}
			}
		} catch ( error ) {
			if ( ! isTabHidden && 'Task Aborted' !== error.message ) {
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
				iframe.src = 'about:blank';
				iframe.width = '0';
				iframe.height = '0';
				currentBatchElement.textContent = '0';
			}
		}
	}

	/**
	 * Flattens the batch to tasks.
	 * @param {Object} batch - The batch to flatten.
	 * @return {Array<{ url: string, width: number, height: number }>} - The flattened tasks.
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
	 * @return {Promise<Object>} - The promise that resolves to the batch of URLs.
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
	 * @param {{ url: string, width: number, height: number }} task   - The breakpoint to set for the iframe.
	 * @param {AbortSignal}                                    signal - The signal to abort the task.
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

			if ( isDebug ) {
				function fitIframe() {
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
				window.addEventListener( 'resize', fitIframe );
				fitIframe();
			}
		} );
	}

	controlButton.addEventListener( 'click', toggleProcessing );

	/**
	 * Pause processing when the tab/window becomes hidden, resume when visible.
	 */
	document.addEventListener( 'visibilitychange', () => {
		if ( 'hidden' === document.visibilityState ) {
			if ( isProcessing ) {
				isProcessing = false;
				isTabHidden = true;
				if ( abortController ) {
					abortController.abort();
					abortController = null;
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
				processBatches();
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
} )();
