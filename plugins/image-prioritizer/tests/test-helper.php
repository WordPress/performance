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
	 */
	public function test_image_prioritizer_register_tag_visitors( Closure $set_up, string $buffer, string $expected ): void {
		$set_up( $this );

		$buffer = preg_replace(
			':<script type="module">.+?</script>:s',
			'<script type="module">/* import detect ... */</script>',
			od_optimize_template_output_buffer( $buffer )
		);

		$this->assertEquals(
			$this->remove_initial_tabs( $expected ),
			$this->remove_initial_tabs( $buffer ),
			"Buffer snapshot:\n$buffer"
		);
	}
}
