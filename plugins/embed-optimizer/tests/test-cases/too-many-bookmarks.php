<?php
return array(
	'set_up'   => static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
		$test_case->setExpectedIncorrectUsage( 'WP_HTML_Tag_Processor::set_bookmark' );

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

							$reflection = new ReflectionObject( $processor );
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
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<figure class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
					<div class="wp-block-embed__wrapper">
						<iframe title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
						<script src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
					</div>
				</figure>
			</body>
		</html>
	',
	// Note that no optimizations are applied because we ran out of bookmarks.
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]">
				<figure class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
					<div data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]" class="wp-block-embed__wrapper">
						<iframe title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
						<script src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
					</div>
				</figure>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
