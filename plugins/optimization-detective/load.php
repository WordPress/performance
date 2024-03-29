<?php
/**
 * Plugin Name: Optimization Detective
 * Plugin URI: https://github.com/WordPress/performance/issues/869
 * Description: Uses real user metrics to improve heuristics WordPress applies on the frontend to improve image loading priority.
 * Requires at least: 6.4
 * Requires PHP: 7.0
 * Version: 0.1.1
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

if (
	( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) &&
	! file_exists( __DIR__ . '/build/web-vitals.asset.php' )
) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
	trigger_error(
		esc_html(
			sprintf(
				/* translators: 1: File path. 2: CLI command. */
				'[Optimization Detective] ' . __( 'Unable to load %1$s. Please make sure you have run %2$s.', 'optimization-detective' ),
				'build/web-vitals.asset.php',
				'`npm install && npm run build:optimization-detective`'
			)
		),
		E_USER_ERROR
	);
}

define( 'OPTIMIZATION_DETECTIVE_VERSION', '0.1.1' );

require_once __DIR__ . '/helper.php';

// Core infrastructure classes.
require_once __DIR__ . '/class-od-data-validation-exception.php';
require_once __DIR__ . '/class-od-url-metric.php';
require_once __DIR__ . '/class-od-url-metrics-group.php';
require_once __DIR__ . '/class-od-url-metrics-group-collection.php';

// Storage logic.
require_once __DIR__ . '/storage/class-od-url-metrics-post-type.php';
require_once __DIR__ . '/storage/class-od-storage-lock.php';
require_once __DIR__ . '/storage/data.php';
require_once __DIR__ . '/storage/rest-api.php';

// Detection logic.
require_once __DIR__ . '/detection.php';

// Optimization logic.
require_once __DIR__ . '/class-od-html-tag-processor.php';
require_once __DIR__ . '/optimization.php';

// Add hooks for the above requires.
require_once __DIR__ . '/hooks.php';
