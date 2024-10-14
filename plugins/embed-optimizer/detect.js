/**
 * Embed Optimizer module for Optimization Detective
 *
 * When a URL metric is being collected by Optimization Detective, this module adds a ResizeObserver to keep track of
 * the changed heights for embed blocks. This data is extended/amended onto the element data of the pending URL metric
 * when it is submitted for storage.
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
 * @typedef {import("../optimization-detective/types.d.ts").ExtendedElementData} ExtendedElementData
 */

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
 * Logs an error.
 *
 * @param {...*} message
 */
function error( ...message ) {
	// eslint-disable-next-line no-console
	console.error( consoleLogPrefix, ...message );
}

/**
 * Embed element heights.
 *
 * @type {Map<string, DOMRectReadOnly>}
 */
const loadedElementContentRects = new Map();

/**
 * Initializes extension.
 *
 * @type {InitializeCallback}
 * @param {InitializeArgs} args Args.
 */
export function initialize( { isDebug } ) {
	/** @type NodeListOf<HTMLDivElement> */
	const embedWrappers = document.querySelectorAll(
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
 * Finalizes extension.
 *
 * @type {FinalizeCallback}
 * @param {FinalizeArgs} args Args.
 */
export async function finalize( {
	isDebug,
	getElementData,
	extendElementData,
} ) {
	for ( const [ xpath, domRect ] of loadedElementContentRects.entries() ) {
		try {
			extendElementData( xpath, {
				resizedBoundingClientRect: domRect,
			} );
			if ( isDebug ) {
				const elementData = getElementData( xpath );
				log(
					`boundingClientRect for ${ xpath } resized:`,
					elementData.boundingClientRect,
					'=>',
					domRect
				);
			}
		} catch ( err ) {
			error(
				`Failed to extend element data for ${ xpath } with resizedBoundingClientRect:`,
				domRect,
				err
			);
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
