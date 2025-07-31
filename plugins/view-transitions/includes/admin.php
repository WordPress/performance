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
	if ( ! isset( $options['enable_admin_transitions'] ) || true !== $options['enable_admin_transitions'] ) {
		return;
	}
	?>
<style>
	@view-transition { navigation: auto; }
	#adminmenu > .menu-top { view-transition-name: attr(id type(<custom-ident>), none); }
</style>
	<?php
}
