/** @typedef {import("web-vitals").LCPMetric} LCPMetric */
/** @typedef {import("web-vitals").CLSMetric} CLSMetric */
/** @typedef {import("web-vitals").INPMetric} INPMetric */
/** @typedef {import("web-vitals").TTFBMetric} TTFBMetric */

const win = window;
const doc = win.document;

const consoleLogPrefix = '[Optimization Detective]';

const storageLockTimeSessionKey = 'odStorageLockTime';

/**
 * Checks whether storage is locked.
 *
 * @param {number} currentTime    - Current time in milliseconds.
 * @param {number} storageLockTTL - Storage lock TTL in seconds.
 * @return {boolean} Whether storage is locked.
 */
function isStorageLocked( currentTime, storageLockTTL ) {
	if ( storageLockTTL === 0 ) {
		return false;
	}

	try {
		const storageLockTime = parseInt(
			sessionStorage.getItem( storageLockTimeSessionKey )
		);
		return (
			! isNaN( storageLockTime ) &&
			currentTime < storageLockTime + storageLockTTL * 1000
		);
	} catch ( e ) {
		return false;
	}
}

/**
 * Set the storage lock.
 *
 * @param {number} currentTime - Current time in milliseconds.
 */
function setStorageLock( currentTime ) {
	try {
		sessionStorage.setItem(
			storageLockTimeSessionKey,
			String( currentTime )
		);
	} catch ( e ) {}
}

/**
 * Log a message.
 *
 * @param {...*} message
 */
function log( ...message ) {
	// eslint-disable-next-line no-console
	console.log( consoleLogPrefix, ...message );
}

/**
 * Log a warning.
 *
 * @param {...*} message
 */
function warn( ...message ) {
	// eslint-disable-next-line no-console
	console.warn( consoleLogPrefix, ...message );
}

/**
 * Log an error.
 *
 * @param {...*} message
 */
function error( ...message ) {
	// eslint-disable-next-line no-console
	console.error( consoleLogPrefix, ...message );
}

/**
 * @typedef {Object} ElementMetrics
 * @property {boolean}         isLCP              - Whether it is the LCP candidate.
 * @property {boolean}         isLCPCandidate     - Whether it is among the LCP candidates.
 * @property {string}          xpath              - XPath.
 * @property {number}          intersectionRatio  - Intersection ratio.
 * @property {DOMRectReadOnly} intersectionRect   - Intersection rectangle.
 * @property {DOMRectReadOnly} boundingClientRect - Bounding client rectangle.
 */

/**
 * @typedef {Object} WebVitalsMetrics
 * @property {?number} LCP  - Largest contentful paint.
 * @property {?number} CLS  - Cumulative layout shift.
 * @property {?number} INP  - Interaction to next paint.
 * @property {?number} TTFB - Time to first byte.
 */

/**
 * @typedef {Object} URLMetrics
 * @property {string}           url             - URL of the page.
 * @property {Object}           viewport        - Viewport.
 * @property {number}           viewport.width  - Viewport width.
 * @property {number}           viewport.height - Viewport height.
 * @property {ElementMetrics[]} elements        - Metrics for the elements observed on the page.
 * @property {WebVitalsMetrics} webVitals       - Web vitals metrics of the page.
 */

/**
 * @typedef {Object} URLMetricsGroupStatus
 * @property {number}  minimumViewportWidth - Minimum viewport width.
 * @property {boolean} complete             - Whether viewport group is complete.
 */

/**
 * Checks whether the URL metric(s) for the provided viewport width is needed.
 *
 * @param {number}                  viewportWidth           - Current viewport width.
 * @param {URLMetricsGroupStatus[]} urlMetricsGroupStatuses - Viewport group statuses.
 * @return {boolean} Whether URL metrics are needed.
 */
function isViewportNeeded( viewportWidth, urlMetricsGroupStatuses ) {
	let lastWasLacking = false;
	for ( const {
		minimumViewportWidth,
		complete,
	} of urlMetricsGroupStatuses ) {
		if ( viewportWidth >= minimumViewportWidth ) {
			lastWasLacking = ! complete;
		} else {
			break;
		}
	}
	return lastWasLacking;
}

/**
 * Gets the current time in milliseconds.
 *
 * @return {number} Current time in milliseconds.
 */
function getCurrentTime() {
	return Date.now();
}

/**
 * Detects the LCP element, loaded images, client viewport and store for future optimizations.
 *
 * @param {Object}                  args                             Args.
 * @param {number}                  args.serveTime                   The serve time of the page in milliseconds from PHP via `microtime( true ) * 1000`.
 * @param {number}                  args.detectionTimeWindow         The number of milliseconds between now and when the page was first generated in which detection should proceed.
 * @param {boolean}                 args.isDebug                     Whether to show debug messages.
 * @param {string}                  args.restApiEndpoint             URL for where to send the detection data.
 * @param {string}                  args.restApiNonce                Nonce for writing to the REST API.
 * @param {string}                  args.currentUrl                  Current URL.
 * @param {string}                  args.urlMetricsSlug              Slug for URL metrics.
 * @param {string}                  args.urlMetricsNonce             Nonce for URL metrics storage.
 * @param {URLMetricsGroupStatus[]} args.urlMetricsGroupStatuses     URL metrics group statuses.
 * @param {number}                  args.storageLockTTL              The TTL (in seconds) for the URL metric storage lock.
 * @param {string}                  args.webVitalsLibrarySrc         The URL for the web-vitals library.
 * @param {Object}                  [args.urlMetricsGroupCollection] URL metrics group collection, when in debug mode.
 */
