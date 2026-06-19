<?php
/**
 * Hook wiring for the AI Performance Advisor plugin.
 *
 * @package ai-performance-advisor
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'site_health_navigation_tabs', 'aipa_add_site_health_tab' );
add_action( 'site_health_tab_content', 'aipa_render_site_health_tab' );
add_action( 'rest_api_init', 'aipa_register_rest_routes' );

/**
 * Enqueues the admin assets on the Site Health screen.
 *
 * The assets are only enqueued on the Site Health page. The script no-ops when the
 * analyze button is not present (i.e. on other tabs), and the styles are scoped to
 * this plugin's own classes, so enqueuing them page-wide is harmless and avoids
 * needing to read the unauthenticated tab query parameter.
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix The current admin page.
 */
function aipa_enqueue_admin_assets( string $hook_suffix ): void {
	if ( 'site-health.php' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'aipa-analyzer',
		plugins_url( 'css/analyzer.css', AI_PERFORMANCE_ADVISOR_MAIN_FILE ),
		array(),
		AI_PERFORMANCE_ADVISOR_VERSION
	);

	wp_enqueue_script(
		'aipa-analyzer',
		plugins_url( 'js/analyzer.js', AI_PERFORMANCE_ADVISOR_MAIN_FILE ),
		array( 'wp-api-fetch', 'wp-i18n' ),
		AI_PERFORMANCE_ADVISOR_VERSION,
		true
	);
	wp_set_script_translations( 'aipa-analyzer', 'ai-performance-advisor' );
}
add_action( 'admin_enqueue_scripts', 'aipa_enqueue_admin_assets' );
