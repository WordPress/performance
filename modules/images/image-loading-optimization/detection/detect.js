/** @typedef {import("web-vitals").LCPMetric} LCPMetric */

const win = window;
const doc = win.document;

const consoleLogPrefix = '[Image Loading Optimization]';

const storageLockTimeSessionKey = 'iloStorageLockTime';

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
 * @typedef {Object} URLMetrics
 * @property {string}           url             - URL of the page.
 * @property {Object}           viewport        - Viewport.
 * @property {number}           viewport.width  - Viewport width.
 * @property {number}           viewport.height - Viewport height.
 * @property {ElementMetrics[]} elements        - Metrics for the elements observed on the page.
 */

/**
 * Checks whether the URL metric(s) for the provided viewport width is needed.
 *
 * @param {number}                   viewportWidth                - Current viewport width.
 * @param {Array<number, boolean>[]} viewportGroupLackingStatuses - Viewport group lacking statuses, mapping minimum viewport width to whether lacking metrics.
 * @return {boolean} Whether URL metrics are needed.
 */
function isViewportNeeded( viewportWidth, viewportGroupLackingStatuses ) {
	let lastWasNeeded = false;
	for ( const [
		minimumViewportWidth,
		isNeeded,
	] of viewportGroupLackingStatuses ) {
		if ( viewportWidth >= minimumViewportWidth ) {
			lastWasNeeded = isNeeded;
		} else {
			break;
		}
	}
	return lastWasNeeded;
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
 * @param {Object}                   args                              Args.
 * @param {number}                   args.serveTime                    The serve time of the page in milliseconds from PHP via `microtime( true ) * 1000`.
 * @param {number}                   args.detectionTimeWindow          The number of milliseconds between now and when the page was first generated in which detection should proceed.
 * @param {boolean}                  args.isDebug                      Whether to show debug messages.
 * @param {string}                   args.restApiEndpoint              URL for where to send the detection data.
 * @param {string}                   args.restApiNonce                 Nonce for writing to the REST API.
 * @param {string}                   args.urlMetricsSlug               Slug for URL metrics.
 * @param {string}                   args.urlMetricsNonce              Nonce for URL metrics storage.
 * @param {Array<number, boolean>[]} args.viewportGroupLackingStatuses Viewport group lacking statuses, mapping minimum viewport width to whether lacking metrics.
 * @param {number}                   args.storageLockTTL               The TTL (in seconds) for the URL metric storage lock.
 * @param {string}                   args.webVitalsLibrarySrc          The URL for the web-vitals library.
 */
export default async function detect( {
	serveTime,
	detectionTimeWindow,
	isDebug,
	restApiEndpoint,
	restApiNonce,
	urlMetricsSlug,
	urlMetricsNonce,
	viewportGroupLackingStatuses,
	storageLockTTL,
	webVitalsLibrarySrc,
} ) {
	const currentTime = getCurrentTime();

	// Abort running detection logic if it was served in a cached page.
	if ( currentTime - serveTime > detectionTimeWindow ) {
		if ( isDebug ) {
			warn(
				'Aborted detection due to being outside detection time window.'
			);
		}
		return;
	}

	// Abort if the current viewport is not among those which need URL metrics.
	if ( ! isViewportNeeded( win.innerWidth, viewportGroupLackingStatuses ) ) {
		if ( isDebug ) {
			log( 'No need for URL metrics from the current viewport.' );
		}
		return;
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

	// As an alternative to this, the ilo_print_detection_script() function can short-circuit if the
	// ilo_is_url_metric_storage_locked() function returns true. However, the downside with that is page caching could
	// result in metrics missed from being gathered when a user navigates around a site and primes the page cache.
	if ( isStorageLocked( currentTime, storageLockTTL ) ) {
		if ( isDebug ) {
			warn( 'Aborted detection due to storage being locked.' );
		}
		return;
	}

	// Prevent detection when page is not scrolled to the initial viewport.
	if ( doc.documentElement.scrollTop > 0 ) {
		if ( isDebug ) {
			warn(
				'Aborted detection since initial scroll position of page is not at the top.'
			);
		}
		return;
	}

	if ( isDebug ) {
		log( 'Proceeding with detection' );
	}

	const breadcrumbedElements =
		doc.body.querySelectorAll( '[data-ilo-xpath]' );

	/** @type {Map<HTMLElement, string>} */
	const breadcrumbedElementsMap = new Map(
		[ ...breadcrumbedElements ].map(
			/**
			 * @param {HTMLElement} element
			 * @return {[HTMLElement, string]} Tuple of element and its XPath.
			 */
			( element ) => [ element, element.dataset.iloXpath ]
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
						if ( entry.isIntersecting ) {
							elementIntersections.push( entry );
						}
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

	const { onLCP } = await import( webVitalsLibrarySrc );

	/** @type {LCPMetric[]} */
	const lcpMetricCandidates = [];

	// Obtain at least one LCP candidate. More may be reported before the page finishes loading.
	await new Promise( ( resolve ) => {
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

	// Stop observing.
	disconnectIntersectionObserver();
	if ( isDebug ) {
		log( 'Detection is stopping.' );
	}

	/** @type {URLMetrics} */
	const urlMetrics = {
		url: win.location.href,
		slug: urlMetricsSlug,
		nonce: urlMetricsNonce,
		viewport: {
			width: win.innerWidth,
			height: win.innerHeight,
		},
		elements: [],
	};

	const lcpMetric = lcpMetricCandidates.at( -1 );

	for ( const elementIntersection of elementIntersections ) {
		const xpath = breadcrumbedElementsMap.get( elementIntersection.target );
		if ( ! xpath ) {
			if ( isDebug ) {
				error( 'Unable to look up XPath for element' );
			}
			continue;
		}

		const isLCP =
			elementIntersection.target === lcpMetric?.entries[ 0 ]?.element;

		/** @type {ElementMetrics} */
		const elementMetrics = {
			isLCP,
			isLCPCandidate: !! lcpMetricCandidates.find(
				( lcpMetricCandidate ) =>
					lcpMetricCandidate.entries[ 0 ]?.element ===
					elementIntersection.target
			),
			xpath,
			intersectionRatio: elementIntersection.intersectionRatio,
			intersectionRect: elementIntersection.intersectionRect,
			boundingClientRect: elementIntersection.boundingClientRect,
		};

		urlMetrics.elements.push( elementMetrics );
	}

	if ( isDebug ) {
		log( 'URL metrics:', urlMetrics );
	}

	// Yield to main before sending data to server to further break up task.
	await new Promise( ( resolve ) => {
		setTimeout( resolve, 0 );
	} );

	try {
		const response = await fetch( restApiEndpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restApiNonce,
			},
			body: JSON.stringify( urlMetrics ),
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
}
