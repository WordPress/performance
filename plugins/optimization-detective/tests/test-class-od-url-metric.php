<?php
/**
 * Tests for optimization-detective class OD_URL_Metric.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_URL_Metric
 */
class Test_OD_URL_Metric extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider(): array {
		$viewport      = array(
			'width'  => 640,
			'height' => 480,
		);
		$valid_element = array(
			'isLCP'              => true,
			'isLCPCandidate'     => true,
			'xpath'              => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::IMG]',
			'intersectionRatio'  => 1.0,
			'intersectionRect'   => $this->get_sample_dom_rect(),
			'boundingClientRect' => $this->get_sample_dom_rect(),
		);

		return array(
			'valid_minimal'                   => array(
				'data' => array(
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
			),
			'valid_with_element'              => array(
				'data' => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(
						$valid_element,
					),
				),
			),
			'bad_uuid'                        => array(
				'data'  => array(
					'uuid'      => 'foo',
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'OD_URL_Metric[uuid] is not a valid UUID.',
			),
			'missing_viewport'                => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'viewport is a required property of OD_URL_Metric.',
			),
			'missing_viewport_width'          => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'viewport'  => array( 'height' => 640 ),
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'width is a required property of OD_URL_Metric[viewport].',
			),
			'bad_viewport'                    => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'viewport'  => array(
						'height' => 'tall',
						'width'  => 'wide',
					),
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'OD_URL_Metric[viewport][height] is not of type integer.',
			),
			'viewport_aspect_ratio_too_small' => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'viewport'  => array(
						'width'  => 1000,
						'height' => 10000,
					),
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'Viewport aspect ratio (0.1) is not in the accepted range of 0.4 to 2.5.',
			),
			'viewport_aspect_ratio_too_large' => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'viewport'  => array(
						'width'  => 10000,
						'height' => 1000,
					),
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'Viewport aspect ratio (10) is not in the accepted range of 0.4 to 2.5.',
			),
			'missing_timestamp'               => array(
				'data'  => array(
					'uuid'     => wp_generate_uuid4(),
					'url'      => home_url( '/' ),
					'viewport' => $viewport,
					'elements' => array(),
				),
				'error' => 'timestamp is a required property of OD_URL_Metric.',
			),
			'missing_elements'                => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
				),
				'error' => 'elements is a required property of OD_URL_Metric.',
			),
			'missing_url'                     => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'error' => 'url is a required property of OD_URL_Metric.',
			),
			'bad_elements'                    => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(
						array(
							'isElSeePee' => true,
						),
					),
				),
				'error' => 'isLCP is a required property of OD_URL_Metric[elements][0].',
			),
			'bad_intersection_width'          => array(
				'data'  => array(
					'uuid'      => wp_generate_uuid4(),
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(
						( static function ( $element ) {
							$element['intersectionRect']['width'] = -10;
							return $element;
						} )( $valid_element ),
					),
				),
				'error' => 'OD_URL_Metric[elements][0][intersectionRect][width] must be greater than or equal to 0',
			),
		);
	}

	/**
	 * Tests construction.
	 *
	 * @covers ::get_viewport
	 * @covers ::get_viewport_width
	 * @covers ::get_timestamp
	 * @covers ::get_elements
	 * @covers ::jsonSerialize
	 *
	 * @dataProvider data_provider
	 *
	 * @param array<string, mixed> $data  Data.
	 * @param string               $error Error.
	 */
	public function test_constructor( array $data, string $error = '' ): void {
		if ( '' !== $error ) {
			$this->expectException( OD_Data_Validation_Exception::class );
			$this->expectExceptionMessage( $error );
		}
		$url_metric = new OD_URL_Metric( $data );
		$this->assertSame( $data['viewport'], $url_metric->get_viewport() );
		$this->assertSame( $data['viewport']['width'], $url_metric->get_viewport_width() );
		$this->assertSame( $data['timestamp'], $url_metric->get_timestamp() );
		$this->assertSame( $data['elements'], $url_metric->get_elements() );
		$this->assertTrue( wp_is_uuid( $url_metric->get_uuid() ) );
		$serialized = $url_metric->jsonSerialize();
		if ( ! array_key_exists( 'uuid', $data ) ) {
			$this->assertTrue( wp_is_uuid( $serialized['uuid'] ) );
			unset( $serialized['uuid'] );
		}
		$this->assertEquals( $data, $serialized );
	}

	/**
	 * Tests get_json_schema().
	 *
	 * @covers ::get_json_schema
	 */
	public function test_get_json_schema(): void {
		$schema = OD_URL_Metric::get_json_schema();
		$this->check_schema_subset( $schema, 'root', false );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_to_test_get_json_schema_extensibility(): array {
		return array(
			'missing_type'                   => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['foo'] = array(
								'missing',
							);
							return $additional_properties;
						}
					);
				},
				'assert'                   => function ( array $original_schema, $extended_schema ): void {
					$this->assertSame( $original_schema, $extended_schema );
				},
				'expected_incorrect_usage' => 'add_filter(od_url_metric_schema_root_additional_properties,...)',
			),

			'bad_type'                       => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['foo'] = array(
								'type' => 123,
							);
							return $additional_properties;
						}
					);
				},
				'assert'                   => function ( array $original_schema, $extended_schema ): void {
					$this->assertSame( $original_schema, $extended_schema );
				},
				'expected_incorrect_usage' => 'add_filter(od_url_metric_schema_root_additional_properties,...)',
			),

			'bad_required'                   => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['foo'] = array(
								'type'     => 'string',
								'required' => true,
							);
							return $additional_properties;
						}
					);
				},
				'assert'                   => function ( array $original_schema, $extended_schema ): void {
					$expected_schema = $original_schema;
					$expected_schema['properties']['foo'] = array(
						'type'     => 'string',
						'required' => false,
					);
					$this->assertSame( $expected_schema, $extended_schema );
				},
				'expected_incorrect_usage' => 'add_filter(od_url_metric_schema_root_additional_properties,...)',
			),

			'bad_existing_root_property'     => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['uuid'] = array(
								'type' => 'number',
							);
							return $additional_properties;
						}
					);
				},
				'assert'                   => function ( array $original_schema, $extended_schema ): void {
					$this->assertSame( $original_schema, $extended_schema );
				},
				'expected_incorrect_usage' => 'add_filter(od_url_metric_schema_root_additional_properties,...)',
			),

			'bad_existing_element_property'  => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_url_metric_schema_element_item_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['intersectionRatio'] = array(
								'type' => 'string',
							);
							return $additional_properties;
						}
					);
				},
				'assert'                   => function ( array $original_schema, $extended_schema ): void {
					$this->assertSame( $original_schema, $extended_schema );
				},
				'expected_incorrect_usage' => 'add_filter(od_url_metric_schema_root_additional_properties,...)',
			),

			'adding_root_string'             => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['foo'] = array(
								'type' => 'string',
							);
							return $additional_properties;
						}
					);
				},
				'assert'                   => function ( array $original_schema, $extended_schema ): void {
					$expected_schema = $original_schema;
					$expected_schema['properties']['foo'] = array(
						'type'     => 'string',
						'required' => false,
					);
					$this->assertSame( $expected_schema, $extended_schema );
				},
				'expected_incorrect_usage' => null,
			),

			'adding_root_and_element_string' => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['foo'] = array(
								'type' => 'string',
							);
							return $additional_properties;
						}
					);
					add_filter(
						'od_url_metric_schema_element_item_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['bar'] = array(
								'type' => 'number',
							);
							return $additional_properties;
						}
					);
				},
				'assert'                   => function ( array $original_schema, $extended_schema ): void {
					$expected_schema = $original_schema;
					$expected_schema['properties']['foo'] = array(
						'type'     => 'string',
						'required' => false,
					);
					$expected_schema['properties']['elements']['items']['properties']['bar'] = array(
						'type'     => 'number',
						'required' => false,
					);
					$this->assertSame( $expected_schema, $extended_schema );
				},
				'expected_incorrect_usage' => null,
			),
		);
	}

	/**
	 * Tests get_json_schema() extensibility.
	 *
	 * @dataProvider data_provider_to_test_get_json_schema_extensibility
	 *
	 * @covers ::get_json_schema
	 */
	public function test_get_json_schema_extensibility( Closure $set_up, Closure $assert, ?string $expected_incorrect_usage ): void {
		if ( null !== $expected_incorrect_usage ) {
			$this->setExpectedIncorrectUsage( $expected_incorrect_usage );
		}
		$original_schema = OD_URL_Metric::get_json_schema();
		$set_up();
		$extended_schema = OD_URL_Metric::get_json_schema();
		$this->check_schema_subset( $extended_schema, 'root', true );
		$assert( $original_schema, $extended_schema );
	}

	/**
	 * Checks schema subset.
	 *
	 * @param array<string, mixed> $schema Schema subset.
	 */
	protected function check_schema_subset( array $schema, string $path, bool $extended = false ): void {
		$this->assertArrayHasKey( 'required', $schema, $path );
		if ( ! $extended ) {
			$this->assertTrue( $schema['required'], $path );
		}
		$this->assertArrayHasKey( 'type', $schema, $path );
		if ( 'object' === $schema['type'] ) {
			$this->assertArrayHasKey( 'properties', $schema, $path );
			$this->assertArrayHasKey( 'additionalProperties', $schema, $path );
			$this->assertFalse( $schema['additionalProperties'] );
			foreach ( $schema['properties'] as $key => $property_schema ) {
				$this->check_schema_subset( $property_schema, "$path/$key", $extended );
			}
		} elseif ( 'array' === $schema['type'] ) {
			$this->assertArrayHasKey( 'items', $schema, $path );
			$this->check_schema_subset( $schema['items'], "$path/items", $extended );
		}
	}
}
