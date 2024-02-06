<?php
/**
 * Can load function to determine if Site Health module is supported or not.
 *
 * @since   2.8.0
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return static function () {
	if ( ! has_filter( 'user_has_cap', 'wp_maybe_grant_site_health_caps' ) ) {
		return new WP_Error( 'cannot_load_module', esc_html__( 'The module cannot be loaded since the Site Health feature is disabled.', 'performance-lab' ) );
	} else {
		return true;
	}
};
