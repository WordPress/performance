<?php
/**
 * Settings functions used for View Transitions.
 *
 * @package view-transitions
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Returns the available options for a View Transitions transition animation and their labels.
 *
 * @since 1.0.0
 *
 * @return array<non-empty-string, string> Associative array of `$animation => $label` pairs.
 */
function plvt_get_view_transition_animation_labels(): array {
	return array(
		'fade'              => _x( 'Fade (default)', 'animation label', 'view-transitions' ),
		'slide-from-right'  => _x( 'Slide (from right)', 'animation label', 'view-transitions' ),
		'slide-from-left'   => _x( 'Slide (from left)', 'animation label', 'view-transitions' ),
		'slide-from-bottom' => _x( 'Slide (from bottom)', 'animation label', 'view-transitions' ),
		'slide-from-top'    => _x( 'Slide (from top)', 'animation label', 'view-transitions' ),
		'swipe-from-right'  => _x( 'Swipe (from right)', 'animation label', 'view-transitions' ),
		'swipe-from-left'   => _x( 'Swipe (from left)', 'animation label', 'view-transitions' ),
		'swipe-from-bottom' => _x( 'Swipe (from bottom)', 'animation label', 'view-transitions' ),
		'swipe-from-top'    => _x( 'Swipe (from top)', 'animation label', 'view-transitions' ),
		'wipe-from-right'   => _x( 'Wipe (from right)', 'animation label', 'view-transitions' ),
		'wipe-from-left'    => _x( 'Wipe (from left)', 'animation label', 'view-transitions' ),
		'wipe-from-bottom'  => _x( 'Wipe (from bottom)', 'animation label', 'view-transitions' ),
		'wipe-from-top'     => _x( 'Wipe (from top)', 'animation label', 'view-transitions' ),
	);
}

/**
 * Returns the default setting value for View Transitions configuration.
 *
 * These are the same defaults that `plvt_sanitize_view_transitions_theme_support()` uses.
 *
 * @since 1.0.0
 * @see plvt_sanitize_view_transitions_theme_support()
 *
 * @return array{ override_theme_config: bool, default_transition_animation: non-empty-string, default_transition_animation_duration: int, header_selector: non-empty-string, main_selector: non-empty-string, post_title_selector: non-empty-string, post_thumbnail_selector: non-empty-string, post_content_selector: non-empty-string, enable_admin_transitions: bool } {
 *     Default setting value.
 *
 *     @type bool   $override_theme_config                 Whether to override the current theme's configuration. Otherwise,
 *                                                         the other frontend specific settings won't be applied.
 *     @type string $default_transition_animation          Default view transition animation.
 *     @type int    $default_transition_animation_duration Default transition animation duration in milliseconds.
 *     @type string $header_selector                       CSS selector for the global header element.
 *     @type string $main_selector                         CSS selector for the global main element.
 *     @type string $post_title_selector                   CSS selector for the post title element.
 *     @type string $post_thumbnail_selector               CSS selector for the post thumbnail element.
 *     @type string $post_content_selector                 CSS selector for the post content element.
 *     @type bool   $enable_admin_transitions              Whether to use view transitions in the admin area.
 * }
 */
function plvt_get_setting_default(): array {
	return array(
		'override_theme_config'                 => false,
		'default_transition_animation'          => 'fade',
		'default_transition_animation_duration' => 400,
		'header_selector'                       => 'header',
		'main_selector'                         => 'main',
		'post_title_selector'                   => '.wp-block-post-title, .entry-title',
		'post_thumbnail_selector'               => '.wp-post-image',
		'post_content_selector'                 => '.wp-block-post-content, .entry-content',
		'enable_admin_transitions'              => false,
	);
}

