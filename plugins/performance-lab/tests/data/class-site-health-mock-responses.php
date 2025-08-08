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
	 *
	 * @return array<string, mixed>
	 */
	public static function return_added_test_info_site_health(): array {
		$added_tests                                      = array();
		$added_tests['async']['enqueued_blocking_assets'] = array(
			'label'             => esc_html__( 'Blocking assets', 'performance-lab' ),
			'test'              => 'enqueued-blocking-assets-test',
			'has_rest'          => false,
			'async_direct_test' => 'perflab_aea_enqueued_blocking_assets_test',
		);
		return $added_tests;
	}

	/**
	 * Callback response for aea_enqueued_js_assets_test if assets are less than the threshold.
	 *
	 * @param int $enqueued_scripts Number of scripts enqueued.
	 * @return array<string, mixed>
	 */
	public static function return_aea_enqueued_js_assets_test_callback_less_than_threshold( int $enqueued_scripts = 1 ): array {
		return array(
			'status'      => 'good',
			'description' => sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: Number of enqueued styles. 2.Styles size. */
						_n(
							'The amount of %1$s blocking script (size: %2$s) is acceptable.',
							'The amount of %1$s blocking scripts (size: %2$s) is acceptable.',
							$enqueued_scripts,
							'performance-lab'
						),
						$enqueued_scripts,
						size_format( perflab_aea_get_total_size_bytes_enqueued_assets( Audit_Assets_Mock_Assets::mock_assets( 'scripts', $enqueued_scripts ), 'scripts' ) )
					)
				)
			),
		);
	}

	/**
	 * Callback response for aea_enqueued_js_assets_test if assets are more than the threshold.
	 *
	 * @param int $enqueued_scripts Number of scripts enqueued.
	 * @return array<string, mixed>
	 */
	public static function return_aea_enqueued_js_assets_test_callback_more_than_threshold( int $enqueued_scripts ): array {
		return array(
			'status'      => 'recommended',
			'description' => sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: Number of enqueued styles. 2.Styles size. */
						_n(
							'Your website has %1$s blocking script (size: %2$s). Try to reduce the number or to concatenate them.',
							'Your website has %1$s blocking scripts (size: %2$s). Try to reduce the number or to concatenate them.',
							$enqueued_scripts,
							'performance-lab'
						),
						$enqueued_scripts,
						size_format( perflab_aea_get_total_size_bytes_enqueued_assets( Audit_Assets_Mock_Assets::mock_assets( 'scripts', $enqueued_scripts ), 'scripts' ) )
					)
				)
			),
		);
	}

	/**
	 * Callback response for aea_enqueued_css_assets_test if assets are less than the threshold.
	 *
	 * @param int $enqueued_styles Number of styles enqueued.
	 * @return array<string, mixed>
	 */
	public static function return_aea_enqueued_css_assets_test_callback_less_than_threshold( int $enqueued_styles = 1 ): array {
		return array(
			'status'      => 'good',
			'description' => sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: Number of enqueued styles. 2.Styles size. */
						_n(
							'The amount of %1$s blocking style (size: %2$s) is acceptable.',
							'The amount of %1$s blocking styles (size: %2$s) is acceptable.',
							$enqueued_styles,
							'performance-lab'
						),
						$enqueued_styles,
						size_format( perflab_aea_get_total_size_bytes_enqueued_assets( Audit_Assets_Mock_Assets::mock_assets( 'styles', $enqueued_styles ), 'styles' ) )
					)
				)
			),
		);
	}

	/**
	 * Callback response for aea_enqueued_css_assets_test if assets are more than the threshold.
	 *
	 * @param int $enqueued_styles Number of styles enqueued.
	 * @return array<string, mixed>
	 */
	public static function return_aea_enqueued_css_assets_test_callback_more_than_threshold( int $enqueued_styles ): array {
		return array(
			'status'      => 'recommended',
			'description' => sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: Number of enqueued styles. 2.Styles size. */
						_n(
							'Your website has %1$s blocking style (size: %2$s). Try to reduce the number or to concatenate them.',
							'Your website has %1$s blocking styles (size: %2$s). Try to reduce the number or to concatenate them.',
							$enqueued_styles,
							'performance-lab'
						),
						$enqueued_styles,
						size_format( perflab_aea_get_total_size_bytes_enqueued_assets( Audit_Assets_Mock_Assets::mock_assets( 'styles', $enqueued_styles ), 'styles' ) )
					)
				)
			),
		);
	}
}
