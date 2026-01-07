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
 * @typedef {import("./types.ts").GetRootDataFunction} GetRootDataFunction
 * @typedef {import("./types.ts").ExtendRootDataFunction} ExtendRootDataFunction
 * @typedef {import("./types.ts").GetElementDataFunction} GetElementDataFunction
 * @typedef {import("./types.ts").ExtendElementDataFunction} ExtendElementDataFunction
 * @typedef {import("./types.ts").Logger} Logger
 */

/**
 * Window reference to reduce size when the script is minified.
 *
 * @type {Window}
 */
const win = window;

/**
 * Document reference to reduce size when the script is minified.
 *
 * @type {Document}
 */
const doc = win.document;

/**
 * Prefix which is prepended to all messages logged to the console.
 *
 * @see {createLogger}
 * @type {string}
 */
const consoleLogPrefix = '[Optimization Detective]';

/**
 * Session storage key for client-side storage lock to prevent clients attempting to submit URL Metrics when there is a server-side storage lock.
 *
 * @see {isStorageLocked}
 * @see {setStorageLock}
 * @type {string}
 */
const storageLockTimeSessionKey = 'odStorageLockTime';

/**
 * Wait duration in milliseconds for debounced calls to re-compress the URL Metric JSON data.
 *
 * @see {debounceCompressUrlMetric}
 * @type {number}
 */
const compressionDebounceWaitDuration = 1000;

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
 * @param {boolean} [debugMode=false]      - Whether all messages should be logged. If false, then only errors are logged.
 * @param {?string} [prefix=null]          - Prefix to prepend to the console message.
 * @param {?string} [scriptModuleUrl=null] - The URL for the script module which is emitting the log. This is used for extensions.
 * @return {Logger} Logger object with log, info, warn, and error methods.
 */
function createLogger(
	debugMode = false,
	prefix = null,
	scriptModuleUrl = null
) {
	const logSource = scriptModuleUrl ? `\nSource: ${ scriptModuleUrl }` : null;

	/**
	 * Constructs the args to pass to the logging function.
	 *
	 * @param {Array}   message       - The message(s) to log.
	 * @param {boolean} includeSource - Whether to include the source. This should be true for warnings or errors.
	 * @return {Array} Amended message.
	 */
	const constructLogArgs = ( message, includeSource = false ) => {
		return [ prefix, ...message, includeSource ? logSource : null ].filter(
			( value ) => value !== null
		);
	};

	return {
		/**
		 * Logs a message if debug mode is enabled.
		 *
		 * @param {...*} message - The message(s) to log.
		 */
		log( ...message ) {
			if ( debugMode ) {
				// eslint-disable-next-line no-console
				console.log( ...constructLogArgs( message, false ) );
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
				console.info( ...constructLogArgs( message, false ) );
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
				console.warn( ...constructLogArgs( message, true ) );
			}
		},

		/**
		 * Logs an error.
		 *
		 * @param {...*} message - The message(s) to log as an error.
		 */
		error( ...message ) {
			// eslint-disable-next-line no-console
			console.error( ...constructLogArgs( message, true ) );
		},
	};
}

/**
 * Attempts to get the extension name (i.e. slug for plugin or theme) from the script module URL.
 *
 * If extraction of the slug fails, then the entire URL is returned.
 *
 * @param {string} scriptModuleUrl - Script module URL.
 * @return {string} Derived extension name.
 */
