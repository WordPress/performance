// noinspection JSUnusedGlobalSymbols

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
 * @typedef {import("./types.ts").Logger} Logger
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
 * Creates a logger object with log, warn, and error methods.
 *
 * @param {boolean} [debugMode=false] - Whether to enable debug mode.
 * @param {string}  [prefix='']       - Prefix to prepend to the console message.
 * @return {Logger} Logger object with log, info, warn, and error methods.
 */
function createLogger( debugMode = false, prefix = '' ) {
	return {
		/**
		 * Logs a message if debug mode is enabled.
		 *
		 * @param {...*} message - The message(s) to log.
		 */
		log( ...message ) {
			if ( debugMode ) {
				// eslint-disable-next-line no-console
				console.log( prefix, ...message );
			}
		},

		/**
		 * Logs an informational message if debug mode is enabled.
		 *
		 * @param {...*} message - The message(s) to log as info.
		 */
		info( ...message ) {
			if ( debugMode ) {
				// eslint-disable-next-line no-console
				console.info( prefix, ...message );
			}
		},

		/**
		 * Logs a warning if debug mode is enabled.
		 *
		 * @param {...*} message - The message(s) to log as a warning.
		 */
		warn( ...message ) {
			if ( debugMode ) {
				// eslint-disable-next-line no-console
				console.warn( prefix, ...message );
			}
		},

		/**
		 * Logs an error.
		 *
		 * @param {...*} message - The message(s) to log as an error.
		 */
		error( ...message ) {
			// eslint-disable-next-line no-console
			console.error( prefix, ...message );
		},
	};
}

/**
 * Gets the status for the URL Metric group for the provided viewport width.
 *
 * The comparison logic here corresponds with the PHP logic in `OD_URL_Metric_Group::is_viewport_width_in_range()`.
 * This function is also similar to the PHP logic in `\OD_URL_Metric_Group_Collection::get_group_for_viewport_width()`.
 *
 * @param {number}                 viewportWidth          - Current viewport width.
 * @param {URLMetricGroupStatus[]} urlMetricGroupStatuses - Viewport group statuses.
 * @return {URLMetricGroupStatus} The URL metric group for the viewport width.
 */
function getGroupForViewportWidth( viewportWidth, urlMetricGroupStatuses ) {
	for ( const urlMetricGroupStatus of urlMetricGroupStatuses ) {
		if (
			viewportWidth > urlMetricGroupStatus.minimumViewportWidth &&
			( null === urlMetricGroupStatus.maximumViewportWidth ||
				viewportWidth <= urlMetricGroupStatus.maximumViewportWidth )
		) {
			return urlMetricGroupStatus;
		}
	}
	throw new Error(
		`${ consoleLogPrefix } Unexpectedly unable to locate group for the current viewport width.`
	);
}

/**
 * Gets the sessionStorage key for keeping track of whether the current client session already submitted a URL Metric.
 *
 * @param {string}               currentETag          - Current ETag.
 * @param {string}               currentUrl           - Current URL.
 * @param {URLMetricGroupStatus} urlMetricGroupStatus - URL Metric group status.
 * @param {Logger}               logger               - Logger.
 * @return {Promise<string|null>} Session storage key for the current URL or null if crypto is not available or caused an error.
 */
