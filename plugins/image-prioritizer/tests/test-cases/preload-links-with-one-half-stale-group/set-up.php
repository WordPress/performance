<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	// Make sure we're using the same expected current ETag.
	// Note: The args don't matter because od_current_url_metrics_etag_data is filtered to be an empty array in Test_Image_Prioritizer_Helper::set_up().
	$current_etag = od_get_current_url_metrics_etag( new OD_Tag_Visitor_Registry(), null, null );

	$url_metrics_data = json_decode( file_get_contents( __DIR__ . '/url-metrics.json' ), true );
	$collection       = new OD_URL_Metric_Group_Collection(
		array_map(
			static function ( array $url_metric_data ) use ( $current_etag ): OD_URL_Metric {
				if ( 'f8527651f96776745f88cc49df70b62d' === $url_metric_data['etag'] ) {
					$url_metric_data['etag'] = $current_etag;
				}
				return new OD_URL_Metric( $url_metric_data );
			},
			$url_metrics_data
		),
		$current_etag,
		array( 480, 600, 782 ),
		3,
		WEEK_IN_SECONDS
	);

	$test_case->assertFalse( $collection->is_every_group_complete() );
	$test_case->assertTrue( $collection->is_every_group_populated() );
	$test_case->assertTrue( $collection->is_any_group_populated() );
	$test_case->assertInstanceOf( OD_Element::class, $collection->get_common_lcp_element() );

	$slug    = od_get_url_metrics_slug( array() );
	$post_id = OD_URL_Metrics_Post_Type::update_post( $slug, $collection );
	$test_case->assertIsInt( $post_id );
};
