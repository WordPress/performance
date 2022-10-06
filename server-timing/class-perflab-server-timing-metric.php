<?php
/**
 * Server-Timing API: Perflab_Server_Timing_Metric class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Class representing a single Server-Timing metric.
 *
 * @since n.e.x.t
 */
class Perflab_Server_Timing_Metric {

	/**
	 * The metric slug.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	private $slug;

	/**
	 * The metric value.
	 *
	 * @since n.e.x.t
	 * @var int|float|null
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $slug The metric slug.
	 */
	public function __construct( $slug ) {
		$this->slug = $slug;
	}

	/**
	 * Gets the metric slug.
	 *
	 * @since n.e.x.t
	 *
	 * @return string The metric slug.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Sets the metric value.
	 *
	 * @since n.e.x.t
	 *
	 * @param int|float $value The metric value to set, in milliseconds.
	 */
	public function set_value( $value ) {
		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: PHP parameter name */
				sprintf( __( 'The %s parameter must be an integer or float.', 'performance-lab' ), '$value' ),
				''
			);
			return;
		}

		if ( headers_sent() ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Metrics can only be measured before headers have been sent.', 'performance-lab' ),
				''
			);
		}

		$this->value = $value;
	}

	/**
	 * Gets the metric value.
	 *
	 * @since n.e.x.t
	 *
	 * @return int|float|null The metric value, or null if none set.
	 */
	public function get_value() {
		return $this->value;
	}
}
