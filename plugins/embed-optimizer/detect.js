/**
 * Embed Optimizer module for Optimization Detective
 *
 * When a URL metric is being collected by Optimization Detective, this module adds a ResizeObserver to keep track of
 * the changed heights for embed blocks. This data is amended onto the element data of the pending URL metric when it
 * is submitted for storage.
 */

const consoleLogPrefix = '[Embed Optimizer]';

/**
 * @typedef {import("../optimization-detective/types.d.ts").ElementData} ElementMetrics
 * @typedef {import("../optimization-detective/types.d.ts").URLMetric} URLMetric
 * @typedef {import("../optimization-detective/types.d.ts").Extension} Extension
 * @typedef {import("../optimization-detective/types.d.ts").InitializeCallback} InitializeCallback
 * @typedef {import("../optimization-detective/types.d.ts").InitializeArgs} InitializeArgs
 * @typedef {import("../optimization-detective/types.d.ts").FinalizeArgs} FinalizeArgs
 * @typedef {import("../optimization-detective/types.d.ts").FinalizeCallback} FinalizeCallback
 */

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
 * Embed element heights.
 *
 * @type {Map<string, DOMRectReadOnly>}
 */
const loadedElementContentRects = new Map();

/**
 * Initialize.
 *
 * @type {InitializeCallback}
 * @param {InitializeArgs} args Args.
 */
export async function initialize( { isDebug } ) {
	const embedWrappers =
		/** @type NodeListOf<HTMLDivElement> */ document.querySelectorAll(
			'.wp-block-embed > .wp-block-embed__wrapper[data-od-xpath]'
		);

	for ( const embedWrapper of embedWrappers ) {
		monitorEmbedWrapperForResizes( embedWrapper, isDebug );
	}

	if ( isDebug ) {
		log( 'Loaded embed content rects:', loadedElementContentRects );
	}
}

/**
 * Finalize.
 *
 * @type {FinalizeCallback}
 * @param {FinalizeArgs} args Args.
 */
export async function finalize( {
	urlMetric,
	isDebug,
	getElementData,
	amendElementData,
} ) {
	if ( isDebug ) {
		log( 'URL metric to be sent:', urlMetric );
	}

	for ( const [ xpath, domRect ] of loadedElementContentRects.entries() ) {
		if (
			amendElementData( xpath, { resizedBoundingClientRect: domRect } )
		) {
			const elementData = getElementData( xpath );
			if ( isDebug ) {
				log(
					`boundingClientRect for ${ xpath } resized:`,
					elementData.boundingClientRect,
					'=>',
					elementData.resizedBoundingClientRect
				);
			}
		} else if ( isDebug ) {
			log( `Unable to amend element data for ${ xpath }` );
		}
	}
}

/**
 * Monitors embed wrapper for resizes.
 *
 * @param {HTMLDivElement} embedWrapper Embed wrapper DIV.
 * @param {boolean}        isDebug      Whether debug.
 */
function monitorEmbedWrapperForResizes( embedWrapper, isDebug ) {
	if ( ! ( 'odXpath' in embedWrapper.dataset ) ) {
		throw new Error( 'Embed wrapper missing data-od-xpath attribute.' );
	}
	const xpath = embedWrapper.dataset.odXpath;
	const observer = new ResizeObserver( ( entries ) => {
		const [ entry ] = entries;
		loadedElementContentRects.set( xpath, entry.contentRect );
		if ( isDebug ) {
			log( `Resized element ${ xpath }:`, entry.contentRect );
		}
	} );
	observer.observe( embedWrapper, { box: 'content-box' } );
}