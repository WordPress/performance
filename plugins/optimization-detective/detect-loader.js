/**
 * Loads the detect module after the page has loaded.
 *
 * This prevents a high-priority script module network request from competing with other critical resources. This
 * JavaScript file must contain a single top-level function which is not exported. The file is inlined as part of
 * another module which wraps the function in an IIFE.
 *
 * @param {string} detectSrc
 * @param {Object} detectArgs
 */
// eslint-disable-next-line no-unused-vars
async function load( detectSrc, detectArgs ) {
	const doc = document;
	const win = window;

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

	const { default: detect } = await import( detectSrc );
	await detect( detectArgs );
}