/**
 * Returns the stored setting value for View Transitions configuration.
 *
 * @since 1.0.0
 *
 * @return array{ override_theme_config: bool, default_transition_animation: non-empty-string, default_transition_animation_duration: int, header_selector: non-empty-string, main_selector: non-empty-string, post_title_selector: non-empty-string, post_thumbnail_selector: non-empty-string, post_content_selector: non-empty-string, enable_admin_transitions: bool } {
 *     Stored setting value.
 *
 *     @type bool   $override_theme_config                 Whether to override the current theme's configuration. Otherwise,
 *                                                         the other frontend specific settings won't be applied.
 *     @type string $default_transition_animation          Default view transition animation.
 *     @type int    $default_transition_animation_duration Default transition animation duration in milliseconds.
 *     @type string $header_selector                       CSS selector for the global header element.
 *     @type string $main_selector                         CSS selector for the global main element.
 *     @type string $post_title_selector                   CSS selector for the post title element.
 *     @type string $post_thumbnail_selector               CSS selector for the post thumbnail element.
 *     @type string $post_content_selector                 CSS selector for the post content element.
 *     @type bool   $enable_admin_transitions              Whether to use view transitions in the admin area.
 * }
 */
function plvt_get_stored_setting_value(): array {
	return plvt_sanitize_setting( get_option( 'plvt_view_transitions' ) );
}

/**
 * Sanitizes the setting for View Transitions configuration.
 *
 * @since 1.0.0
 *
 * @param mixed $input Setting to sanitize.
 * @return array{ override_theme_config: bool, default_transition_animation: non-empty-string, default_transition_animation_duration: int, header_selector: non-empty-string, main_selector: non-empty-string, post_title_selector: non-empty-string, post_thumbnail_selector: non-empty-string, post_content_selector: non-empty-string, enable_admin_transitions: bool } {
 *     Sanitized setting.
 *
 *     @type bool   $override_theme_config                 Whether to override the current theme's configuration. Otherwise,
 *                                                         the other frontend specific settings won't be applied.
 *     @type string $default_transition_animation          Default view transition animation.
 *     @type int    $default_transition_animation_duration Default transition animation duration in milliseconds.
 *     @type string $header_selector                       CSS selector for the global header element.
 *     @type string $main_selector                         CSS selector for the global main element.
 *     @type string $post_title_selector                   CSS selector for the post title element.
 *     @type string $post_thumbnail_selector               CSS selector for the post thumbnail element.
 *     @type string $post_content_selector                 CSS selector for the post content element.
 *     @type bool   $enable_admin_transitions              Whether to use view transitions in the admin area.
 * }
 */
function plvt_sanitize_setting( $input ): array {
	$default_value = plvt_get_setting_default();

	if ( ! is_array( $input ) ) {
		return $default_value;
	}

	$value = $default_value;

	if (
		isset( $input['default_transition_animation'] ) &&
		in_array( $input['default_transition_animation'], array_keys( plvt_get_view_transition_animation_labels() ), true )
	) {
		$value['default_transition_animation'] = $input['default_transition_animation'];
	}

	// Handle default_transition_animation_duration separately.
	if ( isset( $input['default_transition_animation_duration'] ) ) {
		$value['default_transition_animation_duration'] = absint( $input['default_transition_animation_duration'] );
	}

	$selector_options = array(
		'header_selector',
		'main_selector',
		'post_title_selector',
		'post_thumbnail_selector',
		'post_content_selector',
	);
	foreach ( $selector_options as $selector_option ) {
		if ( isset( $input[ $selector_option ] ) && is_string( $input[ $selector_option ] ) ) {
			$selector_option_value = trim( sanitize_text_field( $input[ $selector_option ] ) );
			if ( '' !== $selector_option_value ) {
				$value[ $selector_option ] = $selector_option_value;
			}
		}
	}

	$checkbox_options = array(
		'override_theme_config',
		'enable_admin_transitions',
	);
	foreach ( $checkbox_options as $checkbox_option ) {
		if ( isset( $input[ $checkbox_option ] ) ) {
			$value[ $checkbox_option ] = (bool) $input[ $checkbox_option ];
		}
	}

	return $value;
}

