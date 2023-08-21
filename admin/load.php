<?php
/**
 * Admin integration file
 *
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds the modules page to the Settings menu.
 *
 * @since 1.0.0
 */
function perflab_add_modules_page() {
	// Don't add a page if active modules are controlled programmatically.
	if ( has_filter( 'perflab_active_modules' ) ) {
		return false;
	}

	$hook_suffix = add_options_page(
		__( 'Performance Modules', 'performance-lab' ),
		__( 'Performance', 'performance-lab' ),
		'manage_options',
		PERFLAB_MODULES_SCREEN,
		'perflab_render_modules_page'
	);

	// Add the following hooks only if the screen was successfully added.
	if ( false !== $hook_suffix ) {
		add_action( "load-{$hook_suffix}", 'perflab_load_modules_page', 10, 0 );
		add_filter( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ), 'perflab_plugin_action_links_add_settings' );
	}

	return $hook_suffix;
}
add_action( 'admin_menu', 'perflab_add_modules_page' );

/**
 * Initializes settings sections and fields for the modules page.
 *
 * @global array $wp_settings_sections Registered WordPress settings sections.
 *
 * @since 1.0.0
 *
 * @param array|null $modules     Associative array of available module data, keyed by module slug. By default, this
 *                                will rely on {@see perflab_get_modules()}.
 * @param array|null $focus_areas Associative array of focus area data, keyed by focus area slug. By default, this will
 *                                rely on {@see perflab_get_focus_areas()}.
 */
function perflab_load_modules_page( $modules = null, $focus_areas = null ) {
	global $wp_settings_sections;

	// Register sections for all focus areas, plus 'Other'.
	if ( ! is_array( $focus_areas ) ) {
		$focus_areas = perflab_get_focus_areas();
	}
	$sections          = $focus_areas;
	$sections['other'] = array( 'name' => __( 'Other', 'performance-lab' ) );
	foreach ( $sections as $section_slug => $section_data ) {
		add_settings_section(
			$section_slug,
			$section_data['name'],
			null,
			PERFLAB_MODULES_SCREEN
		);
	}

	// Register fields for all modules.
	if ( ! is_array( $modules ) ) {
		$modules = perflab_get_modules();
	}
	$settings = perflab_get_module_settings();
	foreach ( $modules as $module_slug => $module_data ) {
		$module_settings = isset( $settings[ $module_slug ] ) ? $settings[ $module_slug ] : array();
		$module_section  = isset( $sections[ $module_data['focus'] ] ) ? $module_data['focus'] : 'other';

		// Mark this module's section as added.
		$sections[ $module_section ]['added'] = true;

		add_settings_field(
			$module_slug,
			$module_data['name'],
			static function() use ( $module_slug, $module_data, $module_settings ) {
				perflab_render_modules_page_field( $module_slug, $module_data, $module_settings );
			},
			PERFLAB_MODULES_SCREEN,
			$module_section
		);
	}

	// Remove all sections for which there are no modules.
	foreach ( $sections as $section_slug => $section_data ) {
		if ( empty( $section_data['added'] ) ) {
			unset( $wp_settings_sections[ PERFLAB_MODULES_SCREEN ][ $section_slug ] );
		}
	}
}

/**
 * Renders the modules page.
 *
 * @since 1.0.0
 */
