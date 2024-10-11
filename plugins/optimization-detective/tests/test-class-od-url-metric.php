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
	public function data_provider_to_test_constructor(): array {
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
			// This tests that sanitization converts values into their expected PHP types.
			'valid_but_props_are_strings'     => array(
				'data' => array(
					'url'       => home_url( '/' ),
					'viewport'  => array_map( 'strval', $viewport ),
					'timestamp' => (string) microtime( true ),
					'elements'  => array(
						array_map(
							static function ( $value ) {
								if ( is_array( $value ) ) {
									return array_map( 'strval', $value );
								} else {
									return (string) $value;
								}
							},
							$valid_element
						),
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
	 * @covers ::get
	 * @covers ::get_json_schema
	 *
	 * @dataProvider data_provider_to_test_constructor
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

		$this->assertSame( array_map( 'intval', $data['viewport'] ), $url_metric->get_viewport() );
		$this->assertSame( array_map( 'intval', $data['viewport'] ), $url_metric->get( 'viewport' ) );
		$this->assertSame( (int) $data['viewport']['width'], $url_metric->get_viewport_width() );

		$this->assertSame( (float) $data['timestamp'], $url_metric->get_timestamp() );
		$this->assertSame( (float) $data['timestamp'], $url_metric->get( 'timestamp' ) );

		$this->assertCount( count( $data['elements'] ), $url_metric->get_elements() );
		for ( $i = 0, $length = count( $data['elements'] ); $i < $length; $i++ ) {
			$this->assertSame( (bool) $data['elements'][ $i ]['isLCP'], $url_metric->get_elements()[ $i ]['isLCP'] );
			$this->assertSame( (bool) $data['elements'][ $i ]['isLCPCandidate'], $url_metric->get_elements()[ $i ]['isLCPCandidate'] );
			$this->assertSame( (float) $data['elements'][ $i ]['intersectionRatio'], $url_metric->get_elements()[ $i ]['intersectionRatio'] );
			$this->assertSame( array_map( 'floatval', $data['elements'][ $i ]['boundingClientRect'] ), $url_metric->get_elements()[ $i ]['boundingClientRect'] );
			$this->assertSame( array_map( 'floatval', $data['elements'][ $i ]['intersectionRect'] ), $url_metric->get_elements()[ $i ]['intersectionRect'] );
		}
		$this->assertSame( $url_metric->get_elements(), $url_metric->get( 'elements' ) );

		$this->assertSame( $data['url'], $url_metric->get_url() );
		$this->assertSame( $data['url'], $url_metric->get( 'url' ) );

		$this->assertTrue( wp_is_uuid( $url_metric->get_uuid() ) );
		$this->assertSame( $url_metric->get_uuid(), $url_metric->get( 'uuid' ) );

		$serialized = $url_metric->jsonSerialize();
		if ( ! array_key_exists( 'uuid', $data ) ) {
			$this->assertTrue( wp_is_uuid( $serialized['uuid'] ) );
			unset( $serialized['uuid'] );
		}

		// The use of assertEquals instead of assertSame ensures that lossy type comparisons are employed.
		$this->assertEquals( $data, $serialized );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_to_test_constructor_with_extended_schema(): array {
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
			'added_valid_root_property_populated'        => array(
				'set_up' => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( array $properties ): array {
							$properties['isTouch'] = array(
								'type' => 'boolean',
							);
							return $properties;
						}
					);
				},
				'data'   => array(
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(),
					'isTouch'   => 1, // This should get cast to `true` after the extended schema has applied.
				),
				'assert' => function ( OD_URL_Metric $extended_url_metric, OD_URL_Metric $original_url_metric ): void {
					$this->assertSame( $original_url_metric->get_viewport(), $extended_url_metric->get_viewport() );

					$original_data = $original_url_metric->jsonSerialize();
					$this->assertArrayHasKey( 'isTouch', $original_data );
					$this->assertSame( 1, $original_data['isTouch'] );
					$this->assertSame( 1, $original_url_metric->get( 'isTouch' ) );

					$extended_data = $extended_url_metric->jsonSerialize();
					$this->assertArrayHasKey( 'isTouch', $extended_data );
					$this->assertTrue( $extended_data['isTouch'] );
					$this->assertTrue( $extended_url_metric->get( 'isTouch' ) );
				},
			),

			'added_valid_root_property_not_populated'    => array(
				'set_up' => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( array $properties ): array {
							$properties['isTouch'] = array(
								'type' => 'boolean',
							);
							return $properties;
						}
					);
				},
				'data'   => array(
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(),
				),
				'assert' => function ( OD_URL_Metric $extended_url_metric, OD_URL_Metric $original_url_metric ): void {
					$this->assertSame( $original_url_metric->get_viewport(), $extended_url_metric->get_viewport() );

					$original_data = $original_url_metric->jsonSerialize();
					$this->assertArrayNotHasKey( 'isTouch', $original_data );
					$this->assertNull( $original_url_metric->get( 'isTouch' ) );

					$extended_data = $extended_url_metric->jsonSerialize();
					$this->assertArrayNotHasKey( 'isTouch', $extended_data ); // If rest_sanitize_value_from_schema() took default into account (and we allowed defaults), this could be different.
					$this->assertNull( $extended_url_metric->get( 'isTouch' ) );
				},
			),

			'added_invalid_root_property'                => array(
				'set_up' => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( array $properties ): array {
							$properties['isTouch'] = array(
								'type' => 'boolean',
							);
							return $properties;
						}
					);
				},
				'data'   => array(
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(),
					'isTouch'   => array( 'cannot be cast to boolean' ),
				),
				'assert' => static function (): void {},
				'error'  => 'OD_URL_Metric[isTouch] is not of type boolean.',
			),

			'added_valid_element_property_populated'     => array(
				'set_up' => static function (): void {
					add_filter(
						'od_url_metric_schema_element_item_additional_properties',
						static function ( array $properties ): array {
							$properties['isColorful'] = array(
								'type' => 'boolean',
							);
							return $properties;
						}
					);
				},
				'data'   => array(
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(
						array_merge(
							$valid_element,
							array( 'isColorful' => 'false' )
						),
					),
				),
				'assert' => function ( OD_URL_Metric $extended_url_metric, OD_URL_Metric $original_url_metric ): void {
					$this->assertSame( $original_url_metric->get_viewport(), $extended_url_metric->get_viewport() );

					$original_data = $original_url_metric->jsonSerialize();
					$this->assertArrayHasKey( 'isColorful', $original_data['elements'][0] );
					$this->assertSame( 'false', $original_data['elements'][0]['isColorful'] );
					$this->assertSame( 'false', $original_url_metric->get_elements()[0]['isColorful'] );

					$extended_data = $extended_url_metric->jsonSerialize();
					$this->assertArrayHasKey( 'isColorful', $extended_data['elements'][0] );
					$this->assertFalse( $extended_data['elements'][0]['isColorful'] );
					$this->assertFalse( $extended_url_metric->get_elements()[0]['isColorful'] );
				},
			),

			'added_valid_element_property_not_populated' => array(
				'set_up' => static function (): void {
					add_filter(
						'od_url_metric_schema_element_item_additional_properties',
						static function ( array $properties ): array {
							$properties['isColorful'] = array(
								'type' => 'boolean',
							);
							return $properties;
						}
					);
				},
				'data'   => array(
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array( $valid_element ),
				),
				'assert' => function ( OD_URL_Metric $extended_url_metric, OD_URL_Metric $original_url_metric ): void {
					$this->assertSame( $original_url_metric->get_viewport(), $extended_url_metric->get_viewport() );

					$original_data = $original_url_metric->jsonSerialize();
					$this->assertArrayNotHasKey( 'isColorful', $original_data['elements'][0] );

					$extended_data = $extended_url_metric->jsonSerialize();
					$this->assertArrayNotHasKey( 'isColorful', $extended_data['elements'][0] );  // If rest_sanitize_value_from_schema() took default into account (and we allowed defaults), this could be different.
				},
			),

			'added_invalid_element_property'             => array(
				'set_up' => static function (): void {
					add_filter(
						'od_url_metric_schema_element_item_additional_properties',
						static function ( array $properties ): array {
							$properties['isColorful'] = array(
								'type' => 'boolean',
							);
							return $properties;
						}
					);
				},
				'data'   => array(
					'url'       => home_url( '/' ),
					'viewport'  => $viewport,
					'timestamp' => microtime( true ),
					'elements'  => array(
						array_merge(
							$valid_element,
							array( 'isColorful' => array( 'cannot be cast to boolean' ) )
						),
					),
				),
				'assert' => static function (): void {},
				'error'  => 'OD_URL_Metric[elements][0][isColorful] is not of type boolean.',
			),
		);
	}

	/**
	 * Tests construction with extended schema.
	 *
	 * @covers ::get_json_schema
	 *
	 * @dataProvider data_provider_to_test_constructor_with_extended_schema
	 *
	 * @param Closure              $set_up Set up to extend schema.
	 * @param array<string, mixed> $data   Data.
	 * @param Closure              $assert Assert.
	 * @param string               $error  Error.
	 */
	public function test_constructor_with_extended_schema( Closure $set_up, array $data, Closure $assert, string $error = '' ): void {
		if ( '' !== $error ) {
			$this->expectException( OD_Data_Validation_Exception::class );
			$this->expectExceptionMessage( $error );
		}
		$url_metric_sans_extended_schema = new OD_URL_Metric( $data );
		$set_up();
		$url_metric_with_extended_schema = new OD_URL_Metric( $data );
		$assert( $url_metric_with_extended_schema, $url_metric_sans_extended_schema );
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
				'expected_incorrect_usage' => 'Filter: &#039;od_url_metric_schema_root_additional_properties&#039;',
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
				'expected_incorrect_usage' => 'Filter: &#039;od_url_metric_schema_root_additional_properties&#039;',
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
				'expected_incorrect_usage' => 'Filter: &#039;od_url_metric_schema_root_additional_properties&#039;',
			),

			'bad_default'                    => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( $additional_properties ) {
							$additional_properties['foo'] = array(
								'type'    => 'string',
								'default' => 'bar',
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
				'expected_incorrect_usage' => 'Filter: &#039;od_url_metric_schema_root_additional_properties&#039;',
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
				'expected_incorrect_usage' => 'Filter: &#039;od_url_metric_schema_root_additional_properties&#039;',
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
				'expected_incorrect_usage' => 'Filter: &#039;od_url_metric_schema_root_additional_properties&#039;',
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
			if ( 'root/viewport' === $path || 'root/elements/items/intersectionRect' === $path || 'root/elements/items/boundingClientRect' === $path ) {
				$this->assertFalse( $schema['additionalProperties'], "Path: $path" );
			} else {
				$this->assertTrue( $schema['additionalProperties'], "Path: $path" );
			}
			foreach ( $schema['properties'] as $key => $property_schema ) {
				$this->check_schema_subset( $property_schema, "$path/$key", $extended );
			}
		} elseif ( 'array' === $schema['type'] ) {
			$this->assertArrayHasKey( 'items', $schema, $path );
			$this->check_schema_subset( $schema['items'], "$path/items", $extended );
		}
	}
}
