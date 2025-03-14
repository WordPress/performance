<?php
/**
 * Hook callbacks used for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

// The addition of the following hooks is tested in Test_OD_Hooks::test_hooks_added() and Test_OD_Storage_Post_Type::test_add_hooks().

// @codeCoverageIgnoreStart
add_action( 'init', 'od_initialize_extensions', PHP_INT_MAX );
add_filter( 'template_include', 'od_buffer_output', PHP_INT_MAX );
OD_URL_Metrics_Post_Type::add_hooks();
OD_Storage_Lock::add_hooks();
add_action( 'wp', 'od_maybe_add_template_output_buffer_filter' );
add_action( 'wp_head', 'od_render_generator_meta_tag' );
add_filter( 'site_status_tests', 'od_add_rest_api_availability_test' );
add_action( 'admin_init', 'od_maybe_run_rest_api_health_check' );
add_action( 'after_plugin_row_meta', 'od_render_rest_api_health_check_admin_notice_in_plugin_row', 30 );
add_action( 'rest_api_init', 'od_register_rest_url_metric_store_endpoint' );
add_action( 'od_trigger_page_cache_invalidation', 'od_trigger_post_update_actions' );
// @codeCoverageIgnoreEnd
