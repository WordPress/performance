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
			'single_youtube_embed_inside_viewport'       => array(
				'set_up'   => function (): void {
					$this->populate_url_metrics(
						array(
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
								'isLCP'             => true,
								'intersectionRatio' => 1,
							),
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

			'single_youtube_embed_outside_viewport'      => array(
				'set_up'   => function (): void {
					$this->populate_url_metrics(
						array(
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
								'isLCP'             => false,
								'intersectionRatio' => 0,
							),
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

			'single_twitter_embed_inside_viewport'       => array(
				'set_up'   => function (): void {
					$this->populate_url_metrics(
						array(
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
								'isLCP'             => true,
								'intersectionRatio' => 1,
							),
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

			'single_twitter_embed_outside_viewport'      => array(
				'set_up'   => function (): void {
					$this->populate_url_metrics(
						array(
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
								'isLCP'             => false,
								'intersectionRatio' => 0,
							),
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
							<script type="module">/* const lazyEmbedsScripts ... */</script>
						</body>
					</html>
				',
			),

			'single_wordpress_tv_embed_inside_viewport'  => array(
				'set_up'   => function (): void {
					$this->populate_url_metrics(
						array(
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
								'isLCP'             => true,
								'intersectionRatio' => 1,
							),
						),
						false
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
							<link data-od-added-tag rel="preconnect" href="https://v0.wordpress.com">
						</head>
						<body>
							<figure data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]" class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
									<script src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
								</div>
							</figure>
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'single_wordpress_tv_embed_outside_viewport' => array(
				'set_up'   => function (): void {
					$this->populate_url_metrics(
						array(
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
								'isLCP'             => false,
								'intersectionRatio' => 0,
							),
						),
						false
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
							<figure data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]" class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe data-od-added-loading loading="lazy" title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
									<script data-od-added-type type="application/vnd.embed-optimizer.javascript" src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
								</div>
							</figure>
							<script type="module">/* const lazyEmbedsScripts ... */</script>
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'single_spotify_embed_outside_viewport_with_subsequent_script' => array(
				'set_up'   => function (): void {
					$this->populate_url_metrics(
						array(
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
								'isLCP'             => false,
								'intersectionRatio' => 0,
							),
						),
						false
					);
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-rich is-provider-spotify wp-block-embed-spotify wp-embed-aspect-21-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe title="Spotify Embed: Deep Focus" style="border-radius: 12px" width="100%" height="352" frameborder="0" allowfullscreen allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" src="https://open.spotify.com/embed/playlist/37i9dQZF1DWZeKCadgRdKQ?utm_source=oembed"></iframe>
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
						</head>
						<body>
							<figure data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]" class="wp-block-embed is-type-rich is-provider-spotify wp-block-embed-spotify wp-embed-aspect-21-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe data-od-added-loading loading="lazy" title="Spotify Embed: Deep Focus" style="border-radius: 12px" width="100%" height="352" frameborder="0" allowfullscreen allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" src="https://open.spotify.com/embed/playlist/37i9dQZF1DWZeKCadgRdKQ?utm_source=oembed"></iframe>
								</div>
							</figure>
							<script src="https://example.com/script-not-part-of-embed.js"></script>
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'nested_figure_embed'                        => array(
				'set_up'   => function (): void {
					$this->populate_url_metrics(
						array(
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
								'isLCP'             => false,
								'intersectionRatio' => 1,
							),
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]/*[1][self::VIDEO]',
								'isLCP'             => false,
								'intersectionRatio' => 1,
							),
							array(
								'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]',
								'isLCP'             => false,
								'intersectionRatio' => 0,
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
						function ( OD_Tag_Visitor_Registry $registry, OD_URL_Metrics_Group_Collection $group_collection, OD_Link_Collection $link_collection ): void {
							$registry->register(
								'video_with_poster',
								function ( OD_HTML_Tag_Processor $processor ) use ( $group_collection, $link_collection ): bool {
									static $seen_video_count = 0;
									if ( $processor->get_tag() !== 'VIDEO' ) {
										return false;
									}
									$poster = $processor->get_attribute( 'poster' );
									if ( ! is_string( $poster ) ) {
										return false;
									}
									$seen_video_count++;
									if ( 1 === $seen_video_count ) {
										$processor->set_bookmark( 'the_first_video' );
									} else {
										$this->assertTrue( $processor->has_bookmark( 'the_first_video' ) );
									}
									if ( $group_collection->get_element_max_intersection_ratio( $processor->get_xpath() ) > 0 ) {
										$link_collection->add_link(
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
						},
						10,
						3
					);
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<figure class="wp-block-embed is-type-video">
								<div class="wp-block-embed__wrapper">
									<video src="https://example.com/video1.mp4" poster="https://example.com/poster1.jpg" width="640" height="480"></video>
								</div>
							</figure>
							<figure class="wp-block-embed is-type-rich is-provider-figurine wp-block-embed-figurine">
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
							<link data-od-added-tag rel="preload" as="image" href="https://example.com/poster1.jpg">
						</head>
						<body>
							<figure data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]" class="wp-block-embed is-type-video">
								<div class="wp-block-embed__wrapper">
									<video data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]/*[1][self::VIDEO]" data-od-added-preload preload="auto" src="https://example.com/video1.mp4" poster="https://example.com/poster1.jpg" width="640" height="480"></video>
								</div>
							</figure>
							<figure data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]" class="wp-block-embed is-type-rich is-provider-figurine wp-block-embed-figurine">
								<div class="wp-block-embed__wrapper">
									<figure>
										<p>So I heard you like <code>FIGURE</code>?</p>
										<video data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]/*[1][self::DIV]/*[1][self::FIGURE]/*[2][self::VIDEO]" data-od-added-preload preload="none" src="https://example.com/video2.mp4" poster="https://example.com/poster2.jpg" width="640" height="480"></video>
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
			),

			'too_many_bookmarks'                         => array(
				'set_up'   => function (): void {
					$this->setExpectedIncorrectUsage( 'WP_HTML_Tag_Processor::set_bookmark' );

					// Check what happens when there are too many bookmarks.
					add_action(
						'od_register_tag_visitors',
						function ( OD_Tag_Visitor_Registry $registry ): void {
							$registry->register(
								'body',
								function ( OD_HTML_Tag_Processor $processor ): bool {
									if ( $processor->get_tag() === 'BODY' ) {
										$this->assertFalse( $processor->is_tag_closer() );

										$reflection = new ReflectionObject( $processor );
										$bookmarks_property = $reflection->getProperty( 'bookmarks' );
										$bookmarks_property->setAccessible( true );
										$bookmarks = $bookmarks_property->getValue( $processor );
										$this->assertCount( 2, $bookmarks );
										$this->assertArrayHasKey( OD_HTML_Tag_Processor::END_OF_HEAD_BOOKMARK, $bookmarks );
										$this->assertArrayHasKey( 'optimization_detective_current_tag', $bookmarks );

										// Set a bunch of bookmarks to fill up the total allowed.
										$remaining_bookmark_count = WP_HTML_Tag_Processor::MAX_BOOKMARKS - count( $bookmarks );
										for ( $i = 0; $i < $remaining_bookmark_count; $i++ ) {
											$processor->set_bookmark( "body_bookmark_{$i}" );
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
							<figure data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]" class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
									<script src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
								</div>
							</figure>
							<script type="module">/* import detect ... */</script>
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
		$set_up = Closure::bind( $set_up, $this ); // TODO: It's not clear to me why this is required. Without it, the setExpectedIncorrectUsage calls don't result in the expected_doing_it_wrong property being populated.
		$set_up();

		$remove_initial_tabs = static function ( string $input ): string {
			return (string) preg_replace( '/^\t+/m', '', $input );
		};

		$expected = $remove_initial_tabs( $expected );
		$buffer   = $remove_initial_tabs( $buffer );

		$buffer = od_optimize_template_output_buffer( $buffer );
		$buffer = preg_replace_callback(
			':(<script type="module">)(.+?)(</script>):s',
			static function ( $matches ) {
				array_shift( $matches );
				if ( false !== strpos( $matches[1], 'import detect' ) ) {
					$matches[1] = '/* import detect ... */';
				} elseif ( false !== strpos( $matches[1], 'const lazyEmbedsScripts' ) ) {
					$matches[1] = '/* const lazyEmbedsScripts ... */';
				}
				return implode( '', $matches );
			},
			$buffer
		);
		$this->assertEquals( $expected, $buffer );
	}

	/**
	 * Populates complete URL metrics for the provided element data.
	 *
	 * @phpstan-param ElementDataSubset[] $elements
	 * @param array[] $elements Element data.
	 * @param bool    $complete Whether to fully populate the groups.
	 * @throws Exception But it won't.
	 */
	protected function populate_url_metrics( array $elements, bool $complete = true ): void {
		$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = $complete ? od_get_url_metrics_breakpoint_sample_size() : 1;
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$this->get_validated_url_metric(
						$viewport_width,
						$elements
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
