<?php
/**
 * Plugin Name: TODO-NAME
 * Plugin URI: https://github.com/WordPress/performance
 * Description: Performance plugin from the WordPress Performance Group, which is a collection of standalone performance modules.
 * Requires at least: 5.8
 * Requires PHP: 5.6
 * Version: 1.0.0
 * Author: WordPress Performance Group
 * Text Domain: TODO-SLUG
 *
 * @package TODO-SLUG
 */

define( 'TODO_CONSTANTSLUG_VERSION', '1.0.0' );
define( 'TODO_CONSTANTSLUG_MODULES_SETTING', 'todo_snakecaseslug_modules_settings' );
define( 'TODO_CONSTANTSLUG_MODULES_SCREEN', 'TODO-SLUG-modules' );

/**
 * Registers the performance modules setting.
 *
 * @since 1.0.0
 */
function todo_snakecaseslug_register_modules_setting() {
	register_setting(
		TODO_CONSTANTSLUG_MODULES_SCREEN,
		TODO_CONSTANTSLUG_MODULES_SETTING,
		array(
			'type'              => 'object',
			'sanitize_callback' => 'todo_snakecaseslug_sanitize_modules_setting',
			'default'           => array(),
		)
	);
}
add_action( 'init', 'todo_snakecaseslug_register_modules_setting' );

/**
 * Sanitizes the performance modules setting.
 *
 * @since 1.0.0
 *
 * @param mixed $value Modules setting value.
 * @return array Sanitized modules setting value.
 */
function todo_snakecaseslug_sanitize_modules_setting( $value ) {
	// TODO: Make this more error-proof.
	if ( ! is_array( $value ) ) {
		return array();
	}

	return $value;
}

/**
 * Gets the performance module settings.
 *
 * @since 1.0.0
 *
 * @return array Associative array of module settings keyed by module slug.
 */
function todo_snakecaseslug_get_module_settings() {
	return (array) get_option( TODO_CONSTANTSLUG_MODULES_SETTING, array() );
}

/**
 * Gets the active performance modules.
 *
 * @since 1.0.0
 *
 * @return array List of active module slugs.
 */
function todo_snakecaseslug_get_active_modules() {
	return array_keys(
		array_filter(
			todo_snakecaseslug_get_module_settings(),
			function( $module_settings ) {
				return isset( $module_settings['enabled'] ) && $module_settings['enabled'];
			}
		)
	);
}

/**
 * Loads the active performance modules.
 *
 * @since 1.0.0
 */
function todo_snakecaseslug_load_active_modules() {
	$active_modules = todo_snakecaseslug_get_active_modules();

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

todo_snakecaseslug_load_active_modules();
