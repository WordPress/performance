<?php
/**
 * Tests for image-loading-optimization module optimization.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class ILO_Optimization_Tests extends WP_UnitTestCase {

	public function tear_down() {
		parent::tear_down();
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test ilo_maybe_add_template_output_buffer_filter().
	 *
	 * @covers ::ilo_maybe_add_template_output_buffer_filter
	 */
	public function test_ilo_maybe_add_template_output_buffer_filter() {
		$this->assertFalse( has_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' ) );

		add_filter( 'ilo_can_optimize_response', '__return_false', 1 );
		ilo_maybe_add_template_output_buffer_filter();
		$this->assertFalse( ilo_can_optimize_response() );
		$this->assertFalse( has_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' ) );

		add_filter( 'ilo_can_optimize_response', '__return_true', 2 );
		$this->go_to( home_url( '/' ) );
		$this->assertTrue( ilo_can_optimize_response() );
		ilo_maybe_add_template_output_buffer_filter();
		$this->assertSame( 10, has_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_provider_test_ilo_can_optimize_response(): array {
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
					add_filter( 'ilo_can_optimize_response', '__return_false' );
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
					/** @noinspection PhpIncludeInspection */
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
		);
	}

	/**
	 * Test ilo_can_optimize_response().
	 *
	 * @covers ::ilo_can_optimize_response
	 * @dataProvider data_provider_test_ilo_can_optimize_response
	 */
	public function test_ilo_can_optimize_response( Closure $set_up, bool $expected ) {
		$set_up();
		$this->assertSame( $expected, ilo_can_optimize_response() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_provider_test_ilo_construct_preload_links(): array {
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
						'attributes' => array(
							'src' => 'https://example.com/image.jpg',
						),
					),
				),
				'expected'                                => '
					<link data-ilo-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/image.jpg">
				',
			),
			'one-responsive-lcp-image'                  => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0 => array(
						'attributes' => array(
							'src'         => 'elva-fairy-800w.jpg',
							'srcset'      => 'elva-fairy-480w.jpg 480w, elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
				),
				'expected'                                => '
					<link data-ilo-added-tag rel="preload" fetchpriority="high" as="image" imagesrcset="elva-fairy-480w.jpg 480w, elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous">
				',
			),
			'two-breakpoint-responsive-lcp-images'      => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0   => array(
						'attributes' => array(
							'src'         => 'elva-fairy-800w.jpg',
							'srcset'      => 'elva-fairy-480w.jpg 480w, elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
					601 => array(
						'attributes' => array(
							'src'         => 'alt-elva-fairy-800w.jpg',
							'srcset'      => 'alt-elva-fairy-480w.jpg 480w, alt-elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
				),
				'expected'                                => '
					<link data-ilo-added-tag rel="preload" fetchpriority="high" as="image" imagesrcset="elva-fairy-480w.jpg 480w, elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="( min-width: 0px ) and ( max-width: 600px )">
					<link data-ilo-added-tag rel="preload" fetchpriority="high" as="image" imagesrcset="alt-elva-fairy-480w.jpg 480w, alt-elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="( min-width: 601px )">
				',
			),
			'two-non-consecutive-responsive-lcp-images' => array(
				'lcp_elements_by_minimum_viewport_widths' => array(
					0   => array(
						'attributes' => array(
							'src'         => 'elva-fairy-800w.jpg',
							'srcset'      => 'elva-fairy-480w.jpg 480w, elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
					481 => false,
					601 => array(
						'attributes' => array(
							'src'         => 'alt-elva-fairy-800w.jpg',
							'srcset'      => 'alt-elva-fairy-480w.jpg 480w, alt-elva-fairy-800w.jpg 800w',
							'sizes'       => '(max-width: 600px) 480px, 800px',
							'crossorigin' => 'anonymous',
						),
					),
				),
				'expected'                                => '
					<link data-ilo-added-tag rel="preload" fetchpriority="high" as="image" imagesrcset="elva-fairy-480w.jpg 480w, elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="( min-width: 0px ) and ( max-width: 480px )">
					<link data-ilo-added-tag rel="preload" fetchpriority="high" as="image" imagesrcset="alt-elva-fairy-480w.jpg 480w, alt-elva-fairy-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="( min-width: 601px )">
				',
			),
		);
	}

	/**
	 * Test ilo_construct_preload_links().
	 *
	 * @covers ::ilo_construct_preload_links
	 * @dataProvider data_provider_test_ilo_construct_preload_links
	 */
	public function test_ilo_construct_preload_links( array $lcp_elements_by_minimum_viewport_widths, string $expected ) {
		$this->assertSame(
			$this->normalize_whitespace( $expected ),
			$this->normalize_whitespace( ilo_construct_preload_links( $lcp_elements_by_minimum_viewport_widths ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_provider_test_ilo_optimize_template_output_buffer(): array {
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
							<script type="module">/* import detect ... */</script>
						</head>
						<body>
							<img data-ilo-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
						</body>
					</html>
				',
			),

			'common-lcp-image-with-fully-populated-sample-data' => array(
				'set_up'   => function () {
					$slug = ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() );
					$sample_size = ilo_get_url_metrics_breakpoint_sample_size();
					foreach ( array_merge( ilo_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
						for ( $i = 0; $i < $sample_size; $i++ ) {
							ilo_store_url_metric(
								home_url( '/' ),
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
							<link as="image" data-ilo-added-tag="" fetchpriority="high" href="https://example.com/foo.jpg" rel="preload"/>
						</head>
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high" data-ilo-added-fetchpriority data-ilo-removed-loading="lazy">
							<img src="https://example.com/bar.jpg" alt="Bar" width="10" height="10" loading="lazy" data-ilo-removed-fetchpriority="high">
						</body>
					</html>
				',
			),

			'fetch-priority-high-already-on-common-lcp-image-with-fully-populated-sample-data' => array(
				'set_up'   => function () {
					$slug = ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() );
					$sample_size = ilo_get_url_metrics_breakpoint_sample_size();
					foreach ( array_merge( ilo_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
						for ( $i = 0; $i < $sample_size; $i++ ) {
							ilo_store_url_metric(
								home_url( '/' ),
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
							<link as="image" data-ilo-added-tag="" fetchpriority="high" href="https://example.com/foo.jpg" rel="preload"/>
						</head>
						<body>
							<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high" data-ilo-fetchpriority-already-added>
						</body>
					</html>
				',
			),

			'url-metric-only-captured-for-one-breakpoint' => array(
				'set_up'   => function () {
					ilo_store_url_metric(
						home_url( '/' ),
						ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() ),
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
							<link as="image" data-ilo-added-tag="" fetchpriority="high" href="https://example.com/foo.jpg" rel="preload"/>
							<script type="module">/* import detect ... */</script>
						</head>
						<body>
							<img data-ilo-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
						</body>
					</html>
				',
			),

			'different-lcp-elements-for-different-breakpoints' => array(
				'set_up'   => function () {
					ilo_store_url_metric(
						home_url( '/' ),
						ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() ),
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
					ilo_store_url_metric(
						home_url( '/' ),
						ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() ),
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
							<link as="image" data-ilo-added-tag="" fetchpriority="high" href="https://example.com/mobile-logo.png" media="( min-width: 0px ) and ( max-width: 782px )" rel="preload">
							<link as="image" data-ilo-added-tag="" fetchpriority="high" href="https://example.com/desktop-logo.png" media="( min-width: 783px )" rel="preload">
							<script type="module">/* import detect ... */</script>
						</head>
						<body>
							<img alt="Mobile Logo" data-ilo-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]" height="600" src="https://example.com/mobile-logo.png" width="600"/>
							<img alt="Desktop Logo" data-ilo-xpath="/*[0][self::HTML]/*[1][self::BODY]/*[1][self::IMG]" height="600" src="https://example.com/desktop-logo.png" width="600"/>
						</body>
					</html>
				',
			),

		);
	}

	/**
	 * Test ilo_optimize_template_output_buffer().
	 *
	 * @covers ::ilo_optimize_template_output_buffer
	 * @dataProvider data_provider_test_ilo_optimize_template_output_buffer
	 */
	public function test_ilo_optimize_template_output_buffer( Closure $set_up, string $buffer, string $expected ) {
		$set_up();
		$this->assertEquals(
			$this->parse_html_document( $expected ),
			$this->parse_html_document( ilo_optimize_template_output_buffer( $buffer ) )
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
	 * @return array URL metric.
	 */
	private function get_validated_url_metric( int $viewport_width, array $elements = array() ): array {
		return array(
			'viewport' => array(
				'width'  => $viewport_width,
				'height' => 800,
			),
			'elements' => array_map(
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

		// Normalize contents of module script output by ilo_get_detection_script().
		foreach ( $xpath->query( '//script[ contains( text(), "import detect" ) ]' ) as $script ) {
			$script->textContent = '/* import detect ... */';
		}

		return $dom;
	}
}
