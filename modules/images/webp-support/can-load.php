<?php
/**
 * Can load function to determine if Site Health module is supported or not.
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return static function () {
	if ( ! has_filter( 'user_has_cap', 'wp_maybe_grant_site_health_caps' ) ) {
		return new WP_Error( 'module_not_loaded', esc_html__( 'The module cannot be loaded with Performance Lab since it is disabled.', 'performance-lab' ) );
	} else {
		return true;
	}
};