export default async function detect( {
	serveTime,
	detectionTimeWindow,
	isDebug,
	restApiEndpoint,
	restApiNonce,
	currentUrl,
	urlMetricsSlug,
	urlMetricsNonce,
	urlMetricsGroupStatuses,
	storageLockTTL,
	webVitalsLibrarySrc,
	urlMetricsGroupCollection,
} ) {
	const currentTime = getCurrentTime();

	if ( isDebug ) {
		log(
			'Stored URL metrics group collection:',
			urlMetricsGroupCollection
		);
	}

	// Abort running detection logic if it was served in a cached page.
	if ( currentTime - serveTime > detectionTimeWindow ) {
		if ( isDebug ) {
			warn(
				'Aborted detection due to being outside detection time window.'
			);
		}
		// return;
	}

	// Abort if the current viewport is not among those which need URL metrics.
	if ( ! isViewportNeeded( win.innerWidth, urlMetricsGroupStatuses ) ) {
		if ( isDebug ) {
			log( 'No need for URL metrics from the current viewport.' );
		}
		// return;
	}

	// Ensure the DOM is loaded (although it surely already is since we're executing in a module).
	await new Promise( ( resolve ) => {
		if ( doc.readyState !== 'loading' ) {
			resolve();
		} else {
			doc.addEventListener( 'DOMContentLoaded', resolve, { once: true } );
		}
	} );

	// Wait until the resources on the page have fully loaded.
	await new Promise( ( resolve ) => {
		if ( doc.readyState === 'complete' ) {
			resolve();
		} else {
			win.addEventListener( 'load', resolve, { once: true } );
		}
	} );

	// Wait yet further until idle.
	if ( typeof requestIdleCallback === 'function' ) {
		await new Promise( ( resolve ) => {
			requestIdleCallback( resolve );
		} );
	}

	// As an alternative to this, the od_print_detection_script() function can short-circuit if the
	// od_is_url_metric_storage_locked() function returns true. However, the downside with that is page caching could
	// result in metrics missed from being gathered when a user navigates around a site and primes the page cache.
	if ( isStorageLocked( currentTime, storageLockTTL ) ) {
		if ( isDebug ) {
			warn( 'Aborted detection due to storage being locked.' );
		}
		// return;
	}

	// Prevent detection when page is not scrolled to the initial viewport.
	// TODO: Change this. Web vitals should always get reported.
	if ( doc.documentElement.scrollTop > 0 ) {
		if ( isDebug ) {
			warn(
				'Aborted detection since initial scroll position of page is not at the top.'
			);
		}
		// return;
	}

	if ( isDebug ) {
		log( 'Proceeding with detection' );
	}

	const breadcrumbedElements = doc.body.querySelectorAll( '[data-od-xpath]' );

	/** @type {Map<HTMLElement, string>} */
	const breadcrumbedElementsMap = new Map(
		[ ...breadcrumbedElements ].map(
			/**
			 * @param {HTMLElement} element
			 * @return {[HTMLElement, string]} Tuple of element and its XPath.
			 */
			( element ) => [ element, element.dataset.odXpath ]
		)
	);

	/** @type {IntersectionObserverEntry[]} */
	const elementIntersections = [];

	/** @type {?IntersectionObserver} */
	let intersectionObserver;

	function disconnectIntersectionObserver() {
		if ( intersectionObserver instanceof IntersectionObserver ) {
			intersectionObserver.disconnect();
			win.removeEventListener( 'scroll', disconnectIntersectionObserver ); // Clean up, even though this is registered with once:true.
		}
	}

	// Wait for the intersection observer to report back on the initially-visible elements.
	// Note that the first callback will include _all_ observed entries per <https://github.com/w3c/IntersectionObserver/issues/476>.
	if ( breadcrumbedElementsMap.size > 0 ) {
		await new Promise( ( resolve ) => {
			intersectionObserver = new IntersectionObserver(
				( entries ) => {
					for ( const entry of entries ) {
						elementIntersections.push( entry );
					}
					resolve();
				},
				{
					root: null, // To watch for intersection relative to the device's viewport.
					threshold: 0.0, // As soon as even one pixel is visible.
				}
			);

			for ( const element of breadcrumbedElementsMap.keys() ) {
				intersectionObserver.observe( element );
			}
		} );

		// Stop observing as soon as the page scrolls since we only want initial-viewport elements.
		win.addEventListener( 'scroll', disconnectIntersectionObserver, {
			once: true,
			passive: true,
		} );
	}

	const { onLCP, onCLS, onINP, onTTFB } = await import( webVitalsLibrarySrc );
	/**
	 * @type {{LCP: LCPMetric[], INP: INPMetric[], TTFB: TTFBMetric[], CLS: CLSMetric[]}}
	 */
	const webVitalsMetrics = {
		LCP: [],
		CLS: [],
		INP: [],
		TTFB: [],
	};

	const cb = ( measurement ) =>
		webVitalsMetrics[ measurement.name ].push( measurement );
	/*
	 This avoids needing to click to finalize LCP candidate. While this is helpful for testing, it also
	 ensures that we always get an LCP candidate reported. Otherwise, the callback may never fire if the
	 user never does a click or keydown, per <https://github.com/GoogleChrome/web-vitals/blob/07f6f96/src/onLCP.ts#L99-L107>.
	*/
	const reportOpts = { reportAllChanges: true };

	onCLS( cb, reportOpts );
	onLCP( cb, reportOpts );
	onINP( cb, reportOpts );
	onTTFB( cb, reportOpts );

	// Stop observing.
	disconnectIntersectionObserver();
	if ( isDebug ) {
		log( 'Detection is stopping.' );
	}

	/** @type {URLMetrics} */
	const urlMetrics = {
		url: currentUrl,
		slug: urlMetricsSlug,
		nonce: urlMetricsNonce,
		viewport: {
			width: win.innerWidth,
			height: win.innerHeight,
		},
		elements: [],
		webVitals: {
			LCP: null,
			CLS: null,
			INP: null,
			TTFB: null,
		},
	};

	for ( const elementIntersection of elementIntersections ) {
		const xpath = breadcrumbedElementsMap.get( elementIntersection.target );
		if ( ! xpath ) {
			if ( isDebug ) {
				error( 'Unable to look up XPath for element' );
			}
			continue;
		}

		/** @type {ElementMetrics} */
		const elementMetrics = {
			xpath,
			target: elementIntersection.target,
			intersectionRatio: elementIntersection.intersectionRatio,
			intersectionRect: elementIntersection.intersectionRect,
			boundingClientRect: elementIntersection.boundingClientRect,
		};

		urlMetrics.elements.push( elementMetrics );
	}

	async function sendData() {
		// Data likely already sent.
		if (
			! webVitalsMetrics.LCP.length &&
			! webVitalsMetrics.CLS.length &&
			! webVitalsMetrics.INP.length &&
			! webVitalsMetrics.TTFB.length
		) {
			return;
		}

		for ( const [ webVital, metric ] of Object.entries(
			webVitalsMetrics
		) ) {
			if ( ! metric.length ) {
				continue;
			}

			urlMetrics.webVitals[ webVital ] = metric[ 0 ].value;
		}

		const lcpMetric = webVitalsMetrics.LCP.at( -1 );

		for ( const i in urlMetrics.elements ) {
			const elementMetrics = urlMetrics.elements[ i ];

			elementMetrics.isLCP =
				elementMetrics.target === lcpMetric?.entries[ 0 ]?.element;
			elementMetrics.isLCPCandidate = webVitalsMetrics.LCP.some(
				( lcpMetricCandidate ) =>
					lcpMetricCandidate.entries[ 0 ]?.element ===
					elementMetrics.target
			);
		}

		// TODO: Collect Server-Timing information.

		// TODO: Detect form factor à la CRuX.
		// See https://developer.chrome.com/docs/crux/methodology/dimensions

		if ( isDebug ) {
			log( 'Current URL metrics:', urlMetrics );
		}

		try {
			const response = await fetch( restApiEndpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': restApiNonce,
				},
				body: JSON.stringify( urlMetrics ),
				keepalive: true,
			} );

			if ( response.status === 200 ) {
				setStorageLock( getCurrentTime() );
			}

			if ( isDebug ) {
				const body = await response.json();
				if ( response.status === 200 ) {
					log( 'Response:', body );
				} else {
					error( 'Failure:', body );
				}
			}
		} catch ( err ) {
			if ( isDebug ) {
				error( err );
			}
		}

		// Clean up.
		breadcrumbedElementsMap.clear();

		webVitalsMetrics.LCP = [];
		webVitalsMetrics.CLS = [];
		webVitalsMetrics.INP = [];
		webVitalsMetrics.TTFB = [];
	}

	log( 'Adding event listeners for backgrounding and unloading.' );

	await new Promise( ( resolve ) => {
		setTimeout( resolve, 1000 );
	} );

	await sendData();

	// Report all available metrics whenever the page is backgrounded or unloaded.
	addEventListener( 'visibilitychange', () => {
		if ( document.visibilityState === 'hidden' ) {
			sendData();
		}
	} );

	// NOTE: Safari does not reliably fire the `visibilitychange` event when the
	// page is being unloaded. If Safari support is needed, you should also flush
	// the queue in the `pagehide` event.
	addEventListener( 'pagehide', () => {
		sendData();
	} );
}
