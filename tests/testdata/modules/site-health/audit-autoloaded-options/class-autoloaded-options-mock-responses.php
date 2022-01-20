<?php
/**
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Class Autoloaded_Options_Mock_Responses mock site status health data for autoloaded options.
 *
 * @since 1.0.0
 */
class Autoloaded_Options_Mock_Responses {

	/**
	 * This is the information we are adding into site_status_tests hook.
	 *
	 * @return array
	 */
	public static function return_added_test_info_site_health() {
		$added_tests                                 = array();
		$added_tests['direct']['autoloaded_options'] = array(
			'label' => esc_html__( 'Autoloaded options', 'performance-lab' ),
			'test'  => 'perflab_aao_autoloaded_options_test',
		);
		return $added_tests;
	}

	/**
	 * Callback response for perflab_aao_autoloaded_options_test if autoloaded options are less than the limit.
	 *
	 * @param int $autoloaded_options_size Autoloaded options size in bytes.
	 * @param int $autoloaded_options_count Autoloaded options count.
	 *
	 * @return array
	 */
	public static function return_perflab_aao_autoloaded_options_test_less_than_limit( $autoloaded_options_size, $autoloaded_options_count ) {
		$result = array(
			'label'       => esc_html__( 'Autoloaded options', 'performance-lab' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => esc_html__( 'Performance', 'performance-lab' ),
				'color' => 'blue',
			),
			'description' => sprintf(
			/* translators: 1: Number of autoloaded options. 2.Autoloaded options size. */
				'<p>' . esc_html__( 'The amount of %1$s autoloaded options (size: %2$s) in options table is acceptable.', 'performance-lab' ) . '</p>',
				$autoloaded_options_count,
				size_format( $autoloaded_options_size )
			),
			'actions'     => '',
			'test'        => 'autoloaded_options',
		);
		return $result;
	}

	/**
	 * Callback response for perflab_aao_autoloaded_options_test if autoloaded options are more than the limit.
	 *
	 * @param int $autoloaded_options_size Autoloaded options size in bytes.
	 * @param int $autoloaded_options_count Autoloaded options count.
	 *
	 * @return array
	 */
	public static function return_perflab_aao_autoloaded_options_test_bigger_than_limit( $autoloaded_options_size, $autoloaded_options_count ) {
		$result = self::return_perflab_aao_autoloaded_options_test_less_than_limit( $autoloaded_options_size, $autoloaded_options_count );

		$result['status']         = 'critical';
		$result['badge']['color'] = 'red';
		$result['description']    = sprintf(
		/* translators: 1: Number of autoloaded options. 2.Autoloaded options size. */
			'<p>' . esc_html__( 'Your website uses %1$s autoloaded options (size: %2$s). Try to reduce the number of autoloaded options or performance will be affected.', 'performance-lab' ) . '</p>',
			$autoloaded_options_count,
			size_format( $autoloaded_options_size )
		);
		$result['description'] = apply_filters( 'perflab_aao_autoloaded_options_limit_description', $result['description'] );
		$result['actions']     = apply_filters( 'perflab_aao_autoloaded_options_action_to_perform', 'How to solve it' );
		return $result;
	}


}

