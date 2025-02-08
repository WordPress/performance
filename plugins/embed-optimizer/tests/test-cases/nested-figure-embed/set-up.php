<?php
return static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath'                     => '/HTML/BODY/DIV[@class=\'wp-site-blocks\']/*[1][self::FIGURE]/*[1][self::DIV]',
				'isLCP'                     => false,
				'intersectionRatio'         => 1,
				'resizedBoundingClientRect' => array_merge( $test_case->get_sample_dom_rect(), array( 'height' => 500 ) ),
			),
			array(
				'xpath'             => '/HTML/BODY/DIV[@class=\'wp-site-blocks\']/*[1][self::FIGURE]/*[1][self::DIV]/*[1][self::VIDEO]',
				'isLCP'             => false,
				'intersectionRatio' => 1,
			),
			array(
				'xpath'                     => '/HTML/BODY/DIV[@class=\'wp-site-blocks\']/*[2][self::FIGURE]/*[1][self::DIV]',
				'isLCP'                     => false,
				'intersectionRatio'         => 0,
				'resizedBoundingClientRect' => array_merge( $test_case->get_sample_dom_rect(), array( 'height' => 654 ) ),
			),
			array(
				'xpath'             => '/HTML/BODY/DIV[@class=\'wp-site-blocks\']/*[2][self::FIGURE]/*[1][self::DIV]/*[1][self::FIGURE]/*[2][self::VIDEO]',
				'isLCP'             => false,
				'intersectionRatio' => 0,
			),
		),
		false
	);

	// This tests how the Embed Optimizer plugin plays along with other tag visitors.
	add_action(
		'od_register_tag_visitors',
		static function ( OD_Tag_Visitor_Registry $registry ) use ( $test_case ): void {
			$registry->register(
				'video_with_poster',
				static function ( OD_Tag_Visitor_Context $context ) use ( $test_case ): bool {
					static $seen_video_count = 0;

					$processor = $context->processor;
					if ( $processor->get_tag() !== 'VIDEO' ) {
						return false;
					}
					$poster = $processor->get_attribute( 'poster' );
					if ( ! is_string( $poster ) || '' === $poster ) {
						return false;
					}
					$seen_video_count++;
					if ( 1 === $seen_video_count ) {
						$processor->set_bookmark( 'the_first_video' );
					} else {
						$test_case->assertTrue( $processor->has_bookmark( 'the_first_video' ) );
					}
					if ( $context->url_metric_group_collection->get_element_max_intersection_ratio( $processor->get_xpath() ) > 0 ) {
						$context->link_collection->add_link(
							array(
								'rel'  => 'preload',
								'as'   => 'image',
								'href' => $poster,
							)
						);
						$processor->set_attribute( 'preload', 'auto' );
					} else {
						$processor->set_attribute( 'preload', 'none' );
					}
					return true;
				}
			);
		}
	);
};