function getExtensionNameFromScriptModuleUrl( scriptModuleUrl ) {
	try {
		const url = new URL( scriptModuleUrl, win.location.href );
		const matches = url.pathname.match(
			/\/(?:themes|plugins)\/([^\/]+)\//
		);
		if ( matches ) {
			return matches[ 1 ];
		}
		return url.pathname;
	} catch ( err ) {
		return scriptModuleUrl;
	}
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
	if ( ! win.crypto || ! win.crypto.subtle ) {
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
 * @type {GetRootDataFunction}
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
 * @type {ExtendRootDataFunction}
 * @param {ExtendedRootData} properties
 */
function extendRootData( properties ) {
	for ( const key of Object.getOwnPropertyNames( properties ) ) {
		if ( reservedRootPropertyKeys.has( key ) ) {
			throw new Error( `Disallowed setting of key '${ key }' on root.` );
		}
	}
	Object.assign( urlMetric, properties );
	debounceCompressUrlMetric();
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
 * @type {GetElementDataFunction}
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
 * @type {ExtendElementDataFunction}
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
	debounceCompressUrlMetric();
}

/**
 * Compresses a JSON string using CompressionStream API.
 *
 * @param {string} jsonString - JSON string to compress.
 * @return {Promise<Blob>} Compressed data.
 */
async function compress( jsonString ) {
	const encodedData = new TextEncoder().encode( jsonString );
	const compressedDataStream = new Blob( [ encodedData ] )
		.stream()
		.pipeThrough( new CompressionStream( 'gzip' ) );
	const compressedDataBuffer = await new Response(
		compressedDataStream
	).arrayBuffer();
	return new Blob( [ compressedDataBuffer ], { type: 'application/gzip' } );
}

/**
 * The compressed URL metric data.
 *
 * @see {debounceCompressUrlMetric}
 * @type {?Blob}
 */
let compressedPayload = null;

/**
 * Timeout ID for debouncing URL metric compression.
 *
 * @see {debounceCompressUrlMetric}
 * @type {?ReturnType<typeof setTimeout>}
 */
let recompressionTimeout = null;

/**
 * Handle for requestIdleCallback for URL metric compression.
 *
 * @see {debounceCompressUrlMetric}
 * @type {?number}
 */
let idleCallbackHandle = null;

/**
 * Whether compression is enabled.
 *
 * @see {detect}
 * @see {debounceCompressUrlMetric}
 * @type {boolean}
 */
let compressionEnabled = true;

/**
 * Debounces the compression of the URL Metric.
 */
function debounceCompressUrlMetric() {
	if ( ! compressionEnabled ) {
		return;
	}
	if ( null !== recompressionTimeout ) {
		clearTimeout( recompressionTimeout );
		recompressionTimeout = null;
	}
	if (
		null !== idleCallbackHandle &&
		typeof cancelIdleCallback === 'function'
	) {
		cancelIdleCallback( idleCallbackHandle );
		idleCallbackHandle = null;
	}
	recompressionTimeout = setTimeout( async () => {
		if ( typeof requestIdleCallback === 'function' ) {
			await new Promise( ( resolve ) => {
				idleCallbackHandle = requestIdleCallback( resolve );
			} );
			idleCallbackHandle = null;
		}
		try {
			compressedPayload = await compress( JSON.stringify( urlMetric ) );
		} catch ( err ) {
			const { error } = createLogger( false, consoleLogPrefix );
			error(
				'Failed to compress URL Metric falling back to sending uncompressed data:',
				err
			);
			compressionEnabled = false;
		}
		recompressionTimeout = null;
	}, compressionDebounceWaitDuration );
}

/**
 * @typedef {{timestamp: number, creationDate: Date}} UrlMetricDebugData
 * @typedef {{groups: Array<{url_metrics: Array<UrlMetricDebugData>}>}} CollectionDebugData
 */

/**
 * Args for the detect function.
 *
 * @since 1.0.0
 *
 * @typedef {Object}                  DetectFunctionArgs
 * @property {string[]}               extensionModuleUrls        - URLs for extension script modules to import.
 * @property {number}                 minViewportAspectRatio     - Minimum aspect ratio allowed for the viewport.
 * @property {number}                 maxViewportAspectRatio     - Maximum aspect ratio allowed for the viewport.
 * @property {boolean}                isDebug                    - Whether to show debug messages.
 * @property {string}                 restApiEndpoint            - URL for where to send the detection data.
 * @property {string}                 [restApiNonce]             - Nonce for the REST API when the user is logged-in.
 * @property {boolean}                gzdecodeAvailable          - Whether application/gzip can be sent to the REST API.
 * @property {number}                 maxUrlMetricSize           - Maximum size of the URL Metric to send.
 * @property {string}                 currentETag                - Current ETag.
 * @property {string}                 currentUrl                 - Current URL.
 * @property {string}                 urlMetricSlug              - Slug for URL Metric.
 * @property {number|null}            cachePurgePostId           - Cache purge post ID.
 * @property {string}                 urlMetricHMAC              - HMAC for URL Metric storage.
 * @property {URLMetricGroupStatus[]} urlMetricGroupStatuses     - URL Metric group statuses.
 * @property {number}                 storageLockTTL             - The TTL (in seconds) for the URL Metric storage lock.
 * @property {number}                 freshnessTTL               - The freshness age (TTL) for a given URL Metric.
 * @property {string}                 webVitalsLibrarySrc        - The URL for the web-vitals library.
 * @property {CollectionDebugData}    [urlMetricGroupCollection] - URL Metric group collection, when in debug mode.
 */

/**
 * The detect function.
 *
 * @since 1.0.0
 * @callback DetectFunction
 * @param {DetectFunctionArgs} args - The arguments for the function.
 * @return {Promise<void>}
 */

/**
 * Detects the LCP element, loaded images, client viewport, and store for future optimizations.
 *
 * @type {DetectFunction}
 * @param {DetectFunctionArgs} args - Args.
 */
export default async function detect( {
	minViewportAspectRatio,
	maxViewportAspectRatio,
	isDebug,
	extensionModuleUrls,
	restApiEndpoint,
	restApiNonce,
	gzdecodeAvailable,
	maxUrlMetricSize,
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
	compressionEnabled = gzdecodeAvailable;

	if ( isDebug && Array.isArray( urlMetricGroupCollection?.groups ) ) {
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

	if ( doc.visibilityState === 'hidden' && ! doc.prerendering ) {
		log( 'Page opened in background tab so URL Metric is not collected.' );
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
			( freshnessTTL < 0 ||
				( getCurrentTime() - previousVisitTime ) / 1000 < freshnessTTL )
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

	// TODO: Does this make sense here? Should it be moved up above the isViewportNeeded condition?
	// As an alternative to this, the od_print_detection_script() function can short-circuit if the
	// od_is_url_metric_storage_locked() function returns true. However, the downside with that is page caching could
	// result in metrics missed from being gathered when a user navigates around a site and primes the page cache.
	if ( isStorageLocked( getCurrentTime(), storageLockTTL ) ) {
		warn( 'Aborted detection due to storage being locked.' );
		return;
	}

	// Keep track of whether the window resized. If it was resized, we abort sending the URLMetric.
	let didWindowResize = false;
	win.addEventListener(
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

	const breadcrumbedElements = doc.body.querySelectorAll( '[data-od-xpath]' );

	/** @type {Map<Element, string>} */
	const breadcrumbedElementsMap = new Map(
		[ ...breadcrumbedElements ].map(
			/**
			 * @param {Element} element
			 * @return {[Element, string]} Tuple of an element and its XPath.
			 */
			( element ) => [ element, element.getAttribute( 'data-od-xpath' ) ]
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

	// Wait for the intersection observer to report back on the initially visible elements.
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

	// Get at least one LCP candidate. More may be reported before the page finishes loading.
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
				// This avoids needing to click to finalize the LCP candidate. While this is helpful for testing, it also
				// ensures that we always get an LCP candidate reported. Otherwise, the callback may never fire if the
				// user never does a click or keydown, per <https://github.com/GoogleChrome/web-vitals/blob/07f6f96/src/onLCP.ts#L99-L107>.
				reportAllChanges: true,
			}
		);
	} );

	// Stop observing the initial viewport.
	disconnectIntersectionObserver();

	urlMetric = {
		url: currentUrl,
		viewport: {
			width: win.innerWidth,
			height: win.innerHeight,
		},
		elements: [],
	};

	const lcpMetric = lcpMetricCandidates[ lcpMetricCandidates.length - 1 ];

	// Populate the elements in the URL Metric.
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
	breadcrumbedElementsMap.clear(); // No longer needed.

	/**
	 * Initialize extensions.
	 */

	/** @type {Map<string, Extension>} */
	const extensions = new Map();

	/** @type {boolean} */
	let extensionHasFinalize = false;

	/** @type {Promise[]} */
	const extensionInitializePromises = [];

	/** @type {string[]} */
	const initializingExtensionModuleUrls = [];

	// Load all extensions in parallel.
	await Promise.all(
		extensionModuleUrls.map( async ( extensionModuleUrl ) => {
			const extension = /** @type {Extension} */ await import(
				extensionModuleUrl
			);
			extensions.set( extensionModuleUrl, extension );
		} )
	);

	// Initialize extensions.
	for ( const [ extensionModuleUrl, extension ] of extensions.entries() ) {
		try {
			const extensionLogger = createLogger(
				isDebug,
				`[Optimization Detective: ${
					extension.name ||
					getExtensionNameFromScriptModuleUrl( extensionModuleUrl )
				}]`,
				extensionModuleUrl
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
					getRootData,
					extendRootData,
					getElementData,
					extendElementData,
				} );
				if ( initializePromise instanceof Promise ) {
					extensionInitializePromises.push( initializePromise );
					initializingExtensionModuleUrls.push( extensionModuleUrl );
				}
			}

			if ( extension.finalize instanceof Function ) {
				extensionLogger.warn(
					'Use of the finalize function in extensions is deprecated. Please refactor your extension to use the initialize function instead, and update the URL Metric data as soon as a change is detected rather than waiting until finalization.'
				);
				extensionHasFinalize = true;
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

	if ( compressionEnabled && extensionHasFinalize ) {
		compressionEnabled = false;
		warn(
			'URL Metric compression is disabled because one or more extensions use the deprecated finalize function.'
		);
	}

	log( 'Current URL Metric:', urlMetric );

	// Compress the URL Metric once so that even if there are no extensions available or extending the URL Metric, it is compressed.
	debounceCompressUrlMetric();

	// Wait for the page to be hidden.
	await new Promise( ( resolve ) => {
		win.addEventListener( 'pagehide', resolve, { once: true } );
		win.addEventListener( 'pageswap', resolve, { once: true } );
		doc.addEventListener(
			'visibilitychange',
			() => {
				if ( doc.visibilityState === 'hidden' ) {
					// TODO: This will fire even when switching tabs.
					resolve();
				}
			},
			{ once: true }
		);
	} );

	// Only proceed with submitting the URL Metric if the viewport stayed the same size. Changing the viewport size (e.g. due
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
						extension.name ||
						getExtensionNameFromScriptModuleUrl(
							extensionModuleUrl
						)
					}]`,
					extensionModuleUrl
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
	 * Now prepare the URL Metric to be sent in the JSON request body.
	 */

	const maxBodyLengthKiB = 64;
	const maxBodyLengthBytes = maxBodyLengthKiB * 1024;

	const jsonBody = JSON.stringify( urlMetric );
	if ( jsonBody.length > maxUrlMetricSize ) {
		error(
			`URL Metric is ${ jsonBody.length.toLocaleString() } bytes, exceeding the maximum size of ${ maxUrlMetricSize.toLocaleString() } bytes:`,
			urlMetric
		);
		return;
	}
	compressionEnabled = compressionEnabled && null !== compressedPayload;
	const payloadBlob = compressionEnabled
		? compressedPayload
		: new Blob( [ jsonBody ], { type: 'application/json' } );
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

	let message = 'Sending URL Metric (';
	message += `${ payloadBlob.size.toLocaleString() } bytes`;
	message += `, ${ Math.round(
		percentOfBudget
	) }% of ${ maxBodyLengthKiB } KiB limit`;
	if ( compressionEnabled ) {
		message += `, gzip compressed -${ Math.round(
			( ( jsonBody.length - payloadBlob.size ) / jsonBody.length ) * 100
		) }%`;
	} else {
		message += ', uncompressed';
	}
	message += '):';

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

	const headers = {
		'Content-Type': 'application/json',
	};
	if ( compressionEnabled ) {
		headers[ 'Content-Encoding' ] = 'gzip';
	}

	const request = new Request( url, {
		method: 'POST',
		body: payloadBlob,
		headers,
		keepalive: true, // This makes fetch() behave the same as navigator.sendBeacon().
	} );
	await fetch( request );
}
