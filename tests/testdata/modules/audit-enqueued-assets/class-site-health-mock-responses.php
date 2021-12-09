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
	 * @return array
	 */
	public static function return_added_test_info_site_health() {
		$added_tests                                  = array();
		$added_tests['direct']['enqueued_js_assets']  = array(
			'label' => esc_html__( 'JS assets', 'performance-lab' ),
			'test'  => 'aea_enqueued_js_assets_test',
		);
		$added_tests['direct']['enqueued_css_assets'] = array(
			'label' => esc_html__( 'CSS assets', 'performance-lab' ),
			'test'  => 'aea_enqueued_css_assets_test',
		);
		return $added_tests;
	}

	/**
	 * Callback response for aea_enqueued_js_assets_test if assets are less than the limit.
	 *
	 * @return array
	 */
	public static function return_aea_enqueued_js_assets_test_callback_less_than_limit() {
		$result = array(
			'label'       => esc_html__( 'Enqueued JS assets', 'performance-lab' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => esc_html__( 'Performance', 'performance-lab' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'The amount of enqueued JS assets is acceptable.', 'performance-lab' )
			),
			'actions'     => '',
			'test'        => 'enqueued_js_assets',
		);
		return $result;
	}

	/**
	 * Callback response for aea_enqueued_js_assets_test if assets are more than the limit.
	 *
	 * @param int $enqueued_scripts Number of scripts enqueued.
	 *
	 * @return array
	 */
	public static function return_aea_enqueued_js_assets_test_callback_more_than_limit( $enqueued_scripts ) {
		$result                   = self::return_aea_enqueued_js_assets_test_callback_less_than_limit();
		$result['status']         = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['description']    = sprintf(
		/* translators: %s: Number of enqueued scripts */
			esc_html__( 'Your website enqueues %s scripts. Try to reduce the number of JS assets, or to concatenate them.', 'performance-lab' ),
			$enqueued_scripts
		);
		$result['actions'] .= sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' )
		);
		return $result;
	}

	/**
	 * Callback response for aea_enqueued_css_assets_test if assets are less than the limit.
	 *
	 * @return array
	 */
	public static function return_aea_enqueued_css_assets_test_callback_less_than_limit() {
		$result = array(
			'label'       => esc_html__( 'Enqueued CSS assets', 'performance-lab' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => esc_html__( 'Performance', 'performance-lab' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'The amount of enqueued CSS assets is acceptable.', 'performance-lab' )
			),
			'actions'     => '',
			'test'        => 'enqueued_css_assets',
		);
		return $result;
	}

	/**
	 * Callback response for aea_enqueued_css_assets_test if assets are more than the limit.
	 *
	 * @param int $enqueued_styles Number of styles enqueued.
	 *
	 * @return array
	 */
	public static function return_aea_enqueued_css_assets_test_callback_more_than_limit( $enqueued_styles ) {
		$result                   = self::return_aea_enqueued_css_assets_test_callback_less_than_limit();
		$result['status']         = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['description']    = sprintf(
		/* translators: %s: Number of enqueued styles */
			esc_html__( 'Your website enqueues %s styles. Try to reduce the number of CSS assets, or to concatenate them.', 'performance-lab' ),
			$enqueued_styles
		);
		$result['actions'] .= sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' )
		);
		return $result;
	}
}

