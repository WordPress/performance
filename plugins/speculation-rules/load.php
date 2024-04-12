<?php
/**
 * Plugin Name: Speculative Loading
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/speculation-rules
 * Description: Enables browsers to speculatively prerender or prefetch pages when hovering over links.
 * Requires at least: 6.4
 * Requires PHP: 7.0
 * Version: 1.2.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: speculation-rules
 *
 * @package speculation-rules
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the constant.
if ( defined( 'SPECULATION_RULES_VERSION' ) ) {
	return;
}

define( 'SPECULATION_RULES_VERSION', '1.2.0' );

require_once __DIR__ . '/class-plsr-url-pattern-prefixer.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/settings.php';
