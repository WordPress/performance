<?php
/**
 * Tests for image-loading-optimization module optimization.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class Image_Loading_Optimization_Optimization_Tests extends WP_UnitTestCase {

	/**
	 * Test ilo_maybe_add_template_output_buffer_filter().
	 *
	 * @test
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
	 * @test
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
	 * Test ilo_optimize_template_output_buffer().
	 *
	 * @test
	 * @covers ::ilo_optimize_template_output_buffer
	 */
	public function test_ilo_optimize_template_output_buffer() {
		$this->markTestIncomplete();
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
}
