<?php
/**
 * Can load function to determine if WebP Uploads module already marge in WordPress core.
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

/**
 * Filters whether the module can load or not.
 *
 * @since n.e.x.t
 *
 * @param bool   $can_load Whether to load module. default true.
 * @param string $module   The name of the module.
 * @return bool Whether to load module or not.
 */
function perflab_check_webp_uploads_core_functions( $can_load, $module ) {

	if ( 'images/webp-uploads' !== $module ) {
		return $can_load;
	}

	return ! function_exists( 'wp_image_use_alternate_mime_types' );
}
add_filter( 'perflab_can_load_module', 'perflab_check_webp_uploads_core_functions', 10, 2 );
