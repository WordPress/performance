<?php
/**
 * Can load function to determine if SQLite can be activated.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Checks whether the given module can be activated.
 *
 * @since n.e.x.t
 */
return function() {
	$is_writable_wp_content_dir = wp_is_writable( WP_CONTENT_DIR );
	return class_exists( 'SQLite3' ) && $is_writable_wp_content_dir;
};
