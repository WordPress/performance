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
	register_setting( 'budget_calc_settings', 'html_range', 'absint' );
	add_settings_section( 'budget_calc_settings_id', 'Budget Calculator Metrics', '', 'budget-calculator' );

	add_settings_field(
		'html_range',
		'HTML',
		function () {
			budget_calc_render_range_field( 'html_range', 300 );
		},
		'budget-calculator',
		'budget_calc_settings_id'
	);

}
add_action( 'admin_init', 'budget_calc_register_settings' );

/**
 * Render a range input field for the budget calculator options page.
 *
 * @since 1.0.0
 *
 * @param string $field_id The ID of the field in the database.
 * @param int    $max      The maximum value that can be set on the range input.
 * @param int    $step     The value by which we increment when moving the range slider.
 */
function budget_calc_render_range_field( $field_id, $max, $step = 10 ) {
	$field_id = esc_attr( $field_id );
	$text     = esc_attr( get_option( $field_id ) );
	$max      = esc_attr( $max );
	$step     = esc_attr( $step );

	echo "<input type='range' id='$field_id' name='$field_id' min='0' max='$max' step='$step' value='$text' />";
}
