/** @typedef {import("web-vitals").LCPMetricWithAttribution} LCPMetricWithAttribution */

const consoleLogPrefix = '[Image Loading Optimization]';

function log( ...message ) {
	console.log( consoleLogPrefix, ...message );
}

function warn( ...message ) {
	console.warn( consoleLogPrefix, ...message );
}

/**
 * Yield to the main thread.
 *
 * @see https://developer.chrome.com/blog/introducing-scheduler-yield-origin-trial/#enter-scheduleryield
 * @return {Promise<void>}
 */
function yieldToMain() {
	/** @type  */
	if (
		typeof scheduler !== 'undefined' &&
		typeof scheduler.yield === 'function'
	) {
		return scheduler.yield();
	}

	// Fall back to setTimeout:
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 0 );
	} );
}

/**
 * Detect the LCP element, loaded images, client viewport and store for future optimizations.
 *
 * @param {number}  serveTime           The serve time of the page in milliseconds from PHP via `ceil( microtime( true ) * 1000 )`.
 * @param {number}  detectionTimeWindow The number of milliseconds between now and when the page was first generated in which detection should proceed.
 * @param {boolean} isDebug             Whether to show debug messages.
 */
export default async function detect(
	serveTime,
	detectionTimeWindow,
	isDebug
) {
	const runTime = new Date().valueOf();

	// Abort running detection logic if it was served in a cached page.
	if ( runTime - serveTime > detectionTimeWindow ) {
		if ( isDebug ) {
			warn(
				'Aborted detection for Image Loading Optimization due to being outside detection time window.'
			);
		}
		return;
	}

	if ( isDebug ) {
		log( 'Proceeding with detection' );
	}

	// TODO: Use a local copy of web-vitals.
	const { onLCP } = await import(
		// eslint-disable-next-line import/no-unresolved
		'https://unpkg.com/web-vitals@3/dist/web-vitals.attribution.js?module'
	);

	/** @type {LCPMetricWithAttribution[]} */
	const lcpCandidates = [];

	// Obtain at least one LCP candidate.
	const lcpCandidateObtained = new Promise( ( resolve ) => {
		onLCP(
			( metric ) => {
				lcpCandidates.push( metric );
				resolve();
			},
			{
				// This avoids needing to click to finalize LCP candidate. While this is helpful for testing, it also
				// ensures that we always get an LCP candidate reported. Otherwise, the callback may never fire if the
				// user never does a click or keydown, per <https://github.com/GoogleChrome/web-vitals/blob/07f6f96/src/onLCP.ts#L99-L107>.
				reportAllChanges: true,
			}
		);
	} );

	// To watch for intersection relative to the device's viewport, specify null for the root option.
	log( {
		viewportWidth: window.innerWidth,
		viewportHeight: window.innerHeight,
	} );

	const options = {
		root: null,
		// rootMargin: "0px",
		threshold: 0.0, // As soon as even one pixel is visible.
	};

	const adminBar = document.getElementById( 'wpadminbar' );
	const imageObserver = new IntersectionObserver( ( entries ) => {
		for ( const entry of entries ) {
			if (
				entry.isIntersecting &&
				( ! adminBar || ! adminBar.contains( entry.target ) )
			) {
				log( 'Initial image:', entry.target );
			}
		}
	}, options );
	for ( const img of document.getElementsByTagName( 'img' ) ) {
		imageObserver.observe( img );
	}

	// Wait until we have an LCP candidate, although more may come upon the page finishing loading.
	await lcpCandidateObtained;

	// Wait until the page has fully loaded. Note that a module is delayed like a script with defer.
	await new Promise( ( resolve ) => {
		if ( document.readyState === 'complete' ) {
			resolve();
		} else {
			window.addEventListener( 'load', resolve, { once: true } );
		}
	} );

	// Stop observing.
	imageObserver.disconnect();
	if ( isDebug ) {
		log( 'Detection is stopping.' );
	}

	log( 'lcpCandidates', lcpCandidates );

	// TODO: Send data to server.
}
