<?php
/**
 * Tests for image-prioritizer plugin helper.php.
 *
 * @package image-prioritizer
 */

class Test_Image_Prioritizer_Helper extends WP_UnitTestCase {

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::image_prioritizer_render_generator_meta_tag
	 */
	public function test_image_prioritizer_render_generator_meta_tag(): void {
		$function_name = 'image_prioritizer_render_generator_meta_tag';
		$this->assertSame( 10, has_action( 'wp_head', $function_name ) );
		$tag = get_echo( $function_name );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'image-prioritizer ' . IMAGE_PRIORITIZER_VERSION, $tag );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_filter_tag_walker_visitors(): array {
		return array(
			'no-url-metrics'                              => array(
				'set_up'   => static function (): void {},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
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
							<img data-od-unknown-tag data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'no-url-metrics-with-data-url-background-image' => array(
				'set_up'   => static function (): void {
					ini_set( 'default_mimetype', 'text/html; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
				},
				// Smallest PNG courtesy of <https://evanhahn.com/worlds-smallest-png/>.
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<div style="background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg==); width:100%; height: 200px;">This is so background!</div>
						</body>
					</html>
				',
				// There should be no data-od-xpath added to the DIV because it is using a data: URL for the background-image.
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<div style="background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg==); width:100%; height: 200px;">This is so background!</div>
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'no-url-metrics-with-non-background-image-style' => array(
				'set_up'   => static function (): void {},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<div style="background-color: black; color: white; width:100%; height: 200px;">This is so background!</div>
						</body>
					</html>
				',
				// There should be no data-od-xpath added to the DIV because it is using a data: URL for the background-image.
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<div style="background-color: black; color: white; width:100%; height: 200px;">This is so background!</div>
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'no-url-metrics-with-data-url-image'          => array(
				'set_up'   => static function (): void {},
				// Smallest PNG courtesy of <https://evanhahn.com/worlds-smallest-png/>.
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg==" alt="">
						</body>
					</html>
				',
				// There should be no data-od-xpath added to the IMG because it is using a data: URL.
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg==" alt="">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'no-url-metrics-for-image-without-src'        => array(
				'set_up'   => static function (): void {},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<img id="no-src" alt="">
							<img id="empty-src" src="" alt="">
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
							<img id="no-src" alt="">
							<img id="empty-src" src="" alt="">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'no-lcp-image-with-populated-url-metrics'     => array(
				'set_up'   => function (): void {
					$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
					$sample_size = od_get_url_metrics_breakpoint_sample_size();
					foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
						for ( $i = 0; $i < $sample_size; $i++ ) {
							OD_URL_Metrics_Post_Type::store_url_metric(
								$slug,
								$this->get_validated_url_metric(
									$viewport_width,
									array(
										array(
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::H1]',
											'isLCP' => true,
										),
									)
								)
							);
						}
					}
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<h1>Hello World</h1>
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
							<h1>Hello World</h1>
						</body>
					</html>
				',
			),

			'common-lcp-image-and-lazy-loaded-image-outside-viewport-with-fully-populated-sample-data' => array(
				'set_up'   => function (): void {
					$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
					$sample_size = od_get_url_metrics_breakpoint_sample_size();
					foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
						for ( $i = 0; $i < $sample_size; $i++ ) {
							OD_URL_Metrics_Post_Type::store_url_metric(
								$slug,
								$this->get_validated_url_metric(
									$viewport_width,
									array(
										array(
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
											'isLCP' => true,
										),
										array(
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[3][self::IMG]',
											'isLCP' => false,
											'intersectionRatio' => 0 === $i ? 0.5 : 0.0, // Make sure that the _max_ intersection ratio is considered.
										),
										array(
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[5][self::IMG]',
											'isLCP' => false,
											'intersectionRatio' => 0.0,
										),
										array(
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[6][self::IMG]',
											'isLCP' => false,
											'intersectionRatio' => 0.0,
										),
										array(
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[7][self::IMG]',
											'isLCP' => false,
											'intersectionRatio' => 0.0,
										),
									)
								)
							);
						}
					}
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous">
							<p>Pretend this is a super long paragraph that pushes the next image mostly out of the initial viewport.</p>
							<img src="https://example.com/bar.jpg" alt="Bar" width="10" height="10" fetchpriority="high" loading="lazy">
							<p>Now the following image is definitely outside the initial viewport.</p>
							<img src="https://example.com/baz.jpg" alt="Baz" width="10" height="10" fetchpriority="high">
							<img src="https://example.com/qux.jpg" alt="Qux" width="10" height="10" fetchpriority="high" loading="eager">
							<img src="https://example.com/quux.jpg" alt="Quux" width="10" height="10" loading="lazy"><!-- This one is all good. -->
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" imagesrcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="screen">
						</head>
						<body>
							<img data-od-added-fetchpriority data-od-removed-loading="lazy" fetchpriority="high" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous">
							<p>Pretend this is a super long paragraph that pushes the next image mostly out of the initial viewport.</p>
							<img data-od-removed-fetchpriority="high" data-od-removed-loading="lazy" src="https://example.com/bar.jpg" alt="Bar" width="10" height="10"  >
							<p>Now the following image is definitely outside the initial viewport.</p>
							<img data-od-added-loading data-od-removed-fetchpriority="high" loading="lazy" src="https://example.com/baz.jpg" alt="Baz" width="10" height="10" >
							<img data-od-removed-fetchpriority="high" data-od-replaced-loading="eager" src="https://example.com/qux.jpg" alt="Qux" width="10" height="10"  loading="lazy">
							<img src="https://example.com/quux.jpg" alt="Quux" width="10" height="10" loading="lazy"><!-- This one is all good. -->
						</body>
					</html>
				',
			),

			'common-lcp-image-with-fully-incomplete-sample-data' => array(
				'set_up'   => function (): void {
					$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
					$sample_size = od_get_url_metrics_breakpoint_sample_size();

					// Only populate the largest viewport group.
					for ( $i = 0; $i < $sample_size; $i++ ) {
						OD_URL_Metrics_Post_Type::store_url_metric(
							$slug,
							$this->get_validated_url_metric(
								1000,
								array(
									array(
										'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
										'isLCP' => true,
									),
									array(
										'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
										'isLCP' => false,
									),
								)
							)
						);
					}
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
							<img src="https://example.com/bar.jpg" alt="Bar" width="10" height="10" loading="lazy" fetchpriority="high">
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" media="screen and (min-width: 783px)">
						</head>
						<body>
							<img data-od-removed-loading="lazy" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" >
							<img data-od-removed-loading="lazy" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]" src="https://example.com/bar.jpg" alt="Bar" width="10" height="10"  fetchpriority="high">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'common-lcp-image-with-stale-sample-data'     => array(
				'set_up'   => function (): void {
					$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
					$sample_size = od_get_url_metrics_breakpoint_sample_size();
					foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
						for ( $i = 0; $i < $sample_size; $i++ ) {
							OD_URL_Metrics_Post_Type::store_url_metric(
								$slug,
								$this->get_validated_url_metric(
									$viewport_width,
									array(
										array(
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]', // Note: This is intentionally not reflecting the IMG in the HTML below.
											'isLCP' => true,
										),
									)
								)
							);
						}
					}
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<script>/* Something injected with wp_body_open */</script>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
						</body>
					</html>
				',
				// The preload link should be absent because the URL Metrics were collected before the script was printed at wp_body_open, causing the XPath to no longer be valid.
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<script>/* Something injected with wp_body_open */</script>
							<img data-od-unknown-tag src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
						</body>
					</html>
				',
			),

			'common-lcp-background-image-with-fully-populated-sample-data' => array(
				'set_up'   => function (): void {
					$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
					$sample_size = od_get_url_metrics_breakpoint_sample_size();
					foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
						for ( $i = 0; $i < $sample_size; $i++ ) {
							OD_URL_Metrics_Post_Type::store_url_metric(
								$slug,
								$this->get_validated_url_metric(
									$viewport_width,
									array(
										array(
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]',
											'isLCP' => true,
										),
									)
								)
							);
						}
					}
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo-bg.jpg" media="screen">
						</head>
						<body>
							<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
						</body>
					</html>
				',
			),

			'responsive-background-images'                => array(
				'set_up'   => function (): void {
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

					$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
					$div_index_to_viewport_width_mapping = array(
						0 => $desktop_breakpoint,
						1 => $tablet_breakpoint,
						2 => $mobile_breakpoint,
					);

					foreach ( $div_index_to_viewport_width_mapping as $div_index => $viewport_width ) {
						for ( $i = 0; $i < $sample_size; $i++ ) {
							OD_URL_Metrics_Post_Type::store_url_metric(
								$slug,
								$this->get_validated_url_metric(
									$viewport_width,
									array(
										array(
											'xpath' => sprintf( '/*[1][self::HTML]/*[2][self::BODY]/*[%d][self::DIV]', $div_index + 1 ),
											'isLCP' => true,
										),
									)
								)
							);
						}
					}
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<style>/* responsive styles to show only one div.header at a time... */</style>
						</head>
						<body>
							<div class="header desktop" style="background: red no-repeat center/80% url(\'https://example.com/desktop-bg.jpg\'); width:100%; height: 200px;">This is the desktop background!</div>
							<div class="header tablet" style=\'background-image:url( "https://example.com/tablet-bg.jpg" ); width:100%; height: 200px;\'>This is the tablet background!</div>
							<div class="header mobile" style="background-image:url(https://example.com/mobile-bg.jpg); width:100%; height: 200px;">This is the mobile background!</div>
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<style>/* responsive styles to show only one div.header at a time... */</style>
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-bg.jpg" media="screen and (max-width: 480px)">
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/tablet-bg.jpg" media="screen and (min-width: 481px) and (max-width: 600px)">
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop-bg.jpg" media="screen and (min-width: 601px)">
						</head>
						<body>
							<div class="header desktop" style="background: red no-repeat center/80% url(\'https://example.com/desktop-bg.jpg\'); width:100%; height: 200px;">This is the desktop background!</div>
							<div class="header tablet" style=\'background-image:url( "https://example.com/tablet-bg.jpg" ); width:100%; height: 200px;\'>This is the tablet background!</div>
							<div class="header mobile" style="background-image:url(https://example.com/mobile-bg.jpg); width:100%; height: 200px;">This is the mobile background!</div>
						</body>
					</html>
				',
			),

			'fetch-priority-high-already-on-common-lcp-image-with-fully-populated-sample-data' => array(
				'set_up'   => function (): void {
					$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
					$sample_size = od_get_url_metrics_breakpoint_sample_size();
					foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
						for ( $i = 0; $i < $sample_size; $i++ ) {
							OD_URL_Metrics_Post_Type::store_url_metric(
								$slug,
								$this->get_validated_url_metric(
									$viewport_width,
									array(
										array(
											'isLCP' => true,
											'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
										),
									)
								)
							);
						}
					}
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
						</head>
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high">
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" media="screen">
						</head>
						<body>
							<img data-od-fetchpriority-already-added src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high">
						</body>
					</html>
				',
			),

			'url-metric-only-captured-for-one-breakpoint' => array(
				'set_up'   => function (): void {
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							400,
							array(
								array(
									'isLCP' => true,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
							)
						)
					);
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<!--</head>-->
						</head>
						<!--</head>-->
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
							<!--</body>-->
						</body>
						<!--</body>-->
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<!--</head>-->
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" media="screen and (max-width: 480px)">
						</head>
						<!--</head>-->
						<body>
							<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
							<!--</body>-->
							<script type="module">/* import detect ... */</script>
						</body>
						<!--</body>-->
					</html>
				',
			),

			// TODO: Eventually the images in this test should all be lazy-loaded, leaving the prioritization to the preload links.
			'different-lcp-elements-for-non-consecutive-viewport-groups-with-missing-data-for-middle-group' => array(
				'set_up'   => function (): void {
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							400,
							array(
								array(
									'isLCP'             => true,
									'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
									'intersectionRatio' => 1.0,
								),
								array(
									'isLCP'             => false,
									'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
									'intersectionRatio' => 0.0,
								),
							)
						)
					);
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							800,
							array(
								array(
									'isLCP'             => false,
									'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
									'intersectionRatio' => 0.0,
								),
								array(
									'isLCP'             => true,
									'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
									'intersectionRatio' => 1.0,
								),
							)
						)
					);
				},
				'buffer'   => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<style>/* Never show mobile and desktop logos at the same time. */</style>
						</head>
						<body>
							<img src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
							<img src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<style>/* Never show mobile and desktop logos at the same time. */</style>
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-logo.png" media="screen and (max-width: 480px)">
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop-logo.png" media="screen and (min-width: 783px)">
						</head>
						<body>
							<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
							<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]" src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'different-lcp-elements-for-two-non-consecutive-breakpoints' => array(
				'set_up'   => function (): void {
					add_filter(
						'od_breakpoint_max_widths',
						static function () {
							return array( 480, 600, 782 );
						}
					);

					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							400,
							array(
								array(
									'isLCP' => true,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								),
							)
						)
					);
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							500,
							array(
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								),
							)
						)
					);
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							700,
							array(
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
								array(
									'isLCP' => true,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								),
							)
						)
					);
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							800,
							array(
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								),
							)
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
							<img src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
							<img src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-logo.png" media="screen and (max-width: 480px)">
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop-logo.png" media="screen and (min-width: 601px) and (max-width: 782px)">
						</head>
						<body>
							<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
							<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]" src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'different-lcp-elements-for-two-non-consecutive-breakpoints-and-one-is-stale' => array(
				'set_up'   => function (): void {
					add_filter(
						'od_breakpoint_max_widths',
						static function () {
							return array( 480, 600, 782 );
						}
					);

					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							500,
							array(
								array(
									'isLCP' => true,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								),
							)
						)
					);
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							650,
							array(
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								),
							)
						)
					);
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							800,
							array(
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
								array(
									'isLCP' => true,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								),
							)
						)
					);
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							800,
							array(
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								),
							)
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
							<img src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
							<p>New paragraph since URL Metrics were captured!</p>
							<img data-od-unknown-tag src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-logo.png" media="screen and (min-width: 481px) and (max-width: 600px)">
						</head>
						<body>
							<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
							<p>New paragraph since URL Metrics were captured!</p>
							<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[3][self::IMG]" data-od-unknown-tag src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),
		);
	}

	/**
	 * Test image_prioritizer_register_tag_visitors().
	 *
	 * @covers ::image_prioritizer_register_tag_visitors
	 * @covers Image_Prioritizer_Tag_Visitor
	 * @covers Image_Prioritizer_Img_Tag_Visitor
	 * @covers Image_Prioritizer_Background_Image_Styled_Tag_Visitor
	 *
	 * @dataProvider data_provider_test_filter_tag_walker_visitors
	 * @throws Exception But it won't.
	 */
	public function test_image_prioritizer_register_tag_visitors( Closure $set_up, string $buffer, string $expected ): void {
		$set_up();

		$remove_initial_tabs = static function ( string $input ): string {
			return (string) preg_replace( '/^\t+/m', '', $input );
		};

		$expected = $remove_initial_tabs( $expected );
		$buffer   = $remove_initial_tabs( $buffer );

		$buffer = preg_replace(
			':<script type="module">.+?</script>:s',
			'<script type="module">/* import detect ... */</script>',
			od_optimize_template_output_buffer( $buffer )
		);

		$this->assertEquals( $expected, $buffer );
	}

	/**
	 * Gets a validated URL metric.
	 *
	 * @todo Move this into a trait or an Optimization Detective helper base test class.
	 *
	 * @param int                                      $viewport_width Viewport width for the URL metric.
	 * @param array<array{xpath: string, isLCP: bool}> $elements       Elements.
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
