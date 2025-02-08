<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$mobile_breakpoint  = 480;
	$tablet_breakpoint  = 600;
	$desktop_breakpoint = 782;
	add_filter(
		'od_breakpoint_max_widths',
		static function () use ( $mobile_breakpoint, $tablet_breakpoint ): array {
			return array( $mobile_breakpoint, $tablet_breakpoint );
		}
	);
	$sample_size = od_get_url_metrics_breakpoint_sample_size();

	$slug                                = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$div_index_to_viewport_width_mapping = array(
		0 => $desktop_breakpoint,
		1 => $tablet_breakpoint,
		2 => $mobile_breakpoint,
	);

	foreach ( $div_index_to_viewport_width_mapping as $div_index => $viewport_width ) {
		for ( $i = 0; $i < $sample_size; $i++ ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				$slug,
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'element'        => array(
							'xpath' => sprintf( '/HTML/BODY/DIV[@id=\'page\']/*[%d][self::DIV]', $div_index + 1 ),
							'isLCP' => true,
						),
					)
				)
			);
		}
	}
};
