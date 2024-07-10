<?php
/**
 * Hook callbacks used for Performance Dashboard
 *
 * @package performance-dashboard
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_head', 'performance_dashboard_render_generator_meta_tag' );

add_action( 'wp_dashboard_setup', 'performance_dashboard_add_dashboard_widget' );

add_action( 'admin_menu', 'performance_dashboard_add_submenu_page' );

add_action( 'init', 'performance_dashboard_register_post_type' );

add_action( 'od_url_metric_stored', 'performance_dashboard_store_data' );

add_action( 'rest_api_init', 'performance_dashboard_register_rest_routes' );
