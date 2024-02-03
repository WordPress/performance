<?php
/**
 * Can load function to determine if Partytown Web Worker module can be loaded.
 *
 * @since   n.e.x.t
 * @package partytown-web-worker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return static function () {
	$errors = new WP_Error();

	// Check for Partytown JavaScript library.
	if ( ! file_exists( __DIR__ . '/assets/js/partytown/partytown.js' ) ) {
		$errors->add(
			'partytown_web_worker_library_missing',
			sprintf(
				/* translators: %s: npm commands. */
				__( 'Partytown library is missing from the plugin. Please do "%s" to install it.', 'performance-lab' ),
				'npm install && npm run build'
			)
		);
	}

	// Check for HTTPS.
	if (
		! is_ssl()
		&&
		'localhost' !== wp_parse_url( home_url(), PHP_URL_HOST )
	) {
		$errors->add(
			'partytown_web_worker_ssl_error',
			__( 'Partytown Web Worker Module requires a secure HTTPS connection to be enabled.', 'performance-lab' )
		);
	}

	return $errors->has_errors() ? $errors : true;
};
