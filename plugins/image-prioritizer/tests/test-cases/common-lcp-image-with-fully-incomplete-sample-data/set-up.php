<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$sample_size = od_get_url_metrics_breakpoint_sample_size();

	// Only populate the largest (desktop) viewport group.
	for ( $i = 0; $i < $sample_size; $i++ ) {
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
						array(
							'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[2][self::IMG]',
							'isLCP' => false,
						),
					),
				)
			)
		);
	}
	// Note: loading=lazy is not removed from these images because URL Metrics are only gathered for desktop so far. Both mobile and desktop URL Metrics are required to proceed with lazy-loading.
};
