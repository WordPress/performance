<?php
return array(
	'set_up'   => static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
		$rect = array(
			'width'  => 500.1,
			'height' => 500.2,
			'x'      => 100.3,
			'y'      => 100.4,
			'top'    => 0.1,
			'right'  => 0.2,
			'bottom' => 0.3,
			'left'   => 0.4,
		);

		$test_case->populate_url_metrics(
			array(
				array(
					'xpath'                     => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]',
					'isLCP'                     => false,
					'intersectionRatio'         => 1,
					'resizedBoundingClientRect' => array_merge( $rect, array( 'height' => 500 ) ),
				),
				array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]/*[1][self::VIDEO]',
					'isLCP'             => false,
					'intersectionRatio' => 1,
				),
				array(
					'xpath'                     => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]/*[1][self::DIV]',
					'isLCP'                     => false,
					'intersectionRatio'         => 0,
					'resizedBoundingClientRect' => array_merge( $rect, array( 'height' => 654 ) ),
				),
				array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]/*[1][self::DIV]/*[1][self::FIGURE]/*[2][self::VIDEO]',
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
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<figure style="background: black; color:gray" class="wp-block-embed is-type-video">
					<div class="wp-block-embed__wrapper">
						<video src="https://example.com/video1.mp4" poster="https://example.com/poster1.jpg" width="640" height="480"></video>
					</div>
				</figure>
				<figure id="existing-figurine-id" class="wp-block-embed is-type-rich is-provider-figurine wp-block-embed-figurine">
					<div class="wp-block-embed__wrapper">
						<figure>
							<p>So I heard you like <code>FIGURE</code>?</p>
							<video src="https://example.com/video2.mp4" poster="https://example.com/poster2.jpg" width="640" height="480"></video>
							<figcaption>Tagline from Figurine embed.</figcaption>
						</figure>
						<iframe src="https://example.com/" width="640" height="480"></iframe>
					</div>
				</figure>
				<script src="https://example.com/script-not-part-of-embed.js"></script>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<style>
				@media (max-width: 480px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 481px) and (max-width: 600px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 601px) and (max-width: 782px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 783px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				</style>
				<style>
				@media (max-width: 480px) { #existing-figurine-id { min-height: 654px; } }
				@media (min-width: 481px) and (max-width: 600px) { #existing-figurine-id { min-height: 654px; } }
				@media (min-width: 601px) and (max-width: 782px) { #existing-figurine-id { min-height: 654px; } }
				@media (min-width: 783px) { #existing-figurine-id { min-height: 654px; } }
				</style>
				<link data-od-added-tag rel="preload" as="image" href="https://example.com/poster1.jpg">
			</head>
			<body>
				<figure data-od-added-id id="embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8" style="background: black; color:gray" class="wp-block-embed is-type-video">
					<div data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]" class="wp-block-embed__wrapper">
						<video data-od-added-preload data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]/*[1][self::VIDEO]" preload="auto" src="https://example.com/video1.mp4" poster="https://example.com/poster1.jpg" width="640" height="480"></video>
					</div>
				</figure>
				<figure id="existing-figurine-id" class="wp-block-embed is-type-rich is-provider-figurine wp-block-embed-figurine">
					<div data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]/*[1][self::DIV]" class="wp-block-embed__wrapper">
						<figure>
							<p>So I heard you like <code>FIGURE</code>?</p>
							<video data-od-added-preload data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]/*[1][self::DIV]/*[1][self::FIGURE]/*[2][self::VIDEO]" preload="none" src="https://example.com/video2.mp4" poster="https://example.com/poster2.jpg" width="640" height="480"></video>
							<figcaption>Tagline from Figurine embed.</figcaption>
						</figure>
						<iframe data-od-added-loading loading="lazy" src="https://example.com/" width="640" height="480"></iframe>
					</div>
				</figure>
				<script src="https://example.com/script-not-part-of-embed.js"></script>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
