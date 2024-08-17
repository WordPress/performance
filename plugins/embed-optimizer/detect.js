const consoleLogPrefix = '[Embed Optimizer]';

/**
 * Log a message.
 *
 * @param {...*} message
 */
function log( ...message ) {
	// eslint-disable-next-line no-console
	console.log( consoleLogPrefix, ...message );
}

/*
 * Observe the loading of embeds on the page. We need to run this now before the resources on the page have fully
 * loaded because we need to start observing the embed wrappers before the embeds have loaded. When we detect
 * subtree modifications in an embed wrapper, we then need to measure the new height of the wrapper element.
 * However, since there may be multiple subtree modifications performed as an embed is loaded, we need to wait until
 * what is likely the last mutation.
 */
const EMBED_LOAD_WAIT_MS = 1000;

/**
 * Initialize.
 *
 * @param {Object}  args         Args.
 * @param {boolean} args.isDebug Whether to show debug messages.
 */
export async function initialize( { isDebug } ) {
	const embedWrappers =
		/** @type NodeListOf<HTMLDivElement> */ document.querySelectorAll(
			'.wp-block-embed > .wp-block-embed__wrapper[data-od-xpath]'
		);

	for ( const embedWrapper of embedWrappers ) {
		monitorEmbedWrapperForResizes( embedWrapper );
	}

	if ( isDebug ) {
		log( embedWrappers );
	}

	// TODO: It should update the pending url metrics before they are sent to the server when the page is hidden.
}

/**
 * Initialize.
 *
 * @todo Add typing for args.urlMetric
 *
 * @param {Object}  args           Args.
 * @param {boolean} args.isDebug   Whether to show debug messages.
 * @param {Object}  args.urlMetric Pending URL metric.
 */
export async function finalize( { urlMetric, isDebug } ) {
	if ( isDebug ) {
		log( 'URL metric to be sent:', urlMetric );
	}

	// TODO: Finalize.
}

/**
 * Monitors embed wrapper for resizes.
 *
 * @param {HTMLDivElement} embedWrapper Embed wrapper DIV.
 */
function monitorEmbedWrapperForResizes( embedWrapper ) {
	if ( ! ( 'odXpath' in embedWrapper.dataset ) ) {
		throw new Error( 'Embed wrapper missing data-od-xpath attribute.' );
	}
	const xpath = embedWrapper.dataset.odXpath;
	let timeoutId = 0;
	const observer = new ResizeObserver( ( entries ) => {
		const [ entry ] = entries;
		if ( timeoutId > 0 ) {
			clearTimeout( timeoutId );
		}
		log(
			`Pending embed height of ${ entry.contentRect.height }px for ${ xpath }`
		);
		// TODO: Is the timeout really needed? We can just keep updating the height of the element until the URL metrics are sent when the page closes.
		timeoutId = setTimeout( () => {
			log(
				`Final embed height of ${ entry.contentRect.height }px for ${ xpath }`
			);
			observer.disconnect();
			// TODO: Now amend URL metrics with this rect.height.
		}, EMBED_LOAD_WAIT_MS );
	} );
	observer.observe( embedWrapper, { box: 'content-box' } );
}
