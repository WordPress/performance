<?php
/**
 * Plugin Name: Performance Lab
 * Plugin URI: https://github.com/WordPress/performance
 * Description: Performance plugin from the WordPress Performance Group, which is a collection of standalone performance modules.
 * Requires at least: 5.8
 * Requires PHP: 5.6
 * Version: 1.1.0
 * Author: WordPress Performance Group
 * Author URI: https://make.wordpress.org/core/tag/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: performance-lab
 *
 * @package performance-lab
 */

define( 'PERFLAB_VERSION', '1.1.0' );
define( 'PERFLAB_MAIN_FILE', __FILE__ );
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
			'default'           => perflab_get_modules_setting_default(),
		)
	);
}
add_action( 'init', 'perflab_register_modules_setting' );

/**
 * Gets the default value for the performance modules setting.
 *
 * @since 1.0.0
 */
function perflab_get_modules_setting_default() {
	// Since the default relies on some minimal logic that includes requiring an additional file,
	// the result is "cached" in a static variable.
	static $default_option = null;

	if ( null === $default_option ) {
		// To set the default value for which modules are enabled, rely on this generated file.
		$default_enabled_modules = require plugin_dir_path( __FILE__ ) . 'default-enabled-modules.php';
		$default_option          = array_reduce(
			$default_enabled_modules,
			function( $module_settings, $module_dir ) {
				$module_settings[ $module_dir ] = array( 'enabled' => true );
				return $module_settings;
			},
			array()
		);
	}

	return $default_option;
}

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
	// Even though a default value is registered for this setting, the default must be explicitly
	// passed here, to support scenarios where this function is called before the 'init' action,
	// for example when loading the active modules.
	return (array) get_option( PERFLAB_MODULES_SETTING, perflab_get_modules_setting_default() );
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
	 * @since 1.0.0
	 *
	 * @param array An array of the currently active modules.
	 */
	$modules = apply_filters( 'perflab_active_modules', $modules );

	return $modules;
}

/**
 * Gets the content attribute for the generator tag for the Performance Lab plugin.
 *
 * This attribute is then used in {@see perflab_render_generator()}.
 *
 * @since 1.1.0
 */
function perflab_get_generator_content() {
	$active_modules = perflab_get_active_modules();

	return sprintf(
		'Performance Lab %1$s; modules: %2$s',
		PERFLAB_VERSION,
		implode( ', ', $active_modules )
	);
}

/**
 * Displays the HTML generator tag for the Performance Lab plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 1.1.0
 */
function perflab_render_generator() {
	$content = perflab_get_generator_content();

	echo '<meta name="generator" content="' . esc_attr( $content ) . '">' . "\n";
}
add_action( 'wp_head', 'perflab_render_generator' );

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
