<?php
/**
 * Tests for OD_URL_Metric_Group_Collection.
 *
 * @package optimization-detective
 *
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpDocMissingThrowsInspection
 *
 * @coversDefaultClass OD_URL_Metric_Group_Collection
 */

class Test_OD_URL_Metric_Group_Collection extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_construction(): array {
		$current_etag = md5( '' );

		return array(
			'no_breakpoints_ok'          => array(
				'url_metrics'   => array(),
				'current_etag'  => $current_etag,
				'breakpoints'   => array(),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => '',
			),
			'negative_breakpoint_bad'    => array(
				'url_metrics'   => array(),
				'current_etag'  => $current_etag,
				'breakpoints'   => array( -1 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'zero_breakpoint_bad'        => array(
				'url_metrics'   => array(),
				'current_etag'  => $current_etag,
				'breakpoints'   => array( 0 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'max_breakpoint_bad'         => array(
				'url_metrics'   => array(),
				'current_etag'  => $current_etag,
				'breakpoints'   => array( PHP_INT_MAX ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'string_breakpoint_bad'      => array(
				'url_metrics'   => array(),
				'current_etag'  => $current_etag,
				'breakpoints'   => array( 'narrow' ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'negative_sample_size_bad'   => array(
				'url_metrics'   => array(),
				'current_etag'  => $current_etag,
				'breakpoints'   => array( 400 ),
				'sample_size'   => -3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'negative_freshness_tll_bad' => array(
				'url_metrics'   => array(),
				'current_etag'  => $current_etag,
				'breakpoints'   => array( 400 ),
				'sample_size'   => 3,
				'freshness_ttl' => -HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'invalid_current_etag_bad'   => array(
				'url_metrics'   => array(),
				'current_etag'  => 'invalid_etag',
				'breakpoints'   => array( 400 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'invalid_current_etag_bad2'  => array(
				'url_metrics'   => array(),
				'current_etag'  => md5( '' ) . PHP_EOL, // Note that /^[a-f0-9]{32}$/ would erroneously validate this. So the \z is required instead in /^[a-f0-9]{32}\z/.
				'breakpoints'   => array( 400 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => InvalidArgumentException::class,
			),
			'invalid_url_metrics_bad'    => array(
				'url_metrics'   => array( 'bad' ),
				'current_etag'  => $current_etag,
				'breakpoints'   => array( 400 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => TypeError::class,
			),
			'all_arguments_good'         => array(
				'url_metrics'   => array(
					$this->get_sample_url_metric( array( 'viewport_width' => 200 ) ),
					$this->get_sample_url_metric( array( 'viewport_width' => 400 ) ),
				),
				'current_etag'  => $current_etag,
				'breakpoints'   => array( 400 ),
				'sample_size'   => 3,
				'freshness_ttl' => HOUR_IN_SECONDS,
				'exception'     => '',
			),
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::get_current_etag
	 * @covers ::create_groups
	 * @covers ::count
	 *
	 * @dataProvider data_provider_test_construction
	 *
	 * @param OD_URL_Metric[]  $url_metrics   URL Metrics.
	 * @param non-empty-string $current_etag  Current ETag.
	 * @param int[]            $breakpoints   Breakpoints.
	 * @param int              $sample_size   Sample size.
	 * @param int              $freshness_ttl Freshness TTL.
	 * @param string           $exception     Expected exception.
	 */
	public function test_construction( array $url_metrics, string $current_etag, array $breakpoints, int $sample_size, int $freshness_ttl, string $exception ): void {
		if ( '' !== $exception ) {
			$this->expectException( $exception );
		}
		$group_collection = new OD_URL_Metric_Group_Collection( $url_metrics, $current_etag, $breakpoints, $sample_size, $freshness_ttl );
		$this->assertCount( count( $breakpoints ) + 1, $group_collection );
		$this->assertSame( $current_etag, $group_collection->get_current_etag() );
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
	 * Test clear_cache().
	 *
	 * @covers ::clear_cache
	 * @covers OD_URL_Metric_Group::clear_cache
	 */
	public function test_clear_cache(): void {
		$collection      = new OD_URL_Metric_Group_Collection( array(), md5( '' ), array(), 1, DAY_IN_SECONDS );
		$populated_value = array( 'foo' => true );
		$group           = $collection->get_first_group();

		// Get private members.
		$collection_result_cache_reflection_property = new ReflectionProperty( OD_URL_Metric_Group_Collection::class, 'result_cache' );
		$collection_result_cache_reflection_property->setAccessible( true );
		$this->assertSame( array(), $collection_result_cache_reflection_property->getValue( $collection ) );
		$group_result_cache_reflection_property = new ReflectionProperty( OD_URL_Metric_Group::class, 'result_cache' );
		$group_result_cache_reflection_property->setAccessible( true );
		$this->assertSame( array(), $group_result_cache_reflection_property->getValue( $group ) );

		// Test clear_cache() on collection.
		$collection_result_cache_reflection_property->setValue( $collection, $populated_value );
		$collection->clear_cache();
		$this->assertSame( array(), $collection_result_cache_reflection_property->getValue( $collection ) );

		// Test that adding a URL metric to a collection clears the caches.
		$collection_result_cache_reflection_property->setValue( $collection, $populated_value );
		$group_result_cache_reflection_property->setValue( $group, $populated_value );
		$collection->add_url_metric( $this->get_sample_url_metric( array() ) );
		$url_metric = $group->getIterator()->current();
		$this->assertInstanceOf( OD_URL_Metric::class, $url_metric );
		$this->assertSame( array(), $collection_result_cache_reflection_property->getValue( $collection ) );
		$this->assertSame( array(), $group_result_cache_reflection_property->getValue( $group ) );
	}

	/**
	 * Test add_url_metric().
	 *
	 * @covers ::add_url_metric
	 * @covers OD_URL_Metric_Group::add_url_metric
	 * @covers ::is_any_group_populated
	 *
	 * @param int             $sample_size     Sample size.
	 * @param int[]           $breakpoints     Breakpoints.
	 * @param array<int, int> $viewport_widths Viewport widths mapped to the number of URL Metrics to instantiate.
	 * @param array<int, int> $expected_counts Minimum viewport widths mapped to the expected counts in each group.
	 *
	 * @dataProvider data_provider_sample_size_and_breakpoints
	 */
	public function test_add_url_metric( int $sample_size, array $breakpoints, array $viewport_widths, array $expected_counts ): void {
		$current_etag     = md5( '' );
		$group_collection = new OD_URL_Metric_Group_Collection( array(), $current_etag, $breakpoints, $sample_size, HOUR_IN_SECONDS );
		$this->assertFalse( $group_collection->is_any_group_populated() );
		$this->assertFalse( $group_collection->is_any_group_populated() ); // To check the result cache.

		// Over-populate the sample size for the breakpoints by a dozen.
		foreach ( $viewport_widths as $viewport_width => $count ) {
			for ( $i = 0; $i < $count; $i++ ) {
				$group_collection->add_url_metric( $this->get_sample_url_metric( array( 'viewport_width' => $viewport_width ) ) );
				$this->assertTrue( $group_collection->is_any_group_populated() );
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
	 */
	public function test_adding_pushes_out_old_metrics(): void {
		$current_etag     = md5( '' );
		$sample_size      = 3;
		$breakpoints      = array( 400, 600 );
		$group_collection = new OD_URL_Metric_Group_Collection( array(), $current_etag, $breakpoints, $sample_size, HOUR_IN_SECONDS );

		// Populate the groups with stale URL Metrics.
		$viewport_widths = array( 300, 500, 700 );
		$old_timestamp   = microtime( true ) - ( HOUR_IN_SECONDS + 1 );

		foreach ( $viewport_widths as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				$group_collection->add_url_metric(
					new OD_URL_Metric(
						array_merge(
							$this->get_sample_url_metric( array( 'viewport_width' => $viewport_width ) )->jsonSerialize(),
							array(
								'timestamp' => $old_timestamp,
							)
						)
					)
				);
			}
		}

		// Try adding one URL Metric for each breakpoint group.
		foreach ( $viewport_widths as $viewport_width ) {
			$group_collection->add_url_metric( $this->get_sample_url_metric( array( 'viewport_width' => $viewport_width ) ) );
		}

		$max_possible_url_metrics_count = $sample_size * ( count( $breakpoints ) + 1 );
		$this->assertCount(
			$max_possible_url_metrics_count,
			$group_collection->get_flattened_url_metrics(),
			'Expected the total count of URL Metrics to not exceed the multiple of the sample size.'
		);
		$new_count = 0;
		foreach ( $group_collection->get_flattened_url_metrics() as $url_metric ) {
			if ( $url_metric->get_timestamp() > $old_timestamp ) {
				++$new_count;
			}
		}
		$this->assertGreaterThan( 0, $new_count, 'Expected there to be at least one new URL Metric.' );
		$this->assertSame( count( $viewport_widths ), $new_count, 'Expected the new URL Metrics to all have been added.' );
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
			'0-breakpoints-and-4-viewport-widths' => array(
				'breakpoints'     => array(),
				'viewport_widths' => array( 250, 500, 1000 ),
				'expected_groups' => array(
					array(
						'minimum_viewport_width'     => 0,
						'maximum_viewport_width'     => PHP_INT_MAX,
						'url_metric_viewport_widths' => array( 250, 500, 1000 ),
					),
				),
			),
		);
	}

	/**
	 * Test getIterator(), get_first_group(), and get_last_group().
	 *
	 * @covers ::getIterator
	 * @covers ::get_first_group
	 * @covers ::get_last_group
	 *
	 * @dataProvider data_provider_test_get_iterator
	 *
	 * @param int[]             $breakpoints Breakpoints.
	 * @param int[]             $viewport_widths Viewport widths.
	 * @param array<int, mixed> $expected_groups Expected groups.
	 */
	public function test_get_iterator( array $breakpoints, array $viewport_widths, array $expected_groups ): void {
		$url_metrics = array_map(
			function ( $viewport_width ) {
				return $this->get_sample_url_metric( array( 'viewport_width' => $viewport_width ) );
			},
			$viewport_widths
		);

		$current_etag     = md5( '' );
		$group_collection = new OD_URL_Metric_Group_Collection( $url_metrics, $current_etag, $breakpoints, 3, HOUR_IN_SECONDS );

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

		$groups_array = iterator_to_array( $group_collection );
		$this->assertSame( $groups_array[0], $group_collection->get_first_group() );
		$this->assertSame( $groups_array[ count( $groups_array ) - 1 ], $group_collection->get_last_group() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_get_group_for_viewport_width(): array {
		$current_time = microtime( true );
		$current_etag = md5( '' );

		$none_needed_data = array(
			'url_metrics'   => ( function () use ( $current_time, $current_etag ): array {
				return array_merge(
					array_fill(
						0,
						3,
						new OD_URL_Metric(
							array_merge(
								$this->get_sample_url_metric(
									array(
										'viewport_width' => 400,
										'etag'           => $current_etag,
									)
								)->jsonSerialize(),
								array( 'timestamp' => $current_time )
							)
						)
					),
					array_fill(
						0,
						3,
						new OD_URL_Metric(
							array_merge(
								$this->get_sample_url_metric(
									array(
										'viewport_width' => 600,
										'etag'           => $current_etag,
									)
								)->jsonSerialize(),
								array( 'timestamp' => $current_time )
							)
						)
					)
				);
			} )(),
			'current_time'  => $current_time,
			'current_etag'  => $current_etag,
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

			'url-metric-stale-etag'  => array_merge(
				( static function ( $data ): array {
					$url_metrics_data = $data['url_metrics'][ count( $data['url_metrics'] ) - 1 ]->jsonSerialize();
					$url_metrics_data['etag'] = md5( 'something new!' );
					$data['url_metrics'][ count( $data['url_metrics'] ) - 1 ] = new OD_URL_Metric( $url_metrics_data );
					return $data;
				} )( $none_needed_data ),
				array(
					'expected_return'            => array(
						array(
							'minimumViewportWidth' => 0,
							'complete'             => true,
						),
						array(
							'minimumViewportWidth' => 481,
							'complete'             => false,
						),
					),
					'expected_is_group_complete' => array(
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
	 * Test get_minimum_viewport_width().
	 *
	 * @covers ::get_group_for_viewport_width
	 * @covers ::getIterator
	 * @covers OD_URL_Metric_Group::is_complete
	 * @covers OD_URL_Metric_Group::get_minimum_viewport_width
	 *
	 * @dataProvider data_provider_test_get_group_for_viewport_width
	 *
	 * @param OD_URL_Metric[]   $url_metrics URL Metrics.
	 * @param float             $current_time Current time.
	 * @param non-empty-string  $current_etag Current ETag.
	 * @param int[]             $breakpoints Breakpoints.
	 * @param int               $sample_size Sample size.
	 * @param int               $freshness_ttl Freshness TTL.
	 * @param array<int, mixed> $expected_return Expected return.
	 * @param array<int, bool>  $expected_is_group_complete Expected is group complete.
	 */
	public function test_get_group_for_viewport_width( array $url_metrics, float $current_time, string $current_etag, array $breakpoints, int $sample_size, int $freshness_ttl, array $expected_return, array $expected_is_group_complete ): void {
		$group_collection = new OD_URL_Metric_Group_Collection( $url_metrics, $current_etag, $breakpoints, $sample_size, $freshness_ttl );
		$this->assertSame(
			$expected_return,
			array_map(
				static function ( OD_URL_Metric_Group $group ): array {
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
	 * Test is_any_group_populated(), is_every_group_populated(), and is_every_group_complete().
	 *
	 * @covers ::is_any_group_populated
	 * @covers ::is_every_group_populated
	 * @covers ::is_every_group_complete
	 */
	public function test_is_every_group_populated(): void {
		$breakpoints      = array( 480, 800 );
		$sample_size      = 3;
		$current_etag     = md5( '' );
		$group_collection = new OD_URL_Metric_Group_Collection(
			array(),
			$current_etag,
			$breakpoints,
			$sample_size,
			HOUR_IN_SECONDS
		);
		$this->assertFalse( $group_collection->is_any_group_populated() );
		$this->assertFalse( $group_collection->is_any_group_populated() ); // Check cached value.
		$this->assertFalse( $group_collection->is_every_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_populated() ); // Check cached value.
		$this->assertFalse( $group_collection->is_every_group_complete() );
		$this->assertFalse( $group_collection->is_every_group_complete() ); // Check cached value.
		$group_collection->add_url_metric(
			$this->get_sample_url_metric(
				array(
					'viewport_width' => 200,
					'etag'           => $current_etag,
				)
			)
		);
		$this->assertTrue( $group_collection->is_any_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_complete() );
		$group_collection->add_url_metric(
			$this->get_sample_url_metric(
				array(
					'viewport_width' => 500,
					'etag'           => $current_etag,
				)
			)
		);
		$this->assertTrue( $group_collection->is_any_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_complete() );
		$group_collection->add_url_metric(
			$this->get_sample_url_metric(
				array(
					'viewport_width' => 900,
					'etag'           => $current_etag,
				)
			)
		);
		$this->assertTrue( $group_collection->is_any_group_populated() );
		$this->assertTrue( $group_collection->is_every_group_populated() );
		$this->assertFalse( $group_collection->is_every_group_complete() );

		// Now finish completing all the groups.
		foreach ( array_merge( $breakpoints, array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				$group_collection->add_url_metric(
					$this->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'etag'           => $current_etag,
						)
					)
				);
			}
		}
		$this->assertTrue( $group_collection->is_every_group_complete() );
	}

	/**
	 * Test get_groups_by_lcp_element().
	 *
	 * @covers ::get_groups_by_lcp_element
	 * @covers ::get_common_lcp_element
	 */
	public function test_get_groups_by_lcp_element(): void {

		$first_child_image_xpath  = '/HTML/BODY/DIV/*[1][self::IMG]';
		$second_child_image_xpath = '/HTML/BODY/DIV/*[2][self::IMG]';
		$first_child_h1_xpath     = '/HTML/BODY/DIV/*[1][self::H1]';

		$get_url_metric_with_one_lcp_element = function ( int $viewport_width, string $lcp_element_xpath ): OD_URL_Metric {
			return $this->get_sample_url_metric(
				array(
					'viewport_width' => $viewport_width,
					'element'        => array(
						'isLCP' => true,
						'xpath' => $lcp_element_xpath,
					),
				)
			);
		};

		$breakpoints      = array( 480, 800 );
		$sample_size      = 3;
		$current_etag     = md5( '' );
		$group_collection = new OD_URL_Metric_Group_Collection(
			array(
				// Group 1: 0-480 viewport widths.
				$get_url_metric_with_one_lcp_element( 400, $first_child_image_xpath ),
				$get_url_metric_with_one_lcp_element( 420, $first_child_image_xpath ),
				$get_url_metric_with_one_lcp_element( 440, $second_child_image_xpath ),
				// Group 2: 481-800 viewport widths.
				$get_url_metric_with_one_lcp_element( 500, $first_child_h1_xpath ),
				// Group 3: 801-Infinity viewport widths.
				$get_url_metric_with_one_lcp_element( 820, $first_child_image_xpath ),
				$get_url_metric_with_one_lcp_element( 900, $first_child_image_xpath ),
			),
			$current_etag,
			$breakpoints,
			$sample_size,
			HOUR_IN_SECONDS
		);

		$this->assertCount( 3, $group_collection );
		$groups = iterator_to_array( $group_collection );
		$group1 = $groups[0];
		$this->assertSame( $group1, $group_collection->get_group_for_viewport_width( 480 ) );
		$group2 = $groups[1];
		$this->assertSame( $group2, $group_collection->get_group_for_viewport_width( 800 ) );
		$group3 = $groups[2];
		$this->assertSame( $group3, $group_collection->get_group_for_viewport_width( 801 ) );

		$this->assertSameSets( array( $group1, $group3 ), $group_collection->get_groups_by_lcp_element( $first_child_image_xpath ) );
		$this->assertSameSets( array( $group2 ), $group_collection->get_groups_by_lcp_element( $first_child_h1_xpath ) );
		$this->assertCount( 0, $group_collection->get_groups_by_lcp_element( $second_child_image_xpath ) );

		$this->assertNull( $group_collection->get_common_lcp_element() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_test_get_common_lcp_element(): array {
		$xpath1 = '/HTML/BODY/DIV/*[1][self::IMG]';
		$xpath2 = '/HTML/BODY/DIV/*[2][self::IMG]';

		$get_sample_url_metric = function ( int $viewport_width, string $lcp_element_xpath, bool $is_lcp = true ): OD_URL_Metric {
			return $this->get_sample_url_metric(
				array(
					'viewport_width' => $viewport_width,
					'element'        => array(
						'isLCP' => $is_lcp,
						'xpath' => $lcp_element_xpath,
					),
				)
			);
		};

		return array(
			'all_groups_have_common_lcp'             => array(
				'url_metrics' => array(
					$get_sample_url_metric( 400, $xpath1 ),
					$get_sample_url_metric( 600, $xpath1 ),
					$get_sample_url_metric( 1000, $xpath1 ),
				),
				'expected'    => $xpath1,
			),
			'no_url_metrics'                         => array(
				'url_metrics' => array(),
				'expected'    => null,
			),
			'empty_first_group'                      => array(
				'url_metrics' => array(
					$get_sample_url_metric( 600, $xpath1 ),
					$get_sample_url_metric( 1000, $xpath1 ),
				),
				'expected'    => null,
			),
			'empty_last_group'                       => array(
				'url_metrics' => array(
					$get_sample_url_metric( 400, $xpath1 ),
					$get_sample_url_metric( 600, $xpath1 ),
				),
				'expected'    => null,
			),
			'first_and_last_common_lcp_others_empty' => array(
				'url_metrics' => array(
					$get_sample_url_metric( 400, $xpath1 ),
					$get_sample_url_metric( 1000, $xpath1 ),
				),
				'expected'    => $xpath1,
			),
			'intermediate_groups_conflict'           => array(
				'url_metrics' => array(
					$get_sample_url_metric( 400, $xpath1 ),
					$get_sample_url_metric( 600, $xpath2 ),
					$get_sample_url_metric( 1000, $xpath1 ),
				),
				'expected'    => null,
			),
			'first_and_last_lcp_mismatch'            => array(
				'url_metrics' => array(
					$get_sample_url_metric( 400, $xpath1 ),
					$get_sample_url_metric( 600, $xpath1 ),
					$get_sample_url_metric( 1000, $xpath2 ),
				),
				'expected'    => null,
			),
			'no_lcp_metrics'                         => array(
				'url_metrics' => array(
					$get_sample_url_metric( 400, $xpath1, false ),
					$get_sample_url_metric( 600, $xpath1, false ),
					$get_sample_url_metric( 1000, $xpath1, false ),
				),
				'expected'    => null,
			),
		);
	}

	/**
	 * Test get_common_lcp_element().
	 *
	 * @covers ::get_common_lcp_element
	 *
	 * @dataProvider data_provider_test_get_common_lcp_element
	 *
	 * @param OD_URL_Metric[] $url_metrics URL Metrics.
	 * @param string|null     $expected    Expected.
	 */
	public function test_get_common_lcp_element( array $url_metrics, ?string $expected ): void {
		$breakpoints      = array( 480, 800 );
		$sample_size      = 3;
		$current_etag     = md5( '' );
		$group_collection = new OD_URL_Metric_Group_Collection(
			$url_metrics,
			$current_etag,
			$breakpoints,
			$sample_size,
			HOUR_IN_SECONDS
		);

		$this->assertCount( 3, $group_collection );

		$common_lcp_element = $group_collection->get_common_lcp_element();
		$this->assertSame( $common_lcp_element, $group_collection->get_common_lcp_element() ); // Check cached value.
		if ( is_string( $expected ) ) {
			$this->assertInstanceOf( OD_Element::class, $common_lcp_element );
			$this->assertSame( $expected, $common_lcp_element->get_xpath() );
		} else {
			$this->assertNull( $common_lcp_element );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_element_max_intersection_ratios(): array {
		$xpath1 = '/HTML/BODY/DIV/*[1][self::IMG]';
		$xpath2 = '/HTML/BODY/DIV/*[2][self::IMG]';
		$xpath3 = '/HTML/BODY/DIV/*[3][self::IMG]';

		$get_sample_url_metric = function ( int $viewport_width, string $lcp_element_xpath, float $intersection_ratio ): OD_URL_Metric {
			return $this->get_sample_url_metric(
				array(
					'viewport_width' => $viewport_width,
					'element'        => array(
						'isLCP'             => true,
						'xpath'             => $lcp_element_xpath,
						'intersectionRatio' => $intersection_ratio,
					),
				)
			);
		};

		return array(
			'one-element-one-group'           => array(
				'url_metrics' => array(
					$get_sample_url_metric( 600, $xpath1, 0.5 ),
				),
				'expected'    => array(
					$xpath1 => 0.5,
				),
			),
			'one-element-three-groups-of-one' => array(
				'url_metrics' => array(
					$get_sample_url_metric( 400, $xpath1, 0.0 ),
					$get_sample_url_metric( 600, $xpath1, 0.5 ),
					$get_sample_url_metric( 800, $xpath1, 1.0 ),
				),
				'expected'    => array(
					$xpath1 => 1.0,
				),
			),
			'three-elements-sample-size-two'  => array(
				'url_metrics' => array(
					// Group 1.
					$get_sample_url_metric( 400, $xpath1, 0.0 ),
					$get_sample_url_metric( 400, $xpath1, 1.0 ),
					// Group 2.
					$get_sample_url_metric( 600, $xpath2, 0.9 ),
					$get_sample_url_metric( 600, $xpath2, 0.1 ),
					// Group 3.
					$get_sample_url_metric( 800, $xpath3, 0.5 ),
					$get_sample_url_metric( 800, $xpath3, 0.6 ),
				),
				'expected'    => array(
					$xpath1 => 1.0,
					$xpath2 => 0.9,
					$xpath3 => 0.6,
				),
			),
			'no-url-metrics'                  => array(
				'url_metrics' => array(),
				'expected'    => array(),
			),

		);
	}

	/**
	 * Test get_all_element_max_intersection_ratios(), get_element_max_intersection_ratio(), and get_all_denormalized_elements().
	 *
	 * @covers ::get_all_element_max_intersection_ratios
	 * @covers OD_URL_Metric_Group::get_all_element_max_intersection_ratios
	 * @covers ::get_element_max_intersection_ratio
	 * @covers OD_URL_Metric_Group::get_element_max_intersection_ratio
	 * @covers ::get_xpath_elements_map
	 * @covers OD_URL_Metric_Group::get_xpath_elements_map
	 *
	 * @dataProvider data_provider_element_max_intersection_ratios
	 *
	 * @param array<string, mixed> $url_metrics URL Metrics.
	 * @param array<string, float> $expected    Expected.
	 */
	public function test_get_all_element_max_intersection_ratios( array $url_metrics, array $expected ): void {
		$current_etag     = md5( '' );
		$breakpoints      = array( 480, 600, 782 );
		$sample_size      = 3;
		$group_collection = new OD_URL_Metric_Group_Collection( $url_metrics, $current_etag, $breakpoints, $sample_size, 0 );
		$actual           = $group_collection->get_all_element_max_intersection_ratios();
		$this->assertSame( $actual, $group_collection->get_all_element_max_intersection_ratios(), 'Cached result is identical.' );
		$this->assertSame( $expected, $actual );
		foreach ( $expected as $expected_xpath => $expected_max_ratio ) {
			$this->assertSame( $expected_max_ratio, $group_collection->get_element_max_intersection_ratio( $expected_xpath ) );
		}

		$this->assertNull( $group_collection->get_element_max_intersection_ratio( '/HTML/BODY/DIV/*[1][self::BLINK]' ) );

		// Check get_all_denormalized_elements.
		$all_elements = $group_collection->get_xpath_elements_map();
		$this->assertSame( $all_elements, $group_collection->get_xpath_elements_map() ); // Check cached value.
		$xpath_counts = array();
		foreach ( $url_metrics as $url_metric ) {
			foreach ( $url_metric->get_elements() as $element ) {
				if ( ! isset( $xpath_counts[ $element['xpath'] ] ) ) {
					$xpath_counts[ $element['xpath'] ] = 0;
				}
				$xpath_counts[ $element['xpath'] ] += 1;
			}
		}
		$this->assertCount( count( $xpath_counts ), $all_elements );
		foreach ( $all_elements as $xpath => $elements ) {
			foreach ( $elements as $element ) {
				$this->assertSame( $element->get_url_metric()->get_group(), $element->get_url_metric_group() );
				$this->assertSame( $xpath, $element->get_xpath() );
			}
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_element_minimum_heights(): array {
		$xpath1 = '/HTML/BODY/DIV/*[1][self::IMG]';
		$xpath2 = '/HTML/BODY/DIV/*[2][self::IMG]';
		$xpath3 = '/HTML/BODY/DIV/*[3][self::IMG]';

		$get_sample_url_metric = function ( int $viewport_width, string $lcp_element_xpath, float $element_height ): OD_URL_Metric {
			return $this->get_sample_url_metric(
				array(
					'viewport_width' => $viewport_width,
					'element'        => array(
						'isLCP'              => true,
						'xpath'              => $lcp_element_xpath,
						'intersectionRect'   => array_merge(
							$this->get_sample_dom_rect(),
							array( 'height' => $element_height )
						),
						'boundingClientRect' => array_merge(
							$this->get_sample_dom_rect(),
							array( 'height' => $element_height )
						),
					),
				)
			);
		};

		return array(
			'one-element-sample-size-one'    => array(
				'url_metrics' => array(
					$get_sample_url_metric( 400, $xpath1, 480 ),
					$get_sample_url_metric( 600, $xpath1, 240 ),
					$get_sample_url_metric( 800, $xpath1, 768 ),
				),
				'expected'    => array(
					$xpath1 => 240.0,
				),
			),
			'three-elements-sample-size-two' => array(
				'url_metrics' => array(
					// Group 1.
					$get_sample_url_metric( 400, $xpath1, 400 ),
					$get_sample_url_metric( 400, $xpath1, 600 ),
					// Group 2.
					$get_sample_url_metric( 600, $xpath2, 100.1 ),
					$get_sample_url_metric( 600, $xpath2, 100.2 ),
					$get_sample_url_metric( 600, $xpath2, 100.05 ),
					// Group 3.
					$get_sample_url_metric( 800, $xpath3, 500 ),
					$get_sample_url_metric( 800, $xpath3, 500 ),
				),
				'expected'    => array(
					$xpath1 => 400.0,
					$xpath2 => 100.05,
					$xpath3 => 500.0,
				),
			),
			'no-url-metrics'                 => array(
				'url_metrics' => array(),
				'expected'    => array(),
			),

		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_get_all_elements_positioned_in_any_initial_viewport(): array {
		$xpath1 = '/HTML/BODY/DIV/*[1][self::IMG]';
		$xpath2 = '/HTML/BODY/DIV/*[2][self::IMG]';

		$get_sample_url_metric = function ( int $viewport_width, int $viewport_height, string $xpath, float $intersection_ratio, float $top ): OD_URL_Metric {
			return $this->get_sample_url_metric(
				array(
					'viewport_width'  => $viewport_width,
					'viewport_height' => $viewport_height,
					'element'         => array(
						'isLCP'              => false,
						'xpath'              => $xpath,
						'intersectionRatio'  => $intersection_ratio,
						'intersectionRect'   => array_merge(
							$this->get_sample_dom_rect(),
							array( 'top' => $top )
						),
						'boundingClientRect' => array_merge(
							$this->get_sample_dom_rect(),
							array( 'top' => $top )
						),
					),
				)
			);
		};

		return array(
			'element-inside-viewport'                  => array(
				'url_metrics' => array(
					$get_sample_url_metric( 360, 640, $xpath1, 1.0, 0 ),
					$get_sample_url_metric( 360, 640, $xpath1, 1.0, 100 ),
					$get_sample_url_metric( 360, 640, $xpath1, 1.0, 639 ),
				),
				'expected'    => array(
					$xpath1 => true,
				),
			),
			'element-outside-viewport'                 => array(
				'url_metrics' => array(
					$get_sample_url_metric( 360, 640, $xpath1, 0.0, 640 ),
					$get_sample_url_metric( 360, 640, $xpath1, 0.0, 641 ),
				),
				'expected'    => array(
					$xpath1 => false,
				),
			),
			'two-elements-inside-and-outside-viewport' => array(
				'url_metrics' => array(
					$get_sample_url_metric( 360, 640, $xpath1, 1.0, 100 ),
					$get_sample_url_metric( 360, 640, $xpath2, 0.0, 1000 ),
				),
				'expected'    => array(
					$xpath1 => true,
					$xpath2 => false,
				),
			),
		);
	}

	/**
	 * Test get_all_elements_positioned_in_any_initial_viewport() and is_element_positioned_in_any_initial_viewport().
	 *
	 * @covers ::get_all_elements_positioned_in_any_initial_viewport
	 * @covers ::is_element_positioned_in_any_initial_viewport
	 *
	 * @dataProvider data_provider_get_all_elements_positioned_in_any_initial_viewport
	 *
	 * @param array<string, mixed> $url_metrics URL Metrics.
	 * @param array<string, bool>  $expected    Expected.
	 */
	public function test_get_all_elements_positioned_in_any_initial_viewport( array $url_metrics, array $expected ): void {
		$current_etag     = md5( '' );
		$breakpoints      = array( 480, 600, 782 );
		$sample_size      = 3;
		$group_collection = new OD_URL_Metric_Group_Collection( $url_metrics, $current_etag, $breakpoints, $sample_size, 0 );
		$actual           = $group_collection->get_all_elements_positioned_in_any_initial_viewport();
		$this->assertSame( $actual, $group_collection->get_all_elements_positioned_in_any_initial_viewport(), 'Cached result is identical.' );
		$this->assertSame( $expected, $actual );
		foreach ( $expected as $expected_xpath => $expected_is_positioned ) {
			$this->assertSame( $expected_is_positioned, $group_collection->is_element_positioned_in_any_initial_viewport( $expected_xpath ) );
		}
		$this->assertNull( $group_collection->is_element_positioned_in_any_initial_viewport( '/HTML/BODY/DIV/*[1][self::BLINK]' ) );
	}

	/**
	 * Test get_flattened_url_metrics().
	 *
	 * @covers ::get_flattened_url_metrics
	 */
	public function test_get_flattened_url_metrics(): void {
		$url_metrics = array(
			$this->get_sample_url_metric( array( 'viewport_width' => 400 ) ),
			$this->get_sample_url_metric( array( 'viewport_width' => 600 ) ),
			$this->get_sample_url_metric( array( 'viewport_width' => 800 ) ),
		);

		$group_collection = new OD_URL_Metric_Group_Collection(
			$url_metrics,
			md5( '' ),
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
	 * Test jsonSerialize().
	 *
	 * @covers ::jsonSerialize
	 */
	public function test_json_serialize(): void {
		$url_metrics = array(
			$this->get_sample_url_metric( array( 'viewport_width' => 400 ) ),
			$this->get_sample_url_metric( array( 'viewport_width' => 600 ) ),
			$this->get_sample_url_metric( array( 'viewport_width' => 800 ) ),
		);

		$group_collection = new OD_URL_Metric_Group_Collection(
			$url_metrics,
			md5( '' ),
			array( 500, 700 ),
			3,
			HOUR_IN_SECONDS
		);

		$json          = wp_json_encode( $group_collection );
		$parsed_json   = json_decode( $json, true );
		$expected_keys = array(
			'current_etag',
			'breakpoints',
			'freshness_ttl',
			'sample_size',
			'all_element_max_intersection_ratios',
			'common_lcp_element',
			'every_group_complete',
			'every_group_populated',
			'groups',
		);
		$this->assertIsArray( $parsed_json );
		$this->assertSameSets(
			$expected_keys,
			array_keys( $parsed_json )
		);
	}
}
