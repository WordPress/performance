<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );

	// Populate one URL Metric so that none of the IMG elements are unknown.
	OD_URL_Metrics_Post_Type::store_url_metric(
		$slug,
		$test_case->get_sample_url_metric(
			array(
				'viewport_width' => 1000,
				'elements'       => array(
					array(
						'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[1][self::IMG]',
						'isLCP' => true,
					),
				),
			)
		)
	);
};
