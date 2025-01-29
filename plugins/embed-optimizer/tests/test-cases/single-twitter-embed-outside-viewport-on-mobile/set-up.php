<?php
return static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
	foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $i => $viewport_width ) {
		$elements = array(
			array(
				'xpath'                     => '/HTML/BODY/DIV[@class=\'wp-site-blocks\']/*[1][self::FIGURE]/*[1][self::DIV]',
				'isLCP'                     => true,
				'resizedBoundingClientRect' => array_merge( $test_case->get_sample_dom_rect(), array( 'height' => 500 + $i * 100 ) ),
			),
		);

		// Embed not visible on mobile.
		if ( 480 === $viewport_width ) {
			$elements[0]['intersectionRatio'] = 0;
			$elements[0]['isLCP']             = false;
		}

		$sample_size = od_get_url_metrics_breakpoint_sample_size();
		for ( $j = 0; $j < $sample_size; $j++ ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				od_get_url_metrics_slug( od_get_normalized_query_vars() ),
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'elements'       => $elements,
					)
				)
			);
		}
	}
};
