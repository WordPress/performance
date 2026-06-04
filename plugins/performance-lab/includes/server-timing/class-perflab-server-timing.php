<?php
/**
 * Server-Timing API: Perflab_Server_Timing class
 *
 * @package performance-lab
 * @since 1.8.0
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Class controlling the Server-Timing header.
 *
 * @phpstan-type MetricArguments array{
 *                   measure_callback: callable( Perflab_Server_Timing_Metric ): void,
 *                   access_cap: string
 *               }
 *
 * @since 1.8.0
 */
class Perflab_Server_Timing {

	/**
	 * Map of registered metric slugs and their metric instances.
	 *
	 * @since 1.8.0
	 * @var array<string, Perflab_Server_Timing_Metric>
	 */
	private $registered_metrics = array();

	/**
	 * Map of registered metric slugs and their registered data.
	 *
	 * @since 1.8.0
	 * @phpstan-var array<string, MetricArguments>
	 * @var array<string, array>
	 */
	private $registered_metrics_data = array();

	/**
	 * Registers a metric to calculate for the Server-Timing header.
	 *
	 * This method must be called before the {@see 'perflab_server_timing_send_header'} hook.
	 *
	 * @since 1.8.0
	 *
	 * @phpstan-param MetricArguments $args
	 *
	 * @param string                         $metric_slug The metric slug.
	 * @param array<string, callable|string> $args        {
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
	public function register_metric( string $metric_slug, array $args ): void {
		if ( isset( $this->registered_metrics[ $metric_slug ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: metric slug */
				esc_html( sprintf( __( 'A metric with the slug %s is already registered.', 'performance-lab' ), $metric_slug ) ),
				''
			);
			return;
		}

		if ( 0 !== did_action( 'perflab_server_timing_send_header' ) && ! doing_action( 'perflab_server_timing_send_header' ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: WordPress action name */
				esc_html( sprintf( __( 'The method must be called before or during the %s action.', 'performance-lab' ), 'perflab_server_timing_send_header' ) ),
				''
			);
			return;
		}

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
				esc_html( sprintf( __( 'The %s argument is required and must be a callable.', 'performance-lab' ), 'measure_callback' ) ),
				''
			);
			return;
		}
		if ( ! $args['access_cap'] || ! is_string( $args['access_cap'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: PHP parameter name */
				esc_html( sprintf( __( 'The %s argument is required and must be a string.', 'performance-lab' ), 'access_cap' ) ),
				''
			);
			return;
		}
		/**
		 * Validated args.
		 *
		 * @var MetricArguments $args
		 */

		$this->registered_metrics[ $metric_slug ]      = new Perflab_Server_Timing_Metric( $metric_slug );
		$this->registered_metrics_data[ $metric_slug ] = $args;

		// If the current user has already been determined, and they lack the necessary access,
		// do not even attempt to calculate the metric.
		if ( 0 !== did_action( 'set_current_user' ) && ! current_user_can( $args['access_cap'] ) ) {
			return;
		}

		// Otherwise, call the measuring callback and pass the metric instance to it.
		call_user_func( $args['measure_callback'], $this->registered_metrics[ $metric_slug ] );
	}

	/**
	 * Checks whether the given metric has been registered.
	 *
	 * @since 1.8.0
	 *
	 * @param string $metric_slug The metric slug.
	 * @return bool True if registered, false otherwise.
	 */
	public function has_registered_metric( string $metric_slug ): bool {
		return isset( $this->registered_metrics[ $metric_slug ] ) && isset( $this->registered_metrics_data[ $metric_slug ] );
	}

	/**
	 * Outputs the Server-Timing header.
	 *
	 * This must run before the response body is flushed. When output buffering is enabled it is called at the
	 * {@see 'wp_finalized_template_enhancement_output_buffer'} action, after the template has been captured and
	 * enhanced; otherwise it is called at the {@see 'wp_before_include_template'} action, right before the template
	 * is included. Any extra arguments passed by those hooks are ignored.
	 *
	 * @since 1.8.0
	 */
	public function send_header(): void {
		if ( headers_sent() ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The method must be called before headers have been sent.', 'performance-lab' ),
				''
			);
			return;
		}

		/**
		 * Fires right before the Server-Timing header is sent.
		 *
		 * This action is the last possible point to register a Server-Timing metric.
		 *
		 * @since 1.8.0
		 */
		do_action( 'perflab_server_timing_send_header' );

		$header_value = $this->get_header();
		if ( '' === $header_value ) {
			return;
		}

		header( sprintf( 'Server-Timing: %s', $header_value ), false );
	}

	/**
	 * Gets the value for the Server-Timing header.
	 *
	 * @since 1.8.0
	 *
	 * @return string The Server-Timing header value.
	 */
	public function get_header(): string {
		// Get all metric header values, as long as the current user has access to the metric.
		$metric_header_values = array_filter(
			array_map(
				function ( Perflab_Server_Timing_Metric $metric ) {
					// Check the registered capability here to ensure no metric without access is exposed.
					if ( ! current_user_can( $this->registered_metrics_data[ $metric->get_slug() ]['access_cap'] ) ) {
						return null;
					}

					return $this->format_metric_header_value( $metric );
				},
				$this->registered_metrics
			),
			static function ( $value ) {
				return null !== $value;
			}
		);

		return implode( ', ', $metric_header_values );
	}

	/**
	 * Returns whether an output buffer should be used to gather Server-Timing metrics during template rendering.
	 *
	 * Without an output buffer, it is only possible to cover metrics from before serving the template, i.e. before
	 * the HTML output starts. Therefore, sites that would like to gather metrics while serving the template should
	 * enable this via the {@see 'perflab_server_timing_use_output_buffer'} filter.
	 *
	 * @since 1.8.0
	 *
	 * @return bool True if an output buffer should be used, false otherwise.
	 */
	public function use_output_buffer(): bool {
		$options = (array) get_option( PERFLAB_SERVER_TIMING_SETTING, array() );
		$enabled = isset( $options['output_buffering'] ) && (bool) $options['output_buffering'];

		/**
		 * Filters whether an output buffer should be used to be able to gather additional Server-Timing metrics.
		 *
		 * Without an output buffer, it is only possible to cover metrics from before serving the template, i.e. before
		 * the HTML output starts. Therefore, sites that would like to gather metrics while serving the template should
		 * enable this.
		 *
		 * @since 1.8.0
		 *
		 * @param bool $enabled Whether to use an output buffer.
		 */
		return (bool) apply_filters( 'perflab_server_timing_use_output_buffer', $enabled );
	}

	/**
	 * Adds hooks to send the Server-Timing header.
	 *
	 * Since WordPress 6.9, this relies on the template enhancement output buffer introduced in Core-43258 (see
	 * r60936) rather than starting its own output buffer.
	 *
	 * When output buffering is enabled, the header is sent at the {@see 'wp_finalized_template_enhancement_output_buffer'}
	 * action so that metrics measured while the template is rendering are included. Registering a callback on that
	 * action also opts in to starting the core output buffer (see {@see wp_should_output_buffer_template_for_enhancement()}),
	 * and core ensures the {@see 'wp_template_enhancement_output_buffer'} filter (used by other consumers such as
	 * Optimization Detective) runs before this action, so their metrics are captured before the header is sent.
	 *
	 * When output buffering is disabled, the header is sent at the {@see 'wp_before_include_template'} action, right
	 * before the template is included. Note this action only fires when the resolved template is a readable file,
	 * unlike the previously used `template_include` filter which fired unconditionally.
	 *
	 * @since 3.2.0
	 * @since n.e.x.t Relies on the WordPress 6.9 template enhancement output buffer instead of starting its own.
	 */
	public function add_hooks(): void {
		if ( $this->use_output_buffer() ) {
			add_action( 'wp_finalized_template_enhancement_output_buffer', array( $this, 'send_header' ) );
		} else {
			add_action( 'wp_before_include_template', array( $this, 'send_header' ) );
		}
	}

	/**
	 * Formats the header segment for a single metric.
	 *
	 * @since 1.8.0
	 *
	 * @param Perflab_Server_Timing_Metric $metric The metric to format.
	 * @return string|null Segment for the Server-Timing header, or null if no value set.
	 */
	private function format_metric_header_value( Perflab_Server_Timing_Metric $metric ): ?string {
		$value = $metric->get_value();

		// If no value is set, make sure it's just passed through.
		if ( null === $value ) {
			return null;
		}

		if ( is_float( $value ) ) {
			$value = round( $value, 2 );
		}

		// See https://github.com/WordPress/performance/issues/955.
		$name = preg_replace( '/[^!#$%&\'*+\-.^_`|~0-9a-zA-Z]/', '-', $metric->get_slug() );

		return sprintf( 'wp-%1$s;dur=%2$s', $name, $value );
	}
}
