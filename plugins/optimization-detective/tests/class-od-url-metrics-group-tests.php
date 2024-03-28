<?php
/**
 * Tests for OD_URL_Metrics_Group.
 *
 * @package optimization-detective
 *
 * @noinspection PhpUnhandledExceptionInspection
 *
 * @coversDefaultClass OD_URL_Metrics_Group
 */

class OD_URL_Metrics_Group_Tests extends WP_UnitTestCase {

	/**
	 * Data provider.
	 *
	 * @throws OD_Data_Validation_Exception If bad arguments are provided to OD_URL_Metric.
	 * @return array
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
	 */
	public function test_construction( array $url_metrics, int $minimum_viewport_width, int $maximum_viewport_width, int $sample_size, int $freshness_ttl, string $exception ) {
		if ( $exception ) {
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
	 * @return array
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
	 */
	public function test_is_viewport_width_in_range( int $minimum_viewport_width, int $maximum_viewport_width, array $viewport_widths_expected ) {
		$group = new OD_URL_Metrics_Group( array(), $minimum_viewport_width, $maximum_viewport_width, 3, HOUR_IN_SECONDS );
		foreach ( $viewport_widths_expected as $viewport_width => $expected ) {
			$this->assertSame( $expected, $group->is_viewport_width_in_range( $viewport_width ), "Failed for viewport width of $viewport_width" );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
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
	public function test_add_url_metric( int $viewport_width, string $exception ) {
		if ( $exception ) {
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
}