function perflab_render_modules_page() {
	?>
	<div class="wrap">
		<h1>
			<?php esc_html_e( 'Performance Modules', 'performance-lab' ); ?>
		</h1>

		<form action="options.php" method="post">
			<?php settings_fields( PERFLAB_MODULES_SCREEN ); ?>
			<?php do_settings_sections( PERFLAB_MODULES_SCREEN ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Renders fields for a given module on the modules page.
 *
 * @since 1.0.0
 *
 * @param string $module_slug     Slug of the module.
 * @param array  $module_data     Associative array of the module's parsed data.
 * @param array  $module_settings Associative array of the module's current settings.
 */
function perflab_render_modules_page_field( $module_slug, $module_data, $module_settings ) {
	$base_id                     = sprintf( 'module_%s', $module_slug );
	$base_name                   = sprintf( '%1$s[%2$s]', PERFLAB_MODULES_SETTING, $module_slug );
	$enabled                     = isset( $module_settings['enabled'] ) && $module_settings['enabled'];
	$can_load_module             = perflab_can_load_module( $module_slug );
	$is_standalone_plugin_loaded = perflab_is_standalone_plugin_loaded( $module_slug );
	?>
	<fieldset>
		<legend class="screen-reader-text">
			<?php echo esc_html( $module_data['name'] ); ?>
		</legend>
		<label for="<?php echo esc_attr( "{$base_id}_enabled" ); ?>">
			<?php if ( $can_load_module && ! $is_standalone_plugin_loaded ) { ?>
				<input type="checkbox" id="<?php echo esc_attr( "{$base_id}_enabled" ); ?>" name="<?php echo esc_attr( "{$base_name}[enabled]" ); ?>" aria-describedby="<?php echo esc_attr( "{$base_id}_description" ); ?>" value="1"<?php checked( $enabled ); ?>>
				<?php
				if ( $module_data['experimental'] ) {
					printf(
						wp_kses(
							/* translators: %s: module name */
							__( 'Enable %s <strong>(experimental)</strong>', 'performance-lab' ),
							array( 'strong' => array() )
						),
						esc_html( $module_data['name'] )
					);
				} else {
					printf(
						/* translators: %s: module name */
						esc_html__( 'Enable %s', 'performance-lab' ),
						esc_html( $module_data['name'] )
					);
				}
				?>
			<?php } else { ?>
				<input type="checkbox" id="<?php echo esc_attr( "{$base_id}_enabled" ); ?>" aria-describedby="<?php echo esc_attr( "{$base_id}_description" ); ?>" disabled>
				<input type="hidden" name="<?php echo esc_attr( "{$base_name}[enabled]" ); ?>" value="<?php echo $enabled ? '1' : '0'; ?>">
				<?php
				if ( $is_standalone_plugin_loaded ) {
					esc_html_e( 'The module cannot be managed with Performance Lab since it is already active as a standalone plugin.', 'performance-lab' );
				} else {
					printf(
						/* translators: %s: module name */
						esc_html__( '%s is already part of your WordPress version and therefore cannot be loaded as part of the plugin.', 'performance-lab' ),
						esc_html( $module_data['name'] )
					);
				}
				?>
			<?php } ?>
		</label>
		<p id="<?php echo esc_attr( "{$base_id}_description" ); ?>" class="description">
			<?php echo esc_html( $module_data['description'] ); ?>
		</p>
	</fieldset>
	<?php
}

/**
 * Gets all available focus areas.
 *
 * @since 1.0.0
 *
 * @return array Associative array of focus area data, keyed by focus area slug. Fields for every focus area include
 *               'name'.
 */
function perflab_get_focus_areas() {
	return array(
		'images'       => array(
			'name' => __( 'Images', 'performance-lab' ),
		),
		'js-and-css'   => array(
			'name' => __( 'JS & CSS', 'performance-lab' ),
		),
		'database'     => array(
			'name' => __( 'Database', 'performance-lab' ),
		),
		'measurement'  => array(
			'name' => __( 'Measurement', 'performance-lab' ),
		),
		'object-cache' => array(
			'name' => __( 'Object Cache', 'performance-lab' ),
		),
	);
}

/**
 * Gets all available modules.
 *
 * This function iterates through the modules directory and therefore should only be called on the modules page.
 * It searches all modules, similar to how plugins are searched in the WordPress core function `get_plugins()`.
 *
 * @since 1.0.0
 *
 * @param string $modules_root Modules root directory to look for modules in. Default is the `/modules` directory
 *                                  in the plugin's root.
 * @return array Associative array of parsed module data, keyed by module slug. Fields for every module include
 *               'name', 'description', 'focus', and 'experimental'.
 */
function perflab_get_modules( $modules_root = null ) {
	if ( null === $modules_root ) {
		$modules_root = dirname( __DIR__ ) . '/modules';
	}

	$modules      = array();
	$module_files = array();
	// PHPCS ignore reason: A modules directory is always present.
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$modules_dir = @opendir( $modules_root );

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

			// PHPCS ignore reason: Only the focus area directory is allowed.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$focus_dir = @opendir( $modules_root . '/' . $focus );
			if ( $focus_dir ) {
				// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				while ( ( $file = readdir( $focus_dir ) ) !== false ) {
					// Unlike plugins, modules must be in a directory.
					if ( ! is_dir( $modules_root . '/' . $focus . '/' . $file ) ) {
						continue;
					}

					// PHPCS ignore reason: Only the module directory is allowed.
					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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

	foreach ( $module_files as $module_file ) {
		if ( ! is_readable( "$modules_root/$module_file" ) ) {
			continue;
		}
		$module_dir  = dirname( $module_file );
		$module_data = perflab_get_module_data( "$modules_root/$module_file" );
		if ( ! $module_data ) {
			continue;
		}

		$modules[ $module_dir ] = $module_data;
	}

	uasort(
		$modules,
		static function( $a, $b ) {
			return strnatcasecmp( $a['name'], $b['name'] );
		}
	);

	return $modules;
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
 * Initializes admin pointer.
 *
 * Handles the bootstrapping of the admin pointer.
 * Mainly jQuery code that is self-initialising.
 *
 * @param string $hook_suffix The current admin page.
 * @since 1.0.0
 */
function perflab_admin_pointer( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'index.php', 'plugins.php' ), true ) ) {
		return;
	}

	// Do not show admin pointer in multisite Network admin or User admin UI.
	if ( is_network_admin() || is_user_admin() ) {
		return;
	}

	$current_user = get_current_user_id();
	$dismissed    = explode( ',', (string) get_user_meta( $current_user, 'dismissed_wp_pointers', true ) );

	if ( in_array( 'perflab-admin-pointer', $dismissed, true ) ) {
		return;
	}

	// Enqueue pointer CSS and JS.
	wp_enqueue_style( 'wp-pointer' );
	wp_enqueue_script( 'wp-pointer' );
	add_action( 'admin_print_footer_scripts', 'perflab_render_pointer', 10, 0 );
}
add_action( 'admin_enqueue_scripts', 'perflab_admin_pointer' );

/**
 * Renders the Admin Pointer.
 *
 * Handles the rendering of the admin pointer.
 *
 * @since 1.0.0
 * @since 2.4.0 Optional arguments were added to make the function reusable for different pointers.
 *
 * @param string $pointer_id Optional. ID of the pointer. Default 'perflab-admin-pointer'.
 * @param array  $args       Optional. Pointer arguments. Supports 'heading' and 'content' entries.
 *                           Defaults are the heading and content for the 'perflab-admin-pointer'.
 */
function perflab_render_pointer( $pointer_id = 'perflab-admin-pointer', $args = array() ) {
	if ( ! isset( $args['heading'] ) ) {
		$args['heading'] = __( 'Performance Lab', 'performance-lab' );
	}
	if ( ! isset( $args['content'] ) ) {
		$args['content'] = sprintf(
			/* translators: %s: settings page link */
			__( 'You can now test upcoming WordPress performance features. Open %s to individually toggle the performance features included in the plugin.', 'performance-lab' ),
			'<a href="' . esc_url( add_query_arg( 'page', PERFLAB_MODULES_SCREEN, admin_url( 'options-general.php' ) ) ) . '">' . __( 'Settings > Performance', 'performance-lab' ) . '</a>'
		);
	}

	$wp_kses_options = array(
		'a' => array(
			'href' => array(),
		),
	);

	?>
	<script id="<?php echo esc_attr( $pointer_id ); ?>" type="text/javascript">
		jQuery( function() {
			// Pointer Options.
			var options = {
				content: '<h3><?php echo esc_js( $args['heading'] ); ?></h3><p><?php echo wp_kses( $args['content'], $wp_kses_options ); ?></p>',
				position: {
					edge:  'left',
					align: 'right',
				},
				pointerClass: 'wp-pointer arrow-top',
				pointerWidth: 420,
				close: function() {
					jQuery.post(
						window.ajaxurl,
						{
							pointer: '<?php echo esc_js( $pointer_id ); ?>',
							action:  'dismiss-wp-pointer',
							_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'dismiss_pointer' ) ); ?>,
						}
					);
				}
			};

			jQuery( '#menu-settings' ).pointer( options ).pointer( 'open' );
		} );
	</script>
	<?php
}

