<?php
/**
 * Plugin Name: Image Loading Optimization
 * Plugin URI: https://github.com/WordPress/performance/issues/869
 * Description: Improves accuracy of optimizing the loading of the LCP image by leveraging client-side detection with real user metrics. Also enables output buffering of template rendering which can be filtered.
 * Requires at least: 6.3
 * Requires PHP: 7.0
 * Version: 0.1.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: image-loading-optimization
 *
 * @package image-loading-optimization
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the constant.
if ( defined( 'IMAGE_LOADING_OPTIMIZATION_VERSION' ) ) {
	return;
}

define( 'IMAGE_LOADING_OPTIMIZATION_VERSION', '0.1.0' );

require_once __DIR__ . '/hooks.php';

// Storage logic.
require_once __DIR__ . '/class-ilo-data-validation-exception.php';
require_once __DIR__ . '/class-ilo-url-metric.php';
require_once __DIR__ . '/class-ilo-url-metrics-group.php';
require_once __DIR__ . '/class-ilo-url-metrics-group-collection.php';
require_once __DIR__ . '/class-ilo-storage-lock.php';
require_once __DIR__ . '/storage/post-type.php';
require_once __DIR__ . '/storage/data.php';
require_once __DIR__ . '/storage/rest-api.php';

require_once __DIR__ . '/detection.php';

require_once __DIR__ . '/class-ilo-html-tag-processor.php';
require_once __DIR__ . '/optimization.php';
