<?php
/**
 * Plugin Name: Embed Optimizer
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/embed-optimizer
 * Description: Optimize the performance of embeds by lazy-loading them.
 * Requires at least: 6.3
 * Requires PHP: 7.0
 * Version: 0.1.0
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

define( 'EMBED_OPTIMIZER_VERSION', '0.1.0' );

// Load in the Embed Optimizer module hooks.
require_once __DIR__ . '/hooks.php';
