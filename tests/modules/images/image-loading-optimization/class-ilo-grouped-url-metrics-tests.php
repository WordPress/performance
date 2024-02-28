<?php
/**
 * Tests for ILO_Grouped_URL_Metrics.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 *
 * @noinspection PhpUnhandledExceptionInspection
 *
 * @coversDefaultClass ILO_Grouped_URL_Metrics
 */

class ILO_Grouped_URL_Metrics_Tests extends WP_UnitTestCase {

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_provider_sample_size_and_breakpoints(): array {
		return array(
			'3 sample size and 2 breakpoints' => array(
				'sample_size'     => 3,
				'breakpoints'     => array( 480, 782 ),
				'viewport_widths' => array( 400, 600, 800 ),
			),
			'1 sample size and 1 breakpoint'  => array(
				'sample_size'     => 1,
				'breakpoints'     => array( 480 ),
				'viewport_widths' => array( 400, 800 ),
			),
		);
	}

	/**
	 * Test add().
	 *
	 * @covers ::add
	 *
	 * @dataProvider data_provider_sample_size_and_breakpoints
	 */
	public function test_add( int $sample_size, array $breakpoints, array $viewport_widths ) {
		$grouped_url_metrics = new ILO_Grouped_URL_Metrics( array(), $breakpoints, $sample_size, HOUR_IN_SECONDS );

		// Over-populate the sample size for the breakpoints by a dozen.
		foreach ( $viewport_widths as $viewport_width ) {
			for ( $i = 0; $i < $sample_size + 12; $i++ ) {
				$grouped_url_metrics->add( $this->get_validated_url_metric( $viewport_width ) );
			}
		}
		$max_possible_url_metrics_count = $sample_size * ( count( $breakpoints ) + 1 );
		$this->assertCount(
			$max_possible_url_metrics_count,
			$grouped_url_metrics->flatten(),
			sprintf( 'Expected there to be exactly sample size (%d) times the number of breakpoint groups (which is %d + 1)', $sample_size, count( $breakpoints ) )
		);
	}

	/**
	 * Test that add() pushes out old metrics.
	 *
	 * @covers ::add
	 *
	 * @dataProvider data_provider_sample_size_and_breakpoints
	 * @throws Exception When a parse error happens.
	 */
	public function test_adding_pushes_out_old_metrics( int $sample_size, array $breakpoints, array $viewport_widths ) {
		$old_timestamp = microtime( true ) - ( HOUR_IN_SECONDS + 1 );

		$grouped_url_metrics = new ILO_Grouped_URL_Metrics( array(), $breakpoints, $sample_size, HOUR_IN_SECONDS );

		// Populate the groups with stale URL metrics.
		foreach ( $viewport_widths as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				$grouped_url_metrics->add(
					new ILO_URL_Metric(
						array_merge(
							$this->get_validated_url_metric( $viewport_width )->jsonSerialize(),
							array(
								'timestamp' => $old_timestamp,
							)
						)
					)
				);
			}
		}

		// Try adding one URL metric for each breakpoint group.
		foreach ( $viewport_widths as $viewport_width ) {
			$grouped_url_metrics->add( $this->get_validated_url_metric( $viewport_width ) );
		}

		$max_possible_url_metrics_count = $sample_size * ( count( $breakpoints ) + 1 );
		$this->assertCount(
			$max_possible_url_metrics_count,
			$grouped_url_metrics->flatten(),
			'Expected the total count of URL metrics to not exceed the multiple of the sample size.'
		);
		$new_count = 0;
		foreach ( $grouped_url_metrics->flatten() as $url_metric ) {
			if ( $url_metric->get_timestamp() > $old_timestamp ) {
				++$new_count;
			}
		}
		$this->assertGreaterThan( 0, $new_count, 'Expected there to be at least one new URL metric.' );
		$this->assertSame( count( $viewport_widths ), $new_count, 'Expected the new URL metrics to all have been added.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_provider_test_get_groups_and_get_minimum_viewport_widths(): array {
		return array(
			'2-breakpoints-and-3-viewport-widths' => array(
				'breakpoints'     => array( 480, 640 ),
				'viewport_widths' => array( 400, 480, 800 ),
			),
			'1-breakpoint-and-4-viewport-widths'  => array(
				'breakpoints'     => array( 480 ),
				'viewport_widths' => array( 400, 600, 800, 1000 ),
			),
		);
	}

