/**
 * Loads the detect module after the page has loaded.
 *
 * This prevents a high-priority script module network request from competing with other critical resources.
 *
 * @since 1.0.0
 */
async function load() {
	// Wait until the resources on the page have fully loaded.
	await new Promise( ( resolve ) => {
		if ( document.readyState === 'complete' ) {
			resolve();
		} else {
			window.addEventListener( 'load', resolve, { once: true } );
		}
	} );

	// Wait yet further until idle.
	if ( typeof requestIdleCallback === 'function' ) {
		await new Promise( ( resolve ) => {
			requestIdleCallback( resolve );
		} );
	}

	const data = JSON.parse(
		document.getElementById( 'optimization-detective-detect-args' )
			.textContent
	);

	const detectSrc = /** @type {string} */ data[ 0 ];
	const detectArgs =
		/** @type {import("./detect.js").DetectFunctionArgs} */ data[ 1 ];
	const detect = /** @type {import("./detect.js").DetectFunction} */ (
		( await import( detectSrc ) ).default
	);
	await detect( detectArgs );
}

load();
