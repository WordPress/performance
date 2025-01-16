<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$breakpoint_max_widths = array( 480, 600, 782 );

	add_filter(
		'od_breakpoint_max_widths',
		static function () use ( $breakpoint_max_widths ) {
			return $breakpoint_max_widths;
		}
	);

	foreach ( array_merge( $breakpoint_max_widths, array( 1000 ) ) as $viewport_width ) {
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => $viewport_width,
					'elements'       => array(
						array(
							'isLCP' => true,
							'xpath' => '/HTML/BODY/DIV/*[1][self::VIDEO]',
						),
					),
				)
			)
		);
	}
};
