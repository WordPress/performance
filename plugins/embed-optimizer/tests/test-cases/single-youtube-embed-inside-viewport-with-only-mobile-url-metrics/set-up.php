<?php
return static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
	OD_URL_Metrics_Post_Type::store_url_metric(
		od_get_url_metrics_slug( od_get_normalized_query_vars() ),
		$test_case->get_sample_url_metric(
			array(
				'viewport_width' => 100,
				'elements'       => array(
					array(
						'xpath'                     => '/HTML/BODY/DIV/*[1][self::FIGURE]/*[1][self::DIV]',
						'isLCP'                     => false,
						'intersectionRatio'         => 1,
						'resizedBoundingClientRect' => array_merge( $test_case->get_sample_dom_rect(), array( 'height' => 500 ) ),
					),
				),
			)
		)
	);
};