	/**
	 * Test get_groups() and get_minimum_viewport_widths().
	 *
	 * @covers ::get_groups
	 * @covers ::get_minimum_viewport_widths
	 *
	 * @dataProvider data_provider_test_get_groups_and_get_minimum_viewport_widths
	 */
	public function test_get_groups_and_get_minimum_viewport_widths( array $breakpoints, array $viewport_widths ) {
		$url_metrics = array_map(
			function ( $viewport_width ) {
				return $this->get_validated_url_metric( $viewport_width );
			},
			$viewport_widths
		);

		$grouped_url_metrics = new ILO_Grouped_URL_Metrics( $url_metrics, $breakpoints, 3, HOUR_IN_SECONDS );

		$this->assertCount( count( $breakpoints ) + 1, $grouped_url_metrics->get_groups(), 'Expected number of breakpoint groups to always be one greater than the number of breakpoints.' );
		$minimum_viewport_widths = $grouped_url_metrics->get_minimum_viewport_widths();
		$this->assertSame( array_keys( $grouped_url_metrics->get_groups() ), $minimum_viewport_widths );
		$this->assertSame( 0, array_shift( $minimum_viewport_widths ), 'Expected the first minimum viewport width to always be zero.' );
		foreach ( $breakpoints as $breakpoint ) {
			$this->assertSame( $breakpoint + 1, array_shift( $minimum_viewport_widths ) );
		}

		$minimum_viewport_widths = $grouped_url_metrics->get_minimum_viewport_widths();
		for ( $i = 0, $len = count( $minimum_viewport_widths ); $i < $len; $i++ ) {
			$minimum_viewport_width = $minimum_viewport_widths[ $i ];
			$maximum_viewport_width = $minimum_viewport_widths[ $i + 1 ] ?? null;
			if ( 0 === $i ) {
				$this->assertSame( 0, $minimum_viewport_width );
			} else {
				$this->assertGreaterThan( 0, $minimum_viewport_width );
			}
			if ( isset( $maximum_viewport_width ) ) {
				$this->assertLessThan( $maximum_viewport_width, $minimum_viewport_width );
			}

			foreach ( $grouped_url_metrics->get_groups()[ $minimum_viewport_width ] as $url_metric ) {
				$this->assertGreaterThanOrEqual( $minimum_viewport_width, $url_metric->get_viewport()['width'] );
				if ( isset( $maximum_viewport_width ) ) {
					$this->assertLessThanOrEqual( $maximum_viewport_width, $url_metric->get_viewport()['width'] );
				}
			}
		}
	}

