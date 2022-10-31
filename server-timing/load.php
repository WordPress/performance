<?php
/**
 * Server-Timing API integration file
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Provides access the Server-Timing API.
 *
 * When called for the first time, this also initializes the API to schedule the header for output.
 * In case that no metrics are registered, this is still called on {@see 'wp_loaded'}, so that even then it still fires
 * its action hooks as expected.
 *
 * @since n.e.x.t
 */
function perflab_server_timing() {
	static $server_timing;

	if ( null === $server_timing ) {
		$server_timing = new Perflab_Server_Timing();
		add_filter( 'template_include', array( $server_timing, 'on_template_include' ), PHP_INT_MAX );
	}

	return $server_timing;
}
add_action( 'wp_loaded', 'perflab_server_timing' );

/**
 * Registers a metric to calculate for the Server-Timing header.
 *
 * This method must be called before the {@see 'perflab_server_timing_send_header'} hook.
 *
 * @since n.e.x.t
 *
 * @param string $metric_slug The metric slug.
 * @param array  $args        {
 *     Arguments for the metric.
 *
 *     @type callable $measure_callback The callback that initiates calculating the metric value. It will receive
 *                                      the Perflab_Server_Timing_Metric instance as a parameter, in order to set
 *                                      the value when it has been calculated. Metric values must be provided in
 *                                      milliseconds.
 *     @type string   $access_cap       Capability required to view the metric. If this is a public metric, this
 *                                      needs to be set to "exist".
 * }
 */
function perflab_server_timing_register_metric( $metric_slug, array $args ) {
	perflab_server_timing()->register_metric( $metric_slug, $args );
}

/**
 * Returns whether an output buffer should be used to gather Server-Timing metrics during template rendering.
 *
 * @since n.e.x.t
 *
 * @return bool True if an output buffer should be used, false otherwise.
 */
function perflab_server_timing_use_output_buffer() {
	return perflab_server_timing()->use_output_buffer();
}

/**
 * Registers the default Server-Timing metrics.
 *
 * @since n.e.x.t
 */
function perflab_register_default_server_timing_metrics() {
	$calculate_before_template_metrics = function( $passthrough = null ) {
		// WordPress execution prior to serving the template.
		perflab_server_timing_register_metric(
			'before-template',
			array(
				'measure_callback' => function( $metric ) {
					// The 'timestart' global is set right at the beginning of WordPress execution.
					$metric->set_value( ( microtime( true ) - $GLOBALS['timestart'] ) * 1000.0 );
				},
				'access_cap'       => 'exist',
			)
		);

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			// WordPress database query time before template.
			perflab_server_timing_register_metric(
				'before-template-db-queries',
				array(
					'measure_callback' => function( $metric ) {
						// Store this value in a global to later subtract it from total query time after template.
						$GLOBALS['perflab_query_time_before_template'] = array_reduce(
							$GLOBALS['wpdb']->queries,
							function( $acc, $query ) {
								return $acc + $query[1];
							},
							0.0
						);
						$metric->set_value( $GLOBALS['perflab_query_time_before_template'] * 1000.0 );
					},
					'access_cap'       => 'exist',
				)
			);
		}

		return $passthrough;
	};

	// If output buffering is used, explicitly measure only the time before serving the template.
	// Otherwise, the Server-Timing header will be sent before serving the template anyway.
	if ( perflab_server_timing_use_output_buffer() ) {
		add_filter( 'template_include', $calculate_before_template_metrics, PHP_INT_MAX );
	} else {
		add_action( 'perflab_server_timing_send_header', $calculate_before_template_metrics, PHP_INT_MAX );
	}

	// Template-related metrics can only be recorded if output buffering is used.
	if ( perflab_server_timing_use_output_buffer() ) {
		add_filter(
			'template_include',
			function( $passthrough = null ) {
				// WordPress execution while serving the template.
				perflab_server_timing_register_metric(
					'template',
					array(
						'measure_callback' => function( $metric ) {
							$metric->measure_before();
							add_action( 'perflab_server_timing_send_header', array( $metric, 'measure_after' ), PHP_INT_MAX );
						},
						'access_cap'       => 'exist',
					)
				);

				return $passthrough;
			},
			PHP_INT_MAX
		);

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			add_action(
				'perflab_server_timing_send_header',
				function() {
					// WordPress database query time within template.
					perflab_server_timing_register_metric(
						'template-db-queries',
						array(
							'measure_callback' => function( $metric ) {
								$total_query_time = array_reduce(
									$GLOBALS['wpdb']->queries,
									function( $acc, $query ) {
										return $acc + $query[1];
									},
									0.0
								);
								$metric->set_value( ( $total_query_time - $GLOBALS['perflab_query_time_before_template'] ) * 1000.0 );
							},
							'access_cap'       => 'exist',
						)
					);
				},
				PHP_INT_MAX
			);
		}
	}
}
add_action( 'plugins_loaded', 'perflab_register_default_server_timing_metrics' );

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
	return function( ...$callback_args ) use ( $callback, $metric_slug, $access_cap ) {
		// Gain access to Perflab_Server_Timing_Metric instance.
		$server_timing_metric = null;

		perflab_server_timing_register_metric(
			$metric_slug,
			array(
				'measure_callback' => function( $metric ) use ( &$server_timing_metric ) {
					$server_timing_metric = $metric;
				},
				'access_cap'       => $access_cap,
			)
		);

		// If metric instance was not set, this metric should not be calculated.
		if ( null === $server_timing_metric ) {
			return call_user_func_array( $callback, $callback_args );
		}

		// Measure time before the callback.
		$server_timing_metric->measure_before();

		// Execute the callback.
		$result = call_user_func_array( $callback, $callback_args );

		// Measure time after the callback and calculate total.
		$server_timing_metric->measure_after();

		// Return result (e.g. in case this is a filter callback).
		return $result;
	};
}
