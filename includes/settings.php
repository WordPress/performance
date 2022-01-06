<?php
/**
 * Admin integration file
 *
 * @package performance-lab
 */

/**
 * Adds the modules page to the Settings menu.
 *
 * @since 1.0.0
 */
function perflab_add_modules_page() {
	$hook_suffix = add_options_page(
		__( 'Performance Modules', 'performance-lab' ),
		__( 'Performance', 'performance-lab' ),
		'manage_options',
		PERFLAB_MODULES_SCREEN,
		'perflab_render_modules_page'
	);

	add_action( "load-{$hook_suffix}", 'perflab_load_modules_page', 10, 0 );

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
			function() use ( $module_slug, $module_data, $module_settings ) {
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

		<form action="options.php" method="post" novalidate="novalidate">
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
	$base_id   = sprintf( 'module_%s', $module_slug );
	$base_name = sprintf( '%1$s[%2$s]', PERFLAB_MODULES_SETTING, $module_slug );
	$enabled   = isset( $module_settings['enabled'] ) && $module_settings['enabled'];
	$disabled  = false;
	if ( current_theme_supports( $module_data['slug'] ) ) {
		$enabled  = true;
		$disabled = true;
	}

	?>
	<fieldset>
		<legend class="screen-reader-text">
			<?php echo esc_html( $module_data['name'] ); ?>
		</legend>
		<label for="<?php echo esc_attr( "{$base_id}_enabled" ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( "{$base_id}_enabled" ); ?>"
				name="<?php echo esc_attr( "{$base_name}[enabled]" ); ?>"
				aria-describedby="<?php echo esc_attr( "{$base_id}_description" ); ?>"
				value="1"
				<?php echo $disabled ? 'disabled' : ''; ?>
				<?php checked( $enabled ); ?>
			>

			<?php
			if ( $module_data['experimental'] ) {
				printf(
					/* translators: %s: module name */
					__( 'Enable %s <strong>(experimental)</strong>?', 'performance-lab' ),
					esc_html( $module_data['name'] )
				);
			} else {
				printf(
					/* translators: %s: module name */
					__( 'Enable %s?', 'performance-lab' ),
					esc_html( $module_data['name'] )
				);
			}
			?>
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
		'images'         => array(
			'name' => __( 'Images', 'performance-lab' ),
		),
		'javascript'     => array(
			'name' => __( 'JavaScript', 'performance-lab' ),
		),
		'site-health'    => array(
			'name' => __( 'Site Health', 'performance-lab' ),
		),
		'measurement'    => array(
			'name' => __( 'Measurement', 'performance-lab' ),
		),
		'object-caching' => array(
			'name' => __( 'Object caching', 'performance-lab' ),
		),
	);
}

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
