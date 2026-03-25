<?php
/**
 * Hook callback for Web Worker Offloading.
 *
 * @since 0.1.0
 * @package web-worker-offloading
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
add_action( 'wp_default_scripts', 'plwwo_register_default_scripts' );
add_filter( 'print_scripts_array', 'plwwo_filter_print_scripts_array', PHP_INT_MAX );
add_filter( 'script_loader_tag', 'plwwo_update_script_type', 10, 2 );
add_filter( 'wp_inline_script_attributes', 'plwwo_filter_inline_script_attributes' );
add_action( 'wp_head', 'plwwo_render_generator_meta_tag' );
add_action( 'after_plugin_row_meta', 'plwwo_render_sunset_notice' );
// @codeCoverageIgnoreEnd
