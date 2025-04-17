/**
 * Helper script for Priming URL Metrics through WordPress admin dashboard.
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

	// Initialize state variables.
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
	let abortController = new AbortController();
	const consoleLogPrefix = '[Optimization Detective Priming URL Metrics]';

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
			controlButton.classList.remove( 'updating-message' );
			if ( ! abortController.signal.aborted ) {
				abortController.abort();
			}
		} else {
			// Start/resume processing
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
	 * Main processing controller function.
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

		if ( ! currentBatch.batch.length ) {
			isNextBatchAvailable = false;
			return;
		}

		currentBatchNumber++;
		currentBatchElement.textContent = currentBatchNumber.toString();

		// Initialize batch state.
		verificationToken = currentBatch.verificationToken;
		isDebug = currentBatch.isDebug;
		currentTasks = flattenBatchToTasks( currentBatch );
		currentTaskIndex = 0;

		// Update UI for new batch.
		progressBar.max = currentTasks.length;
		progressBar.value = 0;
		totalTasksInBatchElement.textContent = currentTasks.length.toString();
		currentTaskElement.textContent = '0';
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
				if ( 'Task Aborted' === error.message ) {
					throw error;
				}
			} finally {
				currentTaskIndex++;
				progressBar.value = currentTaskIndex;
				currentTaskElement.textContent = currentTaskIndex.toString();
			}
		}
	}

	/**
	 * Flattens the batch to tasks.
	 *
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
	 *
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
	 *
	 * @param {{ url: string, width: number, height: number }} task   - The breakpoint to set for the iframe.
	 * @param {AbortSignal}                                    signal - The signal to abort the task.
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

			if ( isDebug ) {
				/**
				 * Fits the iframe to the container.
				 */
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
