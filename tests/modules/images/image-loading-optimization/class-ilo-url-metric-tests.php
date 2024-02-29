<?php
/**
 * Tests for image-loading-optimization class ILO_URL_Metric.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 *
 * @coversDefaultClass ILO_URL_Metric
 */
class ILO_URL_Metric_Tests extends WP_UnitTestCase {

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_provider(): array {
		$viewport = array(
			'width'  => 640,
			'height' => 480,
		);

		return array(
			'valid_minimal'          => array(
				'data' => array(
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
			),
			'valid_with_element'     => array(
				'data' => array(
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(
						array(
							'isLCP'             => true,
							'isLCPCandidate'    => true,
							'xpath'             => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
							'intersectionRatio' => 1.0,
						),
					),
				),
			),
			'missing_viewport'       => array(
				'data'  => array(
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'viewport is a required property of ILO_URL_Metric.',
			),
			'missing_viewport_width' => array(
				'data'  => array(
					'viewport'  => array( 'height' => 640 ),
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'width is a required property of ILO_URL_Metric[viewport].',
			),
			'bad_viewport'           => array(
				'data'  => array(
					'viewport'  => array(
						'height' => 'tall',
						'width'  => 'wide',
					),
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'ILO_URL_Metric[viewport][height] is not of type integer.',
			),
			'missing_timestamp'      => array(
				'data'  => array(
					'viewport' => $viewport,
					'elements' => array(),
				),
				'error' => 'timestamp is a required property of ILO_URL_Metric.',
			),
			'missing_elements'       => array(
				'data'  => array(
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
				),
				'error' => 'elements is a required property of ILO_URL_Metric.',
			),
			'bad_elements'           => array(
				'data'  => array(
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(
						array(
							'isElSeePee' => true,
						),
					),
				),
				'error' => 'isLCP is a required property of ILO_URL_Metric[elements][0].',
			),
		);
	}

	/**
	 * Tests construction.
	 *
	 * @covers ::get_viewport
	 * @covers ::get_timestamp
	 * @covers ::get_elements
	 * @covers ::jsonSerialize
	 *
	 * @dataProvider data_provider
	 */
	public function test_constructor( array $data, string $error = '' ) {
		if ( $error ) {
			$this->expectException( ILO_Data_Validation_Exception::class );
			$this->expectExceptionMessage( $error );
		}
		$url_metric = new ILO_URL_Metric( $data );
		$this->assertSame( $data['viewport'], $url_metric->get_viewport() );
		$this->assertSame( $data['timestamp'], $url_metric->get_timestamp() );
		$this->assertSame( $data['elements'], $url_metric->get_elements() );
		$this->assertEquals( $data, $url_metric->jsonSerialize() );
	}

	/**
	 * Tests get_json_schema().
	 *
	 * @covers ::get_json_schema
	 */
	public function test_get_json_schema() {
		$schema = ILO_URL_Metric::get_json_schema();
		$this->assertArrayHasKey( 'properties', $schema );
	}
}
