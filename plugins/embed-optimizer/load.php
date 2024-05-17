<?php
/**
 * Plugin Name: Embed Optimizer
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/embed-optimizer
 * Description: Optimizes the performance of embeds by lazy-loading iframes and scripts.
 * Requires at least: 6.4
 * Requires PHP: 7.2
 * Version: 0.1.2
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: embed-optimizer
 *
 * @package embed-optimizer
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EMBED_OPTIMIZER_VERSION', '0.1.2' );

// Load in the Embed Optimizer plugin hooks.
require_once __DIR__ . '/hooks.php';
