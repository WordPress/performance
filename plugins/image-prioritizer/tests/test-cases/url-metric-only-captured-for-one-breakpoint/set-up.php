<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	OD_URL_Metrics_Post_Type::store_url_metric(
		od_get_url_metrics_slug( od_get_normalized_query_vars() ),
		$test_case->get_sample_url_metric(
			array(
				'viewport_width' => 400,
				'element'        => array(
					'isLCP' => true,
					'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[1][self::IMG]',
				),
			)
		)
	);
};