/**
 * Adds a link to the modules page to the plugin's entry in the plugins list table.
 *
 * This function is only used if the modules page exists and is accessible.
 *
 * @since 1.0.0
 * @see perflab_add_modules_page()
 *
 * @param array $links List of plugin action links HTML.
 * @return array Modified list of plugin action links HTML.
 */
function perflab_plugin_action_links_add_settings( $links ) {
	// Add link as the first plugin action link.
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( add_query_arg( 'page', PERFLAB_MODULES_SCREEN, admin_url( 'options-general.php' ) ) ),
		esc_html__( 'Settings', 'performance-lab' )
	);
	array_unshift( $links, $settings_link );

	return $links;
}

/**
 * Dismisses notification pointer after verifying nonce.
 *
 * This function adds a nonce check before dismissing perflab-admin-pointer
 * It runs before the dismiss-wp-pointer AJAX action is performed.
 *
 * @since 2.3.0
 * @see perflab_render_modules_pointer()
 */
function perflab_dismiss_wp_pointer_wrapper() {
	if ( isset( $_POST['pointer'] ) && 'perflab-admin-pointer' !== $_POST['pointer'] ) {
		// Another plugin's pointer, do nothing.
		return;
	}
	check_ajax_referer( 'dismiss_pointer' );
}
add_action( 'wp_ajax_dismiss-wp-pointer', 'perflab_dismiss_wp_pointer_wrapper', 0 );
