<?php
/**
 * Tests for image-loading-optimization module hooks.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class Image_Loading_Optimization_Hooks_Tests extends WP_UnitTestCase {

	/**
	 * Make sure the hook is added.
	 *
	 * @test
	 */
	public function it_is_hooking_output_buffering_at_template_include() {
		$this->assertEquals( PHP_INT_MAX, has_filter( 'template_include', 'ilo_buffer_output' ) );
	}

	/**
	 * Make output is buffered and that it is also filtered.
	 *
	 * @test
	 * @covers ::ilo_buffer_output
	 */
	public function it_buffers_and_filters_output() {
		$original = 'Hello World!';
		$expected = '¡Hola Mundo!';

		// In order to test, a wrapping output buffer is required because ob_get_clean() does not invoke the output
		// buffer callback. See <https://stackoverflow.com/a/61439514/93579>.
		ob_start();

		add_filter(
			'ilo_template_output_buffer',
			function ( $buffer ) use ( $original, $expected ) {
				$this->assertSame( $original, $buffer );
				return $expected;
			}
		);

		$original_ob_level = ob_get_level();
		ilo_buffer_output( '' );
		$this->assertSame( $original_ob_level + 1, ob_get_level(), 'Expected call to ob_start().' );
		echo $original;

		ob_end_flush(); // Flushing invokes the output buffer callback.

		$buffer = ob_get_clean(); // Get the buffer from our wrapper output buffer.
		$this->assertSame( $expected, $buffer );
	}
}
