<?php
/**
 * Tests for OD_URL_Metric_Group.
 *
 * @package optimization-detective
 *
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpDocMissingThrowsInspection
 *
 * @coversDefaultClass OD_URL_Metric_Group
 */

class Test_OD_URL_Metric_Group extends WP_UnitTestCase {
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
							'etag'      => md5( '' ),
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
	 * @covers ::get_sample_size
	 * @covers ::get_freshness_ttl
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

		// This is not example usage for how a group should be constructed. Normally, a group is only ever constructed
		// by OD_URL_Metric_Group_Collection when the collection is constructed. The OD_URL_Metric_Group is being
		// constructed here just for the sake of testing.
		$dummy_collection = new OD_URL_Metric_Group_Collection( array(), md5( '' ), array(), 1, DAY_IN_SECONDS );
		$group            = new OD_URL_Metric_Group( $url_metrics, $minimum_viewport_width, $maximum_viewport_width, $sample_size, $freshness_ttl, $dummy_collection );

		$this->assertCount( count( $url_metrics ), $group );
		$this->assertSame( $minimum_viewport_width, $group->get_minimum_viewport_width() );
		$this->assertSame( $maximum_viewport_width, $group->get_maximum_viewport_width() );
		$this->assertSame( $sample_size, $group->get_sample_size() );
		$this->assertSame( $freshness_ttl, $group->get_freshness_ttl() );
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
				'breakpoints'              => array( 10 ),
				'group_index'              => 0,
				'viewport_widths_expected' => array(
					0  => true,
					1  => true,
					9  => true,
					10 => true,
					11 => false,
				),
			),
			'100-200' => array(
				'breakpoints'              => array( 99, 200 ),
				'group_index'              => 1,
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
	 * @param int[]            $breakpoints              Breakpoints.
	 * @param int              $group_index              Group index.
	 * @param array<int, bool> $viewport_widths_expected Viewport widths expected.
	 */
	public function test_is_viewport_width_in_range( array $breakpoints, int $group_index, array $viewport_widths_expected ): void {
		$collection = new OD_URL_Metric_Group_Collection(
			array(),
			md5( '' ),
			$breakpoints,
			3,
			HOUR_IN_SECONDS
		);
		$groups     = iterator_to_array( $collection );
		$this->assertArrayHasKey( $group_index, $groups );
		$group = $groups[ $group_index ];
		$this->assertInstanceOf( OD_URL_Metric_Group::class, $group );
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
				'viewport_width' => 400,
				'exception'      => InvalidArgumentException::class,
			),
			'within_range' => array(
				'viewport_width' => 600,
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

		$etag       = md5( '' );
		$collection = new OD_URL_Metric_Group_Collection( array(), $etag, array( 480, 800 ), 1, HOUR_IN_SECONDS );
		$groups     = iterator_to_array( $collection );
		$this->assertCount( 3, $groups );
		$group = $groups[1];

		$this->assertFalse( $group->is_complete() );
		$group->add_url_metric(
			new OD_URL_Metric(
				array(
					'url'       => home_url( '/' ),
					'etag'      => $etag,
					'viewport'  => array(
						'width'  => $viewport_width,
						'height' => ceil( $viewport_width / 2 ),
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
	public function data_provider_test_is_complete(): array {
		// Note: Test cases for empty URL Metrics and for exact sample size are already covered in the test_add_url_metric() method.
		return array(
			'old_url_metric' => array(
				'url_metric'                 => $this->get_sample_url_metric(
					array(
						'timestamp' => microtime( true ) - ( HOUR_IN_SECONDS + 1 ),
						'etag'      => md5( '' ),
					)
				),
				'expected_is_group_complete' => false,
			),
			'etag_mismatch'  => array(
				'url_metric'                 => $this->get_sample_url_metric( array( 'etag' => md5( 'different_etag' ) ) ),
				'expected_is_group_complete' => false,
			),
			'etag_match'     => array(
				'url_metric'                 => $this->get_sample_url_metric( array( 'etag' => md5( '' ) ) ),
				'expected_is_group_complete' => true,
			),
		);
	}

	/**
	 * Test is_complete().
	 *
	 * @covers ::is_complete
	 *
	 * @dataProvider data_provider_test_is_complete
	 */
	public function test_is_complete( OD_URL_Metric $url_metric, bool $expected_is_group_complete ): void {
		$collection = new OD_URL_Metric_Group_Collection( array(), md5( '' ), array( 768 ), 1, HOUR_IN_SECONDS );
		$group      = $collection->get_first_group();

		$group->add_url_metric( $url_metric );

		$this->assertSame( $expected_is_group_complete, $group->is_complete() );
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
		$current_etag     = md5( '' );
		$group_collection = new OD_URL_Metric_Group_Collection( $url_metrics, $current_etag, $breakpoints, 10, HOUR_IN_SECONDS );

		$lcp_element_xpaths_by_minimum_viewport_widths = array();
		foreach ( $group_collection as $group ) {
			$lcp_element = $group->get_lcp_element();
			$this->assertSame( $lcp_element, $group->get_lcp_element() ); // Check cached result.
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
		$current_etag = md5( '' );
		$url_metrics  = array_map(
			function ( $viewport_width ) {
				return $this->get_sample_url_metric( array( 'viewport_width' => $viewport_width ) );
			},
			array( 400, 600, 800 )
		);

		$collection = new OD_URL_Metric_Group_Collection( $url_metrics, $current_etag, array( 1000 ), 1, HOUR_IN_SECONDS );
		$group      = $collection->get_first_group();

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
		$xpath = '';
		foreach ( $breadcrumbs as $i => $tag_name ) {
			if ( $i < 2 || ( 2 === $i && '/HTML/BODY' === $xpath ) ) {
				$xpath .= "/$tag_name";
			} else {
				$xpath .= sprintf( '/*[%d][self::%s]', $i + 1, $tag_name );
			}
		}
		return $xpath;
	}
}