/**
 * Registers setting to control View Transitions configuration.
 *
 * @since 1.0.0
 * @access private
 */
function plvt_register_setting(): void {
	register_setting(
		'reading',
		'plvt_view_transitions',
		array(
			'type'              => 'object',
			'description'       => __( 'Configuration for View Transitions.', 'view-transitions' ),
			'sanitize_callback' => 'plvt_sanitize_setting',
			'default'           => plvt_get_setting_default(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'default_transition_animation' => array(
							'description' => __( 'Animation to use for the default view transition type.', 'view-transitions' ),
							'type'        => 'string',
							'enum'        => array_keys( plvt_get_view_transition_animation_labels() ),
						),
					),
					'additionalProperties' => false,
				),
			),
		)
	);
}

/**
 * Applies the current stored View Transitions settings to the registered theme support.
 *
 * Important: This runs _after_ `plvt_sanitize_view_transitions_theme_support()`, so we can assume theme support has already been sanitized.
 *
 * @since 1.0.0
 * @access private
 *
 * @global bool|null            $plvt_has_theme_support_with_args Whether the current theme explicitly supports view transitions with custom config.
 * @global array<string, mixed> $_wp_theme_features               Theme support features added and their arguments.
 */
function plvt_apply_settings_to_theme_support(): void {
	global $plvt_has_theme_support_with_args, $_wp_theme_features;

	// Bail if the feature is disabled.
	if ( ! isset( $_wp_theme_features['view-transitions'] ) ) {
		return;
	}

	// Bail if the current theme explicitly supports view transitions and the option to override is turned off.
	$options = plvt_get_stored_setting_value();
	if ( $plvt_has_theme_support_with_args && ! $options['override_theme_config'] ) {
		return;
	}

	$args = $_wp_theme_features['view-transitions'];

	// Apply the settings.
	$args['default-animation']          = $options['default_transition_animation'];
	$args['default-animation-duration'] = absint( $options['default_transition_animation_duration'] );
	$selector_options                   = array(
		'global' => array(
			'header_selector' => 'header',
			'main_selector'   => 'main',
		),
		'post'   => array(
			'post_title_selector'     => 'post-title',
			'post_thumbnail_selector' => 'post-thumbnail',
			'post_content_selector'   => 'post-content',
		),
	);
	foreach ( $selector_options as $group => $option_name_view_transition_name_map ) {
		foreach ( $option_name_view_transition_name_map as $option_name => $view_transition_name ) {
			$existing_key = array_search( $view_transition_name, $args[ $group . '-transition-names' ], true );
			if ( is_string( $existing_key ) ) {
				unset( $args[ $group . '-transition-names' ][ $existing_key ] );
			}
			$args[ $group . '-transition-names' ][ $options[ $option_name ] ] = $view_transition_name;
		}
	}

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$_wp_theme_features['view-transitions'] = $args;
}

/**
 * Adds the settings sections and fields for the View Transitions configuration.
 *
 * @since 1.0.0
 * @access private
 *
 * @global bool|null $plvt_has_theme_support_with_args Whether the current theme explicitly supports view transitions with custom config.
 */
