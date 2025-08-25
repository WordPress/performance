<?php
/**
 * Plugin Name: Modern Image Formats
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/webp-uploads
 * Description: Converts images to more modern formats such as WebP or AVIF during upload.
 * Requires at least: 6.6
 * Requires PHP: 7.2
 * Version: 2.6.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: webp-uploads
 *
 * @package webp-uploads
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

// Define required constants.
if ( defined( 'WEBP_UPLOADS_VERSION' ) ) {
	return;
}

define( 'WEBP_UPLOADS_VERSION', '2.6.0' );
define( 'WEBP_UPLOADS_MAIN_FILE', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/image-edit.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/picture-element.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/deprecated.php';
