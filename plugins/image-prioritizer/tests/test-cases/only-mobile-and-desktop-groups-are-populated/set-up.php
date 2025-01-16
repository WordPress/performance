<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	add_filter(
		'od_breakpoint_max_widths',
		static function () {
			return array( 480, 600, 782 );
		}
	);

	$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$sample_size = od_get_url_metrics_breakpoint_sample_size();

	$outside_viewport_rect = array_merge(
		$test_case->get_sample_dom_rect(),
		array(
			'top' => 100000,
		)
	);

	// Populate the mobile and desktop viewport groups only.
	foreach ( array( 400, 800 ) as $viewport_width ) {
		for ( $i = 0; $i < $sample_size; $i++ ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				$slug,
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'elements'       => array(
							array(
								'xpath'             => '/HTML/BODY/DIV/*[2][self::MAIN]/*[2][self::ARTICLE]/*[2][self::FIGURE]/*[1][self::IMG]',
								'isLCP'             => $viewport_width > 600,
								'intersectionRatio' => $viewport_width > 600 ? 1.0 : 0.1,
							),
							array(
								'xpath'              => '/HTML/BODY/DIV/*[2][self::MAIN]/*[4][self::DIV]',
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
