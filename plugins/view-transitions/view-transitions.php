<?php
/**
 * Plugin Name: View Transitions
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/view-transitions
 * Description: Adds smooth transitions between navigations to your WordPress site.
 * Requires at least: 6.6
 * Requires PHP: 7.2
 * Version: 1.1.1
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

// Define the constant.
if ( defined( 'VIEW_TRANSITIONS_VERSION' ) ) {
	return;
}

define( 'VIEW_TRANSITIONS_VERSION', '1.1.1' );
define( 'VIEW_TRANSITIONS_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/class-plvt-view-transition-animation.php';
require_once __DIR__ . '/includes/class-plvt-view-transition-animation-registry.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/hooks.php';
// @codeCoverageIgnoreEnd
