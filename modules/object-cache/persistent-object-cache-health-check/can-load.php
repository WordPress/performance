<?php
/**
 * Can load function to determine if Persistent Object Cache Health Check module is already merged in WordPress core.
 *
 * @since 1.6.0
 * @package performance-lab
 */

/**
 * Checks whether the given module is already merged into the WordPress core.
 *
 * @since 1.6.0
 *
 * @global string $wp_version The WordPress version string.
 */
return function() {
	global $wp_version;

	return version_compare( $wp_version, '6.1.0', '<' );
};
