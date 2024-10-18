<?php
/**
 * Server-Timing API: Perflab_Server_Timing_Metric class
 *
 * @package performance-lab
 * @since 1.8.0
 */

/**
 * Class representing a single Server-Timing metric.
 *
 * @since 1.8.0
 */
class Perflab_Server_Timing_Metric {

	/**
	 * The metric slug.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	private $slug;

	/**
	 * The metric value in milliseconds.
	 *
	 * @since 1.8.0
	 * @var int|float|null
	 */
	private $value;

	/**
	 * The value measured before relevant execution logic in milliseconds, if used.
	 *
	 * @since 1.8.0
	 * @since n.e.x.t Renamed from $before_value to $start_value.
	 * @var int|float|null
	 */
	private $start_value;

	/**
	 * Constructor.
	 *
	 * @since 1.8.0
	 *
	 * @param string $slug The metric slug.
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;
	}

	/**
	 * Gets the metric slug.
	 *
	 * @since 1.8.0
	 *
	 * @return string The metric slug.
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Sets the metric value.
	 *
	 * Alternatively to setting the metric value directly, the {@see Perflab_Server_Timing_Metric::measure_before()}
	 * and {@see Perflab_Server_Timing_Metric::measure_after()} methods can be used to further simplify measuring.
	 *
	 * @since 1.8.0
	 *
	 * @param int|float|mixed $value The metric value to set, in milliseconds.
	 */
	public function set_value( $value ): void {
		if ( ! $this->check_value( $value, __METHOD__ ) ) {
			return;
		}

		// In case e.g. a numeric string is passed, cast it.
		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			$value = (float) $value;
		}

		$this->value = $value;
	}

	/**
	 * Sets the start value for the metric.
	 *
	 * For metrics where the start value is set, this timestamp will be sent via an optional `start` parameter.
	 *
	 * Alternatively to setting the start value directly, the {@see Perflab_Server_Timing_Metric::measure_start()} and
	 * {@see Perflab_Server_Timing_Metric::measure_end()} methods can be used to further simplify measuring.
	 *
	 * @since n.e.x.t
	 *
	 * @param int|float|mixed $value The start value to set for the metric, in milliseconds.
	 */
	public function set_start_value( $value ): void {
		if ( ! $this->check_value( $value, __METHOD__ ) ) {
			return;
		}

		// In case e.g. a numeric string is passed, cast it.
		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			$value = (float) $value;
		}

		$this->start_value = $value;
	}

	/**
	 * Gets the metric value, if set.
	 *
	 * @since 1.8.0
	 *
	 * @return int|float|null The metric value, or null if none set.
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Gets the metric start value, if set.
	 *
	 * @since n.e.x.t
	 *
	 * @return int|float|null The metric start value, or null if none set.
	 */
	public function get_start_value() {
		return $this->start_value;
	}

	/**
	 * Captures the current time as metric start time, to calculate the duration of a task afterward.
	 *
	 * This should be used in combination with {@see Perflab_Server_Timing_Metric::measure_end()}. Alternatively,
	 * {@see Perflab_Server_Timing_Metric::set_value()} and {@see Perflab_Server_Timing_Metric::set_start_value()} can
	 * be used to set a calculated value manually.
	 *
	 * @since n.e.x.t
	 */
	public function measure_start(): void {
		$this->set_start_value( microtime( true ) * 1000.0 );
	}

	/**
	 * Captures the current time and compares it to the metric start time to calculate a task's duration.
	 *
	 * This should be used in combination with {@see Perflab_Server_Timing_Metric::measure_start()}. Alternatively,
	 * {@see Perflab_Server_Timing_Metric::set_value()} and {@see Perflab_Server_Timing_Metric::set_start_value()} can
	 * be used to set a calculated value manually.
	 *
	 * @since n.e.x.t
	 */
	public function measure_end(): void {
		if ( null === $this->start_value ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: 1: PHP method name, 2: alternative PHP method name */
					esc_html__( 'The %1$s method or %2$s method must be called before.', 'performance-lab' ),
					__CLASS__ . '::measure_start()',
					__CLASS__ . '::set_start_value()'
				),
				''
			);
			return;
		}

		$this->set_value( microtime( true ) * 1000.0 - $this->start_value );
	}

	/**
	 * Captures the current time, as a reference point to calculate the duration of a task afterward.
	 *
	 * @since 1.8.0
	 * @deprecated n.e.x.t
	 * @see Perflab_Server_Timing_Metric::measure_start()
	 */
	public function measure_before(): void {
		_deprecated_function( __METHOD__, 'n.e.x.t (Performance Lab)', __CLASS__ . '::measure_start()' );
		$this->measure_start();
	}

	/**
	 * Captures the current time and compares it to the reference point to calculate a task's duration.
	 *
	 * @since 1.8.0
	 * @deprecated n.e.x.t
	 * @see Perflab_Server_Timing_Metric::measure_end()
	 */
	public function measure_after(): void {
		_deprecated_function( __METHOD__, 'n.e.x.t (Performance Lab)', __CLASS__ . '::measure_end()' );
		$this->measure_end();
	}

	/**
	 * Checks whether the passed metric value is valid and whether the timing of the method call is correct.
	 *
	 * @since n.e.x.t
	 *
	 * @param int|float|mixed $value The value to check, in milliseconds.
	 * @param string          $method The method name originally called (typically passed via `__METHOD__`).
	 * @return bool True if the method call with the value is valid, false otherwise.
	 */
	private function check_value( $value, string $method ): bool {
		if ( ! is_numeric( $value ) ) {
			_doing_it_wrong(
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$method,
				/* translators: %s: PHP parameter name */
				sprintf( esc_html__( 'The %s parameter must be an integer, float, or numeric string.', 'performance-lab' ), '$value' ),
				''
			);
			return false;
		}

		if ( 0 !== did_action( 'perflab_server_timing_send_header' ) && ! doing_action( 'perflab_server_timing_send_header' ) ) {
			_doing_it_wrong(
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$method,
				/* translators: %s: WordPress action name */
				sprintf( esc_html__( 'The method must be called before or during the %s action.', 'performance-lab' ), 'perflab_server_timing_send_header' ),
				''
			);
			return false;
		}

		return true;
	}
}
