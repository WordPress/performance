<?php
/**
 * Plugin Name: Performance Lab
 * Plugin URI: https://github.com/WordPress/performance
 * Description: Performance plugin from the WordPress Performance Team, which is a collection of standalone performance features.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 4.2.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: performance-lab
 *
 * @package performance-lab
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

define( 'PERFLAB_VERSION', '4.2.0' );
define( 'PERFLAB_MAIN_FILE', __FILE__ );
define( 'PERFLAB_PLUGIN_DIR_PATH', plugin_dir_path( PERFLAB_MAIN_FILE ) );
define( 'PERFLAB_SCREEN', 'performance-lab' );

// If the constant isn't defined yet, it means the Performance Lab object cache file is not loaded.
if ( ! defined( 'PERFLAB_OBJECT_CACHE_DROPIN_VERSION' ) ) {
	define( 'PERFLAB_OBJECT_CACHE_DROPIN_VERSION', false );
}
define( 'PERFLAB_OBJECT_CACHE_DROPIN_LATEST_VERSION', 3 );

require_once __DIR__ . '/hooks.php';

// Load server-timing API.
require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/class-perflab-server-timing-metric.php';
require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/class-perflab-server-timing.php';
require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/load.php';
require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/defaults.php';

// Load site health checks.
require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/site-health/load.php';

// Only load admin integration when in admin.
if ( is_admin() ) {
	require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/admin/load.php';
	require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/admin/server-timing.php';
	require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/admin/plugins.php';
}

// Load REST API.
require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/admin/rest-api.php';
