<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$breakpoint_max_widths = array( 480, 600, 782, 1000 );

	add_filter(
		'od_breakpoint_max_widths',
		static function () use ( $breakpoint_max_widths ) {
			return $breakpoint_max_widths;
		}
	);

	foreach ( array_merge( $breakpoint_max_widths, array( 1600 ) ) as $i => $viewport_width ) {
		$elements                = array(
			array(
				'isLCP' => false,
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[1][self::IMG]',
			),
			array(
				'isLCP' => false,
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[2][self::IMG]',
			),
			array(
				'isLCP' => false,
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[3][self::IMG]',
			),
			array(
				'isLCP' => false,
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[4][self::IMG]',
			),
			array(
				'isLCP' => false,
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[5][self::IMG]',
			),
		);
		$elements[ $i ]['isLCP'] = true;
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
};
