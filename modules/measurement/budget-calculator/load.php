<?php
/**
 * Module Name: Budget Calculator
 * Description: Allows users to define their own metrics and generate a budget.json file from them.
 * Experimental: No
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Display the content of the budget calculator option page.
 *
 * @since n.e.x.t
 */
function budget_calc_page_content() {
	?>
	<div class="wrap">
	<h1><?php esc_html_e( 'Budget Calculator', 'performance-lab' ); ?></h1>
	<form method="post" action="options.php">
	<?php

	settings_fields( 'budget_calc_settings' );
	do_settings_sections( 'budget-calculator' );
	submit_button( __( 'Download budget.json', 'performance-lab' ) );

	?>
	</form></div>
	<?php
}

/**
 * Register the option page.
 *
 * @since n.e.x.t
 */
function budget_calc_menu_page() {
	add_menu_page(
		__( 'Budget Calculator', 'performance-lab' ),
		__( 'Budget Calculator', 'performance-lab' ),
		'manage_options',
		'budget-calculator',
		'budget_calc_page_content',
		'dashicons-calculator'
	);
}
add_action( 'admin_menu', 'budget_calc_menu_page' );

/**
 * Register the settings for the option page.
 *
 * @since n.e.x.t
 */
function budget_calc_register_settings() {
	register_setting(
		'budget_calc_settings',
		'budget_calc_options',
		array(
			'sanitize_callback' => 'budget_calc_sanitize_settings',
		)
	);

	add_settings_section( 'budget_calc_settings_id', __( 'Budget Calculator Metrics', 'performance-lab' ), '', 'budget-calculator' );

	$options        = get_option( 'budget_calc_options', array() );
	$range_settings = array(
		'HTML'       => 300,
		'CSS'        => 200,
		'Font'       => 200,
		'Images'     => 3000,
		'JavaScript' => 750,
	);

	foreach ( $range_settings as $title => $max ) {
		$id = strtolower( $title ) . '_range';

		add_settings_field(
			$id,
			$title,
			function () use ( $options, $id, $max ) {
				budget_calc_render_range_field( $options, $id, $max );
			},
			'budget-calculator',
			'budget_calc_settings_id'
		);
	}

	add_settings_field(
		'updated',
		'',
		'budget_calc_render_hidden_updated_field',
		'budget-calculator',
		'budget_calc_settings_id',
		array(
			'class' => 'hidden',
		)
	);
}
add_action( 'admin_init', 'budget_calc_register_settings' );

/**
 * Callback to sanitize the input from the settings form.
 *
 * @since n.e.x.t
 *
 * @param array $input The raw submitted input.
 * @return array The sanitized input.
 */
function budget_calc_sanitize_settings( $input ) {
	$number_fields = array(
		'html_range',
		'css_range',
		'font_range',
		'images_range',
		'javascript_range',
		'updated',
	);

	foreach ( $number_fields as $field ) {
		if ( isset( $input[ $field ] ) ) {
			$input[ $field ] = (int) $input[ $field ];
		}
	}

	return $input;
}

/**
 * Callback to render an updated hidden field.
 * This field saves a timestamp of the current time.
 * Its purpose is to still trigger an update event to download the budget.json file in case nothing was modified.
 *
 * @since n.e.x.t
 */
function budget_calc_render_hidden_updated_field() {
	?>
		<input type="hidden" value="<?php echo time(); ?>" name="budget_calc_options[updated]" />
	<?php
}

/**
 * Render a range input field for the budget calculator options page.
 *
 * @since n.e.x.t
 *
 * @param array  $options  The settings value already set in the database, if any.
 * @param string $field_id The ID of the field in the database.
 * @param int    $max      The maximum value that can be set on the range input.
 */
function budget_calc_render_range_field( $options, $field_id, $max ) {
	$range_value = isset( $options[ $field_id ] ) ? (int) $options[ $field_id ] : 0;

	?>
	<input
		type="range"
		id="<?php echo esc_attr( $field_id ); ?>"
		name="budget_calc_options[<?php echo esc_attr( $field_id ); ?>]"
		min="0"
		max="<?php echo esc_attr( $max ); ?>"
		step="10"
		value="<?php echo esc_attr( $range_value ); ?>"
		oninput="document.getElementById('<?php echo esc_attr( $field_id ) . '_output'; ?>').textContent=this.value+'KB'"
	>
	<span id="<?php echo esc_attr( $field_id ) . '_output'; ?>">
		<?php echo esc_html( $range_value ) . 'KB'; ?>
	</span>
	<?php
}

/**
 * Format data to the budget.json expected formatting.
 *
 * @since n.e.x.t
 *
 * @param array $value The value of the options from the database.
 * @return array The formatted data,
 */
function budget_calc_format_for_budget_json( $value ) {
	$resource_sizes = array();
	$type_mapping   = array(
		'html_range'       => 'document',
		'css_range'        => 'stylesheet',
		'font_range'       => 'font',
		'images_range'     => 'image',
		'javascript_range' => 'script',
	);

	foreach ( $type_mapping as $range => $type ) {
		if ( isset( $value[ $range ] ) ) {
			$resource_sizes[] = array(
				'resourceType' => $type,
				'budget'       => $value[ $range ],
			);
		}
	}

	return array( array( 'resourceSizes' => $resource_sizes ) );
}

/**
 * Callback for the option page updated event.
 *
 * @since n.e.x.t
 *
 * @param string $option    The name of the option that has been updated.
 * @param array  $old_value The previously set value for this option.
 * @param array  $value     The newly set value for this option.
 */
function budget_calc_updated_option_callback( $option, $old_value, $value ) {
	if ( 'budget_calc_options' !== $option ) {
		return;
	}

	$data = budget_calc_format_for_budget_json( $value );

	header( 'Content-disposition: attachment; filename=budget.json' );
	wp_send_json( $data );
}
add_action( 'updated_option', 'budget_calc_updated_option_callback', 10, 3 );

/**
 * Callback for the option page added event.
 *
 * @since n.e.x.t
 *
 * @param string $option The name of the option that has been updated.
 * @param array  $value The newly set value for this option.
 */
function budget_calc_added_option_callback( $option, $value ) {
	if ( 'budget_calc_options' !== $option ) {
		return;
	}

	$data = budget_calc_format_for_budget_json( $value );

	header( 'Content-disposition: attachment; filename=budget.json' );
	wp_send_json( $data );
}
add_action( 'added_option', 'budget_calc_added_option_callback', 10, 2 );
