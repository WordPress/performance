<?php
/**
 * Tests for optimization-detective plugin optimization.php.
 *
 * @package optimization-detective
 *
 * @todo There are "Cannot resolve ..." errors and "Element img doesn't have a required attribute src" warnings that should be excluded from inspection.
 */

class OD_Optimization_Tests extends WP_UnitTestCase {

	/**
	 * @var string
	 */
	private $original_request_uri;

	/**
	 * @var string
	 */
	private $original_request_method;

	public function set_up() {
		$this->original_request_uri    = $_SERVER['REQUEST_URI'];
		$this->original_request_method = $_SERVER['REQUEST_METHOD'];
		parent::set_up();
	}

	public function tear_down() {
		$_SERVER['REQUEST_URI']    = $this->original_request_uri;
		$_SERVER['REQUEST_METHOD'] = $this->original_request_method;
		unset( $GLOBALS['wp_customize'] );
		parent::tear_down();
	}

	/**
	 * Make output is buffered and that it is also filtered.
	 *
	 * @covers ::od_buffer_output
	 */
	public function test_od_buffer_output() {
		$original = 'Hello World!';
		$expected = '¡Hola Mundo!';

		// In order to test, a wrapping output buffer is required because ob_get_clean() does not invoke the output
		// buffer callback. See <https://stackoverflow.com/a/61439514/93579>.
		ob_start();

		add_filter(
			'od_template_output_buffer',
			function ( $buffer ) use ( $original, $expected ) {
				$this->assertSame( $original, $buffer );
				return $expected;
			}
		);

		$original_ob_level = ob_get_level();
		od_buffer_output( '' );
		$this->assertSame( $original_ob_level + 1, ob_get_level(), 'Expected call to ob_start().' );
		echo $original;

		ob_end_flush(); // Flushing invokes the output buffer callback.

		$buffer = ob_get_clean(); // Get the buffer from our wrapper output buffer.
		$this->assertSame( $expected, $buffer );
	}

