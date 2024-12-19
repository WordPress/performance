<?php
/**
 * Tests for optimization-detective plugin helper.php.
 *
 * @package optimization-detective
 */

class Test_OD_Helper extends WP_UnitTestCase {

	/**
	 * @covers ::od_initialize_extensions
	 */
	public function test_od_initialize_extensions(): void {
		unset( $GLOBALS['wp_actions']['od_init'] );
		$passed_version = null;
		add_action(
			'od_init',
			static function ( string $version ) use ( &$passed_version ): void {
				$passed_version = $version;
			}
		);
		od_initialize_extensions();
		$this->assertSame( 1, did_action( 'od_init' ) );
		$this->assertSame( OPTIMIZATION_DETECTIVE_VERSION, $passed_version );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function data_to_test_od_generate_media_query(): array {
		return array(
			'mobile'      => array(
				'min_width' => 0,
				'max_width' => 320,
				'expected'  => '(max-width: 320px)',
			),
			'mobile_alt'  => array(
				'min_width' => null,
				'max_width' => 320,
				'expected'  => '(max-width: 320px)',
			),
			'tablet'      => array(
				'min_width' => 321,
				'max_width' => 600,
				'expected'  => '(min-width: 321px) and (max-width: 600px)',
			),
			'desktop'     => array(
				'min_width' => 601,
				'max_width' => PHP_INT_MAX,
				'expected'  => '(min-width: 601px)',
			),
			'desktop_alt' => array(
				'min_width' => 601,
				'max_width' => null,
				'expected'  => '(min-width: 601px)',
			),
			'no_widths'   => array(
				'min_width' => null,
				'max_width' => null,
				'expected'  => null,
			),
			'bad_widths'  => array(
				'min_width'       => 1000,
				'max_width'       => 10,
				'expected'        => null,
				'incorrect_usage' => 'od_generate_media_query',
			),
		);
	}

	/**
	 * Test generating media query.
	 *
	 * @dataProvider data_to_test_od_generate_media_query
	 * @covers ::od_generate_media_query
	 */
	public function test_od_generate_media_query( ?int $min_width, ?int $max_width, ?string $expected, ?string $incorrect_usage = null ): void {
		if ( null !== $incorrect_usage ) {
			$this->setExpectedIncorrectUsage( $incorrect_usage );
		}
		$this->assertSame( $expected, od_generate_media_query( $min_width, $max_width ) );
	}

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::od_render_generator_meta_tag
	 */
	public function test_od_render_generator_meta_tag(): void {
		$tag = get_echo( 'od_render_generator_meta_tag' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'optimization-detective ' . OPTIMIZATION_DETECTIVE_VERSION, $tag );
	}
}
