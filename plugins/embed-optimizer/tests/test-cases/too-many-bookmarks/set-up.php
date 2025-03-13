<?php
return static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
	$test_case->setExpectedIncorrectUsage( 'WP_HTML_Tag_Processor::set_bookmark' );

	$test_case->populate_url_metrics(
		array(
			array(
				'xpath'             => '/HTML/BODY/DIV[@class=\'wp-site-blocks\']/*[1][self::BOGUS]',
				'isLCP'             => false,
				'intersectionRatio' => 0.0,
			),
		),
		false
	);

	// Check what happens when there are too many bookmarks.
	add_action(
		'od_register_tag_visitors',
		static function ( OD_Tag_Visitor_Registry $registry ) use ( $test_case ): void {
			$registry->register(
				'body',
				static function ( OD_Tag_Visitor_Context $context ) use ( $test_case ): bool {
					$processor = $context->processor;
					if ( $processor->get_tag() === 'BODY' ) {
						$test_case->assertFalse( $processor->is_tag_closer() );

						$reflection         = new ReflectionObject( $processor );
						$bookmarks_property = $reflection->getProperty( 'bookmarks' );
						$bookmarks_property->setAccessible( true );
						$bookmarks = $bookmarks_property->getValue( $processor );
						$test_case->assertCount( 2, $bookmarks );
						$test_case->assertArrayHasKey( OD_HTML_Tag_Processor::END_OF_HEAD_BOOKMARK, $bookmarks );
						$test_case->assertArrayHasKey( 'optimization_detective_current_tag', $bookmarks );

						// Set a bunch of bookmarks to fill up the total allowed.
						$remaining_bookmark_count = WP_HTML_Tag_Processor::MAX_BOOKMARKS - count( $bookmarks );
						for ( $i = 0; $i < $remaining_bookmark_count; $i++ ) {
							$test_case->assertTrue( $processor->set_bookmark( "body_bookmark_{$i}" ) );
						}
						return true;
					}
					return false;
				}
			);
		}
	);
};
