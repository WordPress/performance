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
			<div id="od-prime-url-metrics-container">
				<p><?php esc_html_e( 'This tool is useful if you have installed the Optimization Detective plugin for the first time. Since the plugin relies on URL metrics collected when users visit your site, it may take time to gather this data naturally. This tool allows you to collect the minimum URL metrics required for Optimization Detective to function properly.', 'optimization-detective' ); ?></p>
				<h3><?php esc_html_e( 'Instructions:', 'optimization-detective' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Click the "Start" button to begin the priming process.', 'optimization-detective' ); ?></li>
					<li><?php esc_html_e( 'The progress bar will indicate the progress of current batch.', 'optimization-detective' ); ?></li>
					<li><?php esc_html_e( 'You can "Pause" and "Resume" the process as needed..', 'optimization-detective' ); ?></li>
					<li><?php esc_html_e( 'Once finished, the button will display "Finished".', 'optimization-detective' ); ?></li>
				</ol>
				<h3><?php esc_html_e( 'Important Information:', 'optimization-detective' ); ?></h3>
				<div>
					<p><?php esc_html_e( 'Running this process will consume server resources as it loads URLs in an iframe to prime metrics. Performance may be temporarily affected during processing, especially on large websites or during peak traffic times. It is recommended to run this tool during off-peak hours.', 'optimization-detective' ); ?></p>
					<p><?php esc_html_e( 'The priming process may take a significant amount of time depending on the number of URLs and breakpoints being processed. Please be patient and allow the process to complete, especially for websites with many URLs.', 'optimization-detective' ); ?></p>
					<p><?php esc_html_e( 'Note: You must keep this page open and the tab visible. If the browser window is minimized or you switch to another tab, URL priming will stop.', 'optimization-detective' ); ?></p>
				</div>
				<div class="od-prime-url-metrics-controls">
					<button id="od-prime-url-metrics-control-button" class="button button-primary"><?php esc_html_e( 'Start', 'optimization-detective' ); ?></button>
				</div>
				<progress id="od-prime-url-metrics-progress" value="0" max="0"></progress>
				<div class="od-prime-url-metrics-status">
					<span id="od-prime-url-metrics-batch-status"><?php esc_html_e( 'Batch:', 'optimization-detective' ); ?> <span id="od-prime-url-metrics-current-batch">0</span></span>
					<span id="od-prime-url-metrics-task-status"><?php esc_html_e( 'Task:', 'optimization-detective' ); ?> <span id="od-prime-url-metrics-current-task">0</span> / <span id="od-prime-url-metrics-total-tasks-in-batch">0</span></span>
				</div>
				<div id="od-prime-url-metrics-iframe-container">
					<iframe id="od-prime-url-metrics-iframe" src="" style="position: fixed; transform: scale(0.05); top: 0px; left: 0px; transform-origin: 0px 0px; pointer-events: none; opacity: 1e-06; z-index: -99999;"></iframe>
				</div>
			</div>
		</div>
	</div>
	</div>
	<?php
}