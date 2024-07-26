<?php
/**
 * Tests for auto-sizes plugin's optimization-detective.php.
 *
 * @package auto-sizes
 */

require_once __DIR__ . '/../../../tests/class-optimization-detective-test-helpers.php';

class Test_Auto_Sizes_Optimization_Detective extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

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
			'wrongly_lazy_responsive_img'       => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 1,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
				'expected'        => '<img data-od-removed-loading="lazy" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
			),

			'non_responsive_image'              => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 0,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Quux" width="1200" height="800" loading="lazy">',
				'expected'        => '<img src="https://example.com/foo.jpg" alt="Quux" width="1200" height="800" loading="lazy">',
			),

			'auto_sizes_added'                  => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 0,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
				'expected'        => '<img data-od-replaced-sizes="(max-width: 600px) 480px, 800px" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
			),

			'auto_sizes_already_added'          => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 0,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
				'expected'        => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
			),

			// If Auto Sizes added the sizes=auto attribute but Image Prioritizer ended up removing it due to the image not being lazy-loaded, remove sizes=auto again.
			'wrongly_auto_sized_responsive_img' => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 1,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
				'expected'        => '<img data-od-removed-loading="lazy" data-od-replaced-sizes="auto, (max-width: 600px) 480px, 800px" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
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
		$this->populate_url_metrics( array( $element_metrics ) );

		$html_start_doc = '<html lang="en"><head><meta charset="utf-8"><title>...</title></head><body>';
		$html_end_doc   = '</body></html>';

		$buffer = od_optimize_template_output_buffer( $html_start_doc . $buffer . $html_end_doc );
		$buffer = preg_replace( '#.+?<body[^>]*>#s', '', $buffer );
		$buffer = preg_replace( '#</body>.*$#s', '', $buffer );

		$this->assertEquals(
			$this->remove_initial_tabs( $expected ),
			$this->remove_initial_tabs( $buffer ),
			"Buffer snapshot:\n$buffer"
		);
	}
}
