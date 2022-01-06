<?php
/**
 * General module helper functions.
 *
 * @package performance-lab
 */

/**
 * Gets all available modules.
 *
 * This function iterates through the modules directory and therefore should only be called on the modules page.
 * It searches all modules, similar to how plugins are searched in the WordPress core function `get_plugins()`.
 *
 * @since 1.0.0
 *
 * @param string $modules_root Modules root directory to look for modules in. Default is the `/modules` directory
 *                             in the plugin's root.
 * @return array Associative array of parsed module data, keyed by module slug. Fields for every module include
 *               'name', 'description', 'focus', and 'experimental'.
 */
function perflab_get_modules( $modules_root = null ) {
	static $modules = array();

	if ( null === $modules_root ) {
		$modules_root = PERFLAB_ABSPATH . '/modules';
	}

	if ( isset( $modules[ $modules_root ] ) ) {
		return $modules[ $modules_root ];
	}

	$module_files = array();
	$modules_dir  = @opendir( $modules_root );

	// Modules are organized as {focus}/{module-slug} in the modules folder.
	if ( $modules_dir ) {
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( ( $focus = readdir( $modules_dir ) ) !== false ) {
			if ( '.' === substr( $focus, 0, 1 ) ) {
				continue;
			}

			// Each focus area must be a directory.
			if ( ! is_dir( $modules_root . '/' . $focus ) ) {
				continue;
			}

			$focus_dir = @opendir( $modules_root . '/' . $focus );
			if ( $focus_dir ) {
				// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				while ( ( $file = readdir( $focus_dir ) ) !== false ) {
					// Unlike plugins, modules must be in a directory.
					if ( ! is_dir( $modules_root . '/' . $focus . '/' . $file ) ) {
						continue;
					}

					$module_dir = @opendir( $modules_root . '/' . $focus . '/' . $file );
					if ( $module_dir ) {
						// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
						while ( ( $subfile = readdir( $module_dir ) ) !== false ) {
							if ( '.' === substr( $subfile, 0, 1 ) ) {
								continue;
							}

							// Unlike plugins, module main files must be called `load.php`.
							if ( 'load.php' !== $subfile ) {
								continue;
							}

							$module_files[] = "$focus/$file/$subfile";
						}

						closedir( $module_dir );
					}
				}

				closedir( $focus_dir );
			}
		}

		closedir( $modules_dir );
	}

	$found_modules = array();
	foreach ( $module_files as $module_file ) {
		if ( ! is_readable( "$modules_root/$module_file" ) ) {
			continue;
		}

		$module_dir  = dirname( $module_file );
		$module_data = perflab_get_module_data( "$modules_root/$module_file" );
		if ( ! $module_data ) {
			continue;
		}

		$found_modules[ $module_dir ] = $module_data;
	}

	uasort(
		$found_modules,
		function( $a, $b ) {
			return strnatcasecmp( $a['name'], $b['name'] );
		}
	);

	$modules[ $modules_root ] = $found_modules;

	return $modules[ $modules_root ];
}

/**
 * Parses the module main file to get the module's metadata.
 *
 * This is similar to how plugin data is parsed in the WordPress core function `get_plugin_data()`.
 * The user-facing strings will be translated.
 *
 * @since 1.0.0
 *
 * @param string $module_file Absolute path to the main module file.
 * @return array|bool Associative array of parsed module data, or false on failure. Fields for every module include
 *                    'name', 'description', 'focus', and 'experimental'.
 */
function perflab_get_module_data( $module_file ) {
	// Extract the module dir in the form {focus}/{module-slug}.
	preg_match( '/.*\/(.*\/.*)\/load\.php$/i', $module_file, $matches );
	$module_dir = $matches[1];

	$default_headers = array(
		'name'         => 'Module Name',
		'description'  => 'Description',
		'experimental' => 'Experimental',
	);

	$module_data = get_file_data( $module_file, $default_headers, 'perflab_module' );

	// Module name and description are the minimum requirements.
	if ( ! $module_data['name'] || ! $module_data['description'] ) {
		return false;
	}

	// Experimental should be a boolean.
	if ( 'yes' === strtolower( trim( $module_data['experimental'] ) ) ) {
		$module_data['experimental'] = true;
	} else {
		$module_data['experimental'] = false;
	}

	// Extract the module focus from the module directory.
	if ( strpos( $module_dir, '/' ) ) {
		list( $focus, $slug ) = explode( '/', $module_dir );
		$module_data['focus'] = $focus;
		$module_data['slug']  = $slug;
	}

	// Translate fields using low-level function since they come from PHP comments, including the necessary context for
	// `_x()`. This must match how these are translated in the generated `/module-i18n.php` file.
	$translatable_fields = array(
		'name'        => 'module name',
		'description' => 'module description',
	);
	foreach ( $translatable_fields as $field => $context ) {
		// phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralContext,WordPress.WP.I18n.NonSingularStringLiteralText
		$module_data[ $field ] = translate_with_gettext_context( $module_data[ $field ], $context, 'performance-lab' );
	}

	return $module_data;
}

/**
 * Gets the active performance modules.
 *
 * @since 1.0.0
 *
 * @return array List of active module slugs.
 */
function perflab_get_active_modules() {
	$module_settings = perflab_get_module_settings();
	$all_modules     = perflab_get_modules();

	$enabled_modules = array();
	foreach ( $all_modules as $module_key => $module_details ) {
		if (
			current_theme_supports( $module_details['slug'] ) ||
			(
				isset( $module_settings[ $module_key ]['enabled'] ) &&
				filter_var( $module_settings[ $module_key ]['enabled'], FILTER_VALIDATE_BOOLEAN )
			)
		) {
			$enabled_modules[] = $module_key;
		}
	}

	return $enabled_modules;
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
		$module_file = PERFLAB_ABSPATH . 'modules/' . $module . '/load.php';
		if ( ! file_exists( $module_file ) ) {
			continue;
		}

		require_once $module_file;
	}
}
