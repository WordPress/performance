<?php
/**
 * Server-Timing API integration file
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Initializes the Server-Timing API.
 *
 * This fires the {@see 'perflab_server_timing_init'} action that should be used to register metrics.
 *
 * @since n.e.x.t
 */
function perflab_server_timing() {
	static $server_timing;

	if ( null !== $server_timing ) {
		return;
	}

	$server_timing = new Perflab_Server_Timing();

	// The 'template_include' filter is the very last point before HTML is rendered.
	add_filter(
		'template_include',
		function( $passthrough ) use ( $server_timing ) {
			$server_timing->add_header();
			return $passthrough;
		},
		PHP_INT_MAX
	);

	/**
	 * Initialization hook for the Server-Timing API.
	 *
	 * @since n.e.x.t
	 *
	 * @param Perflab_Server_Timing $server_timing Server-Timing API to register metrics.
	 */
	do_action( 'perflab_server_timing_init', $server_timing );
}
add_action( 'plugins_loaded', 'perflab_server_timing', 0 );

/**
 * Registers the default Server-Timing metrics.
 *
 * @since n.e.x.t
 *
 * @param Perflab_Server_Timing $server_timing Server-Timing API to register metrics.
 */
function perflab_register_default_server_timing_metrics( $server_timing ) {
	// WordPress execution prior to serving the template.
	$server_timing->register_metric(
		'before-template',
		array(
			'measure_callback' => function( $metric ) {
				global $timestart;

				// Use original value of global in case a plugin messes with it.
				$start_time = $timestart;

				add_filter(
					'template_include',
					function( $passthrough ) use ( $metric, $start_time ) {
						$metric->set_value( ( microtime( true ) - $start_time ) * 1000.0 );
						return $passthrough;
					},
					PHP_INT_MAX - 1
				);
			},
			'access_cap'       => 'exist',
		)
	);
}
add_action( 'perflab_server_timing_init', 'perflab_register_default_server_timing_metrics' );
