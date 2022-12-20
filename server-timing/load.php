<?php
/**
 * Server-Timing API integration file
 *
 * @package performance-lab
 * @since 1.8.0
 */

/**
 * Provides access the Server-Timing API.
 *
 * When called for the first time, this also initializes the API to schedule the header for output.
 * In case that no metrics are registered, this is still called on {@see 'wp_loaded'}, so that even then it still fires
 * its action hooks as expected.
 *
 * @since 1.8.0
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
 * @since 1.8.0
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
 * @since 1.8.0
 *
 * @return bool True if an output buffer should be used, false otherwise.
 */
function perflab_server_timing_use_output_buffer() {
	return perflab_server_timing()->use_output_buffer();
}

/**
 * Wraps a callback (e.g. for an action or filter) to be measured and included in the Server-Timing header.
 *
 * @since 1.8.0
 *
 * @param callable $callback    The callback to wrap.
 * @param string   $metric_slug The metric slug to use within the Server-Timing header.
 * @param string   $access_cap  Capability required to view the metric. If this is a public metric, this needs to be
 *                              set to "exist".
 * @return callable Callback function that will run $callback and measure its execution time once called.
 */
function perflab_wrap_server_timing( $callback, $metric_slug, $access_cap ) {
	return function( ...$callback_args ) use ( $callback, $metric_slug, $access_cap ) {
		// Gain access to Perflab_Server_Timing_Metric instance.
		$server_timing_metric = null;

		// Only register the metric the first time the function is called.
		// For now, this also means only the first function call is measured.
		if ( ! perflab_server_timing()->has_registered_metric( $metric_slug ) ) {
			perflab_server_timing_register_metric(
				$metric_slug,
				array(
					'measure_callback' => function( $metric ) use ( &$server_timing_metric ) {
						$server_timing_metric = $metric;
					},
					'access_cap'       => $access_cap,
				)
			);
		}

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
