<?php
/**
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Class Site_Health_Mock_Responses mock site status health data.
 *
 * @since 1.0.0
 */
class Site_Health_Mock_Responses {

	/**
	 * This is the information we are adding into site_status_tests hook.
	 */
	public static function return_added_test_info_site_health(): array {
		$added_tests                                  = array();
		$added_tests['direct']['enqueued_js_assets']  = array(
			'label' => esc_html__( 'JS assets', 'performance-lab' ),
			'test'  => 'perflab_aea_enqueued_js_assets_test',
		);
		$added_tests['direct']['enqueued_css_assets'] = array(
			'label' => esc_html__( 'CSS assets', 'performance-lab' ),
			'test'  => 'perflab_aea_enqueued_css_assets_test',
		);
		return $added_tests;
	}

	/**
	 * Callback response for aea_enqueued_js_assets_test if assets are less than the threshold.
	 *
	 * @param int $enqueued_scripts Number of scripts enqueued.
	 */
	public static function return_aea_enqueued_js_assets_test_callback_less_than_threshold( $enqueued_scripts = 1 ): array {
		$result = array(
			'label'       => esc_html__( 'Enqueued scripts', 'performance-lab' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => esc_html__( 'Performance', 'performance-lab' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: Number of enqueued styles. 2.Styles size. */
						_n(
							'The amount of %1$s enqueued script (size: %2$s) is acceptable.',
							'The amount of %1$s enqueued scripts (size: %2$s) is acceptable.',
							$enqueued_scripts,
							'performance-lab'
						),
						$enqueued_scripts,
						size_format( perflab_aea_get_total_size_bytes_enqueued_scripts() )
					)
				)
			),
			'actions'     => '',
			'test'        => 'enqueued_js_assets',
		);
		return $result;
	}

	/**
	 * Callback response for aea_enqueued_js_assets_test if assets are more than the threshold.
	 *
	 * @param int $enqueued_scripts Number of scripts enqueued.
	 */
	public static function return_aea_enqueued_js_assets_test_callback_more_than_threshold( $enqueued_scripts ): array {
		$result                = self::return_aea_enqueued_js_assets_test_callback_less_than_threshold();
		$result['status']      = 'recommended';
		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'Your website enqueues %1$s script (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website enqueues %1$s scripts (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( perflab_aea_get_total_size_bytes_enqueued_scripts() )
				)
			)
		);
		$result['actions'] = sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			esc_html__( 'Clean Test Cache', 'performance-lab' )
		);
		return $result;
	}

	/**
	 * Callback response for aea_enqueued_css_assets_test if assets are less than the threshold.
	 *
	 * @param int $enqueued_styles Number of styles enqueued.
	 */
	public static function return_aea_enqueued_css_assets_test_callback_less_than_threshold( $enqueued_styles = 1 ): array {
		$result = array(
			'label'       => esc_html__( 'Enqueued styles', 'performance-lab' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => esc_html__( 'Performance', 'performance-lab' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: Number of enqueued styles. 2.Styles size. */
						_n(
							'The amount of %1$s enqueued style (size: %2$s) is acceptable.',
							'The amount of %1$s enqueued styles (size: %2$s) is acceptable.',
							$enqueued_styles,
							'performance-lab'
						),
						$enqueued_styles,
						size_format( perflab_aea_get_total_size_bytes_enqueued_styles() )
					)
				)
			),
			'actions'     => '',
			'test'        => 'enqueued_css_assets',
		);
		return $result;
	}

	/**
	 * Callback response for aea_enqueued_css_assets_test if assets are more than the threshold.
	 *
	 * @param int $enqueued_styles Number of styles enqueued.
	 */
	public static function return_aea_enqueued_css_assets_test_callback_more_than_threshold( $enqueued_styles ): array {
		$result                = self::return_aea_enqueued_css_assets_test_callback_less_than_threshold();
		$result['status']      = 'recommended';
		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'Your website enqueues %1$s style (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website enqueues %1$s styles (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( perflab_aea_get_total_size_bytes_enqueued_styles() )
				)
			)
		);
		$result['actions'] = sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			esc_html__( 'Clean Test Cache', 'performance-lab' )
		);
		return $result;
	}
}
