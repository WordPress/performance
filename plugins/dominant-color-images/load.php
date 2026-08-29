<?php
/**
 * Plugin Name: Image Placeholders
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/dominant-color-images
 * Description: Displays placeholders based on an image's dominant color while the image is loading.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.3.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: dominant-color-images
 *
 * @package dominant-color-images
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


// Define required constants.
if ( defined( 'DOMINANT_COLOR_IMAGES_VERSION' ) ) {
	return;
}

define( 'DOMINANT_COLOR_IMAGES_VERSION', '1.3.0' );

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';

add_filter(
	'wp_image_editors',
	static function ( array $editors ): array {
		// Ensure core editor classes are loaded before delegating, since this
		// filter can run early (e.g. during wp_image_editor_supports()).
		if ( ! class_exists( 'WP_Image_Editor_GD' ) ) {
			require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
		}
		if ( ! class_exists( 'WP_Image_Editor_Imagick' ) ) {
			require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
		}

		return dominant_color_set_image_editors( $editors );
	},
	999,
	1
);
// @codeCoverageIgnoreEnd
