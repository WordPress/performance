<?php
/**
 * Can load function to determine if ILO can load.
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return static function () {
	if (
		! file_exists( __DIR__ . '/detection/web-vitals.asset.php' ) ||
		! file_exists( __DIR__ . '/detection/web-vitals.js' )
	) {
		return new WP_Error(
			'perflab_missing_web_vitals_library',
			__( 'The Web Vitals library is missing. Please do "npm install &amp;&amp; npm run build" to finish installing the plugin.', 'performance-lab' )
		);
	}

	return true;
};
