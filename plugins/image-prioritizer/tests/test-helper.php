<?php
/**
 * Tests for image-prioritizer plugin helper.php.
 *
 * @package image-prioritizer
 *
 * @noinspection PhpUnhandledExceptionInspection
 */

class Test_Image_Prioritizer_Helper extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function data_provider_to_test_image_prioritizer_init(): array {
		return array(
			'with_old_version' => array(
				'version'  => '0.5.0',
				'expected' => false,
			),
			'with_new_version' => array(
				'version'  => '0.7.0',
				'expected' => true,
			),
		);
	}

	/**
	 * @covers ::image_prioritizer_init
	 * @dataProvider data_provider_to_test_image_prioritizer_init
	 */
	public function test_image_prioritizer_init( string $version, bool $expected ): void {
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'od_register_tag_visitors' );

		image_prioritizer_init( $version );

		$this->assertSame( ! $expected, has_action( 'admin_notices' ) );
		$this->assertSame( $expected ? 10 : false, has_action( 'wp_head', 'image_prioritizer_render_generator_meta_tag' ) );
		$this->assertSame( $expected ? 10 : false, has_action( 'od_register_tag_visitors', 'image_prioritizer_register_tag_visitors' ) );
	}

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
	public function data_provider_test_filter_tag_visitors(): array {
		$test_cases = array();
		foreach ( (array) glob( __DIR__ . '/test-cases/*.php' ) as $test_case ) {
			$name                = basename( $test_case, '.php' );
			$test_cases[ $name ] = require $test_case;
		}
		return $test_cases;
	}

	/**
	 * Test image_prioritizer_register_tag_visitors().
	 *
	 * @covers ::image_prioritizer_register_tag_visitors
	 * @covers Image_Prioritizer_Tag_Visitor
	 * @covers Image_Prioritizer_Img_Tag_Visitor
	 * @covers Image_Prioritizer_Background_Image_Styled_Tag_Visitor
	 *
	 * @dataProvider data_provider_test_filter_tag_visitors
	 *
	 * @param callable        $set_up   Setup function.
	 * @param callable|string $buffer   Content before.
	 * @param callable|string $expected Expected content after.
	 */
	public function test_image_prioritizer_register_tag_visitors( callable $set_up, $buffer, $expected ): void {
		$set_up( $this, $this::factory() );

		$buffer = is_string( $buffer ) ? $buffer : $buffer();
		$buffer = preg_replace(
			':<script type="module">.+?</script>:s',
			'<script type="module">/* import detect ... */</script>',
			od_optimize_template_output_buffer( $buffer )
		);

		$expected = is_string( $expected ) ? $expected : $expected();

		$this->assertEquals(
			$this->remove_initial_tabs( $expected ),
			$this->remove_initial_tabs( $buffer ),
			"Buffer snapshot:\n$buffer"
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_auto_sizes(): array {
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

			'wrongly_auto_sized_responsive_img_with_only_auto' => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 1,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto">',
				'expected'        => '<img data-od-removed-loading="lazy" data-od-replaced-sizes="auto" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="">',
			),
		);
	}

	/**
	 * Test auto sizes.
	 *
	 * @covers Image_Prioritizer_Img_Tag_Visitor::__invoke
	 *
	 * @dataProvider data_provider_test_auto_sizes
	 * @phpstan-param array{ xpath: string, isLCP: bool, intersectionRatio: int } $element_metrics
	 */
	public function test_auto_sizes( array $element_metrics, string $buffer, string $expected ): void {
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
