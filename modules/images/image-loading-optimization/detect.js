/** @typedef {import("web-vitals").LCPMetricWithAttribution} LCPMetricWithAttribution */

const consoleLogPrefix = '[Image Loading Optimization]';

const win = window;
const doc = win.document;

function log( ...message ) {
	console.log( consoleLogPrefix, ...message );
}

function warn( ...message ) {
	console.warn( consoleLogPrefix, ...message );
}

/**
 * @typedef {Object} Breadcrumb
 * @property {number} index   - Index of element among sibling elements.
 * @property {string} tagName - Tag name.
 */

/**
 * @typedef {Object} ElementBreadcrumb
 * @property {Element}      element     - Element node.
 * @property {Breadcrumb[]} breadcrumbs - Breadcrumb for the element.
 */

/**
 * Get breadcrumbed elements.
 *
 * @param {string} selector
 * @return {ElementBreadcrumb[]} Breadcrumbed elements.
 */
function getBreadcrumbedElements( selector ) {
	/** @type {ElementBreadcrumb[]} */
	const breadcrumbedElements = [];

	/** @type {HTMLCollection} */
	const elements = doc.body.querySelectorAll( selector );
	for ( const element of elements ) {
		breadcrumbedElements.push( {
			element,
			breadcrumb: getBreadcrumbs( element ),
		} );
	}

	return breadcrumbedElements;
}

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
				'Aborted detection due to being outside detection time window.'
			);
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

	// Obtain the admin bar element because we don't want to detect elements inside of it.
	const adminBar =
		/** @type {?HTMLDivElement} */ doc.getElementById( 'wpadminbar' );

	// Note that we capture an array of image elements because getElementsByTagName() returns a live HTMLCollection.
	// We also need to capture the original elements and their breadcrumbs as early as possible in case JavaScript is
	// mutating the DOM from the original HTML rendered by the server, in which case the breadcrumbs obtained from the
	// client will no longer be valid on the server.
	const breadcrumbedImages = getBreadcrumbedElements( 'img' );

	const results = {
		viewport: {
			width: win.innerWidth,
			height: win.innerHeight,
		},
		images: [],
	};

	// Ensure the DOM is loaded (although it surely already is since we're executing in a module).
	await new Promise( ( resolve ) => {
		if ( doc.readyState !== 'loading' ) {
			resolve();
		} else {
			doc.addEventListener( 'DOMContentLoaded', resolve, { once: true } );
		}
	} );

	/** @type {IntersectionObserverEntry[]} */
	const imageIntersections = [];

	/** @type {?IntersectionObserver} */
	let imageObserver;

	// Wait for the intersection observer to report back on the initially-visible images.
	// Note that the first callback will include _all_ observed entries per <https://github.com/w3c/IntersectionObserver/issues/476>.
	if ( breadcrumbedImages.length > 0 ) {
		await new Promise( ( resolve ) => {
			imageObserver = new IntersectionObserver(
				( entries ) => {
					for ( const entry of entries ) {
						if ( entry.isIntersecting ) {
							imageIntersections.push( entry );
						}
					}
					resolve();
				},
				{
					root: null, // To watch for intersection relative to the device's viewport.
					threshold: 0.0, // As soon as even one pixel is visible.
				}
			);

			for ( const breadcrumbedImage of breadcrumbedImages ) {
				if (
					! adminBar ||
					! adminBar.contains( breadcrumbedImage.element )
				) {
					imageObserver.observe( breadcrumbedImage.element );
				}
			}
		} );
	}

	// TODO: Use a local copy of web-vitals.
	const { onLCP } = await import(
		// eslint-disable-next-line import/no-unresolved
		'https://unpkg.com/web-vitals@3/dist/web-vitals.attribution.js?module'
	);

	/** @type {LCPMetricWithAttribution[]} */
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

	// Wait until the images on the page have fully loaded.
	await new Promise( ( resolve ) => {
		if ( doc.readyState === 'complete' ) {
			resolve();
		} else {
			win.addEventListener( 'load', resolve, { once: true } );
		}
	} );

	// Stop observing.
	if ( imageObserver ) {
		imageObserver.disconnect();
	}
	if ( isDebug ) {
		log( 'Detection is stopping.' );
	}

	const lcpMetric = lcpMetricCandidates.at( -1 );
	for ( const imageIntersection of imageIntersections ) {
		log(
			'imageIntersection.target',
			imageIntersection.target,
			getBreadcrumbs( imageIntersection.target ),
			lcpMetric &&
				imageIntersection.target ===
					lcpMetric.attribution.lcpEntry.element
				? 'is LCP'
				: 'is NOT LCP'
		);
	}

	log( 'lcpCandidates', lcpMetricCandidates );

	// TODO: Send data to server.
	log( results );
}
