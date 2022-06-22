<?php
/**
 * Can load function to determine if WebP Uploads module already marge in WordPress core.
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

add_filter( 'perflab_can_load_module', 'perflab_check_webp_uploads_core_functions', 10, 2 );

function perflab_check_webp_uploads_core_functions( $can_load, $module ) {

    if ( 'images/webp-uploads' !== $module ) {
        return $can_load;
    }

    if ( function_exists( 'wp_image_use_alternate_mime_types' ) ) {
        return false;
    }

    return true;
}