async function getAlreadySubmittedSessionStorageKey(
	currentETag,
	currentUrl,
	urlMetricGroupStatus,
	{ warn, error }
) {
	if ( ! window.crypto || ! window.crypto.subtle ) {
		warn(
			'Unable to generate sessionStorage key for already-submitted URL since crypto is not available, likely due to to the page not being served via HTTPS.'
		);
		return null;
	}

	try {
		const message = [
			currentETag,
			currentUrl,
			urlMetricGroupStatus.minimumViewportWidth,
			urlMetricGroupStatus.maximumViewportWidth || '',
		].join( '-' );

		/*
		 * Note that the components are hashed for a couple of reasons:
		 *
		 * 1. It results in a consistent length string devoid of any special characters that could cause problems.
		 * 2. Since the key includes the URL, hashing it avoids potential privacy concerns where the sessionStorage is
		 *    examined to see which URLs the client went to.
		 *
		 * The SHA-1 algorithm is chosen since it is the fastest and there is no need for cryptographic security.
		 */
		const msgBuffer = new TextEncoder().encode( message );
		const hashBuffer = await crypto.subtle.digest( 'SHA-1', msgBuffer );
		const hashHex = Array.from( new Uint8Array( hashBuffer ) )
			.map( ( b ) => b.toString( 16 ).padStart( 2, '0' ) )
			.join( '' );
		return `odSubmitted-${ hashHex }`;
	} catch ( err ) {
		error(
			'Unable to generate sessionStorage key for already-submitted URL due to error:',
			err
		);
		return null;
	}
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
 * @param {Object} obj - Object to recursively freeze.
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
 * @param {string} xpath - XPath.
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
 * @param {string}              xpath      - XPath.
 * @param {ExtendedElementData} properties - Properties.
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
 * @param {Object}                 args                            - Args.
 * @param {string[]}               args.extensionModuleUrls        - URLs for extension script modules to import.
 * @param {number}                 args.minViewportAspectRatio     - Minimum aspect ratio allowed for the viewport.
 * @param {number}                 args.maxViewportAspectRatio     - Maximum aspect ratio allowed for the viewport.
 * @param {boolean}                args.isDebug                    - Whether to show debug messages.
 * @param {string}                 args.restApiEndpoint            - URL for where to send the detection data.
 * @param {string}                 [args.restApiNonce]             - Nonce for the REST API when the user is logged-in.
 * @param {string}                 args.currentETag                - Current ETag.
 * @param {string}                 args.currentUrl                 - Current URL.
 * @param {string}                 args.urlMetricSlug              - Slug for URL Metric.
 * @param {number|null}            args.cachePurgePostId           - Cache purge post ID.
 * @param {string}                 args.urlMetricHMAC              - HMAC for URL Metric storage.
 * @param {URLMetricGroupStatus[]} args.urlMetricGroupStatuses     - URL Metric group statuses.
 * @param {number}                 args.storageLockTTL             - The TTL (in seconds) for the URL Metric storage lock.
 * @param {number}                 args.freshnessTTL               - The freshness age (TTL) for a given URL Metric.
 * @param {string}                 args.webVitalsLibrarySrc        - The URL for the web-vitals library.
 * @param {CollectionDebugData}    [args.urlMetricGroupCollection] - URL Metric group collection, when in debug mode.
 */
export default async function detect( {
	minViewportAspectRatio,
	maxViewportAspectRatio,
	isDebug,
	extensionModuleUrls,
	restApiEndpoint,
	restApiNonce,
	currentETag,
	currentUrl,
	urlMetricSlug,
	cachePurgePostId,
	urlMetricHMAC,
	urlMetricGroupStatuses,
	storageLockTTL,
	freshnessTTL,
	webVitalsLibrarySrc,
	urlMetricGroupCollection,
} ) {
	const logger = createLogger( isDebug, consoleLogPrefix );
	const { log, warn, error } = logger;

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

	if ( win.innerWidth === 0 || win.innerHeight === 0 ) {
		log(
			'Window must have non-zero dimensions for URL Metric collection.'
		);
		return;
	}

	// Abort if the current viewport is not among those which need URL Metrics.
	const urlMetricGroupStatus = getGroupForViewportWidth(
		win.innerWidth,
		urlMetricGroupStatuses
	);
	if ( urlMetricGroupStatus.complete ) {
		log( 'No need for URL Metrics from the current viewport.' );
		return;
	}

	// Abort if the client already submitted a URL Metric for this URL and viewport group.
	const alreadySubmittedSessionStorageKey =
		await getAlreadySubmittedSessionStorageKey(
			currentETag,
			currentUrl,
			urlMetricGroupStatus,
			logger
		);
	if (
		null !== alreadySubmittedSessionStorageKey &&
		alreadySubmittedSessionStorageKey in sessionStorage
	) {
		const previousVisitTime = parseInt(
			sessionStorage.getItem( alreadySubmittedSessionStorageKey ),
			10
		);
		if (
			! isNaN( previousVisitTime ) &&
			( getCurrentTime() - previousVisitTime ) / 1000 < freshnessTTL
		) {
			log(
				'The current client session already submitted a fresh URL Metric for this URL so a new one will not be collected now.'
			);
			return;
		}
	}

	// Abort if the viewport aspect ratio is not in a common range.
	const aspectRatio = win.innerWidth / win.innerHeight;
	if (
		aspectRatio < minViewportAspectRatio ||
		aspectRatio > maxViewportAspectRatio
	) {
		warn(
			`Viewport aspect ratio (${ aspectRatio }) is not in the accepted range of ${ minViewportAspectRatio } to ${ maxViewportAspectRatio }.`
		);
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
		warn( 'Aborted detection due to storage being locked.' );
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
		warn(
			'Aborted detection since initial scroll position of page is not at the top.'
		);
		return;
	}

	log( 'Proceeding with detection' );

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

			const extensionLogger = createLogger(
				isDebug,
				`[Optimization Detective: ${
					extension.name || 'Unnamed Extension'
				}]`
			);

			// TODO: There should to be a way to pass additional args into the module. Perhaps extensionModuleUrls should be a mapping of URLs to args.
			if ( extension.initialize instanceof Function ) {
				const initializePromise = extension.initialize( {
					isDebug,
					...extensionLogger,
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
	log( 'Detection is stopping.' );

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
			warn( 'Unable to look up XPath for element' );
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

	log( 'Current URL Metric:', urlMetric );

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
		log( 'Aborting URL Metric collection due to viewport size change.' );
		return;
	}

	// Finalize extensions.
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
				const extensionLogger = createLogger(
					isDebug,
					`[Optimization Detective: ${
						extension.name || 'Unnamed Extension'
					}]`
				);

				try {
					const finalizePromise = extension.finalize( {
						isDebug,
						...extensionLogger,
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

	/*
	 * Now prepare the URL Metric to be sent as JSON request body.
	 */

	const maxBodyLengthKiB = 64;
	const maxBodyLengthBytes = maxBodyLengthKiB * 1024;

	const jsonBody = JSON.stringify( urlMetric );
	const payloadBlob = new Blob( [ jsonBody ], { type: 'application/json' } );
	const percentOfBudget =
		( payloadBlob.size / ( maxBodyLengthKiB * 1000 ) ) * 100;

	/*
	 * According to the fetch() spec:
	 * "If the sum of contentLength and inflightKeepaliveBytes is greater than 64 kibibytes, then return a network error."
	 * This is what browsers also implement for navigator.sendBeacon(). Therefore, if the size of the JSON is greater
	 * than the maximum, we should avoid even trying to send it.
	 */
	if ( payloadBlob.size > maxBodyLengthBytes ) {
		error(
			`Unable to send URL Metric because it is ${ payloadBlob.size.toLocaleString() } bytes, ${ Math.round(
				percentOfBudget
			) }% of ${ maxBodyLengthKiB } KiB limit:`,
			urlMetric
		);
		return;
	}

	// Even though the server may reject the REST API request, we still have to set the storage lock
	// because we can't look at the response when sending a beacon.
	setStorageLock( getCurrentTime() );

	// Remember that the URL Metric was submitted for this URL to avoid having multiple entries submitted by the same client.
	if ( null !== alreadySubmittedSessionStorageKey ) {
		sessionStorage.setItem(
			alreadySubmittedSessionStorageKey,
			String( getCurrentTime() )
		);
	}

	const message = `Sending URL Metric (${ payloadBlob.size.toLocaleString() } bytes, ${ Math.round(
		percentOfBudget
	) }% of ${ maxBodyLengthKiB } KiB limit):`;

	// The threshold of 50% is used because the limit for all beacons combined is 64 KiB, not just the data for one beacon.
	if ( percentOfBudget < 50 ) {
		log( message, urlMetric );
	} else {
		warn( message, urlMetric );
	}

	const url = new URL( restApiEndpoint );
	if ( typeof restApiNonce === 'string' ) {
		url.searchParams.set( '_wpnonce', restApiNonce );
	}
	url.searchParams.set( 'slug', urlMetricSlug );
	url.searchParams.set( 'current_etag', currentETag );
	if ( typeof cachePurgePostId === 'number' ) {
		url.searchParams.set(
			'cache_purge_post_id',
			cachePurgePostId.toString()
		);
	}
	url.searchParams.set( 'hmac', urlMetricHMAC );
	navigator.sendBeacon( url, payloadBlob );

	// Clean up.
	breadcrumbedElementsMap.clear();
}
