<?php
/**
 * Performance Lab Database Utility Functions.
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */

/**
 * Performance Lab Database Utility Functions class, including thresholds.
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */
class PerflabDbUtilities {
	/** Stored threshold values.
	 *
	 * @var array  the values
	 */
	private $list;
	/** Singleton instance
	 *
	 * @var PerflabDbUtilities instance.
	 */
	private static $instance;

	/** Constructor, private because this is a singleton.
	 */
	private function __construct() {
		$this->list = array(
			'server_response_very_slow'  => 20.0,
			'server_response_slow'       => 5.0,
			'server_response_iterations' => 20.0,
			'server_response_timeout'    => 100.0,
			'target_storage_engine'      => 'InnoDB',
			'target_row_format'          => 'Dynamic',

		);
	}

	/** Get the singleton threshold instance.
	 *
	 * @return PerflabDbUtilities
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Get a DB threshold value after filtering it.
	 *
	 * @param string $name name of the value.
	 *
	 * @return float
	 * @throws ValueError Upon an unrecognized name.
	 */
	public function get( $name ) {
		if ( ! array_key_exists( $name, $this->list ) ) {
			throw new ValueError( $name . ': no such PerflabDbThreshold item' );
		}

		/**
		 * Filter database performance troubleshooting thresholds.
		 *
		 * To filter a particular threshold value, check the $name for a match,
		 * then return the $value you want.
		 *
		 * @since 1.4.0
		 *
		 * @param mixed $value The threshold value.
		 * @param string $name The threshold name.
		 */

		return apply_filters( 'perflab_db_threshold', $this->list[ $name ], $name );

	}
}
