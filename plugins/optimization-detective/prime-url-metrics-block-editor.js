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
	const iframe = document.createElement( 'iframe' );
	iframe.id = 'od-prime-url-metrics-iframe';
	iframe.style.display = 'block';
	iframe.style.position = 'absolute';
	iframe.style.transform = 'translateX(-9999px)';
	iframe.style.visibility = 'hidden';
	document.body.appendChild( iframe );

	/**
	 * Primes the URL metrics for all breakpoints.
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	async function primeURL() {
		isProcessing = true;
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

			for ( const breakpoint of breakpoints ) {
				await processTask( {
					url: postURL,
					width: breakpoint.width,
					height: breakpoint.height,
					verificationToken,
				} );
			}
			isProcessing = false;
		} catch ( error ) {
			isProcessing = false;
		}
	}

	/**
	 * Loads the iframe and waits for the message.
	 * @param {{url: string, width: number, height: number, verificationToken: string}} task - The breakpoint to set for the iframe.
	 * @return {Promise<void>} The promise that resolves to void.
	 */
	function processTask( task ) {
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
			iframe.src = task.url;
			iframe.width = task.width.toString();
			iframe.height = task.height.toString();
			iframe.dataset.odPrimeUrlMetricsVerificationToken =
				task.verificationToken;
		} );
	}

	// Listen for post save/publish events
	subscribe( () => {
		const isSaving = select( 'core/editor' ).isSavingPost();
		const isAutosaving = select( 'core/editor' ).isAutosavingPost();
		const isPublished =
			select( 'core/editor' ).getCurrentPost().status === 'publish';
		const isJustSaved = isSaving && ! isAutosaving && isPublished;
		if ( isJustSaved ) {
			primeURL();
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
