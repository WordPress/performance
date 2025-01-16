/**
 * @typedef {import("web-vitals").LCPMetric} LCPMetric
 * @typedef {import("web-vitals").LCPMetricWithAttribution} LCPMetricWithAttribution
 * @typedef {import("./types.ts").ElementData} ElementData
 * @typedef {import("./types.ts").OnTTFBFunction} OnTTFBFunction
 * @typedef {import("./types.ts").OnFCPFunction} OnFCPFunction
 * @typedef {import("./types.ts").OnLCPFunction} OnLCPFunction
 * @typedef {import("./types.ts").OnINPFunction} OnINPFunction
 * @typedef {import("./types.ts").OnCLSFunction} OnCLSFunction
 * @typedef {import("./types.ts").OnTTFBWithAttributionFunction} OnTTFBWithAttributionFunction
 * @typedef {import("./types.ts").OnFCPWithAttributionFunction} OnFCPWithAttributionFunction
 * @typedef {import("./types.ts").OnLCPWithAttributionFunction} OnLCPWithAttributionFunction
 * @typedef {import("./types.ts").OnINPWithAttributionFunction} OnINPWithAttributionFunction
 * @typedef {import("./types.ts").OnCLSWithAttributionFunction} OnCLSWithAttributionFunction
 * @typedef {import("./types.ts").URLMetric} URLMetric
 * @typedef {import("./types.ts").URLMetricGroupStatus} URLMetricGroupStatus
 * @typedef {import("./types.ts").Extension} Extension
 * @typedef {import("./types.ts").ExtendedRootData} ExtendedRootData
 * @typedef {import("./types.ts").ExtendedElementData} ExtendedElementData
 */

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
 * Sets the storage lock.
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
 * Logs a message.
 *
 * @param {...*} message
 */
function log( ...message ) {
	// eslint-disable-next-line no-console
	console.log( consoleLogPrefix, ...message );
}

/**
 * Logs a warning.
 *
 * @param {...*} message
 */
function warn( ...message ) {
	// eslint-disable-next-line no-console
	console.warn( consoleLogPrefix, ...message );
}

/**
 * Logs an error.
 *
 * @param {...*} message
 */
function error( ...message ) {
	// eslint-disable-next-line no-console
	console.error( consoleLogPrefix, ...message );
}

/**
 * Checks whether the URL Metric(s) for the provided viewport width is needed.
 *
 * @param {number}                 viewportWidth          - Current viewport width.
 * @param {URLMetricGroupStatus[]} urlMetricGroupStatuses - Viewport group statuses.
 * @return {boolean} Whether URL Metrics are needed.
 */
