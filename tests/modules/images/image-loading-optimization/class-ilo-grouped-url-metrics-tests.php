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
				'viewport_widths' => array(
					400 => 3,
					600 => 3,
					800 => 1,
				),
				'expected_counts' => array(
					0   => 3,
					481 => 3,
					783 => 1,
				),
			),
			'2 sample size and 3 breakpoints' => array(
				'sample_size'     => 2,
				'breakpoints'     => array( 480, 600, 782 ),
				'viewport_widths' => array(
					200 => 4,
					481 => 2,
					601 => 7,
					783 => 6,
				),
				'expected_counts' => array(
					0   => 2,
					481 => 2,
					601 => 2,
					783 => 2,
				),
			),
			'1 sample size and 1 breakpoint'  => array(
				'sample_size'     => 1,
				'breakpoints'     => array( 480 ),
				'viewport_widths' => array(
					400 => 1,
					800 => 1,
				),
				'expected_counts' => array(
					0   => 1,
					481 => 1,
				),
			),
		);
	}

	/**
	 * Test add().
	 *
	 * @covers ::add
	 *
	 * @param int             $sample_size     Sample size.
	 * @param array           $breakpoints     Breakpoints.
	 * @param array<int, int> $viewport_widths Viewport widths mapped to the number of URL metrics to instantiate.
	 * @param array<int, int> $expected_counts Minimum viewport widths mapped to the expected counts in each group.
	 *
	 * @dataProvider data_provider_sample_size_and_breakpoints
	 * @throws ILO_Data_Validation_Exception When failing to instantiate a URL metric.
	 */
	public function test_add( int $sample_size, array $breakpoints, array $viewport_widths, array $expected_counts ) {
		$grouped_url_metrics = new ILO_Grouped_URL_Metrics( array(), $breakpoints, $sample_size, HOUR_IN_SECONDS );

		// Over-populate the sample size for the breakpoints by a dozen.
		foreach ( $viewport_widths as $viewport_width => $count ) {
			for ( $i = 0; $i < $count; $i++ ) {
				$grouped_url_metrics->add( $this->get_validated_url_metric( $viewport_width ) );
			}
		}

		$this->assertLessThanOrEqual(
			$sample_size * ( count( $breakpoints ) + 1 ),
			count( $grouped_url_metrics->flatten() ),
			sprintf( 'Expected there to be at most sample size (%d) times the number of breakpoint groups (which is %d + 1)', $sample_size, count( $breakpoints ) )
		);

		foreach ( $expected_counts as $minimum_viewport_width => $count ) {
			$this->assertCount( $count, $grouped_url_metrics->get_groups()[ $minimum_viewport_width ] );
		}
	}

	/**
	 * Test that add() pushes out old metrics.
	 *
	 * @covers ::add
	 *
	 * @throws ILO_Data_Validation_Exception When failing to instantiate a URL metric.
	 */
	public function test_adding_pushes_out_old_metrics() {
		$sample_size         = 3;
		$breakpoints         = array( 400, 600 );
		$grouped_url_metrics = new ILO_Grouped_URL_Metrics( array(), $breakpoints, $sample_size, HOUR_IN_SECONDS );

		// Populate the groups with stale URL metrics.
		$viewport_widths = array( 300, 500, 700 );
		$old_timestamp   = microtime( true ) - ( HOUR_IN_SECONDS + 1 );

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
	 * @return array[]
	 */
	public function data_provider_test_get_group_statuses(): array {
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
					'expected_return'           => array(
						array(
							'minimumViewportWidth' => 0,
							'isLacking'            => false,
						),
						array(
							'minimumViewportWidth' => 481,
							'isLacking'            => false,
						),
					),
					'expected_is_group_lacking' => array(
						400 => false,
						480 => false,
						600 => false,
					),
				)
			),

			'not-enough-url-metrics' => array_merge(
				$none_needed_data,
				array(
					'sample_size' => $none_needed_data['sample_size'] + 1,
				),
				array(
					'expected_return'           => array(
						array(
							'minimumViewportWidth' => 0,
							'isLacking'            => true,
						),
						array(
							'minimumViewportWidth' => 481,
							'isLacking'            => true,
						),
					),
					'expected_is_group_lacking' => array(
						200 => true,
						480 => true,
						481 => true,
						500 => true,
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
					'expected_return'           => array(
						array(
							'minimumViewportWidth' => 0,
							'isLacking'            => true,
						),
						array(
							'minimumViewportWidth' => 481,
							'isLacking'            => false,
						),
					),
					'expected_is_group_lacking' => array(
						200 => true,
						400 => true,
						480 => true,
						481 => false,
						500 => false,
					),
				)
			),
		);
	}

	/**
	 * Test get_group_statuses().
	 *
	 * @covers ::get_group_statuses
	 * @covers ::is_group_lacking
	 *
	 * @dataProvider data_provider_test_get_group_statuses
	 */
	public function test_get_group_statuses( array $url_metrics, float $current_time, array $breakpoints, int $sample_size, int $freshness_ttl, array $expected_return, array $expected_is_group_lacking ) {
		$grouped_url_metrics = new ILO_Grouped_URL_Metrics( $url_metrics, $breakpoints, $sample_size, $freshness_ttl );
		$this->assertSame(
			$expected_return,
			array_map(
				static function ( ILO_URL_Metrics_Group_Status $status ) {
					return $status->jsonSerialize();
				},
				$grouped_url_metrics->get_group_statuses()
			)
		);

		foreach ( $expected_is_group_lacking as $viewport_width => $expected ) {
			$this->assertSame(
				$expected,
				$grouped_url_metrics->is_group_lacking( $viewport_width ),
				"Unexpected value for viewport width of $viewport_width"
			);
		}
	}

	/**
	 * Test is_every_group_populated().
	 *
	 * @covers ::is_every_group_populated
	 */
	public function test_is_every_group_populated() {
		$grouped_url_metrics = new ILO_Grouped_URL_Metrics(
			array(),
			array( 480, 800 ),
			3,
			HOUR_IN_SECONDS
		);
		$this->assertFalse( $grouped_url_metrics->is_every_group_populated() );
		$grouped_url_metrics->add( $this->get_validated_url_metric( 200 ) );
		$this->assertFalse( $grouped_url_metrics->is_every_group_populated() );
		$grouped_url_metrics->add( $this->get_validated_url_metric( 500 ) );
		$this->assertFalse( $grouped_url_metrics->is_every_group_populated() );
		$grouped_url_metrics->add( $this->get_validated_url_metric( 900 ) );
		$this->assertTrue( $grouped_url_metrics->is_every_group_populated() );
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
	 * @param int $viewport_width Viewport width.
	 *
	 * @return ILO_URL_Metric Validated URL metric.
	 * @throws ILO_Data_Validation_Exception From ILO_URL_Metric if there is a parse error, but there won't be.
	 */
	private function get_validated_url_metric( int $viewport_width = 480 ): ILO_URL_Metric {
		$data = array(
			'viewport'  => array(
				'width'  => $viewport_width,
				'height' => 640,
			),
			'timestamp' => microtime( true ),
			'elements'  => array(
				array(
					'isLCP'             => true,
					'isLCPCandidate'    => true,
					'xpath'             => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]/*[1]',
					'intersectionRatio' => 1,
				),
			),
		);
		return new ILO_URL_Metric( $data );
	}
}
