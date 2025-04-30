<?php
/**
 * Plugin Name: View Transitions
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/view-transitions
 * Description: Adds smooth transitions between navigations to your WordPress site.
 * Requires at least: 6.6
 * Requires PHP: 7.2
 * Version: 1.0.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: view-transitions
 *
 * @package view-transitions
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

// Define the constant.
if ( defined( 'VIEW_TRANSITIONS_VERSION' ) ) {
	return;
}

define( 'VIEW_TRANSITIONS_VERSION', '1.0.0' );

require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/hooks.php';
