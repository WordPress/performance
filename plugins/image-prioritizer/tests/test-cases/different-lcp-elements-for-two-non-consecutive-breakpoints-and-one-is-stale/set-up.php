<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	add_filter(
		'od_breakpoint_max_widths',
		static function () {
			return array( 480, 600, 782 );
		}
	);

	OD_URL_Metrics_Post_Type::store_url_metric(
		od_get_url_metrics_slug( od_get_normalized_query_vars() ),
		$test_case->get_sample_url_metric(
			array(
				'viewport_width' => 500,
				'elements'       => array(
					array(
						'isLCP' => true,
						'xpath' => '/HTML/BODY/DIV/*[1][self::IMG]',
					),
					array(
						'isLCP' => false,
						'xpath' => '/HTML/BODY/DIV/*[2][self::IMG]',
					),
				),
			)
		)
	);
	OD_URL_Metrics_Post_Type::store_url_metric(
		od_get_url_metrics_slug( od_get_normalized_query_vars() ),
		$test_case->get_sample_url_metric(
			array(
				'viewport_width' => 650,
				'elements'       => array(
					array(
						'isLCP' => false,
						'xpath' => '/HTML/BODY/DIV/*[1][self::IMG]',
					),
					array(
						'isLCP' => false,
						'xpath' => '/HTML/BODY/DIV/*[2][self::IMG]',
					),
				),
			)
		)
	);
	OD_URL_Metrics_Post_Type::store_url_metric(
		od_get_url_metrics_slug( od_get_normalized_query_vars() ),
		$test_case->get_sample_url_metric(
			array(
				'viewport_width' => 800,
				'elements'       => array(
					array(
						'isLCP' => false,
						'xpath' => '/HTML/BODY/DIV/*[1][self::IMG]',
					),
					array(
						'isLCP' => true,
						'xpath' => '/HTML/BODY/DIV/*[2][self::IMG]',
					),
				),
			)
		)
	);
	OD_URL_Metrics_Post_Type::store_url_metric(
		od_get_url_metrics_slug( od_get_normalized_query_vars() ),
		$test_case->get_sample_url_metric(
			array(
				'viewport_width' => 800,
				'elements'       => array(
					array(
						'isLCP' => false,
						'xpath' => '/HTML/BODY/DIV/*[1][self::IMG]',
					),
					array(
						'isLCP' => false,
						'xpath' => '/HTML/BODY/DIV/*[2][self::IMG]',
					),
				),
			)
		)
	);
};
