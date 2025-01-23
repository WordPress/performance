<?php
/**
 * Settings for the Optimization Detective plugin.
 *
 * @package optimization-detective
 *
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Render the Optimization Detective settings page.
 *
 * @since n.e.x.t
 */
function od_add_optimization_detective_menu(): void {
	add_submenu_page(
		'tools.php',
		__( 'Optimization Detective', 'optimization-detective' ),
		__( 'Optimization Detective', 'optimization-detective' ),
		'manage_options',
		'od-optimization-detective',
		'od_render_optimization_detective_page'
	);
}

/**
 * Render the Optimization Detective settings page.
 *
 * @since n.e.x.t
 */
function od_render_optimization_detective_page(): void {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Optimization Detective', 'optimization-detective' ); ?></h1>
		<div class="wrap">
			<h2><?php esc_html_e( 'Prime URL Metrics', 'optimization-detective' ); ?></h2>
		</div>
	</div>
	</div>
	<?php
}