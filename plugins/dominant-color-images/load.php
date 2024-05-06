<?php
/**
 * Plugin Name: Image Placeholders
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/dominant-color-images
 * Description: Displays placeholders based on an image's dominant color while the image is loading.
 * Requires at least: 6.4
 * Requires PHP: 7.2
 * Version: 1.1.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: dominant-color-images
 *
 * @package dominant-color-images
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define required constants.
if ( defined( 'DOMINANT_COLOR_IMAGES_VERSION' ) ) {
	return;
}

define( 'DOMINANT_COLOR_IMAGES_VERSION', '1.1.0' );

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
