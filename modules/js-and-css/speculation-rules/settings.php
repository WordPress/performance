<?php
/**
 * Settings functions used for Speculation Rules.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Returns the available options for the Speculation Rules mode and their labels.
 *
 * @since n.e.x.t
 *
 * @return array Associative array of `$mode => $label` pairs.
 */
function plsr_get_mode_labels() {
	return array(
		'prefetch'  => _x( 'Prefetch', 'setting label', 'performance-lab' ),
		'prerender' => _x( 'Prerender', 'setting label', 'performance-lab' ),
	);
}

/**
 * Returns the available options for the Speculation Rules eagerness and their labels.
 *
 * @since n.e.x.t
 *
 * @return array Associative array of `$eagerness => $label` pairs.
 */
function plsr_get_eagerness_labels() {
	return array(
		'conservative' => _x( 'Conservative (typically on click)', 'setting label', 'performance-lab' ),
		'moderate'     => _x( 'Moderate (typically on hover)', 'setting label', 'performance-lab' ),
		'eager'        => _x( 'Eager (on slightest suggestion)', 'setting label', 'performance-lab' ),
	);
}

/**
 * Returns the default setting value for Speculation Rules configuration.
 *
 * @since n.e.x.t
 *
 * @return array Default value, an associative array with 'mode' and 'eagerness' keys.
 */
function plsr_get_setting_default() {
	return array(
		'mode'      => 'prerender',
		'eagerness' => 'moderate',
	);
}

/**
 * Sanitizes the setting for Speculation Rules configuration.
 *
 * @since n.e.x.t
 *
 * @param mixed $input Setting to sanitize.
 * @return array Sanitized setting, an associative array with 'mode' and 'eagerness' keys.
 */
function plsr_sanitize_setting( $input ) {
	$default_value = plsr_get_setting_default();

	if ( ! is_array( $input ) ) {
		return $default_value;
	}

	$mode_labels      = plsr_get_mode_labels();
	$eagerness_labels = plsr_get_eagerness_labels();

	// Ensure only valid keys are present.
	$value = array_intersect_key( $input, $default_value );

	// Set any missing or invalid values to their defaults.
	if ( ! isset( $value['mode'] ) || ! isset( $mode_labels[ $value['mode'] ] ) ) {
		$value['mode'] = $default_value['mode'];
	}
	if ( ! isset( $value['eagerness'] ) || ! isset( $eagerness_labels[ $value['eagerness'] ] ) ) {
		$value['eagerness'] = $default_value['eagerness'];
	}

	return $value;
}

/**
 * Registers setting to control Speculation Rules configuration.
 *
 * @since n.e.x.t
 * @access private
 */
function plsr_register_setting() {
	register_setting(
		'reading',
		'plsr_speculation_rules',
		array(
			'type'              => 'object',
			'description'       => __( 'Configuration for the Speculation Rules API.', 'performance-lab' ),
			'sanitize_callback' => 'plsr_sanitize_setting',
			'default'           => plsr_get_setting_default(),
			'show_in_rest'      => array(
				'schema' => array(
					'properties' => array(
						'mode'      => array(
							'description' => __( 'Whether to prefetch or prerender URLs.', 'performance-lab' ),
							'type'        => 'string',
							'enum'        => array_keys( plsr_get_mode_labels() ),
						),
						'eagerness' => array(
							'description' => __( 'Whether to trigger on click (conservative), on hover (moderate), or on even the slight suggestion (eager) that the user may navigate to the URL.', 'performance-lab' ),
							'type'        => 'string',
							'enum'        => array_keys( plsr_get_eagerness_labels() ),
						),
					),
				),
			),
		)
	);
}
add_action( 'init', 'plsr_register_setting' );

/**
 * Adds the settings sections and fields for the Speculation Rules configuration.
 *
 * @since n.e.x.t
 * @access private
 */
function plsr_add_setting_ui() {
	add_settings_section(
		'plsr_speculation_rules',
		__( 'Speculation Rules', 'performance-lab' ),
		static function () {
			?>
			<p class="description">
				<?php esc_html_e( 'This section allows you to control how URLs that your users navigate to are speculatively loaded to improve performance.', 'performance-lab' ); ?>
			</p>
			<?php
		},
		'reading'
	);

	$fields = array(
		'mode'      => array(
			'title'       => __( 'Mode', 'performance-lab' ),
			'description' => __( 'Prerendering will lead to faster load times than prefetching. However, in case of interactive content, prefetching may be a safer choice.', 'performance-lab' ),
		),
		'eagerness' => array(
			'title'       => __( 'Eagerness', 'performance-lab' ),
			'description' => __( 'The eagerness setting defines the heuristics based on which the loading is triggered.', 'performance-lab' )
				. '<br>' . __( '"Eager" will have the minimum delay to start loading, "Conservative" increases the chance that only URLs the user actually navigates to are loaded.', 'performance-lab' ),
		),
	);
	foreach ( $fields as $slug => $args ) {
		add_settings_field(
			"plsr_speculation_rules_{$slug}",
			$args['title'],
			'plsr_render_settings_field',
			'reading',
			'plsr_speculation_rules',
			array_merge(
				array( 'field' => $slug ),
				$args
			)
		);
	}
}
add_action( 'load-options-reading.php', 'plsr_add_setting_ui' );

/**
 * Renders a settings field for the Speculation Rules configuration.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array $args Associative array with 'field', 'title', and optional 'description' keys.
 */
function plsr_render_settings_field( array $args ) {
	if ( empty( $args['field'] ) || empty( $args['title'] ) ) { // Invalid.
		return;
	}

	$option = get_option( 'plsr_speculation_rules' );
	if ( ! isset( $option[ $args['field'] ] ) ) { // Invalid.
		return;
	}

	$value   = $option[ $args['field'] ];
	$choices = call_user_func( "plsr_get_{$args['field']}_labels" );

	?>
	<fieldset>
		<legend class="screen-reader-text"><?php echo esc_html( $args['title'] ); ?></legend>
		<?php
		foreach ( $choices as $slug => $label ) {
			?>
			<p>
				<label>
					<input
						name="<?php echo esc_attr( "plsr_speculation_rules[{$args['field']}]" ); ?>"
						type="radio"
						value="<?php echo esc_attr( $slug ); ?>"
						<?php checked( $value, $slug ); ?>
					>
					<?php echo esc_html( $label ); ?>
				</label>
			</p>
			<?php
		}

		if ( ! empty( $args['description'] ) ) {
			?>
			<p class="description">
				<?php echo wp_kses( $args['description'], array( 'br' => array() ) ); ?>
			</p>
			<?php
		}
		?>
	</fieldset>
	<?php
}
