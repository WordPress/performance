<?php
/**
 * Tests for auto-sizes plugin's optimization-detective.php.
 *
 * @package auto-sizes
 */

class Test_Auto_Sizes_Optimization_Detective extends WP_UnitTestCase {
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
	 * Tests auto_sizes_register_tag_visitors().
	 *
	 * @covers ::auto_sizes_register_tag_visitors
	 */
	public function test_auto_sizes_register_tag_visitors(): void {
		if ( ! class_exists( OD_Tag_Visitor_Registry::class ) ) {
			$this->markTestSkipped( 'Optimization Detective is not active.' );
		}
		$registry = new OD_Tag_Visitor_Registry();
		auto_sizes_register_tag_visitors( $registry );
		$this->assertTrue( $registry->is_registered( 'auto-sizes' ) );
		$this->assertEquals( 'auto_sizes_visit_tag', $registry->get_registered( 'auto-sizes' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		return array(
			// Note: The Image Prioritizer plugin removes the loading attribute, and so then Auto Sizes does not then add sizes=auto.
			'wrongly_lazy_responsive_img' => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 1,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
				'expected'        => '<img data-od-removed-loading="lazy" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
			),

			'non_responsive_image'        => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 0,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Quux" width="1200" height="800" loading="lazy">',
				'expected'        => '<img src="https://example.com/foo.jpg" alt="Quux" width="1200" height="800" loading="lazy">',
			),

			'auto_sizes_added'            => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 0,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
				'expected'        => '<img data-od-replaced-sizes="(max-width: 600px) 480px, 800px" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
			),

			'auto_sizes_already_added'    => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 0,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
				'expected'        => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
			),
		);
	}

	/**
	 * Test auto_sizes_visit_tag().
	 *
	 * @covers ::auto_sizes_visit_tag
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 * @throws Exception But it won't.
	 * @phpstan-param array<string, mixed> $element_metrics
	 */
	public function test_od_optimize_template_output_buffer( array $element_metrics, string $buffer, string $expected ): void {
		$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = od_get_url_metrics_breakpoint_sample_size();
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$this->get_validated_url_metric(
						$viewport_width,
						array(
							$element_metrics,
						)
					)
				);
			}
		}

		$remove_initial_tabs = static function ( string $input ): string {
			return (string) preg_replace( '/^\t+/m', '', $input );
		};

		$html_start_doc = '<html lang="en"><head><meta charset="utf-8"><title>...</title></head><body>';
		$html_end_doc   = '</body></html>';

		$expected = $remove_initial_tabs( $expected );
		$buffer   = $remove_initial_tabs( $buffer );

		$buffer = od_optimize_template_output_buffer( $html_start_doc . $buffer . $html_end_doc );
		$buffer = preg_replace( '#.+?<body[^>]*>#s', '', $buffer );
		$buffer = preg_replace( '#</body>.*$#s', '', $buffer );

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
