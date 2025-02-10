/**
 * Helper script for the Priming URL Metrics in block editor.
 */
( function () {
	// @ts-ignore
	const { select, subscribe } = wp.data;
	// @ts-ignore
	const { apiFetch } = wp;

	let isProcessing = false;
	let breakpoints = [];
	let currentTasks = [];

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
	async function primeURL() {
		try {
			if ( 0 === breakpoints.length ) {
				breakpoints = await apiFetch( {
					path: '/optimization-detective/v1/prime-urls-breakpoints',
					method: 'GET',
				} );
			}

			const postURL = select( 'core/editor' ).getPermalink();
			const verificationToken = await apiFetch( {
				path: '/optimization-detective/v1/prime-urls-verification-token',
				method: 'GET',
			} );

			currentTasks = breakpoints.map( ( breakpoint ) => ( {
				url: postURL,
				width: breakpoint.width,
				height: breakpoint.height,
				verificationToken,
			} ) );

			if ( ! isProcessing && currentTasks.length > 0 ) {
				isProcessing = true;
				processTasks();
			}
		} catch ( error ) {
			isProcessing = false;
		}
	}

	/**
	 * Loads the iframe and waits for the message.
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	async function processTasks() {
		const task = currentTasks.shift();
		await new Promise( ( resolve, reject ) => {
			const handleMessage = ( event ) => {
				if ( event.data === 'OD_PRIME_URL_METRICS_REQUEST_SUCCESS' ) {
					cleanup();
					resolve();
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
				task.verificationToken;
		} );

		if ( currentTasks.length > 0 ) {
			processTasks();
		} else {
			isProcessing = false;
		}
	}

	// Listen for post save/publish events.
	let wasSaving = false;
	subscribe( () => {
		const isSaving = select( 'core/editor' ).isSavingPost();
		const isAutosaving = select( 'core/editor' ).isAutosavingPost();

		// Trigger when saving transitions from true to false (save completed).
		if ( wasSaving && ! isSaving && ! isAutosaving ) {
			primeURL();
		}

		wasSaving = isSaving;
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
