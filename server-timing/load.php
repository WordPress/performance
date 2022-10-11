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

	if ( perflab_server_timing_use_output_buffer() ) {
		// The 'template_include' filter is the very last point before HTML is rendered.
		add_filter(
			'template_include',
			function( $passthrough ) {
				ob_start();
				return $passthrough;
			},
			PHP_INT_MAX
		);
		add_action(
			'wp_footer',
			function() use ( $server_timing ) {
				$output = ob_get_clean();
				$server_timing->add_header();
				echo $output;
			},
			PHP_INT_MAX
		);
	} else {
		// The 'template_include' filter is the very last point before HTML is rendered.
		add_filter(
			'template_include',
			function( $passthrough ) use ( $server_timing ) {
				$server_timing->add_header();
				return $passthrough;
			},
			PHP_INT_MAX
		);
	}

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
 * Returns whether an output buffer should be used to gather Server-Timing metrics during template rendering.
 *
 * @since n.e.x.t
 *
 * @return bool True if an output buffer should be used, false otherwise.
 */
function perflab_server_timing_use_output_buffer() {
	/**
	 * Filters whether an output buffer should be used to be able to gather additional Server-Timing metrics.
	 *
	 * @since n.e.x.t
	 *
	 * @param bool $use_output_buffer Whether to use an output buffer.
	 */
	return apply_filters( 'perflab_server_timing_use_output_buffer', false );
}

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

	if ( perflab_server_timing_use_output_buffer() ) {
		// WordPress execution while serving the template.
		$server_timing->register_metric(
			'template',
			array(
				'measure_callback' => function( $metric ) {
					$start_time = null;

					add_filter(
						'template_include',
						function( $passthrough ) use ( &$start_time ) {
							$start_time = microtime( true );
							return $passthrough;
						},
						PHP_INT_MAX - 1
					);
					add_action(
						'wp_footer',
						function() use ( $metric, &$start_time ) {
							if ( null === $start_time ) {
								return;
							}
							$metric->set_value( ( microtime( true ) - $start_time ) * 1000.0 );
						},
						PHP_INT_MAX - 1
					);
				},
				'access_cap'       => 'exist',
			)
		);
	}
}
add_action( 'perflab_server_timing_init', 'perflab_register_default_server_timing_metrics' );

/**
 * Wraps a callback (e.g. for an action or filter) to be measured and included in the Server-Timing header.
 *
 * @since n.e.x.t
 *
 * @param callable $callback    The callback to wrap.
 * @param string   $metric_slug The metric slug to use within the Server-Timing header.
 * @param string   $access_cap  Capability required to view the metric. If this is a public metric, this needs to be
 *                              set to "exist".
 */
function perflab_wrap_server_timing( $callback, $metric_slug, $access_cap ) {
	// Gain access to Perflab_Server_Timing_Metric instance.
	$server_timing_metric = null;
	add_action(
		'perflab_server_timing_init',
		function( $server_timing ) use ( &$server_timing_metric, $metric_slug, $access_cap ) {
			$server_timing->register_metric(
				$metric_slug,
				array(
					'measure_callback' => function( $metric ) use ( &$server_timing_metric ) {
						$server_timing_metric = $metric;
					},
					'access_cap'       => $access_cap,
				)
			);
		}
	);

	return function( ...$callback_args ) use ( &$server_timing_metric, $callback ) {
		// If metric instance was not set, this metric should not be calculated.
		if ( null === $server_timing_metric ) {
			return call_user_func_array( $callback, $callback_args );
		}

		// Store start time (in microseconds).
		$start_time = microtime( true );

		// Execute the callback.
		$result = call_user_func_array( $callback, $callback_args );

		// Calculate total time (in milliseconds) and set it for the metric.
		$total_time = ( microtime( true ) - $start_time ) * 1000.0;
		$server_timing_metric->set_value( $total_time );

		// Return result (e.g. in case this is a filter callback).
		return $result;
	};
}