function plvt_add_setting_ui(): void {
	global $plvt_has_theme_support_with_args;

	add_settings_section(
		'plvt_view_transitions',
		_x( 'View Transitions', 'Settings section', 'view-transitions' ),
		static function (): void {
			global $plvt_has_theme_support_with_args;
			?>
			<p class="description">
				<?php esc_html_e( 'This section allows you to control view transitions usage on your site to enhance the navigation user experience.', 'view-transitions' ); ?>
				<br>
				<?php esc_html_e( 'To reset any of the selector text inputs, clear the field and save the changes.', 'view-transitions' ); ?>
			</p>
			<?php
			if ( $plvt_has_theme_support_with_args ) {
				wp_admin_notice(
					__( 'Your theme already supports view transitions with its own adapted configuration. The settings below will override those.', 'view-transitions' ),
					array(
						'type'               => 'info',
						'additional_classes' => array( 'inline' ),
					)
				);
			}
		},
		'reading',
		array(
			'before_section' => '<div id="view-transitions">',
			'after_section'  => '</div>',
		)
	);

	add_settings_section(
		'plvt_admin_view_transitions',
		_x( 'Admin View Transitions', 'Settings section', 'view-transitions' ),
		static function (): void {
			?>
			<p class="description">
				<?php esc_html_e( 'This section allows you to control view transitions usage in the WordPress admin area.', 'view-transitions' ); ?>
			</p>
			<?php
		},
		'reading',
		array(
			'before_section' => '<div id="admin-view-transitions">',
			'after_section'  => '</div>',
		)
	);

	$fields = array(
		'override_theme_config'                 => array(
			'section'     => 'plvt_view_transitions',
			'title'       => __( 'Override Theme Configuration', 'view-transitions' ),
			'description' => __( 'Override the theme provided configuration with the settings below.', 'view-transitions' ),
		),
		'default_transition_animation'          => array(
			'section'     => 'plvt_view_transitions',
			'title'       => __( 'Default Transition Animation', 'view-transitions' ),
			'description' => __( 'Choose the animation that is used for the default view transition type.', 'view-transitions' ),
		),
		'default_transition_animation_duration' => array(
			'section'     => 'plvt_view_transitions',
			'title'       => __( 'Transition Animation Duration', 'view-transitions' ),
			'description' => __( 'Control the duration of the view transition. Enter the value in milliseconds (e.g., 500, 1000, 2000).', 'view-transitions' ),
		),
		'header_selector'                       => array(
			'section'     => 'plvt_view_transitions',
			'title'       => __( 'Header Selector', 'view-transitions' ),
			'description' => __( 'Provide the CSS selector to detect the global header element.', 'view-transitions' ),
		),
		'main_selector'                         => array(
			'section'     => 'plvt_view_transitions',
			'title'       => __( 'Main Selector', 'view-transitions' ),
			'description' => __( 'Provide the CSS selector to detect the global main element.', 'view-transitions' ),
		),
		'post_title_selector'                   => array(
			'section'     => 'plvt_view_transitions',
			'title'       => __( 'Post Title Selector', 'view-transitions' ),
			'description' => __( 'Provide the CSS selector to detect the post title element.', 'view-transitions' ),
		),
		'post_thumbnail_selector'               => array(
			'section'     => 'plvt_view_transitions',
			'title'       => __( 'Post Thumbnail Selector', 'view-transitions' ),
			'description' => __( 'Provide the CSS selector to detect the post thumbnail element.', 'view-transitions' ),
		),
		'post_content_selector'                 => array(
			'section'     => 'plvt_view_transitions',
			'title'       => __( 'Post Content Selector', 'view-transitions' ),
			'description' => __( 'Provide the CSS selector to detect the post content element.', 'view-transitions' ),
		),
		'enable_admin_transitions'              => array(
			'section'     => 'plvt_admin_view_transitions',
			'title'       => __( 'WP Admin', 'view-transitions' ),
			'description' => __( 'Enable view transitions in the WordPress admin area.', 'view-transitions' ),
		),
	);

	// Do not render the checkbox to override if there is nothing to override.
	if ( ! $plvt_has_theme_support_with_args ) {
		unset( $fields['override_theme_config'] );
	}

	foreach ( $fields as $slug => $args ) {
		$section = $args['section'];
		unset( $args['section'] );

		$additional_args = array(
			'field'     => $slug,
			'label_for' => "plvt-view-transitions-field-{$slug}",
		);

		// Remove 'label_for' for checkbox fields to avoid duplicate label association.
		if ( 'override_theme_config' === $slug || 'enable_admin_transitions' === $slug ) {
			unset( $additional_args['label_for'] );
		}

		add_settings_field(
			"plvt_view_transitions_{$slug}",
			$args['title'],
			'plvt_render_settings_field',
			'reading',
			$section,
			array_merge(
				$additional_args,
				$args
			)
		);
	}
}

