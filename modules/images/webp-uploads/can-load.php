<?php
/**
 * Can load function to determine if WebP Uploads module is already merged in WordPress core.
 *
 * @since   1.3.0
 * @package performance-lab
 */

return function() {
	return ! function_exists( 'wp_image_use_alternate_mime_types' );
};
