<?php
/**
 * Final test image editor class for testing final class handling.
 *
 * @package webp-uploads
 * @since n.e.x.t
 */

/**
 * Final test image editor class.
 *
 * This class is used for testing that webp_uploads_set_image_editors
 * handles final classes gracefully.
 */
if ( class_exists( 'WP_Image_Editor_Imagick' ) && ! class_exists( 'Final_Test_Image_Editor' ) ) {
	final class Final_Test_Image_Editor extends WP_Image_Editor_Imagick {}
}
