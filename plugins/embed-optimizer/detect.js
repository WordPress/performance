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
 * @typedef {import("../optimization-detective/types.ts").GetElementDataFunction} GetElementDataFunction
 * @typedef {import("../optimization-detective/types.ts").ExtendElementDataFunction} ExtendElementDataFunction
 * @typedef {import("../optimization-detective/types.ts").ExtendedElementData} ExtendedElementData
 * @typedef {import("../optimization-detective/types.ts").LogFunction} LogFunction
 */

/**
 * Initializes extension.
 *
 * @type {InitializeCallback}
 * @param {InitializeArgs} args Args.
 */
export async function initialize( {
	log,
	error,
	getElementData,
	extendElementData,
} ) {
	/** @type NodeListOf<HTMLDivElement> */
	const embedWrappers = document.querySelectorAll(
		'.wp-block-embed > .wp-block-embed__wrapper[data-od-xpath]'
	);

	for ( /** @type {HTMLElement} */ const embedWrapper of embedWrappers ) {
		monitorEmbedWrapperForResizes(
			embedWrapper,
			extendElementData,
			getElementData,
			log,
			error
		);
	}
}

/**
 * Monitors embed wrapper for resizes.
 *
 * @param {HTMLDivElement}            embedWrapper      - Embed wrapper DIV.
 * @param {ExtendElementDataFunction} extendElementData - Function to extend element data with.
 * @param {GetElementDataFunction}    getElementData    - Function to get element data.
 * @param {LogFunction}               log               - The function to call with log messages.
 * @param {LogFunction}               error             - The function to call with error messages.
 */
function monitorEmbedWrapperForResizes(
	embedWrapper,
	extendElementData,
	getElementData,
	log,
	error
) {
	if ( ! ( 'odXpath' in embedWrapper.dataset ) ) {
		throw new Error( 'Embed wrapper missing data-od-xpath attribute.' );
	}
	const xpath = embedWrapper.dataset.odXpath;
	const observer = new ResizeObserver( ( entries ) => {
		const [ entry ] = entries;

		try {
			extendElementData( xpath, {
				resizedBoundingClientRect: entry.contentRect,
			} );
			const elementData = getElementData( xpath );
			log(
				`Resized element ${ xpath }:`,
				elementData.boundingClientRect,
				'=>',
				entry.contentRect
			);
		} catch ( err ) {
			error(
				`Failed to extend element data for ${ xpath } with resizedBoundingClientRect:`,
				entry.contentRect,
				err
			);
		}
	} );
	observer.observe( embedWrapper, { box: 'content-box' } );
}
