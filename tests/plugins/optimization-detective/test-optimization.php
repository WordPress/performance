<?php
/**
 * Tests for optimization-detective plugin optimization.php.
 *
 * @package optimization-detective
 *
 * @todo There are "Cannot resolve ..." errors and "Element img doesn't have a required attribute src" warnings that should be excluded from inspection.
 */

class Test_OD_Optimization extends WP_UnitTestCase {

	/**
	 * @var string
	 */
	private $original_request_uri;

	/**
	 * @var string
	 */
	private $original_request_method;

	/**
	 * @var string
	 */
	private $default_mimetype;

	public function set_up(): void {
		$this->original_request_uri    = $_SERVER['REQUEST_URI'];
		$this->original_request_method = $_SERVER['REQUEST_METHOD'];
		$this->default_mimetype        = (string) ini_get( 'default_mimetype' );
		parent::set_up();
	}

	public function tear_down(): void {
		$_SERVER['REQUEST_URI']    = $this->original_request_uri;
		$_SERVER['REQUEST_METHOD'] = $this->original_request_method;
		ini_set( 'default_mimetype', $this->default_mimetype ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		unset( $GLOBALS['wp_customize'] );
		parent::tear_down();
	}

	/**
	 * Make output is buffered and that it is also filtered.
	 *
	 * @covers ::od_buffer_output
	 */
	public function test_od_buffer_output(): void {
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
	public function test_od_maybe_add_template_output_buffer_filter(): void {
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
	 * Test od_maybe_add_template_output_buffer_filter().
	 *
	 * @covers ::od_maybe_add_template_output_buffer_filter
	 */
	public function test_od_maybe_add_template_output_buffer_filter_with_query_var_to_disable(): void {
		$this->assertFalse( has_filter( 'od_template_output_buffer' ) );

		add_filter( 'od_can_optimize_response', '__return_true' );
		$this->go_to( home_url( '/?optimization_detective_disabled=1' ) );
		$this->assertTrue( od_can_optimize_response() );
		od_maybe_add_template_output_buffer_filter();
		$this->assertFalse( has_filter( 'od_template_output_buffer' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_can_optimize_response(): array {
		return array(
			'homepage'           => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
				},
				'expected' => true,
			),
			'homepage_filtered'  => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
					add_filter( 'od_can_optimize_response', '__return_false' );
				},
				'expected' => false,
			),
			'search'             => array(
				'set_up'   => function (): void {
					self::factory()->post->create( array( 'post_title' => 'Hello' ) );
					$this->go_to( home_url( '?s=Hello' ) );
				},
				'expected' => false,
			),
			'customizer_preview' => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
					global $wp_customize;
					require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
					$wp_customize = new WP_Customize_Manager();
					$wp_customize->start_previewing_theme();
				},
				'expected' => false,
			),
			'post_request'       => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
					$_SERVER['REQUEST_METHOD'] = 'POST';
				},
				'expected' => false,
			),
			'subscriber_user'    => array(
				'set_up'   => function (): void {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
					$this->go_to( home_url( '/' ) );
				},
				'expected' => true,
			),
			'admin_user'         => array(
				'set_up'   => function (): void {
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
	public function test_od_can_optimize_response( Closure $set_up, bool $expected ): void {
		$set_up();
		$this->assertSame( $expected, od_can_optimize_response() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		return array(
			'no-url-metrics'       => array(
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
							<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
							<script type="module">/* import detect ... */</script>
						</body>
					</html>
				',
			),

			'complete-url-metrics' => array(
				'set_up'   => function (): void {
					ini_set( 'default_mimetype', 'text/html; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky

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
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
						</body>
					</html>
				',
			),

			'rss-response'         => array(
				'set_up'   => static function (): void {
					ini_set( 'default_mimetype', 'application/rss+xml' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
				},
				'buffer'   => '<?xml version="1.0" encoding="UTF-8"?>
					<rss version="2.0">
						<channel>
							<title>Example Blog</title>
							<link>https://www.example.com</link>
							<description>
								<img src="https://www.example.com/logo.jpg" alt="Example Blog Logo" />
								A blog about technology, design, and culture.
							</description>
							<language>en-us</language>
						</channel>
					</rss>
				',
				'expected' => '<?xml version="1.0" encoding="UTF-8"?>
					<rss version="2.0">
						<channel>
							<title>Example Blog</title>
							<link>https://www.example.com</link>
							<description>
								<img src="https://www.example.com/logo.jpg" alt="Example Blog Logo" />
								A blog about technology, design, and culture.
							</description>
							<language>en-us</language>
						</channel>
					</rss>
				',
			),

			'xhtml-response'       => array(
				'set_up'   => static function (): void {
					ini_set( 'default_mimetype', 'application/xhtml+xml; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
				},
				'buffer'   => '<?xml version="1.0" encoding="UTF-8"?>
					<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
					<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
						<head>
						  <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
						  <title>XHTML 1.0 Strict Example</title>
						</head>
						<body>
							<p><img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" /></p>
						</body>
					</html>
				',
				'expected' => '<?xml version="1.0" encoding="UTF-8"?>
					<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
					<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
						<head>
						  <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
						  <title>XHTML 1.0 Strict Example</title>
						</head>
						<body>
							<p><img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::P]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" /></p>
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
	 * @covers ::od_is_response_html_content_type
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 * @throws Exception But it won't.
	 */
	public function test_od_optimize_template_output_buffer( Closure $set_up, string $buffer, string $expected ): void {
		$set_up();

		$remove_initial_tabs = static function ( string $input ): string {
			return (string) preg_replace( '/^\t+/m', '', $input );
		};

		add_action(
			'od_register_tag_visitors',
			function ( OD_Tag_Visitor_Registry $tag_visitor_registry, OD_URL_Metrics_Group_Collection $url_metrics_outer, OD_Preload_Link_Collection $preload_links_outer ): void {
				$tag_visitor_registry->register(
					'img',
					function ( OD_HTML_Tag_Walker $walker, OD_URL_Metrics_Group_Collection $url_metrics, OD_Preload_Link_Collection $preload_links ) use ( $url_metrics_outer, $preload_links_outer ): bool {
						$this->assertSame( $url_metrics, $url_metrics_outer );
						$this->assertSame( $preload_links, $preload_links_outer );
						return $walker->get_tag() === 'IMG';
					}
				);
			},
			10,
			3
		);

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
