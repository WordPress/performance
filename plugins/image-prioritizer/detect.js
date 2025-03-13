/**
 * Image Prioritizer module for Optimization Detective
 *
 * This extension to Optimization Detective captures the LCP element's CSS background image which is not defined with
 * an inline style attribute but rather in either an external stylesheet loaded with a LINK tag or by stylesheet in
 * a STYLE element. The URL for this LCP background image and the tag's name, ID, and class are all amended to the
 * stored URL Metric so that a responsive preload link with fetchpriority=high will be added for that background image
 * once a URL Metric group is fully populated with URL Metrics that all agree on that being the LCP image, and if the
 * document has a tag with the same name, ID, and class.
 */

export const name = 'Image Prioritizer';

/**
 * Detected LCP external background image candidates.
 *
 * @type {Array<{
 *     url: string,
 *     tag: string,
 *     id: string|null,
 *     class: string|null,
 * }>}
 */
const externalBackgroundImages = [];

/**
 * @typedef {import("web-vitals").LCPMetric} LCPMetric
 * @typedef {import("../optimization-detective/types.ts").InitializeCallback} InitializeCallback
 * @typedef {import("../optimization-detective/types.ts").InitializeArgs} InitializeArgs
 * @typedef {import("../optimization-detective/types.ts").FinalizeArgs} FinalizeArgs
 * @typedef {import("../optimization-detective/types.ts").FinalizeCallback} FinalizeCallback
 * @typedef {import("../optimization-detective/types.ts").LogFunction} LogFunction
 */

/**
 * Initializes extension.
 *
 * @since 0.3.0
 *
 * @type {InitializeCallback}
 * @param {InitializeArgs} args Args.
 */
export async function initialize( { log: _log, onLCP } ) {
	// eslint-disable-next-line no-console
	const log = _log || console.log; // TODO: Remove once Optimization Detective likely updated, or when strict version requirement added in od_init action.

	onLCP(
		( metric ) => {
			handleLCPMetric( metric, log );
		},
		{
			// This avoids needing to click to finalize LCP candidate. While this is helpful for testing, it also
			// ensures that we always get an LCP candidate reported. Otherwise, the callback may never fire if the
			// user never does a click or keydown, per <https://github.com/GoogleChrome/web-vitals/blob/07f6f96/src/onLCP.ts#L99-L107>.
			reportAllChanges: true,
		}
	);
}

/**
 * Handles a new LCP metric being reported.
 *
 * @since 0.3.0
 *
 * @param {LCPMetric}   metric - LCP Metric.
 * @param {LogFunction} log    - The function to call with log messages.
 */
function handleLCPMetric( metric, log ) {
	for ( const entry of metric.entries ) {
		// Look only for LCP entries that have a URL and a corresponding element which is not an IMG or VIDEO.
		if (
			! entry.url ||
			! ( entry.element instanceof HTMLElement ) ||
			entry.element instanceof HTMLImageElement ||
			entry.element instanceof HTMLVideoElement
		) {
			continue;
		}

		// Always ignore data: URLs.
		if ( entry.url.startsWith( 'data:' ) ) {
			continue;
		}

		// Skip elements that have the background image defined inline.
		// These are handled by Image_Prioritizer_Background_Image_Styled_Tag_Visitor.
		if ( entry.element.style.backgroundImage ) {
			continue;
		}

		// Skip URLs that are excessively long. This is the maxLength defined in image_prioritizer_add_element_item_schema_properties().
		if ( entry.url.length > 500 ) {
			log( `Skipping very long URL: ${ entry.url }` );
			return;
		}

		// Also skip Custom Elements which have excessively long tag names. This is the maxLength defined in image_prioritizer_add_element_item_schema_properties().
		if ( entry.element.tagName.length > 100 ) {
			log( `Skipping very long tag name: ${ entry.element.tagName }` );
			return;
		}

		// Note that getAttribute() is used instead of properties so that null can be returned in case of an absent attribute.
		// The maxLengths are defined in image_prioritizer_add_element_item_schema_properties().
		const id = entry.element.getAttribute( 'id' );
		if ( typeof id === 'string' && id.length > 100 ) {
			log( `Skipping very long ID: ${ id }` );
			return;
		}
		const className = entry.element.getAttribute( 'class' );
		if ( typeof className === 'string' && className.length > 500 ) {
			log( `Skipping very long className: ${ className }` );
			return;
		}

		// The id and className allow the tag visitor to detect whether the element is still in the document.
		// This is used instead of having a full XPath which is likely not available since the tag visitor would not
		// know to return true for this element since it has no awareness of which elements have external backgrounds.
		const externalBackgroundImage = {
			url: entry.url,
			tag: entry.element.tagName,
			id,
			class: className,
		};

		log(
			'Detected external LCP background image:',
			externalBackgroundImage
		);

		externalBackgroundImages.push( externalBackgroundImage );
	}
}

/**
 * Finalizes extension.
 *
 * @since 0.3.0
 *
 * @type {FinalizeCallback}
 * @param {FinalizeArgs} args Args.
 */
export async function finalize( { extendRootData, log: _log } ) {
	// eslint-disable-next-line no-console
	const log = _log || console.log; // TODO: Remove once Optimization Detective likely updated, or when strict version requirement added in od_init action.

	if ( externalBackgroundImages.length === 0 ) {
		return;
	}

	// Get the last detected external background image which is going to be for the LCP element (or very likely will be).
	const lcpElementExternalBackgroundImage = externalBackgroundImages.pop();

	log(
		'Sending external background image for LCP element:',
		lcpElementExternalBackgroundImage
	);

	extendRootData( { lcpElementExternalBackgroundImage } );
}
