<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$sample_size = od_get_url_metrics_breakpoint_sample_size();
	foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
		for ( $i = 0; $i < $sample_size; $i++ ) {
			$test_case->store_url_metric(
				$slug,
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'elements'       => array(
							array(
								'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[1][self::DIV]/*[1][self::IMG]',
								'isLCP' => true,
							),
							array(
								'xpath'             => '/HTML/BODY/DIV[@id=\'page\']/*[1][self::DIV]/*[2][self::IMG]',
								'isLCP'             => false,
								'intersectionRatio' => 0.0, // Subsequent carousel slide.
							),
							array(
								'xpath'             => '/HTML/BODY/DIV[@id=\'page\']/*[1][self::DIV]/*[3][self::IMG]',
								'isLCP'             => false,
								'intersectionRatio' => 0.0, // Subsequent carousel slide.
							),
							array(
								'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[3][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0 === $i ? 0.5 : 0.0, // Make sure that the _max_ intersection ratio is considered.
								'boundingClientRect' => array(
									'width' => $viewport_width - 10,
								),
							),
							// All are outside all initial viewports.
							array(
								'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[5][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'boundingClientRect' => array( 'top' => 100000 ),
							),
							array(
								'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[6][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'boundingClientRect' => array( 'top' => 100000 ),
							),
							array(
								'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[7][self::IMG]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'boundingClientRect' => array( 'top' => 100000 ),
							),
						),
					)
				)
			);
		}
	}
};
