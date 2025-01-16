<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$slug                  = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$sample_size           = od_get_url_metrics_breakpoint_sample_size();
	$outside_viewport_rect = array_merge(
		$test_case->get_sample_dom_rect(),
		array(
			'top' => 100000,
		)
	);
	foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
		for ( $i = 0; $i < $sample_size; $i++ ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				$slug,
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'elements'       => array(
							array(
								'xpath' => '/HTML/BODY/DIV/*[1][self::DIV]/*[1][self::IMG]',
								'isLCP' => true,
							),
							array(
								'xpath'             => '/HTML/BODY/DIV/*[1][self::DIV]/*[2][self::IMG]',
								'isLCP'             => false,
								'intersectionRatio' => 0.0, // Subsequent carousel slide.
							),
							array(
								'xpath'             => '/HTML/BODY/DIV/*[1][self::DIV]/*[3][self::IMG]',
								'isLCP'             => false,
								'intersectionRatio' => 0.0, // Subsequent carousel slide.
							),
							array(
								'xpath'             => '/HTML/BODY/DIV/*[3][self::IMG]',
								'isLCP'             => false,
								'intersectionRatio' => 0 === $i ? 0.5 : 0.0, // Make sure that the _max_ intersection ratio is considered.
							),
							// All are outside all initial viewports.
							array(
								'xpath'              => '/HTML/BODY/DIV/*[5][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'intersectionRect'   => $outside_viewport_rect,
								'boundingClientRect' => $outside_viewport_rect,
							),
							array(
								'xpath'              => '/HTML/BODY/DIV/*[6][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'intersectionRect'   => $outside_viewport_rect,
								'boundingClientRect' => $outside_viewport_rect,
							),
							array(
								'xpath'              => '/HTML/BODY/DIV/*[7][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'intersectionRect'   => $outside_viewport_rect,
								'boundingClientRect' => $outside_viewport_rect,
							),
						),
					)
				)
			);
		}
	}
};