function isViewportNeeded( viewportWidth, urlMetricGroupStatuses ) {
	let lastWasLacking = false;
	for ( const { minimumViewportWidth, complete } of urlMetricGroupStatuses ) {
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
 * Recursively freezes an object to prevent mutation.
 *
 * @param {Object} obj Object to recursively freeze.
 */
function recursiveFreeze( obj ) {
	for ( const prop of Object.getOwnPropertyNames( obj ) ) {
		const value = obj[ prop ];
		if ( null !== value && typeof value === 'object' ) {
			recursiveFreeze( value );
		}
	}
	Object.freeze( obj );
}

/**
 * URL Metric being assembled for submission.
 *
 * @type {URLMetric}
 */
let urlMetric;

/**
 * Reserved root property keys.
 *
 * @see {URLMetric}
 * @see {ExtendedElementData}
 * @type {Set<string>}
 */
const reservedRootPropertyKeys = new Set( [ 'url', 'viewport', 'elements' ] );

/**
 * Gets root URL Metric data.
 *
 * @return {URLMetric} URL Metric.
 */
function getRootData() {
	const immutableUrlMetric = structuredClone( urlMetric );
	recursiveFreeze( immutableUrlMetric );
	return immutableUrlMetric;
}

/**
 * Extends root URL Metric data.
 *
 * @param {ExtendedRootData} properties
 */
function extendRootData( properties ) {
	for ( const key of Object.getOwnPropertyNames( properties ) ) {
		if ( reservedRootPropertyKeys.has( key ) ) {
			throw new Error( `Disallowed setting of key '${ key }' on root.` );
		}
	}
	Object.assign( urlMetric, properties );
}

/**
 * Mapping of XPath to element data.
 *
 * @type {Map<string, ElementData>}
 */
const elementsByXPath = new Map();

/**
 * Reserved element property keys.
 *
 * @see {ElementData}
 * @see {ExtendedRootData}
 * @type {Set<string>}
 */
const reservedElementPropertyKeys = new Set( [
	'isLCP',
	'isLCPCandidate',
	'xpath',
	'intersectionRatio',
	'intersectionRect',
	'boundingClientRect',
] );

/**
 * Gets element data.
 *
 * @param {string} xpath XPath.
 * @return {ElementData|null} Element data, or null if no element for the XPath exists.
 */
function getElementData( xpath ) {
	const elementData = elementsByXPath.get( xpath );
	if ( elementData ) {
		const cloned = structuredClone( elementData );
		recursiveFreeze( cloned );
		return cloned;
	}
	return null;
}

/**
 * Extends element data.
 *
 * @param {string}              xpath      XPath.
 * @param {ExtendedElementData} properties Properties.
 */
function extendElementData( xpath, properties ) {
	if ( ! elementsByXPath.has( xpath ) ) {
		throw new Error( `Unknown element with XPath: ${ xpath }` );
	}
	for ( const key of Object.getOwnPropertyNames( properties ) ) {
		if ( reservedElementPropertyKeys.has( key ) ) {
			throw new Error(
				`Disallowed setting of key '${ key }' on element.`
			);
		}
	}
	const elementData = elementsByXPath.get( xpath );
	Object.assign( elementData, properties );
}

/**
 * @typedef {{timestamp: number, creationDate: Date}} UrlMetricDebugData
 * @typedef {{groups: Array<{url_metrics: Array<UrlMetricDebugData>}>}} CollectionDebugData
 */

/**
 * Detects the LCP element, loaded images, client viewport and store for future optimizations.
 *
 * @param {Object}                 args                            Args.
 * @param {string[]}               args.extensionModuleUrls        URLs for extension script modules to import.
 * @param {number}                 args.minViewportAspectRatio     Minimum aspect ratio allowed for the viewport.
 * @param {number}                 args.maxViewportAspectRatio     Maximum aspect ratio allowed for the viewport.
 * @param {boolean}                args.isDebug                    Whether to show debug messages.
 * @param {string}                 args.restApiEndpoint            URL for where to send the detection data.
 * @param {string}                 args.currentETag                Current ETag.
 * @param {string}                 args.currentUrl                 Current URL.
 * @param {string}                 args.urlMetricSlug              Slug for URL Metric.
 * @param {number|null}            args.cachePurgePostId           Cache purge post ID.
 * @param {string}                 args.urlMetricHMAC              HMAC for URL Metric storage.
 * @param {URLMetricGroupStatus[]} args.urlMetricGroupStatuses     URL Metric group statuses.
 * @param {number}                 args.storageLockTTL             The TTL (in seconds) for the URL Metric storage lock.
 * @param {string}                 args.webVitalsLibrarySrc        The URL for the web-vitals library.
 * @param {CollectionDebugData}    [args.urlMetricGroupCollection] URL Metric group collection, when in debug mode.
 */
export default async function detect( {
	minViewportAspectRatio,
	maxViewportAspectRatio,
	isDebug,
	extensionModuleUrls,
	restApiEndpoint,
	currentETag,
	currentUrl,
	urlMetricSlug,
	cachePurgePostId,
	urlMetricHMAC,
	urlMetricGroupStatuses,
	storageLockTTL,
	webVitalsLibrarySrc,
	urlMetricGroupCollection,
} ) {
	if ( isDebug ) {
		const allUrlMetrics = /** @type Array<UrlMetricDebugData> */ [];
		for ( const group of urlMetricGroupCollection.groups ) {
			for ( const otherUrlMetric of group.url_metrics ) {
				otherUrlMetric.creationDate = new Date(
					otherUrlMetric.timestamp * 1000
				);
				allUrlMetrics.push( otherUrlMetric );
			}
		}
		log( 'Stored URL Metric Group Collection:', urlMetricGroupCollection );
		allUrlMetrics.sort( ( a, b ) => b.timestamp - a.timestamp );
		log(
			'Stored URL Metrics in reverse chronological order:',
			allUrlMetrics
		);
	}

	// Abort if the current viewport is not among those which need URL Metrics.
	if ( ! isViewportNeeded( win.innerWidth, urlMetricGroupStatuses ) ) {
		if ( isDebug ) {
			log( 'No need for URL Metrics from the current viewport.' );
		}
		return;
	}

	// Abort if the viewport aspect ratio is not in a common range.
	const aspectRatio = win.innerWidth / win.innerHeight;
	if (
		aspectRatio < minViewportAspectRatio ||
		aspectRatio > maxViewportAspectRatio
	) {
		if ( isDebug ) {
			warn(
				`Viewport aspect ratio (${ aspectRatio }) is not in the accepted range of ${ minViewportAspectRatio } to ${ maxViewportAspectRatio }.`
			);
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

	// TODO: Does this make sense here? Should it be moved up above the isViewportNeeded condition?
	// As an alternative to this, the od_print_detection_script() function can short-circuit if the
	// od_is_url_metric_storage_locked() function returns true. However, the downside with that is page caching could
	// result in metrics missed from being gathered when a user navigates around a site and primes the page cache.
	if ( isStorageLocked( getCurrentTime(), storageLockTTL ) ) {
		if ( isDebug ) {
			warn( 'Aborted detection due to storage being locked.' );
		}
		return;
	}

	// Keep track of whether the window resized. If it resized, we abort sending the URLMetric.
	let didWindowResize = false;
	window.addEventListener(
		'resize',
		() => {
			didWindowResize = true;
		},
		{ once: true }
	);

	const {
		/** @type {OnTTFBFunction|OnTTFBWithAttributionFunction} */ onTTFB,
		/** @type {OnFCPFunction|OnFCPWithAttributionFunction} */ onFCP,
		/** @type {OnLCPFunction|OnLCPWithAttributionFunction} */ onLCP,
		/** @type {OnINPFunction|OnINPWithAttributionFunction} */ onINP,
		/** @type {OnCLSFunction|OnCLSWithAttributionFunction} */ onCLS,
	} = await import( webVitalsLibrarySrc );

	// TODO: Does this make sense here?
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

	/** @type {Map<string, Extension>} */
	const extensions = new Map();

	/** @type {Promise[]} */
	const extensionInitializePromises = [];

	/** @type {string[]} */
	const initializingExtensionModuleUrls = [];

	for ( const extensionModuleUrl of extensionModuleUrls ) {
		try {
			/** @type {Extension} */
			const extension = await import( extensionModuleUrl );
			extensions.set( extensionModuleUrl, extension );
			// TODO: There should to be a way to pass additional args into the module. Perhaps extensionModuleUrls should be a mapping of URLs to args.
			if ( extension.initialize instanceof Function ) {
				const initializePromise = extension.initialize( {
					isDebug,
					onTTFB,
					onFCP,
					onLCP,
					onINP,
					onCLS,
				} );
				if ( initializePromise instanceof Promise ) {
					extensionInitializePromises.push( initializePromise );
					initializingExtensionModuleUrls.push( extensionModuleUrl );
				}
			}
		} catch ( err ) {
			error(
				`Failed to start initializing extension '${ extensionModuleUrl }':`,
				err
			);
		}
	}

	// Wait for all extensions to finish initializing.
	const settledInitializePromises = await Promise.allSettled(
		extensionInitializePromises
	);
	for ( const [
		i,
		settledInitializePromise,
	] of settledInitializePromises.entries() ) {
		if ( settledInitializePromise.status === 'rejected' ) {
			error(
				`Failed to initialize extension '${ initializingExtensionModuleUrls[ i ] }':`,
				settledInitializePromise.reason
			);
		}
	}

	const breadcrumbedElements = doc.body.querySelectorAll( '[data-od-xpath]' );

	/** @type {Map<Element, string>} */
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

	/** @type {(LCPMetric|LCPMetricWithAttribution)[]} */
	const lcpMetricCandidates = [];

	// Obtain at least one LCP candidate. More may be reported before the page finishes loading.
	await new Promise( ( resolve ) => {
		onLCP(
			/**
			 * Handles an LCP metric being reported.
			 *
			 * @param {LCPMetric|LCPMetricWithAttribution} metric
			 */
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

	urlMetric = {
		url: currentUrl,
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

		const element = /** @type {Element|null} */ (
			lcpMetric?.entries[ 0 ]?.element
		);
		const isLCP = elementIntersection.target === element;

		/** @type {ElementData} */
		const elementData = {
			isLCP,
			isLCPCandidate: !! lcpMetricCandidates.find(
				( lcpMetricCandidate ) => {
					const candidateElement = /** @type {Element|null} */ (
						lcpMetricCandidate.entries[ 0 ]?.element
					);
					return candidateElement === elementIntersection.target;
				}
			),
			xpath,
			intersectionRatio: elementIntersection.intersectionRatio,
			intersectionRect: elementIntersection.intersectionRect,
			boundingClientRect: elementIntersection.boundingClientRect,
		};

		urlMetric.elements.push( elementData );
		elementsByXPath.set( elementData.xpath, elementData );
	}

	if ( isDebug ) {
		log( 'Current URL Metric:', urlMetric );
	}

	// Wait for the page to be hidden.
	await new Promise( ( resolve ) => {
		win.addEventListener( 'pagehide', resolve, { once: true } );
		win.addEventListener( 'pageswap', resolve, { once: true } );
		doc.addEventListener(
			'visibilitychange',
			() => {
				if ( document.visibilityState === 'hidden' ) {
					// TODO: This will fire even when switching tabs.
					resolve();
				}
			},
			{ once: true }
		);
	} );

	// Only proceed with submitting the URL Metric if viewport stayed the same size. Changing the viewport size (e.g. due
	// to resizing a window or changing the orientation of a device) will result in unexpected metrics being collected.
	if ( didWindowResize ) {
		if ( isDebug ) {
			log(
				'Aborting URL Metric collection due to viewport size change.'
			);
		}
		return;
	}

	if ( extensions.size > 0 ) {
		/** @type {Promise[]} */
		const extensionFinalizePromises = [];

		/** @type {string[]} */
		const finalizingExtensionModuleUrls = [];

		for ( const [
			extensionModuleUrl,
			extension,
		] of extensions.entries() ) {
			if ( extension.finalize instanceof Function ) {
				try {
					const finalizePromise = extension.finalize( {
						isDebug,
						getRootData,
						getElementData,
						extendElementData,
						extendRootData,
					} );
					if ( finalizePromise instanceof Promise ) {
						extensionFinalizePromises.push( finalizePromise );
						finalizingExtensionModuleUrls.push(
							extensionModuleUrl
						);
					}
				} catch ( err ) {
					error(
						`Unable to start finalizing extension '${ extensionModuleUrl }':`,
						err
					);
				}
			}
		}

		// Wait for all extensions to finish finalizing.
		const settledFinalizePromises = await Promise.allSettled(
			extensionFinalizePromises
		);
		for ( const [
			i,
			settledFinalizePromise,
		] of settledFinalizePromises.entries() ) {
			if ( settledFinalizePromise.status === 'rejected' ) {
				error(
					`Failed to finalize extension '${ finalizingExtensionModuleUrls[ i ] }':`,
					settledFinalizePromise.reason
				);
			}
		}
	}

	// Even though the server may reject the REST API request, we still have to set the storage lock
	// because we can't look at the response when sending a beacon.
	setStorageLock( getCurrentTime() );

	if ( isDebug ) {
		log( 'Sending URL Metric:', urlMetric );
	}

	const url = new URL( restApiEndpoint );
	url.searchParams.set( 'slug', urlMetricSlug );
	url.searchParams.set( 'current_etag', currentETag );
	if ( typeof cachePurgePostId === 'number' ) {
		url.searchParams.set(
			'cache_purge_post_id',
			cachePurgePostId.toString()
		);
	}
	url.searchParams.set( 'hmac', urlMetricHMAC );
	navigator.sendBeacon(
		url,
		new Blob( [ JSON.stringify( urlMetric ) ], {
			type: 'application/json',
		} )
	);

	// Clean up.
	breadcrumbedElementsMap.clear();
}
