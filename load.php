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
define( 'PERFLAB_MODULES_SETTING', 'perflab_modules_settings' );
define( 'PERFLAB_MODULES_SCREEN', 'perflab-modules' );

/**
 * Registers the performance modules setting.
 *
 * @since 1.0.0
 */
function perflab_register_modules_setting() {
	register_setting(
		PERFLAB_MODULES_SCREEN,
		PERFLAB_MODULES_SETTING,
		array(
			'type'              => 'object',
			'sanitize_callback' => 'perflab_sanitize_modules_setting',
			'default'           => array(),
		)
	);
}
add_action( 'init', 'perflab_register_modules_setting' );

/**
 * Sanitizes the performance modules setting.
 *
 * @since 1.0.0
 *
 * @param mixed $value Modules setting value.
 * @return array Sanitized modules setting value.
 */
function perflab_sanitize_modules_setting( $value ) {
	if ( ! is_array( $value ) ) {
		return array();
	}

	// Ensure that every element is an array with an 'enabled' key.
	return array_filter(
		array_map(
			function( $module_settings ) {
				if ( ! is_array( $module_settings ) ) {
					return array();
				}
				return array_merge(
					array( 'enabled' => false ),
					$module_settings
				);
			},
			$value
		)
	);
}

/**
 * Gets the performance module settings.
 *
 * @since 1.0.0
 *
 * @return array Associative array of module settings keyed by module slug.
 */
function perflab_get_module_settings() {
	return (array) get_option( PERFLAB_MODULES_SETTING, array() );
}

/**
 * Gets the active performance modules.
 *
 * @since 1.0.0
 *
 * @return array List of active module slugs.
 */
function perflab_get_active_modules() {
	$modules = array_keys(
		array_filter(
			perflab_get_module_settings(),
			function( $module_settings ) {
				return isset( $module_settings['enabled'] ) && $module_settings['enabled'];
			}
		)
	);

	/**
	 * Filters active modules to allow programmatically control which modules are active.
	 *
	 * @since n.e.x.t
	 *
	 * @param array An array of the currently active modules.
	 */
	$modules = apply_filters( 'perflab_active_modules', $modules );

	return $modules;
}

/**
 * Loads the active performance modules.
 *
 * @since 1.0.0
 */
function perflab_load_active_modules() {
	$active_modules = perflab_get_active_modules();

	if ( empty( $active_modules ) ) {
		return;
	}

	foreach ( $active_modules as $module ) {
		// Do not load module if it no longer exists.
		$module_file = plugin_dir_path( __FILE__ ) . 'modules/' . $module . '/load.php';
		if ( ! file_exists( $module_file ) ) {
			continue;
		}

		require_once $module_file;
	}
}

perflab_load_active_modules();

// Only load admin integration when in admin.
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'admin/load.php';
}
