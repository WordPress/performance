<?php
/**
 * Server-Timing API: Perflab_Server_Timing class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Class controlling the Server-Timing header.
 *
 * @since n.e.x.t
 */
class Perflab_Server_Timing {

	/**
	 * Map of registered metric slugs and their metric instances.
	 *
	 * @since n.e.x.t
	 * @var array
	 */
	private $registered_metrics = array();

	/**
	 * Map of registered metric slugs and their registered data.
	 *
	 * @since n.e.x.t
	 * @var array
	 */
	private $registered_metrics_data = array();

	/**
	 * Registers a metric to calculate for the Server-Timing header.
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
	public function register_metric( $metric_slug, array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'measure_callback' => null,
				'access_cap'       => null,
			)
		);
		if ( ! $args['measure_callback'] || ! is_callable( $args['measure_callback'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: PHP parameter name */
				sprintf( __( 'The %s argument is required and must be a callable.', 'performance-lab' ), '$args["measure_callback"]' ),
				''
			);
			return;
		}
		if ( ! $args['access_cap'] || ! is_string( $args['access_cap'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: PHP parameter name */
				sprintf( __( 'The %s argument is required and must be a string.', 'performance-lab' ), '$args["access_cap"]' ),
				''
			);
			return;
		}

		$this->registered_metrics[ $metric_slug ]      = new Perflab_Server_Timing_Metric( $metric_slug );
		$this->registered_metrics_data[ $metric_slug ] = $args;

		// If the current user has already been determined and they lack the necessary access,
		// do not even attempt to calculate the metric.
		if ( did_action( 'set_current_user' ) && ! current_user_can( $args['access_cap'] ) ) {
			return;
		}

		// Otherwise, call the measuring callback and pass the metric instance to it.
		call_user_func( $args['measure_callback'], $this->registered_metrics[ $metric_slug ] );
	}

	/**
	 * Outputs the Server-Timing header.
	 *
	 * This method must be called before rendering the page.
	 *
	 * @since n.e.x.t
	 */
	public function add_header() {
		if ( headers_sent() ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The method must be called before headers have been sent.', 'performance-lab' ),
				''
			);
			return;
		}

		$header_value = $this->get_header_value();
		if ( ! $header_value ) {
			return;
		}

		header( sprintf( 'Server-Timing: %s', $header_value ), false );
	}

	/**
	 * Gets the value for the Server-Timing header.
	 *
	 * @since n.e.x.t
	 *
	 * @return string The Server-Timing header value.
	 */
	public function get_header_value() {
		// Get all metric header values, as long as the current user has access to the metric.
		$metric_header_values = array_filter(
			array_map(
				function( Perflab_Server_Timing_Metric $metric ) {
					$value = $metric->get_value();
					if ( null === $value ) {
						return null;
					}

					// Check the registered capability here to ensure no metric without access is exposed.
					if ( ! current_user_can( $this->registered_metrics_data[ $metric->get_slug() ]['access_cap'] ) ) {
						return null;
					}

					return $this->format_metric_header_value( $metric );
				},
				$this->registered_metrics
			),
			function( $value ) {
				return null !== $value;
			}
		);

		return implode( ', ', $metric_header_values );
	}

	/**
	 * Formats the header segment for a single metric.
	 *
	 * @since n.e.x.t
	 *
	 * @param Perflab_Server_Timing_Metric $metric The metric to format.
	 * @return string Segment for the Server-Timing header.
	 */
	private function format_metric_header_value( Perflab_Server_Timing_Metric $metric ) {
		$value = $metric->get_value();
		if ( is_float( $value ) ) {
			$value = round( $value, 2 );
		}
		return sprintf( 'wp-%1$s;dur=%2$s', $metric->get_slug(), $value );
	}
}
