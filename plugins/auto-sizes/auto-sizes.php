<?php
/**
 * Plugin Name: Auto-sizes for Lazy-loaded Images
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/auto-sizes
 * Description: Instructs browsers to automatically choose the right image size for lazy-loaded images.
 * Requires at least: 6.5
 * Requires PHP: 7.2
 * Version: 1.0.2
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: auto-sizes
 *
 * @package auto-sizes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the constant.
if ( defined( 'IMAGE_AUTO_SIZES_VERSION' ) ) {
	return;
}

define( 'IMAGE_AUTO_SIZES_VERSION', '1.0.2' );

require_once __DIR__ . '/hooks.php';
