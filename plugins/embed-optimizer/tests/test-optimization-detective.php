<?php
/**
 * Tests for embed-optimizer plugin hooks.php.
 *
 * @package embed-optimizer
 *
 * @noinspection PhpUnhandledExceptionInspection
 */

class Test_Embed_Optimizer_Optimization_Detective extends WP_UnitTestCase {
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
	 * Tests embed_optimizer_register_tag_visitors().
	 *
	 * @covers ::embed_optimizer_register_tag_visitors
	 */
	public function test_embed_optimizer_register_tag_visitors(): void {
		$registry = new OD_Tag_Visitor_Registry();
		embed_optimizer_register_tag_visitors( $registry );
		$this->assertTrue( $registry->is_registered( 'embeds' ) );
		$this->assertInstanceOf( Embed_Optimizer_Tag_Visitor::class, $registry->get_registered( 'embeds' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		$test_cases = array();
		foreach ( (array) glob( __DIR__ . '/test-cases/*.php' ) as $test_case ) {
			$name                = basename( $test_case, '.php' );
			$test_cases[ $name ] = require $test_case;
		}
		return $test_cases;
	}

	/**
	 * Test embed_optimizer_visit_tag().
	 *
	 * @covers Embed_Optimizer_Tag_Visitor
	 * @covers ::embed_optimizer_update_markup
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 */
	public function test_od_optimize_template_output_buffer( Closure $set_up, string $buffer, string $expected ): void {
		$set_up( $this );

		$buffer = od_optimize_template_output_buffer( $buffer );
		$buffer = preg_replace_callback(
			':(<script type="module">)(.+?)(</script>):s',
			static function ( $matches ) {
				array_shift( $matches );
				if ( false !== strpos( $matches[1], 'import detect' ) ) {
					$matches[1] = '/* import detect ... */';
				} elseif ( false !== strpos( $matches[1], 'const lazyEmbedsScripts' ) ) {
					$matches[1] = '/* const lazyEmbedsScripts ... */';
				}
				return implode( '', $matches );
			},
			$buffer
		);
		$this->assertEquals(
			$this->remove_initial_tabs( $expected ),
			$this->remove_initial_tabs( $buffer ),
			"Buffer snapshot:\n$buffer"
		);
	}
}
