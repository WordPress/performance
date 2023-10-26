/**
 * Detect the LCP element, loaded images, client viewport and store for future optimizations.
 *
 * @param {number} serveTime The serve time of the page in milliseconds from PHP via `ceil( microtime( true ) * 1000 )`.
 * @param {number} detectionTimeWindow The number of milliseconds between now and when the page was first generated in which detection should proceed.
 * @param {boolean} isDebug Whether to show debug messages.
 */
function detect( serveTime, detectionTimeWindow, isDebug ) {
	const runTime = new Date().valueOf();

	// Abort running detection logic if it was served in a cached page.
	if ( runTime - serveTime > detectionTimeWindow ) {
		if ( isDebug ) {
			console.warn( 'Aborted detection for Image Loading Optimization due to being outside detection time window.' );
		}
		return;
	}

	if ( isDebug ) {
		console.info('Proceeding with detection for Image Loading Optimization.');
	}
}
