<?php
/**
 * Tests for embed-optimizer plugin hooks.php.
 *
 * @package embed-optimizer
 */

/**
 * @phpstan-type ElementDataSubset array{xpath: string, isLCP: bool, intersectionRatio: float}
 */
class Test_Embed_Optimizer_Optimization_Detective extends WP_UnitTestCase {
	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) ) {
			$this->markTestSkipped( 'Optimization Detective is not active.' );
		}
	}

	/**
	 * Tests embed_optimizer_register_tag_visitors().
	 *
	 * @covers ::embed_optimizer_register_tag_visitors
	 */
	public function test_embed_optimizer_register_tag_visitors(): void {
		$link_collection  = new OD_Link_Collection();
		$group_collection = new OD_URL_Metrics_Group_Collection( array(), array( 1024 ), 3, DAY_IN_SECONDS );
		$registry         = new OD_Tag_Visitor_Registry();
		embed_optimizer_register_tag_visitors( $registry, $group_collection, $link_collection );
		$this->assertTrue( $registry->is_registered( 'embeds' ) );
		$this->assertInstanceOf( Embed_Optimizer_Tag_Visitor::class, $registry->get_registered( 'embeds' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		return array(
			'single_youtube_embed_inside_viewport'      => array(
				'set_up'   => function (): void {
					$this->populate_complete_url_metrics(
						array(
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
							'isLCP'             => true,
							'intersectionRatio' => 1,
						)
					);
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
								</div>
							</figure>
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preconnect" href="https://www.youtube.com">
							<link data-od-added-tag rel="preconnect" href="https://i.ytimg.com">
						</head>
						<body>
							<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
								</div>
							</figure>
						</body>
					</html>
				',
			),

			'single_youtube_embed_outside_viewport'     => array(
				'set_up'   => function (): void {
					$this->populate_complete_url_metrics(
						array(
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
							'isLCP'             => false,
							'intersectionRatio' => 0,
						)
					);
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
								</div>
							</figure>
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe data-od-added-loading loading="lazy" title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
								</div>
							</figure>
						</body>
					</html>
				',
			),

			'single_twitter_embed_inside_viewport'      => array(
				'set_up'   => function (): void {
					$this->populate_complete_url_metrics(
						array(
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
							'isLCP'             => true,
							'intersectionRatio' => 1,
						)
					);
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
								<div class="wp-block-embed__wrapper">
									<blockquote class="twitter-tweet" data-width="550" data-dnt="true"><p lang="en" dir="ltr">We want your feedback for the Privacy Sandbox 📨<br><br>Learn why your feedback is critical through real examples and learn how to provide it ↓ <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; Chrome for Developers (@ChromiumDev) <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote>
									<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
								</div>
							</figure>
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preconnect" href="https://syndication.twitter.com">
							<link data-od-added-tag rel="preconnect" href="https://pbs.twimg.com">
						</head>
						<body>
							<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
								<div class="wp-block-embed__wrapper">
									<blockquote class="twitter-tweet" data-width="550" data-dnt="true"><p lang="en" dir="ltr">We want your feedback for the Privacy Sandbox 📨<br><br>Learn why your feedback is critical through real examples and learn how to provide it ↓ <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; Chrome for Developers (@ChromiumDev) <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote>
									<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
								</div>
							</figure>
						</body>
					</html>
				',
			),

			'single_twitter_embed_outside_viewport'     => array(
				'set_up'   => function (): void {
					$this->populate_complete_url_metrics(
						array(
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
							'isLCP'             => false,
							'intersectionRatio' => 0,
						)
					);
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
								<div class="wp-block-embed__wrapper">
									<blockquote class="twitter-tweet" data-width="550" data-dnt="true"><p lang="en" dir="ltr">We want your feedback for the Privacy Sandbox 📨<br><br>Learn why your feedback is critical through real examples and learn how to provide it ↓ <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; Chrome for Developers (@ChromiumDev) <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote>
									<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
								</div>
							</figure>
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
								<div class="wp-block-embed__wrapper">
									<blockquote class="twitter-tweet" data-width="550" data-dnt="true"><p lang="en" dir="ltr">We want your feedback for the Privacy Sandbox 📨<br><br>Learn why your feedback is critical through real examples and learn how to provide it ↓ <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; Chrome for Developers (@ChromiumDev) <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote>
									<script data-od-added-type type="application/vnd.embed-optimizer.javascript" async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
								</div>
							</figure>
							<script type="module">/* ... */</script>
						</body>
					</html>
				',
			),

			'single_wordpresstv_embed_inside_viewport'  => array(
				'set_up'   => function (): void {
					$this->populate_complete_url_metrics(
						array(
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
							'isLCP'             => true,
							'intersectionRatio' => 1,
						)
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
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preconnect" href="https://video.wordpress.com">
							<link data-od-added-tag rel="preconnect" href="https://public-api.wordpress.com">
							<link data-od-added-tag rel="preconnect" href="https://videos.files.wordpress.com">
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
			),

			'single_wordpresstv_embed_outside_viewport' => array(
				'set_up'   => function (): void {
					$this->populate_complete_url_metrics(
						array(
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
							'isLCP'             => false,
							'intersectionRatio' => 0,
						)
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
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe data-od-added-loading loading="lazy" title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
									<script data-od-added-type type="application/vnd.embed-optimizer.javascript" src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
								</div>
							</figure>
							<script type="module">/* ... */</script>
						</body>
					</html>
				',
			),
		);
	}

	/**
	 * Test embed_optimizer_visit_tag().
	 *
	 * @covers Embed_Optimizer_Tag_Visitor
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 * @throws Exception But it won't.
	 */
	public function test_od_optimize_template_output_buffer( Closure $set_up, string $buffer, string $expected ): void {
		$set_up();

		$remove_initial_tabs = static function ( string $input ): string {
			return (string) preg_replace( '/^\t+/m', '', $input );
		};

		$expected = $remove_initial_tabs( $expected );
		$buffer   = $remove_initial_tabs( $buffer );

		$buffer = preg_replace(
			':<script type="module">.+?</script>:s',
			'<script type="module">/* ... */</script>',
			od_optimize_template_output_buffer( $buffer )
		);

		$this->assertEquals( $expected, $buffer );
	}

	/**
	 * Populates complete URL metrics for the provided element data.
	 *
	 * @phpstan-param ElementDataSubset $element
	 * @param array $element Element data.
	 * @throws Exception But it won't.
	 */
	protected function populate_complete_url_metrics( array $element ): void {
		$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = od_get_url_metrics_breakpoint_sample_size();
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$this->get_validated_url_metric(
						$viewport_width,
						array(
							$element,
						)
					)
				);
			}
		}
	}

	/**
	 * Gets a validated URL metric.
	 *
	 * @param int                      $viewport_width Viewport width for the URL metric.
	 * @param array<ElementDataSubset> $elements       Elements.
	 * @return OD_URL_Metric URL metric.
	 * @throws Exception From OD_URL_Metric if there is a parse error, but there won't be.
	 */
	private function get_validated_url_metric( int $viewport_width, array $elements = array() ): OD_URL_Metric {
		$data = array(
			'url'       => home_url( '/' ),
			'viewport'  => array(
				'width'  => $viewport_width,
				'height' => 800,
			),
			'timestamp' => microtime( true ),
			'elements'  => array_map(
				static function ( array $element ): array {
					return array_merge(
						array(
							'isLCPCandidate'     => true,
							'intersectionRatio'  => 1,
							'intersectionRect'   => array(
								'width'  => 100,
								'height' => 100,
							),
							'boundingClientRect' => array(
								'width'  => 100,
								'height' => 100,
							),
						),
						$element
					);
				},
				$elements
			),
		);
		return new OD_URL_Metric( $data );
	}
}
