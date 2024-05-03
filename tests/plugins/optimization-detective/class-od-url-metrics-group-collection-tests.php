<?php
/**
 * Tests for OD_URL_Metrics_Group_Collection.
 *
 * @package optimization-detective
 *
 * @noinspection PhpUnhandledExceptionInspection
 *
 * @coversDefaultClass OD_URL_Metrics_Group_Collection
 */

class OD_URL_Metrics_Group_Collection_Tests extends WP_UnitTestCase {

	/**
	 * Data provider.
	 *
	 * @throws OD_Data_Validation_Exception From OD_URL_Metric if there is a parse error, but there won't be.
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_construction(): array {
		return array(
			'no_breakpoints_ok'          => array(
				'url_metrics'   => array(),
				'breakpoints'   => array(),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => '',
			),
			'negative_breakpoint_bad'    => array(
				'url_metrics'   => array(),
				'breakpoints'   => array( -1 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'zero_breakpoint_bad'        => array(
				'url_metrics'   => array(),
				'breakpoints'   => array( 0 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'max_breakpoint_bad'         => array(
				'url_metrics'   => array(),
				'breakpoints'   => array( PHP_INT_MAX ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'string_breakpoint_bad'      => array(
				'url_metrics'   => array(),
				'breakpoints'   => array( 'narrow' ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => PHP_MAJOR_VERSION >= 8 ? TypeError::class : InvalidArgumentException::class,
			),
			'negative_sample_size_bad'   => array(
				'url_metrics'   => array(),
				'breakpoints'   => array( 400 ),
				'sample_size'   => -3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'negative_freshness_tll_bad' => array(
				'url_metrics'   => array(),
				'breakpoints'   => array( 400 ),
				'sample_size'   => 3,
				'freshness_ttl' => -HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'invalid_url_metrics_bad'    => array(
				'url_metrics'   => array( 'bad' ),
				'breakpoints'   => array( 400 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => TypeError::class,
			),
			'all_arguments_good'         => array(
				'url_metrics'   => array(
					$this->get_validated_url_metric( 200 ),
					$this->get_validated_url_metric( 400 ),
				),
				'breakpoints'   => array( 400 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => '',
			),
		);
	}

	/**
	 * @covers ::__construct
	 *
	 * @dataProvider data_provider_test_construction
	 *
	 * @param OD_URL_Metric[] $url_metrics URL Metrics.
	 * @param int[]           $breakpoints Breakpoints.
	 * @param int             $sample_size Sample size.
	 */
	public function test_construction( array $url_metrics, array $breakpoints, int $sample_size, int $freshness_ttl, string $exception ): void {
		if ( $exception ) {
			$this->expectException( $exception );
		}
		$group_collection = new OD_URL_Metrics_Group_Collection( $url_metrics, $breakpoints, $sample_size, $freshness_ttl );
		$this->assertCount( count( $breakpoints ) + 1, $group_collection );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
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
			'2 sample size and 0 breakpoints' => array(
				'sample_size'     => 2,
				'breakpoints'     => array(),
				'viewport_widths' => array(
					400 => 1,
					600 => 1,
				),
				'expected_counts' => array(
					0 => 2,
				),
			),
		);
	}

	/**
	 * Test add_url_metric().
	 *
	 * @covers ::add_url_metric
	 *
	 * @param int             $sample_size     Sample size.
	 * @param int[]           $breakpoints     Breakpoints.
	 * @param array<int, int> $viewport_widths Viewport widths mapped to the number of URL metrics to instantiate.
	 * @param array<int, int> $expected_counts Minimum viewport widths mapped to the expected counts in each group.
	 *
	 * @dataProvider data_provider_sample_size_and_breakpoints
	 * @throws OD_Data_Validation_Exception When failing to instantiate a URL metric.
	 */
	public function test_add_url_metric( int $sample_size, array $breakpoints, array $viewport_widths, array $expected_counts ): void {
		$group_collection = new OD_URL_Metrics_Group_Collection( array(), $breakpoints, $sample_size, HOUR_IN_SECONDS );

		// Over-populate the sample size for the breakpoints by a dozen.
		foreach ( $viewport_widths as $viewport_width => $count ) {
			for ( $i = 0; $i < $count; $i++ ) {
				$group_collection->add_url_metric( $this->get_validated_url_metric( $viewport_width ) );
			}
		}

		$this->assertLessThanOrEqual(
			$sample_size * ( count( $breakpoints ) + 1 ),
			count( $group_collection->get_flattened_url_metrics() ),
			sprintf( 'Expected there to be at most sample size (%d) times the number of breakpoint groups (which is %d + 1)', $sample_size, count( $breakpoints ) )
		);

		$this->assertCount( count( $expected_counts ), $group_collection );
		foreach ( $expected_counts as $minimum_viewport_width => $count ) {
			$group = $group_collection->get_group_for_viewport_width( $minimum_viewport_width );
			$this->assertCount( $count, $group, "Expected equal count for $minimum_viewport_width minimum viewport width." );
		}
	}

	/**
	 * Test that add_url_metric() pushes out old metrics.
	 *
	 * @covers ::add_url_metric
	 *
	 * @throws OD_Data_Validation_Exception When failing to instantiate a URL metric.
	 */
	public function test_adding_pushes_out_old_metrics(): void {
		$sample_size      = 3;
		$breakpoints      = array( 400, 600 );
		$group_collection = new OD_URL_Metrics_Group_Collection( array(), $breakpoints, $sample_size, HOUR_IN_SECONDS );

		// Populate the groups with stale URL metrics.
		$viewport_widths = array( 300, 500, 700 );
		$old_timestamp   = microtime( true ) - ( HOUR_IN_SECONDS + 1 );

		foreach ( $viewport_widths as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				$group_collection->add_url_metric(
					new OD_URL_Metric(
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
			$group_collection->add_url_metric( $this->get_validated_url_metric( $viewport_width ) );
		}

		$max_possible_url_metrics_count = $sample_size * ( count( $breakpoints ) + 1 );
		$this->assertCount(
			$max_possible_url_metrics_count,
			$group_collection->get_flattened_url_metrics(),
			'Expected the total count of URL metrics to not exceed the multiple of the sample size.'
		);
		$new_count = 0;
		foreach ( $group_collection->get_flattened_url_metrics() as $url_metric ) {
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
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_get_iterator(): array {
		return array(
			'2-breakpoints-and-3-viewport-widths' => array(
				'breakpoints'     => array( 480, 640 ),
				'viewport_widths' => array( 400, 480, 800 ),
				'expected_groups' => array(
					array(
						'minimum_viewport_width'     => 0,
						'maximum_viewport_width'     => 480,
						'url_metric_viewport_widths' => array( 400, 480 ),
					),
					array(
						'minimum_viewport_width'     => 481,
						'maximum_viewport_width'     => 640,
						'url_metric_viewport_widths' => array(),
					),
					array(
						'minimum_viewport_width'     => 641,
						'maximum_viewport_width'     => PHP_INT_MAX,
						'url_metric_viewport_widths' => array( 800 ),
					),
				),
			),
			'1-breakpoint-and-4-viewport-widths'  => array(
				'breakpoints'     => array( 480 ),
				'viewport_widths' => array( 400, 600, 800, 1000 ),
				'expected_groups' => array(
					array(
						'minimum_viewport_width'     => 0,
						'maximum_viewport_width'     => 480,
						'url_metric_viewport_widths' => array( 400 ),
					),
					array(
						'minimum_viewport_width'     => 481,
						'maximum_viewport_width'     => PHP_INT_MAX,
						'url_metric_viewport_widths' => array( 600, 800, 1000 ),
					),
				),
			),
		);
	}

	/**
	 * Test getIterator().
	 *
	 * @covers ::getIterator
	 *
	 * @dataProvider data_provider_test_get_iterator
	 * @throws OD_Data_Validation_Exception From OD_URL_Metric if there is a parse error, but there won't be.
	 *
	 * @param int[]             $breakpoints Breakpoints.
	 * @param int[]             $viewport_widths Viewport widths.
	 * @param array<int, mixed> $expected_groups Expected groups.
	 */
	public function test_get_iterator( array $breakpoints, array $viewport_widths, array $expected_groups ): void {
		$url_metrics = array_map(
			function ( $viewport_width ) {
				return $this->get_validated_url_metric( $viewport_width );
			},
			$viewport_widths
		);

		$group_collection = new OD_URL_Metrics_Group_Collection( $url_metrics, $breakpoints, 3, HOUR_IN_SECONDS );

		$this->assertCount(
			count( $breakpoints ) + 1,
			$group_collection,
			'Expected number of breakpoint groups to always be one greater than the number of breakpoints.'
		);

		$actual_groups = array();
		foreach ( $group_collection as $group ) {
			$actual_groups[] = array(
				'minimum_viewport_width'     => $group->get_minimum_viewport_width(),
				'maximum_viewport_width'     => $group->get_maximum_viewport_width(),
				'url_metric_viewport_widths' => array_map(
					static function ( OD_URL_Metric $url_metric ): int {
						return $url_metric->get_viewport_width();
					},
					iterator_to_array( $group )
				),
			);
		}

		$this->assertEquals( $expected_groups, $actual_groups );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_get_group_for_viewport_width(): array {
		$current_time = microtime( true );

		$none_needed_data = array(
			'url_metrics'   => ( function () use ( $current_time ): array {
				return array_merge(
					array_fill(
						0,
						3,
						new OD_URL_Metric(
							array_merge(
								$this->get_validated_url_metric( 400 )->jsonSerialize(),
								array( 'timestamp' => $current_time )
							)
						)
					),
					array_fill(
						0,
						3,
						new OD_URL_Metric(
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
					'expected_return'            => array(
						array(
							'minimumViewportWidth' => 0,
							'complete'             => true,
						),
						array(
							'minimumViewportWidth' => 481,
							'complete'             => true,
						),
					),
					'expected_is_group_complete' => array(
						400 => true,
						480 => true,
						600 => true,
					),
				)
			),

			'not-enough-url-metrics' => array_merge(
				$none_needed_data,
				array(
					'sample_size' => $none_needed_data['sample_size'] + 1,
				),
				array(
					'expected_return'            => array(
						array(
							'minimumViewportWidth' => 0,
							'complete'             => false,
						),
						array(
							'minimumViewportWidth' => 481,
							'complete'             => false,
						),
					),
					'expected_is_group_complete' => array(
						200 => false,
						480 => false,
						481 => false,
						500 => false,
					),
				)
			),

			'url-metric-too-old'     => array_merge(
				( static function ( $data ): array {
					$url_metrics_data = $data['url_metrics'][0]->jsonSerialize();
					$url_metrics_data['timestamp'] -= $data['freshness_ttl'] + 1;
					$data['url_metrics'][0] = new OD_URL_Metric( $url_metrics_data );
					return $data;
				} )( $none_needed_data ),
				array(
					'expected_return'            => array(
						array(
							'minimumViewportWidth' => 0,
							'complete'             => false,
						),
						array(
							'minimumViewportWidth' => 481,
							'complete'             => true,
						),
					),
					'expected_is_group_complete' => array(
						200 => false,
						400 => false,
						480 => false,
						481 => true,
						500 => true,
					),
				)
			),
		);
	}

	/**
	 * Test get_minimum_viewport_width().
	 *
	 * @covers ::get_group_for_viewport_width
	 * @covers ::getIterator
	 * @covers OD_URL_Metrics_Group::is_complete
	 * @covers OD_URL_Metrics_Group::get_minimum_viewport_width
	 *
	 * @dataProvider data_provider_test_get_group_for_viewport_width
	 *
	 * @param OD_URL_Metric[]   $url_metrics URL Metrics.
	 * @param float             $current_time Current time.
	 * @param int[]             $breakpoints Breakpoints.
	 * @param int               $sample_size Sample size.
	 * @param int               $freshness_ttl Freshness TTL.
	 * @param array<int, mixed> $expected_return Expected return.
	 * @param array<int, bool>  $expected_is_group_complete Expected is group complete.
	 */
	public function test_get_group_for_viewport_width( array $url_metrics, float $current_time, array $breakpoints, int $sample_size, int $freshness_ttl, array $expected_return, array $expected_is_group_complete ): void {
		$group_collection = new OD_URL_Metrics_Group_Collection( $url_metrics, $breakpoints, $sample_size, $freshness_ttl );
		$this->assertSame(
			$expected_return,
			array_map(
				static function ( OD_URL_Metrics_Group $group ): array {
					return array(
						'minimumViewportWidth' => $group->get_minimum_viewport_width(),
						'complete'             => $group->is_complete(),
					);
				},
				iterator_to_array( $group_collection )
			)
		);

		foreach ( $expected_is_group_complete as $viewport_width => $expected ) {
			$this->assertSame(
				$expected,
				$group_collection->get_group_for_viewport_width( $viewport_width )->is_complete(),
				"Unexpected value for viewport width of $viewport_width"
			);
		}
	}

	/**
	 * Test is_every_group_populated() and is_every_group_complete().
	 *
	 * @covers ::is_every_group_populated
	 * @covers ::is_every_group_complete
	 */
	public function test_is_every_group_populated(): void {
		$breakpoints      = array( 480, 800 );
		$sample_size      = 3;
		$group_collection = new OD_URL_Metrics_Group_Collection(
			array(),
			$breakpoints,
			$sample_size,
			HOUR_IN_SECONDS
		);
		$this->assertFalse( $group_collection->is_every_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_complete() );
		$group_collection->add_url_metric( $this->get_validated_url_metric( 200 ) );
		$this->assertFalse( $group_collection->is_every_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_complete() );
		$group_collection->add_url_metric( $this->get_validated_url_metric( 500 ) );
		$this->assertFalse( $group_collection->is_every_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_complete() );
		$group_collection->add_url_metric( $this->get_validated_url_metric( 900 ) );
		$this->assertTrue( $group_collection->is_every_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_complete() );

		// Now finish completing all the groups.
		foreach ( array_merge( $breakpoints, array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				$group_collection->add_url_metric( $this->get_validated_url_metric( $viewport_width ) );
			}
		}
		$this->assertTrue( $group_collection->is_every_group_complete() );
	}

	/**
	 * Test get_flattened_url_metrics().
	 *
	 * @covers ::get_flattened_url_metrics
	 */
	public function test_get_flattened_url_metrics(): void {
		$url_metrics = array(
			$this->get_validated_url_metric( 400 ),
			$this->get_validated_url_metric( 600 ),
			$this->get_validated_url_metric( 800 ),
		);

		$group_collection = new OD_URL_Metrics_Group_Collection(
			$url_metrics,
			array( 500, 700 ),
			3,
			HOUR_IN_SECONDS
		);

		$this->assertEquals( $url_metrics, $group_collection->get_flattened_url_metrics() );

		$this->assertEquals(
			$url_metrics,
			array_merge( ...array_map( 'iterator_to_array', iterator_to_array( $group_collection ) ) )
		);
	}

	/**
	 * Gets a validated URL metric for testing.
	 *
	 * @param int $viewport_width Viewport width.
	 *
	 * @return OD_URL_Metric Validated URL metric.
	 * @throws OD_Data_Validation_Exception From OD_URL_Metric if there is a parse error, but there won't be.
	 */
	private function get_validated_url_metric( int $viewport_width = 480 ): OD_URL_Metric {
		$data = array(
			'url'       => home_url( '/' ),
			'viewport'  => array(
				'width'  => $viewport_width,
				'height' => 640,
			),
			'timestamp' => microtime( true ),
			'elements'  => array(
				array(
					'isLCP'              => true,
					'isLCPCandidate'     => true,
					'xpath'              => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]/*[1]',
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
			),
		);
		return new OD_URL_Metric( $data );
	}
}
