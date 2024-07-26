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
	 * @throws Exception But it won't.
	 */
	public function test_image_prioritizer_register_tag_visitors( Closure $set_up, string $buffer, string $expected ): void {
		$set_up( $this );

		$remove_initial_tabs = static function ( string $input ): string {
			return (string) preg_replace( '/^\t+/m', '', $input );
		};

		$buffer = preg_replace(
			':<script type="module">.+?</script>:s',
			'<script type="module">/* import detect ... */</script>',
			od_optimize_template_output_buffer( $buffer )
		);

		$this->assertEquals(
			$remove_initial_tabs( $expected ),
			$remove_initial_tabs( $buffer ),
			"Buffer snapshot:\n$buffer"
		);
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
	public function get_validated_url_metric( int $viewport_width, array $elements = array() ): OD_URL_Metric {
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
