<?php
/**
 * Custom test image editor class for testing subclass handling.
 *
 * @package webp-uploads
 * @since n.e.x.t
 */

/**
 * Custom test image editor class.
 *
 * This class is used for testing that webp_uploads_set_image_editors
 * creates class_alias for subclasses correctly.
 */
if ( class_exists( 'WP_Image_Editor_Imagick' ) && ! class_exists( 'Custom_Image_Editor_Imagick' ) ) {
	class Custom_Image_Editor_Imagick extends WP_Image_Editor_Imagick {}
}
