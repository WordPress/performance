<?php
/**
 * Can load function to determine if WebP Uploads module is already merged in WordPress core.
 *
 * @since   1.3.0
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return static function() {
	return ! function_exists( 'wp_image_use_alternate_mime_types' );
};
