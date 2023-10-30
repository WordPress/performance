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
 * @typedef {Object} Breadcrumb
 * @property {number} index
 * @property {string} tagName
 */

/**
 * Gets breadcrumbs for a given element.
 *
 * @param {Element} element
 * @return {Breadcrumb[]} Breadcrumbs.
 */
function getBreadcrumbs( element ) {
	/** @type {Breadcrumb[]} */
	const breadcrumbs = [];

	let node = element;
	while ( node instanceof Element ) {
		breadcrumbs.unshift( {
			tagName: node.tagName,
			index: node.parentElement
				? Array.from( node.parentElement.children ).indexOf( node )
				: 0,
		} );
		node = node.parentElement;
	}

	return breadcrumbs;
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

	const results = {
		viewport: {
			width: window.innerWidth,
			height: window.innerHeight,
		},
		images: [],
	};

	// TODO: Use a local copy of web-vitals.
	const { onLCP } = await import(
		// eslint-disable-next-line import/no-unresolved
		'https://unpkg.com/web-vitals@3/dist/web-vitals.attribution.js?module'
	);

	/** @type {LCPMetricWithAttribution[]} */
	const lcpMetricCandidates = [];

	// Obtain at least one LCP candidate.
	const lcpCandidateObtained = new Promise( ( resolve ) => {
		onLCP(
			( metric ) => {
				lcpMetricCandidates.push( metric );
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

	/** @type {IntersectionObserverEntry[]} */
	const imageIntersections = [];

	const imageObserver = new IntersectionObserver(
		( entries ) => {
			for ( const entry of entries ) {
				//if ( entry.isIntersecting ) {
				console.info( 'interesecting!', entry );
				imageIntersections.push( entry );
				//}
			}
		},
		{
			root: null, // To watch for intersection relative to the device's viewport, specify null for the root option.
			threshold: 0.0, // As soon as even one pixel is visible.
		}
	);

	const adminBar = document.getElementById( 'wpadminbar' );
	for ( const img of document.getElementsByTagName( 'img' ) ) {
		if ( ! adminBar || ! adminBar.contains( img ) ) {
			imageObserver.observe( img );
		}
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

	console.info( imageIntersections );
	const lcpMetric = lcpMetricCandidates.at( -1 );
	for ( const imageIntersection of imageIntersections ) {
		log(
			'imageIntersection.target',
			imageIntersection.target,
			getBreadcrumbs( imageIntersection.target )
		);
	}
	// lcpMetric.attribution.element

	log( 'lcpCandidates', lcpMetricCandidates );

	// TODO: Send data to server.
}
