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
	$uses_ssl = (
		is_ssl()
		&&
		( strpos( get_bloginfo( 'wpurl' ), 'https' ) === 0 )
		&&
		( strpos( get_bloginfo( 'url' ), 'https' ) === 0 )
	);

	if ( ! $uses_ssl ) {
		return new WP_Error(
			'partytown_web_worker_ssl_error',
			__( 'Partytown Web Worker Module requires a secure HTTPS connection to be enabled.', 'performance-lab' )
		);
	}

	return true;
};
