<?php
/**
 * Plugin Name: Performance Lab
 * Plugin URI: https://github.com/WordPress/performance
 * Description: Performance plugin from the WordPress Performance Group, which is a collection of standalone performance modules.
 * Requires at least: 5.8
 * Requires PHP: 5.6
 * Version: 1.0.0
 * Author: WordPress Performance Group
 * Text Domain: performance-lab
 *
 * @package performance-lab
 */

define( 'PERFLAB_VERSION', '1.0.0' );
define( 'PERFLAB_ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'PERFLAB_MODULES_PATH', PERFLAB_ABSPATH . 'modules' . DIRECTORY_SEPARATOR );

define( 'PERFLAB_MODULES_SETTING', 'perflab_modules_settings' );
define( 'PERFLAB_MODULES_SCREEN', 'perflab-modules' );

require_once PERFLAB_ABSPATH . 'includes/modules.php';
require_once PERFLAB_ABSPATH . 'includes/settings.php';

perflab_load_active_modules();