	/**
	 * Test od_maybe_add_template_output_buffer_filter().
	 *
	 * @covers ::od_maybe_add_template_output_buffer_filter
	 */
	public function test_od_maybe_add_template_output_buffer_filter() {
		$this->assertFalse( has_filter( 'od_template_output_buffer' ) );

		add_filter( 'od_can_optimize_response', '__return_false', 1 );
		od_maybe_add_template_output_buffer_filter();
		$this->assertFalse( od_can_optimize_response() );
		$this->assertFalse( has_filter( 'od_template_output_buffer' ) );

		add_filter( 'od_can_optimize_response', '__return_true', 2 );
		$this->go_to( home_url( '/' ) );
		$this->assertTrue( od_can_optimize_response() );
		od_maybe_add_template_output_buffer_filter();
		$this->assertTrue( has_filter( 'od_template_output_buffer' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_provider_test_od_can_optimize_response(): array {
		return array(
			'homepage'           => array(
				'set_up'   => function () {
					$this->go_to( home_url( '/' ) );
				},
				'expected' => true,
			),
			'homepage_filtered'  => array(
				'set_up'   => function () {
					$this->go_to( home_url( '/' ) );
					add_filter( 'od_can_optimize_response', '__return_false' );
				},
				'expected' => false,
			),
			'search'             => array(
				'set_up'   => function () {
					self::factory()->post->create( array( 'post_title' => 'Hello' ) );
					$this->go_to( home_url( '?s=Hello' ) );
				},
				'expected' => false,
			),
			'customizer_preview' => array(
				'set_up'   => function () {
					$this->go_to( home_url( '/' ) );
					global $wp_customize;
					require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
					$wp_customize = new WP_Customize_Manager();
					$wp_customize->start_previewing_theme();
				},
				'expected' => false,
			),
			'post_request'       => array(
				'set_up'   => function () {
					$this->go_to( home_url( '/' ) );
					$_SERVER['REQUEST_METHOD'] = 'POST';
				},
				'expected' => false,
			),
			'subscriber_user'    => array(
				'set_up'   => function () {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
					$this->go_to( home_url( '/' ) );
				},
				'expected' => true,
			),
			'admin_user'         => array(
				'set_up'   => function () {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
					$this->go_to( home_url( '/' ) );
				},
				'expected' => false,
			),
		);
	}

	/**
	 * Test od_can_optimize_response().
	 *
	 * @covers ::od_can_optimize_response
	 *
	 * @dataProvider data_provider_test_od_can_optimize_response
	 */
	public function test_od_can_optimize_response( Closure $set_up, bool $expected ) {
		$set_up();
		$this->assertSame( $expected, od_can_optimize_response() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_provider_test_od_construct_preload_links(): array {
		return array(
			'no-lcp-image'                              => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0 => false,
				),
				'expected'                                => '',
			),
			'one-non-responsive-lcp-image'              => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0 => array(
						'img_attributes' => array(
							'src' => 'https://example.com/image.jpg',
						),
					),
				),
				'expected'                                => '
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/image.jpg" media="screen">
				',
			),
			'one-responsive-lcp-image'                  => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0 => array(
						'img_attributes' => array(
							'src'         => 'https://example.com/elva-fairy-800w.jpg',
							'srcset'      => 'https://example.com/elva-fairy-480w.jpg 480w, https://example.com/elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
				),
				'expected'                                => '
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/elva-fairy-800w.jpg" imagesrcset="https://example.com/elva-fairy-480w.jpg 480w, https://example.com/elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="screen">
				',
			),
			'two-breakpoint-responsive-lcp-images'      => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0   => array(
						'img_attributes' => array(
							'src'         => 'https://example.com/elva-fairy-800w.jpg',
							'srcset'      => 'https://example.com/elva-fairy-480w.jpg 480w, https://example.com/elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
					601 => array(
						'img_attributes' => array(
							'src'         => 'https://example.com/alt-elva-fairy-800w.jpg',
							'srcset'      => 'https://example.com/alt-elva-fairy-480w.jpg 480w, https://example.com/alt-elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
				),
				'expected'                                => '
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/elva-fairy-800w.jpg" imagesrcset="https://example.com/elva-fairy-480w.jpg 480w, https://example.com/elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="screen and (max-width: 600px)">
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/alt-elva-fairy-800w.jpg" imagesrcset="https://example.com/alt-elva-fairy-480w.jpg 480w, https://example.com/alt-elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="screen and (min-width: 601px)">
				',
			),
			'two-non-consecutive-responsive-lcp-images' => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0   => array(
						'img_attributes' => array(
							'src'         => 'https://example.com/elva-fairy-800w.jpg',
							'srcset'      => 'https://example.com/elva-fairy-480w.jpg 480w, https://example.com/elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
					481 => false,
					601 => array(
						'img_attributes' => array(
							'src'         => 'https://example.com/alt-elva-fairy-800w.jpg',
							'srcset'      => 'https://example.com/alt-elva-fairy-480w.jpg 480w, https://example.com/alt-elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
				),
				'expected'                                => '
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/elva-fairy-800w.jpg" imagesrcset="https://example.com/elva-fairy-480w.jpg 480w, https://example.com/elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="screen and (max-width: 480px)">
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/alt-elva-fairy-800w.jpg" imagesrcset="https://example.com/alt-elva-fairy-480w.jpg 480w, https://example.com/alt-elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="screen and (min-width: 601px)">
				',
			),
			'one-background-lcp-image'                  => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0 => array(
						'background_image' => 'https://example.com/image.jpg',
					),
				),
				'expected'                                => '
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/image.jpg" media="screen">
				',
			),
			'two-background-lcp-images'                 => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0   => array(
						'background_image' => 'https://example.com/mobile.jpg',
					),
					481 => array(
						'background_image' => 'https://example.com/desktop.jpg',
					),
				),
				'expected'                                => '
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile.jpg" media="screen and (max-width: 480px)">
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop.jpg" media="screen and (min-width: 481px)">
				',
			),
			'one-bg-image-one-img-element'              => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0   => array(
						'img_attributes' => array(
							'src'         => 'https://example.com/mobile-800w.jpg',
							'srcset'      => 'https://example.com/mobile-480w.jpg 480w, https://example.com/mobile-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
					481 => array(
						'background_image' => 'https://example.com/desktop.jpg',
					),
				),
				'expected'                                => '
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-800w.jpg" imagesrcset="https://example.com/mobile-480w.jpg 480w, https://example.com/mobile-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="screen and (max-width: 480px)">
					<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop.jpg" media="screen and (min-width: 481px)">
				',
			),
		);
	}

	/**
	 * Test od_construct_preload_links().
	 *
	 * @covers ::od_construct_preload_links
	 *
	 * @dataProvider data_provider_test_od_construct_preload_links
	 */
	public function test_od_construct_preload_links( array $lcp_elements_by_minimum_viewport_widths, string $expected ) {
		$this->assertSame(
			$this->normalize_whitespace( $expected ),
			$this->normalize_whitespace( od_construct_preload_links( $lcp_elements_by_minimum_viewport_widths ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		return array(
			'no-url-metrics'                              => array(
				'set_up'   => static function () {},
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
							<img data-od-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'no-url-metrics-with-data-url-background-image' => array(
				'set_up'   => static function () {},
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

			'no-url-metrics-with-data-url-image'          => array(
				'set_up'   => static function () {},
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
				'set_up'   => static function () {},
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

			'common-lcp-image-with-fully-populated-sample-data' => array(
				'set_up'   => function () {
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
											'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
											'isLCP' => true,
										),
										array(
											'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
											'isLCP' => false,
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
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/foo.jpg" rel="preload" media="screen">
						</head>
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high" data-od-added-fetchpriority data-od-removed-loading="lazy">
							<img src="https://example.com/bar.jpg" alt="Bar" width="10" height="10" loading="lazy" data-od-removed-fetchpriority="high">
						</body>
					</html>
				',
			),

			'common-lcp-image-with-stale-sample-data'     => array(
				'set_up'   => function () {
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
											'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
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
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
						</body>
					</html>
				',
			),

			'common-lcp-background-image-with-fully-populated-sample-data' => array(
				'set_up'   => function () {
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
											'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::DIV]',
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
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/foo-bg.jpg" rel="preload" media="screen">
						</head>
						<body>
							<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
						</body>
					</html>
				',
			),

			'responsive-background-images'                => array(
				'set_up'   => function () {
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
											'xpath' => "/*[0][self::HTML]/*[1][self::BODY]/*[{$div_index}][self::DIV]",
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
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/mobile-bg.jpg" media="screen and (max-width: 480px)" rel="preload">
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/tablet-bg.jpg" media="screen and (min-width: 481px) and (max-width: 600px)" rel="preload">
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/desktop-bg.jpg" media="screen and (min-width: 601px)" rel="preload">
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
				'set_up'   => function () {
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
											'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
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
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/foo.jpg" rel="preload" media="screen">
						</head>
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high" data-od-fetchpriority-already-added>
						</body>
					</html>
				',
			),

			'url-metric-only-captured-for-one-breakpoint' => array(
				'set_up'   => function () {
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							400,
							array(
								array(
									'isLCP' => true,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
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
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/foo.jpg" rel="preload" media="screen">
						</head>
						<body>
							<img data-od-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'different-lcp-elements-for-different-breakpoints' => array(
				'set_up'   => function () {
					OD_URL_Metrics_Post_Type::store_url_metric(
						od_get_url_metrics_slug( od_get_normalized_query_vars() ),
						$this->get_validated_url_metric(
							400,
							array(
								array(
									'isLCP' => true,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => true,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/mobile-logo.png" media="screen and (max-width: 782px)" rel="preload">
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/desktop-logo.png" media="screen and (min-width: 783px)" rel="preload">
						</head>
						<body>
							<img alt="Mobile Logo" data-od-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]" height="600" src="https://example.com/mobile-logo.png" width="600"/>
							<img alt="Desktop Logo" data-od-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]" height="600" src="https://example.com/desktop-logo.png" width="600"/>
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'different-lcp-elements-for-two-non-consecutive-breakpoints' => array(
				'set_up'   => function () {
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => true,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/mobile-logo.png" media="screen and (max-width: 480px)" rel="preload"/>
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/desktop-logo.png" media="screen and (min-width: 601px) and (max-width: 782px)" rel="preload"/>
						</head>
						<body>
							<img alt="Mobile Logo" data-od-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]" height="600" src="https://example.com/mobile-logo.png" width="600"/>
							<img alt="Desktop Logo" data-od-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]" height="600" src="https://example.com/desktop-logo.png" width="600"/>
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'different-lcp-elements-for-two-non-consecutive-breakpoints-and-one-is-stale' => array(
				'set_up'   => function () {
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => true,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
								),
								array(
									'isLCP' => false,
									'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]',
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
							<img src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
						</body>
					</html>
				',
				'expected' => '
					<html lang="en">
						<head>
							<meta charset="utf-8">
							<title>...</title>
							<link as="image" data-od-added-tag="" fetchpriority="high" href="https://example.com/mobile-logo.png" media="screen and (min-width: 481px) and (max-width: 600px)" rel="preload"/>
						</head>
						<body>
							<img alt="Mobile Logo" data-od-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]" height="600" src="https://example.com/mobile-logo.png" width="600"/>
							<p>New paragraph since URL Metrics were captured!</p>
							<img alt="Desktop Logo" data-od-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[2][self::IMG]" height="600" src="https://example.com/desktop-logo.png" width="600"/>
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),
		);
	}

	/**
	 * Test od_optimize_template_output_buffer().
	 *
	 * @covers ::od_optimize_template_output_buffer
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 */
	public function test_od_optimize_template_output_buffer( Closure $set_up, string $buffer, string $expected ) {
		$set_up();

		// Simulate wp_print_footer_scripts().
		$buffer = preg_replace( ':(?=</body>):', get_echo( 'od_print_detection_script_placeholder' ), $buffer );

		$this->assertEquals(
			$this->parse_html_document( $expected ),
			$this->parse_html_document( od_optimize_template_output_buffer( $buffer ) )
		);
	}

	/**
	 * Normalizes whitespace.
	 *
	 * @param string $str String to normalize.
	 * @return string Normalized string.
	 */
	private function normalize_whitespace( string $str ): string {
		return preg_replace( '/\s+/', ' ', trim( $str ) );
	}

	/**
	 * Gets a validated URL metric.
	 *
	 * @param int $viewport_width Viewport width for the URL metric.
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
							'isLCPCandidate'    => true,
							'intersectionRatio' => 1,
						),
						$element
					);
				},
				$elements
			),
		);
		return new OD_URL_Metric( $data );
	}

	/**
	 * Parse an HTML markup fragment and normalize for comparison.
	 *
	 * @param string $markup Markup.
	 * @return DOMDocument Document containing the normalized markup fragment.
	 */
	protected function parse_html_document( string $markup ): DOMDocument {
		$dom = new DOMDocument();
		$dom->loadHTML( trim( $markup ) );

		// Remove all whitespace nodes.
		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//text()' ) as $node ) {
			/** @var DOMText $node */
			if ( preg_match( '/^\s+$/', $node->nodeValue ) ) {
				$node->nodeValue = '';
			}
		}

		// Insert a newline before each node to make the diff easier to read.
		foreach ( $xpath->query( '/html//*' ) as $node ) {
			/** @var DOMElement $node */
			$node->parentNode->insertBefore( $dom->createTextNode( "\n" ), $node );
		}

		// Normalize contents of module script output by od_get_detection_script().
		foreach ( $xpath->query( '//script[ contains( text(), "import detect" ) ]' ) as $script ) {
			$script->textContent = '/* import detect ... */';
		}

		return $dom;
	}
}
