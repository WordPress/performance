<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {

	$current_etag = 'f8527651f96776745f88cc49df70b62d';

	$url_metrics_data = json_decode( file_get_contents( __DIR__ . '/url-metrics.json' ), true ); // TODO: Also do all of the below assertions with this sent through array_reverse()!
	$collection       = new OD_URL_Metric_Group_Collection(
		array_map(
			static function ( array $url_metric_data ): OD_URL_Metric {
				return new OD_URL_Metric( $url_metric_data );
			},
			$url_metrics_data
		),
		$current_etag,
		array( 480, 600, 782 ),
		3,
		WEEK_IN_SECONDS
	);

	$expected_lcp_element_xpath = '/HTML/BODY/DIV[@class=\'wp-site-blocks\']/*[2][self::MAIN]/*[2][self::DIV]/*[1][self::UL]/*[1][self::LI]/*[1][self::DIV]/*[1][self::FIGURE]/*[1][self::A]/*[1][self::IMG]';

	$test_case->assertFalse( $collection->is_every_group_complete() );
	$test_case->assertTrue( $collection->is_every_group_populated() );
	$test_case->assertTrue( $collection->is_any_group_populated() );
	$test_case->assertNull( $collection->get_common_lcp_element() ); // TODO: Actually it should not be!

	$slug    = od_get_url_metrics_slug( array() );
	$post_id = OD_URL_Metrics_Post_Type::update_post( $slug, $collection );
	$test_case->assertIsInt( $post_id );

	$mobile_group = $collection->get_group_for_viewport_width( 400 );
	$test_case->assertCount( 3, $mobile_group );
	$test_case->assertTrue( $mobile_group->is_complete() );
	$lcp_element = $mobile_group->get_lcp_element();
	$test_case->assertInstanceOf( OD_Element::class, $lcp_element );
	$test_case->assertSame( $expected_lcp_element_xpath, $lcp_element->jsonSerialize()['xpath'] );

	$phablet_group = $collection->get_group_for_viewport_width( 500 );
	$test_case->assertCount( 3, $phablet_group );
	$test_case->assertTrue( $phablet_group->is_complete() );
	$lcp_element = $phablet_group->get_lcp_element();
	$test_case->assertInstanceOf( OD_Element::class, $lcp_element );
	$test_case->assertSame( $expected_lcp_element_xpath, $lcp_element->jsonSerialize()['xpath'] );

	$tablet_group = $collection->get_group_for_viewport_width( 700 );
	$test_case->assertCount( 2, $tablet_group );
	$test_case->assertFalse( $tablet_group->is_complete() );
	$lcp_element = $tablet_group->get_lcp_element();
	$test_case->assertInstanceOf( OD_Element::class, $lcp_element );
	$test_case->assertNotEquals( $expected_lcp_element_xpath, $lcp_element->jsonSerialize()['xpath'] ); // TODO: This should actually be the same!

	$desktop_group = $collection->get_group_for_viewport_width( 800 );
	$test_case->assertCount( 3, $desktop_group );
	$test_case->assertTrue( $desktop_group->is_complete() );
	$lcp_element = $desktop_group->get_lcp_element();
	$test_case->assertInstanceOf( OD_Element::class, $lcp_element );
	$test_case->assertSame( $expected_lcp_element_xpath, $lcp_element->jsonSerialize()['xpath'] );
};
