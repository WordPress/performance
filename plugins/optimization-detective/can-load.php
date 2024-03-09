<?php
/**
 * Can load function to determine if ILO can load.
 *
 * @since   n.e.x.t
 * @package optimization-detective
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
			sprintf(
				/* translators: npm command. */
				__( 'The Web Vitals library is missing. Please do "%s" to finish installing the plugin.', 'optimization-detective' ),
				'npm install && npm run build'
			)
		);
	}

	return true;
};
