<?php
/**
 * Tests for optimization-detective module hooks.php.
 *
 * @packageoptimization-detective
 */

class OD_Hooks_Tests extends WP_UnitTestCase {

	/**
	 * Make sure the hook is added.
	 */
	public function test_hooking_output_buffering_at_template_include() {
		$this->assertEquals( PHP_INT_MAX, has_filter( 'template_include', 'od_buffer_output' ) );
	}

	/**
	 * Make output is buffered and that it is also filtered.
	 *
	 * @covers ::od_buffer_output
	 */
	public function test_buffering_and_filtering_output() {
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
}
