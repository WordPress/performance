<?php
/**
 * Admin related functions for View Transitions.
 *
 * @package view-transitions
 * @since 1.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Outputs the necessary CSS styles for view transitions.
 *
 * This function is responsible for printing the required inline styles
 * to enable or enhance view transitions within the theme or plugin.
 * It should be hooked to an appropriate action to ensure the styles
 * are included in the page output.
 *
 * @since 1.1.0
 */
function plvt_print_view_transitions_admin_style(): void {
	$options = plvt_get_stored_setting_value();
	if ( ! isset( $options['enable_admin_transitions'] ) || ! (bool) $options['enable_admin_transitions'] ) {
		return;
	}

	$duration = absint( $options['default_transition_animation_duration'] );
	?>
<style>
	@view-transition { navigation: auto; }
	::view-transition-group(*) { --plvt-view-transition-animation-duration: <?php echo (int) $duration; ?>ms; }
	#adminmenu > .menu-top { view-transition-name: attr(id type(<custom-ident>), none); }
</style>
	<?php
}

/**
 * Enqueues the CSS selector validation scripts and styles on the settings page.
 *
 * This function loads the JavaScript and CSS needed for real-time CSS selector
 * validation in the View Transitions settings panel.
 *
 * @since n.e.x.t
 * @access private
 */
function plvt_enqueue_selector_validation(): void {
	$current_screen = get_current_screen();

	// Only enqueue on the reading settings page.
	if ( null === $current_screen || 'options-reading' !== $current_screen->id ) {
		return;
	}

	// Enqueue validation CSS.
	wp_enqueue_style(
		'plvt-selector-validator',
		plugin_dir_url( VIEW_TRANSITIONS_MAIN_FILE ) . 'css/validator-selector.css',
		array(),
		VIEW_TRANSITIONS_VERSION
	);

	// Enqueue validation JS.
	wp_enqueue_script(
		'plvt-selector-validator',
		plugin_dir_url( VIEW_TRANSITIONS_MAIN_FILE ) . 'js/validator-selector.js',
		array(),
		VIEW_TRANSITIONS_VERSION,
		array( 'in_footer' => false )
	);
}
