<?php
/**
 * Plugin Name: Web Worker Offloading
 * Plugin URI: https://github.com/WordPress/performance/issues/176
 * Description: Offloads select JavaScript execution to a Web Worker to reduce work on the main thread and improve the Interaction to Next Paint (INP) metric.
 * Requires at least: 6.6
 * Requires PHP: 7.2
 * Version: 0.2.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: web-worker-offloading
 *
 * @package web-worker-offloading
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the constant.
if ( defined( 'WEB_WORKER_OFFLOADING_VERSION' ) ) {
	return;
}

if (
	( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) &&
	! file_exists( __DIR__ . '/build/partytown.asset.php' )
) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
	trigger_error(
		esc_html(
			sprintf(
				/* translators: 1: File path. 2: CLI command. */
				'[Web Worker Offloading] ' . __( 'Unable to load %1$s. Please make sure you have run %2$s.', 'web-worker-offloading' ),
				'build/partytown.asset.php',
				'`npm install && npm run build:plugin:web-worker-offloading`'
			)
		),
		E_USER_ERROR
	);
}

define( 'WEB_WORKER_OFFLOADING_VERSION', '0.2.0' );

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/third-party.php';