/**
 * Renders a settings field for the View Transitions configuration.
 *
 * @since 1.0.0
 * @access private
 *
 * @param array{ field: non-empty-string, title: non-empty-string, description: string, label_for: non-empty-string } $args {
 *     Associative array of arguments.
 *
 *     @type string $field       The slug of the sub setting controlled by the field.
 *     @type string $title       The title for the field.
 *     @type string $description Optional. A description to show for the field.
 *     @type string $label_for   ID to use for the field control.
 * }
 */
function plvt_render_settings_field( array $args ): void {
	$option = plvt_get_stored_setting_value();

	switch ( $args['field'] ) {
		case 'default_transition_animation':
			$type    = 'select';
			$choices = plvt_get_view_transition_animation_labels();
			break;
		case 'default_transition_animation_duration':
			$type    = 'number';
			$choices = array(); // Defined just for consistency.
			break;
		case 'override_theme_config':
		case 'enable_admin_transitions':
			$type    = 'checkbox';
			$choices = array(); // Defined just for consistency.
			break;
		default:
			$type    = 'text';
			$choices = array(); // Defined just for consistency.
	}

	$value = $option[ $args['field'] ] ?? '';

	if ( 'select' === $type ) {
		?>
		<select
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( "plvt_view_transitions[{$args['field']}]" ); ?>"
			<?php
			if ( '' !== $args['description'] ) {
				?>
				aria-describedby="<?php echo esc_attr( $args['label_for'] . '-description' ); ?>"
				<?php
			}
			?>
		>
			<?php
			foreach ( $choices as $slug => $label ) {
				?>
				<option
					value="<?php echo esc_attr( $slug ); ?>"
					<?php selected( $value, $slug ); ?>
				>
					<?php echo esc_html( $label ); ?>
				</option>
				<?php
			}
			?>
		</select>
		<?php
	} elseif ( 'checkbox' === $type ) {
		?>
		<label for="<?php echo esc_attr( "plvt-view-transitions-field-{$args['field']}" ); ?>">
			<input
				id="<?php echo esc_attr( "plvt-view-transitions-field-{$args['field']}" ); ?>"
				name="<?php echo esc_attr( "plvt_view_transitions[{$args['field']}]" ); ?>"
				type="checkbox"
				value="1"
				<?php checked( $value, 1 ); ?>
			>
			<?php echo esc_html( $args['description'] ); ?>
		</label>
		<?php
	} else {
		?>
		<input
			<?php echo ( 'number' === $type ) ? 'type="number"' : ''; ?>
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( "plvt_view_transitions[{$args['field']}]" ); ?>"
			value="<?php echo esc_attr( (string) $value ); ?>"
			class="regular-text code"
			<?php
			if ( '' !== $args['description'] ) {
				?>
				aria-describedby="<?php echo esc_attr( $args['label_for'] . '-description' ); ?>"
				<?php
			}
			?>
		>
		<?php
	}

	if ( '' !== $args['description'] && 'checkbox' !== $type ) {
		?>
		<p
			id="<?php echo esc_attr( $args['label_for'] . '-description' ); ?>"
			class="description"
			style="max-width: 800px;"
		>
			<?php echo esc_html( $args['description'] ); ?>
		</p>
		<?php
	}
}

/**
 * Adds a settings link to the plugin's action links.
 *
 * @since 1.0.0
 *
 * @param string[]|mixed $links An array of plugin action links.
 * @return non-empty-array<string> The modified list of actions.
 */
function plvt_add_settings_action_link( $links ): array {
	if ( ! is_array( $links ) ) {
		$links = array();
	}

	return array_merge(
		array(
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-reading.php#view-transitions' ) ),
				esc_html__( 'Settings', 'view-transitions' )
			),
		),
		$links
	);
}
