<?php
/**
 * Plugin Name: Optimization Detective
 * Plugin URI: https://github.com/WordPress/performance/issues/869
 * Description: Improves accuracy of optimizing the loading of the LCP image by leveraging client-side detection with real user metrics. Also enables output buffering of template rendering which can be filtered.
 * Requires at least: 6.3
 * Requires PHP: 7.0
 * Version: 0.1.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: optimization-detective
 *
 * @package optimization-detective
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the constant.
if ( defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) ) {
	return;
}

define( 'OPTIMIZATION_DETECTIVE_VERSION', '0.1.0' );

require_once __DIR__ . '/hooks.php';

// Storage logic.
require_once __DIR__ . '/class-od-data-validation-exception.php';
require_once __DIR__ . '/class-od-url-metric.php';
require_once __DIR__ . '/class-od-url-metrics-group.php';
require_once __DIR__ . '/class-od-url-metrics-group-collection.php';
require_once __DIR__ . '/class-od-storage-lock.php';
require_once __DIR__ . '/storage/class-od-url-metrics-post-type.php';
require_once __DIR__ . '/storage/data.php';
require_once __DIR__ . '/storage/rest-api.php';
OD_URL_Metrics_Post_Type::add_hooks();

require_once __DIR__ . '/detection.php';

require_once __DIR__ . '/class-od-html-tag-processor.php';
require_once __DIR__ . '/optimization.php';
