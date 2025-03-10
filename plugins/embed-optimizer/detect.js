/**
 * Embed Optimizer module for Optimization Detective
 *
 * When a URL Metric is being collected by Optimization Detective, this module adds a ResizeObserver to keep track of
 * the changed heights for embed blocks. This data is extended/amended onto the element data of the pending URL Metric
 * when it is submitted for storage.
 */

export const name = 'Embed Optimizer';

/**
 * @typedef {import("../optimization-detective/types.ts").URLMetric} URLMetric
 * @typedef {import("../optimization-detective/types.ts").Extension} Extension
 * @typedef {import("../optimization-detective/types.ts").InitializeCallback} InitializeCallback
 * @typedef {import("../optimization-detective/types.ts").InitializeArgs} InitializeArgs
 * @typedef {import("../optimization-detective/types.ts").FinalizeArgs} FinalizeArgs
 * @typedef {import("../optimization-detective/types.ts").FinalizeCallback} FinalizeCallback
 * @typedef {import("../optimization-detective/types.ts").ExtendedElementData} ExtendedElementData
 * @typedef {import("../optimization-detective/types.ts").LogFunction} LogFunction
 */

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
export async function initialize( { log: _log } ) {
	// eslint-disable-next-line no-console
	const log = _log || console.log; // TODO: Remove once Optimization Detective likely updated, or when strict version requirement added in od_init action.

	/** @type NodeListOf<HTMLDivElement> */
	const embedWrappers = document.querySelectorAll(
		'.wp-block-embed > .wp-block-embed__wrapper[data-od-xpath]'
	);

	for ( /** @type {HTMLElement} */ const embedWrapper of embedWrappers ) {
		monitorEmbedWrapperForResizes( embedWrapper, log );
	}

	log( 'Loaded embed content rects:', loadedElementContentRects );
}

/**
 * Finalizes extension.
 *
 * @type {FinalizeCallback}
 * @param {FinalizeArgs} args Args.
 */
export async function finalize( {
	log: _log,
	error: _error,
	getElementData,
	extendElementData,
} ) {
	/* eslint-disable no-console */
	// TODO: Remove once Optimization Detective likely updated, or when strict version requirement added in od_init action.
	const log = _log || console.log;
	const error = _error || console.error;
	/* eslint-enable no-console */

	for ( const [ xpath, domRect ] of loadedElementContentRects.entries() ) {
		try {
			extendElementData( xpath, {
				resizedBoundingClientRect: domRect,
			} );
			const elementData = getElementData( xpath );
			log(
				`boundingClientRect for ${ xpath } resized:`,
				elementData.boundingClientRect,
				'=>',
				domRect
			);
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
 * @param {LogFunction}    log          The function to call with log messages.
 */
function monitorEmbedWrapperForResizes( embedWrapper, log ) {
	if ( ! ( 'odXpath' in embedWrapper.dataset ) ) {
		throw new Error( 'Embed wrapper missing data-od-xpath attribute.' );
	}
	const xpath = embedWrapper.dataset.odXpath;
	const observer = new ResizeObserver( ( entries ) => {
		const [ entry ] = entries;
		loadedElementContentRects.set( xpath, entry.contentRect );
		log( `Resized element ${ xpath }:`, entry.contentRect );
	} );
	observer.observe( embedWrapper, { box: 'content-box' } );
}
