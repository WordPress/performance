<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$sample_size = od_get_url_metrics_breakpoint_sample_size();

	$get_dom_rect = static function ( $left, $top, $width, $height ) {
		$dom_rect           = array(
			'top'    => $top,
			'left'   => $left,
			'width'  => $width,
			'height' => $height,
			'x'      => $left,
			'y'      => $top,
		);
		$dom_rect['bottom'] = $dom_rect['top'] + $height;
		$dom_rect['right']  = $dom_rect['left'] + $width;
		return $dom_rect;
	};

	$width                  = 10;
	$height                 = 10;
	$above_viewport_rect    = $get_dom_rect( 0, -100, $width, $height );
	$left_of_viewport_rect  = $get_dom_rect( -100, 0, $width, $height );
	$right_of_viewport_rect = $get_dom_rect( 10000000, 0, $width, $height );
	$below_viewport_rect    = $get_dom_rect( 0, 1000000, $width, $height );

	foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
		for ( $i = 0; $i < $sample_size; $i++ ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				$slug,
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'elements'       => array(
							array(
								'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[1][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'intersectionRect'   => $above_viewport_rect,
								'boundingClientRect' => $above_viewport_rect,
							),
							array(
								'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[2][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'intersectionRect'   => $left_of_viewport_rect,
								'boundingClientRect' => $left_of_viewport_rect,
							),
							array(
								'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[3][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'intersectionRect'   => $right_of_viewport_rect,
								'boundingClientRect' => $right_of_viewport_rect,
							),
							array(
								'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[4][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'intersectionRect'   => $below_viewport_rect,
								'boundingClientRect' => $below_viewport_rect,
							),
						),
					)
				)
			);
		}
	}
};
