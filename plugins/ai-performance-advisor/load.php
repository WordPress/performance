<?php
/**
 * AI Performance Advisor.
 *
 * @wordpress-plugin
 * Plugin Name: AI Performance Advisor
 * Plugin URI: https://github.com/WordPress/performance/tree/trunk/plugins/ai-performance-advisor
 * Description: Generates actionable, AI-powered performance tuning recommendations from real site data, surfaced in a Site Health tab.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: ai-performance-advisor
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define the constant.
if ( defined( 'AI_PERFORMANCE_ADVISOR_VERSION' ) ) {
	return;
}

define( 'AI_PERFORMANCE_ADVISOR_VERSION', '1.0.0' );
define( 'AI_PERFORMANCE_ADVISOR_MAIN_FILE', __FILE__ );
define( 'AI_PERFORMANCE_ADVISOR_DIR', __DIR__ );

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/includes/class-aipa-context-provider.php';
require_once __DIR__ . '/includes/class-aipa-context-provider-registry.php';
require_once __DIR__ . '/includes/providers/class-aipa-provider-environment.php';
require_once __DIR__ . '/includes/providers/class-aipa-provider-site-health.php';
require_once __DIR__ . '/includes/providers/class-aipa-provider-site-health-tests.php';
require_once __DIR__ . '/includes/providers/class-aipa-provider-pagespeed.php';
require_once __DIR__ . '/includes/providers/class-aipa-provider-optimization-detective.php';
require_once __DIR__ . '/includes/class-aipa-analyzer.php';
require_once __DIR__ . '/includes/site-health.php';
require_once __DIR__ . '/includes/rest-api.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/hooks.php';
