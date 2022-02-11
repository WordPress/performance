<?php
/**
 * Module Name: Budget Calculator
 * Description: Allows users to define their own metrics and generate a budget.json file from them.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Display the content of the budget calculator option page.
 *
 * @since 1.0.0
 */
function budget_calc_page_content() {
	echo '<div class="wrap">
	<h1>Budget Calculator</h1>
	<form method="post" action="options.php">';

	settings_fields( 'budget_calc_settings' );
	do_settings_sections( 'budget-calculator' );
	submit_button();

	echo '</form></div>';
}

/**
 * Register the option page.
 *
 * @since 1.0.0
 */
function budget_calc_menu_page() {
	add_menu_page(
		'Budget Calculator',
		'Budget Calculator',
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
 * @since 1.0.0
 */
function budget_calc_register_settings() {
	register_setting( 'budget_calc_settings', 'budget_calc_options' );
	add_settings_section( 'budget_calc_settings_id', 'Budget Calculator Metrics', '', 'budget-calculator' );

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
}
add_action( 'admin_init', 'budget_calc_register_settings' );

/**
 * Render a range input field for the budget calculator options page.
 *
 * @since 1.0.0
 *
 * @param array  $options  The settings value already set in the database, if any.
 * @param string $field_id The ID of the field in the database.
 * @param int    $max      The maximum value that can be set on the range input.
 */
function budget_calc_render_range_field( $options, $field_id, $max ) {
	$text     = isset( $options[ $field_id ] ) ? esc_attr( $options[ $field_id ] ) : 0;
	$field_id = esc_attr( $field_id );
	$max      = esc_attr( $max );

	echo "<input type='range' id='$field_id' name='budget_calc_options[$field_id]' min='0' max='$max' step='10'
		value='$text' oninput='document.getElementById(\"${field_id}_output\").value = this.value'/>
		<output id='${field_id}_output' for='budget_calc_options[$field_id]'>${text}</output><span>KB</span>";
}
