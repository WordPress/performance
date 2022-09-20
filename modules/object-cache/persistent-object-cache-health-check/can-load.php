<?php
/**
 * Can load function to determine if Persistent Object Cache Health Check module is already merged in WordPress core.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Checks whether the given module is already merged into the WordPress core.
 *
 * @since n.e.x.t
 *
 * @global string $wp_version The WordPress version string.
 */
return function() {
	global $wp_version;

	return ! version_compare( $wp_version, '6.1.0', '>=' );
};
