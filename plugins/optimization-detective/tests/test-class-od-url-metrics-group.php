<?php
/**
 * Tests for OD_URL_Metrics_Group.
 *
 * @package optimization-detective
 *
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpDocMissingThrowsInspection
 *
 * @coversDefaultClass OD_URL_Metrics_Group
 */

class Test_OD_URL_Metrics_Group extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_construction(): array {
		return array(
			'bad_minimum_viewport_width'       => array(
				'url_metrics'            => array(),
				'minimum_viewport_width' => -1,
				'maximum_viewport_width' => 100,
				'sample_size'            => 3,
				'freshness_ttl'          => HOUR_IN_SECONDS,
				'exception'              => InvalidArgumentException::class,
			),
			'bad_maximum_viewport_width'       => array(
				'url_metrics'            => array(),
				'minimum_viewport_width' => 0,
				'maximum_viewport_width' => -1,
				'sample_size'            => 3,
				'freshness_ttl'          => HOUR_IN_SECONDS,
				'exception'              => InvalidArgumentException::class,
			),
			'bad_min_max_viewport_width'       => array(
				'url_metrics'            => array(),
				'minimum_viewport_width' => 200,
				'maximum_viewport_width' => 100,
				'sample_size'            => 3,
				'freshness_ttl'          => HOUR_IN_SECONDS,
				'exception'              => InvalidArgumentException::class,
			),
			'bad_sample_size_viewport_width'   => array(
				'url_metrics'            => array(),
				'minimum_viewport_width' => 0,
				'maximum_viewport_width' => 100,
				'sample_size'            => -3,
				'freshness_ttl'          => HOUR_IN_SECONDS,
				'exception'              => InvalidArgumentException::class,
			),
			'bad_freshness_ttl_viewport_width' => array(
				'url_metrics'            => array(),
				'minimum_viewport_width' => 0,
				'maximum_viewport_width' => 100,
				'sample_size'            => 3,
				'freshness_ttl'          => -HOUR_IN_SECONDS,
				'exception'              => InvalidArgumentException::class,
			),
			'good_empty_url_metrics'           => array(
				'url_metrics'            => array(),
				'minimum_viewport_width' => 0,
				'maximum_viewport_width' => 100,
				'sample_size'            => 3,
				'freshness_ttl'          => HOUR_IN_SECONDS,
				'exception'              => '',
			),
			'good_one_url_metric'              => array(
				'url_metrics'            => array(
					new OD_URL_Metric(
						array(
							'url'       => home_url( '/' ),
							'viewport'  => array(
								'width'  => 1,
								'height' => 2,
							),
							'timestamp' => microtime( true ),
							'elements'  => array(),
						)
					),
				),
				'minimum_viewport_width' => 0,
				'maximum_viewport_width' => 100,
				'sample_size'            => 3,
				'freshness_ttl'          => HOUR_IN_SECONDS,
				'exception'              => '',
			),
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::get_minimum_viewport_width
	 * @covers ::get_maximum_viewport_width
	 * @covers ::getIterator
	 * @covers ::count
	 *
	 * @dataProvider data_provider_test_construction
	 *
	 * @param OD_URL_Metric[] $url_metrics URL Metrics.
	 * @param int             $minimum_viewport_width Minimum viewport width.
	 * @param int             $maximum_viewport_width Maximum viewport width.
	 * @param int             $sample_size Sample size.
	 * @param int             $freshness_ttl Freshness TTL.
	 * @param string          $exception Expected exception.
	 */
	public function test_construction( array $url_metrics, int $minimum_viewport_width, int $maximum_viewport_width, int $sample_size, int $freshness_ttl, string $exception ): void {
		if ( '' !== $exception ) {
			$this->expectException( $exception );
		}
		$group = new OD_URL_Metrics_Group( $url_metrics, $minimum_viewport_width, $maximum_viewport_width, $sample_size, $freshness_ttl );

		$this->assertCount( count( $url_metrics ), $group );
		$this->assertSame( $minimum_viewport_width, $group->get_minimum_viewport_width() );
		$this->assertSame( $maximum_viewport_width, $group->get_maximum_viewport_width() );
		$this->assertCount( count( $url_metrics ), $group );
		$this->assertSame( $url_metrics, iterator_to_array( $group ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_is_viewport_width_in_range(): array {
		return array(
			'0-10'    => array(
				'minimum_viewport_width'   => 0,
				'maximum_viewport_width'   => 10,
				'viewport_widths_expected' => array(
					0  => true,
					1  => true,
					9  => true,
					10 => true,
					11 => false,
				),
			),
			'100-200' => array(
				'minimum_viewport_width'   => 100,
				'maximum_viewport_width'   => 200,
				'viewport_widths_expected' => array(
					0   => false,
					99  => false,
					100 => true,
					101 => true,
					150 => true,
					199 => true,
					200 => true,
					201 => false,
				),
			),
		);
	}

	/**
	 * @covers ::is_viewport_width_in_range
	 *
	 * @dataProvider data_provider_test_is_viewport_width_in_range
	 *
	 * @param int              $minimum_viewport_width Minimum viewport width.
	 * @param int              $maximum_viewport_width Maximum viewport width.
	 * @param array<int, bool> $viewport_widths_expected Viewport widths expected.
	 */
	public function test_is_viewport_width_in_range( int $minimum_viewport_width, int $maximum_viewport_width, array $viewport_widths_expected ): void {
		$group = new OD_URL_Metrics_Group( array(), $minimum_viewport_width, $maximum_viewport_width, 3, HOUR_IN_SECONDS );
		foreach ( $viewport_widths_expected as $viewport_width => $expected ) {
			$this->assertSame( $expected, $group->is_viewport_width_in_range( $viewport_width ), "Failed for viewport width of $viewport_width" );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_add_url_metric(): array {
		return array(
			'out_of_range' => array(
				'viewport_width' => 1,
				'exception'      => InvalidArgumentException::class,
			),
			'within_range' => array(
				'viewport_width' => 100,
				'exception'      => '',
			),
		);
	}

	/**
	 * @covers ::add_url_metric
	 * @covers ::is_complete
	 *
	 * @dataProvider data_provider_test_add_url_metric
	 */
	public function test_add_url_metric( int $viewport_width, string $exception ): void {
		if ( '' !== $exception ) {
			$this->expectException( $exception );
		}
		$group = new OD_URL_Metrics_Group( array(), 100, 200, 1, HOUR_IN_SECONDS );

		$this->assertFalse( $group->is_complete() );
		$group->add_url_metric(
			new OD_URL_Metric(
				array(
					'url'       => home_url( '/' ),
					'viewport'  => array(
						'width'  => $viewport_width,
						'height' => 1000,
					),
					'timestamp' => microtime( true ),
					'elements'  => array(),
				)
			)
		);

		$this->assertCount( 1, $group );
		$this->assertSame( $viewport_width, iterator_to_array( $group )[0]->get_viewport()['width'] );
		$this->assertTrue( $group->is_complete() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_get_lcp_element(): array {
		$get_sample_url_metric = function ( int $viewport_width, array $breadcrumbs, $is_lcp = true ) {
			return $this->get_sample_url_metric(
				array(
					'viewport_width' => $viewport_width,
					'element'        => array(
						'xpath' => $this->get_xpath( ...$breadcrumbs ),
						'isLCP' => $is_lcp,
					),
				)
			);
		};

		return array(
			'common_lcp_element_across_breakpoints'    => array(
				'breakpoints'                 => array( 600, 800 ),
				'url_metrics'                 => array(
					// 0.
					$get_sample_url_metric( 400, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					$get_sample_url_metric( 500, array( 'HTML', 'BODY', 'DIV', 'IMG' ) ), // Ignored since less common than the other two.
					$get_sample_url_metric( 599, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					// 600.
					$get_sample_url_metric( 600, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					$get_sample_url_metric( 700, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					// 800.
					$get_sample_url_metric( 900, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
				),
				'expected_lcp_element_xpaths' => array_fill_keys(
					array(
						'0:600',
						'601:800',
						'801:',
					),
					$this->get_xpath( 'HTML', 'BODY', 'FIGURE', 'IMG' )
				),
			),
			'different_lcp_elements_across_breakpoint' => array(
				'breakpoints'                 => array( 600 ),
				'url_metrics'                 => array(
					// 0.
					$get_sample_url_metric( 400, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					$get_sample_url_metric( 500, array( 'HTML', 'BODY', 'DIV', 'IMG' ) ), // Ignored since less common than the other two.
					$get_sample_url_metric( 600, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					// 600.
					$get_sample_url_metric( 800, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
					$get_sample_url_metric( 900, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
				),
				'expected_lcp_element_xpaths' => array(
					'0:600' => $this->get_xpath( 'HTML', 'BODY', 'FIGURE', 'IMG' ),
					'601:'  => $this->get_xpath( 'HTML', 'BODY', 'MAIN', 'IMG' ),
				),
			),
			'same_lcp_element_across_non_consecutive_breakpoints' => array(
				'breakpoints'                 => array( 400, 600 ),
				'url_metrics'                 => array(
					// 0.
					$get_sample_url_metric( 300, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
					// 400.
					$get_sample_url_metric( 500, array( 'HTML', 'BODY', 'HEADER', 'IMG' ), false ),
					// 600.
					$get_sample_url_metric( 800, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
					$get_sample_url_metric( 900, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
				),
				'expected_lcp_element_xpaths' => array(
					'0:400'   => $this->get_xpath( 'HTML', 'BODY', 'MAIN', 'IMG' ),
					'401:600' => null, // The (image) element is either not visible at this breakpoint or it is not LCP element.
					'601:'    => $this->get_xpath( 'HTML', 'BODY', 'MAIN', 'IMG' ),
				),
			),
			'no_lcp_image_elements'                    => array(
				'breakpoints'                 => array( 600 ),
				'url_metrics'                 => array(
					// 0.
					$get_sample_url_metric( 300, array( 'HTML', 'BODY', 'IMG' ), false ),
					// 600.
					$get_sample_url_metric( 700, array( 'HTML', 'BODY', 'IMG' ), false ),
				),
				'expected_lcp_element_xpaths' => array_fill_keys(
					array(
						'0:600',
						'601:',
					),
					null
				),
			),
		);
	}

	/**
	 * Test get_lcp_element().
	 *
	 * @covers ::get_lcp_element
	 * @dataProvider data_provider_test_get_lcp_element
	 *
	 * @param int[]              $breakpoints Breakpoints.
	 * @param OD_URL_Metric[]    $url_metrics URL Metrics.
	 * @param array<int, string> $expected_lcp_element_xpaths Expected XPaths.
	 */
	public function test_get_lcp_element( array $breakpoints, array $url_metrics, array $expected_lcp_element_xpaths ): void {
		$group_collection = new OD_URL_Metrics_Group_Collection( $url_metrics, $breakpoints, 10, HOUR_IN_SECONDS );

		$lcp_element_xpaths_by_minimum_viewport_widths = array();
		foreach ( $group_collection as $group ) {
			$lcp_element = $group->get_lcp_element();
			$width_range = sprintf( '%d:', $group->get_minimum_viewport_width() );
			if ( $group->get_maximum_viewport_width() !== PHP_INT_MAX ) {
				$width_range .= $group->get_maximum_viewport_width();
			}
			$lcp_element_xpaths_by_minimum_viewport_widths[ $width_range ] = $lcp_element['xpath'] ?? null;
		}

		$this->assertSame( $expected_lcp_element_xpaths, $lcp_element_xpaths_by_minimum_viewport_widths );
	}

	/**
	 * Test jsonSerialize().
	 *
	 * @covers ::jsonSerialize
	 */
	public function test_json_serialize(): void {
		$group = new OD_URL_Metrics_Group(
			array_map(
				function ( $viewport_width ) {
					return $this->get_sample_url_metric( array( 'viewport_width' => $viewport_width ) );
				},
				array( 400, 600, 800 )
			),
			0,
			1000,
			1,
			HOUR_IN_SECONDS
		);

		$json          = wp_json_encode( $group );
		$parsed_json   = json_decode( $json, true );
		$expected_keys = array(
			'freshness_ttl',
			'sample_size',
			'minimum_viewport_width',
			'maximum_viewport_width',
			'lcp_element',
			'complete',
			'url_metrics',
		);
		$this->assertIsArray( $parsed_json );
		$this->assertSameSets(
			$expected_keys,
			array_keys( $parsed_json )
		);
	}

	/**
	 * Gets sample XPath.
	 *
	 * @param string ...$breadcrumbs List of tags.
	 * @return string XPath.
	 */
	private function get_xpath( string ...$breadcrumbs ): string {
		return implode(
			'',
			array_map(
				static function ( $tag ): string {
					return sprintf( '/*[0][self::%s]', strtoupper( $tag ) );
				},
				$breadcrumbs
			)
		);
	}
}
