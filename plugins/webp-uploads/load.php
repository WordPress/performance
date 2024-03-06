<?php
/**
 * Plugin Name: WebP Uploads
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/webp-uploads
 * Description: Creates WebP versions for new JPEG image uploads if supported by the server.
 * Requires at least: 6.3
 * Requires PHP: 7.0
 * Version: 1.0.6
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: webp-uploads
 *
 * @package webp-uploads
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define required constants.
if ( defined( 'WEBP_UPLOADS_VERSION' ) ) {
	return;
}

define( 'WEBP_UPLOADS_VERSION', '1.0.6' );

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/image-edit.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/hooks.php';