	/**
	 * Data provider.
	 *
	 * @throws Exception When invalid URL metric (which there should not be).
	 * @return array[]
	 */
	public function data_provider_test_get_lcp_elements_by_minimum_viewport_widths(): array {
		return array(
			'common_lcp_element_across_breakpoints'    => array(
				'breakpoints'                 => array( 600, 800 ),
				'url_metrics'                 => array(
					// 0.
					$this->get_validated_url_metric( 400, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					$this->get_validated_url_metric( 500, array( 'HTML', 'BODY', 'DIV', 'IMG' ) ), // Ignored since less common than the other two.
					$this->get_validated_url_metric( 599, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					// 600.
					$this->get_validated_url_metric( 600, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					$this->get_validated_url_metric( 700, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					// 800.
					$this->get_validated_url_metric( 900, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
				),
				'expected_lcp_element_xpaths' => array(
					0 => $this->get_xpath( 'HTML', 'BODY', 'FIGURE', 'IMG' ),
				),
			),
			'different_lcp_elements_across_breakpoint' => array(
				'breakpoints'                 => array( 600 ),
				'url_metrics'                 => array(
					// 0.
					$this->get_validated_url_metric( 400, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					$this->get_validated_url_metric( 500, array( 'HTML', 'BODY', 'DIV', 'IMG' ) ), // Ignored since less common than the other two.
					$this->get_validated_url_metric( 600, array( 'HTML', 'BODY', 'FIGURE', 'IMG' ) ),
					// 600.
					$this->get_validated_url_metric( 800, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
					$this->get_validated_url_metric( 900, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
				),
				'expected_lcp_element_xpaths' => array(
					0   => $this->get_xpath( 'HTML', 'BODY', 'FIGURE', 'IMG' ),
					601 => $this->get_xpath( 'HTML', 'BODY', 'MAIN', 'IMG' ),
				),
			),
			'same_lcp_element_across_non_consecutive_breakpoints' => array(
				'breakpoints'                 => array( 400, 600 ),
				'url_metrics'                 => array(
					// 0.
					$this->get_validated_url_metric( 300, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
					// 400.
					$this->get_validated_url_metric( 500, array( 'HTML', 'BODY', 'HEADER', 'IMG' ), false ),
					// 600.
					$this->get_validated_url_metric( 800, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
					$this->get_validated_url_metric( 900, array( 'HTML', 'BODY', 'MAIN', 'IMG' ) ),
				),
				'expected_lcp_element_xpaths' => array(
					0   => $this->get_xpath( 'HTML', 'BODY', 'MAIN', 'IMG' ),
					401 => false, // The (image) element is either not visible at this breakpoint or it is not LCP element.
					601 => $this->get_xpath( 'HTML', 'BODY', 'MAIN', 'IMG' ),
				),
			),
			'no_lcp_image_elements'                    => array(
				'breakpoints'                 => array( 600 ),
				'url_metrics'                 => array(
					// 0.
					$this->get_validated_url_metric( 300, array( 'HTML', 'BODY', 'IMG' ), false ),
					// 600.
					$this->get_validated_url_metric( 700, array( 'HTML', 'BODY', 'IMG' ), false ),
				),
				'expected_lcp_element_xpaths' => array(
					0 => false,
				),
			),
		);
	}

	/**
	 * Test get_lcp_elements_by_minimum_viewport_widths().
	 *
	 * @covers ::get_lcp_elements_by_minimum_viewport_widths
	 * @dataProvider data_provider_test_get_lcp_elements_by_minimum_viewport_widths
	 */
	public function test_get_lcp_elements_by_minimum_viewport_widths( array $breakpoints, array $url_metrics, array $expected_lcp_element_xpaths ) {
		$grouped_url_metrics = new ILO_Grouped_URL_Metrics( $url_metrics, $breakpoints, 10, HOUR_IN_SECONDS );

		$lcp_elements_by_minimum_viewport_widths = $grouped_url_metrics->get_lcp_elements_by_minimum_viewport_widths();

		$lcp_element_xpaths_by_minimum_viewport_widths = array();
		foreach ( $lcp_elements_by_minimum_viewport_widths as $minimum_viewport_width => $lcp_element ) {
			$this->assertTrue( is_array( $lcp_element ) || false === $lcp_element );
			if ( is_array( $lcp_element ) ) {
				$this->assertTrue( $lcp_element['isLCP'] );
				$this->assertTrue( $lcp_element['isLCPCandidate'] );
				$this->assertIsString( $lcp_element['xpath'] );
				$this->assertIsNumeric( $lcp_element['intersectionRatio'] );
				$lcp_element_xpaths_by_minimum_viewport_widths[ $minimum_viewport_width ] = $lcp_element['xpath'];
			} else {
				$lcp_element_xpaths_by_minimum_viewport_widths[ $minimum_viewport_width ] = false;
			}
		}

		$this->assertSame( $expected_lcp_element_xpaths, $lcp_element_xpaths_by_minimum_viewport_widths );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_provider_test_get_needed_minimum_viewport_widths(): array {
		$current_time = microtime( true );

		$none_needed_data = array(
			'url_metrics'   => ( function () use ( $current_time ): array {
				return array_merge(
					array_fill(
						0,
						3,
						new ILO_URL_Metric(
							array_merge(
								$this->get_validated_url_metric( 400 )->jsonSerialize(),
								array( 'timestamp' => $current_time )
							)
						)
					),
					array_fill(
						0,
						3,
						new ILO_URL_Metric(
							array_merge(
								$this->get_validated_url_metric( 600 )->jsonSerialize(),
								array( 'timestamp' => $current_time )
							)
						)
					)
				);
			} )(),
			'current_time'  => $current_time,
			'breakpoints'   => array( 480 ),
			'sample_size'   => 3,
			'freshness_ttl' => HOUR_IN_SECONDS,
		);

		return array(
			'none-needed'            => array_merge(
				$none_needed_data,
				array(
					'expected' => array(
						array( 0, false ),
						array( 481, false ),
					),
				)
			),

			'not-enough-url-metrics' => array_merge(
				$none_needed_data,
				array(
					'sample_size' => $none_needed_data['sample_size'] + 1,
				),
				array(
					'expected' => array(
						array( 0, true ),
						array( 481, true ),
					),
				)
			),

			'url-metric-too-old'     => array_merge(
				( static function ( $data ): array {
					$url_metrics_data = $data['url_metrics'][0]->jsonSerialize();
					$url_metrics_data['timestamp'] -= $data['freshness_ttl'] + 1;
					$data['url_metrics'][0] = new ILO_URL_Metric( $url_metrics_data );
					return $data;
				} )( $none_needed_data ),
				array(
					'expected' => array(
						array( 0, true ),
						array( 481, false ),
					),
				)
			),
		);
	}

	/**
	 * Test get_needed_minimum_viewport_widths().
	 *
	 * @covers ::get_needed_minimum_viewport_widths
	 *
	 * @dataProvider data_provider_test_get_needed_minimum_viewport_widths
	 */
	public function test_get_needed_minimum_viewport_widths( array $url_metrics, float $current_time, array $breakpoints, int $sample_size, int $freshness_ttl, array $expected ) {
		$grouped_url_metrics = new ILO_Grouped_URL_Metrics( $url_metrics, $breakpoints, $sample_size, $freshness_ttl );
		$this->assertSame(
			$expected,
			$grouped_url_metrics->get_needed_minimum_viewport_widths()
		);
	}

	/**
	 * Test are_all_groups_populated().
	 *
	 * @covers ::are_all_groups_populated
	 */
	public function test_are_all_groups_populated() {
		$grouped_url_metrics = new ILO_Grouped_URL_Metrics(
			array(),
			array( 480, 800 ),
			3,
			HOUR_IN_SECONDS
		);
		$this->assertFalse( $grouped_url_metrics->are_all_groups_populated() );
		$grouped_url_metrics->add( $this->get_validated_url_metric( 200 ) );
		$this->assertFalse( $grouped_url_metrics->are_all_groups_populated() );
		$grouped_url_metrics->add( $this->get_validated_url_metric( 500 ) );
		$this->assertFalse( $grouped_url_metrics->are_all_groups_populated() );
		$grouped_url_metrics->add( $this->get_validated_url_metric( 900 ) );
		$this->assertTrue( $grouped_url_metrics->are_all_groups_populated() );
	}

	/**
	 * Test flatten().
	 *
	 * @covers ::flatten
	 */
	public function test_flatten() {
		$url_metrics = array(
			$this->get_validated_url_metric( 400 ),
			$this->get_validated_url_metric( 600 ),
			$this->get_validated_url_metric( 800 ),
		);

		$grouped_url_metrics = new ILO_Grouped_URL_Metrics(
			$url_metrics,
			array( 500, 700 ),
			3,
			HOUR_IN_SECONDS
		);

		$this->assertEquals( $url_metrics, $grouped_url_metrics->flatten() );
	}

	/**
	 * Gets a validated URL metric for testing.
	 *
	 * @param int      $viewport_width Viewport width.
	 * @param string[] $breadcrumbs    Breadcrumb tags.
	 * @param bool     $is_lcp         Whether LCP.
	 *
	 * @return ILO_URL_Metric Validated URL metric.
	 * @throws Exception From ILO_URL_Metric if there is a parse error, but there won't be.
	 */
	private function get_validated_url_metric( int $viewport_width = 480, array $breadcrumbs = array( 'HTML', 'BODY', 'IMG' ), bool $is_lcp = true ): ILO_URL_Metric {
		$data = array(
			'viewport'  => array(
				'width'  => $viewport_width,
				'height' => 640,
			),
			'timestamp' => microtime( true ),
			'elements'  => array(
				array(
					'isLCP'             => $is_lcp,
					'isLCPCandidate'    => $is_lcp,
					'xpath'             => $this->get_xpath( ...$breadcrumbs ),
					'intersectionRatio' => 1,
				),
			),
		);
		return new ILO_URL_Metric( $data );
	}

	/**
	 * Gets sample XPath.
	 *
	 * @param string ...$breadcrumbs List of tags.
	 * @return string XPath.
	 */
	private function get_xpath( ...$breadcrumbs ): string {
		return implode(
			'',
			array_map(
				static function ( $tag ) {
					return sprintf( '/*[0][self::%s]', strtoupper( $tag ) );
				},
				$breadcrumbs
			)
		);
	}
}
