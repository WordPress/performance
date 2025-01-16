<?php
/**
 * Tests for optimization-detective class OD_Element.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_Element
 */
class Test_OD_Element extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * Tests construction.
	 *
	 * @covers ::get
	 * @covers ::get_url_metric
	 * @covers ::get_url_metric_group
	 * @covers ::is_lcp
	 * @covers ::is_lcp_candidate
	 * @covers ::get_xpath
	 * @covers ::get_intersection_ratio
	 * @covers ::get_intersection_rect
	 * @covers ::get_bounding_client_rect
	 * @covers ::offsetExists
	 * @covers ::offsetGet
	 * @covers ::offsetSet
	 * @covers ::offsetUnset
	 * @covers ::jsonSerialize
	 */
	public function test_constructor(): void {
		add_filter(
			'od_url_metric_schema_element_item_additional_properties',
			static function ( array $schema ): array {
				$schema['customProp'] = array(
					'type' => 'string',
				);
				return $schema;
			}
		);

		$element_data = array(
			'xpath'              => '/HTML/BODY/DIV/*[1][self::IMG]',
			'isLCP'              => false,
			'isLCPCandidate'     => true,
			'intersectionRatio'  => 0.123,
			'intersectionRect'   => array(
				'width'  => 100.0,
				'height' => 200.0,
				'x'      => 0.0,
				'y'      => 101.0,
				'top'    => 1.0,
				'right'  => 2000.0,
				'bottom' => 90.0,
				'left'   => 111.0,
			),
			'boundingClientRect' => array(
				'width'  => 200.0,
				'height' => 400.0,
				'x'      => 1.0,
				'y'      => 201.0,
				'top'    => 2.0,
				'right'  => 4000.0,
				'bottom' => 180.0,
				'left'   => 211.0,
			),
			'customProp'         => 'customValue',
		);
		$url_metric   = $this->get_sample_url_metric( array( 'element' => $element_data ) );

		$element = $url_metric->get_elements()[0];
		$this->assertInstanceOf( OD_Element::class, $element );
		$this->assertSame( $url_metric, $element->get_url_metric() );
		$this->assertNull( $element->get_url_metric_group() );
		$current_etag = md5( '' );
		$collection   = new OD_URL_Metric_Group_Collection( array( $url_metric ), $current_etag, array(), 1, DAY_IN_SECONDS );
		$collection->add_url_metric( $url_metric );
		$this->assertSame( iterator_to_array( $collection )[0], $element->get_url_metric_group() );

		$this->assertSame( $element_data['xpath'], $element->get_xpath() );
		$this->assertSame( $element_data['xpath'], $element['xpath'] );
		$this->assertSame( $element_data['xpath'], $element->offsetGet( 'xpath' ) );
		$this->assertSame( $element_data['xpath'], $element->get( 'xpath' ) );
		$this->assertTrue( isset( $element['xpath'] ) );
		$this->assertTrue( $element->offsetExists( 'xpath' ) );

		$this->assertSame( $element_data['isLCP'], $element->is_lcp() );
		$this->assertSame( $element_data['isLCP'], $element['isLCP'] );
		$this->assertSame( $element_data['isLCP'], $element->offsetGet( 'isLCP' ) );
		$this->assertSame( $element_data['isLCP'], $element->get( 'isLCP' ) );
		$this->assertTrue( isset( $element['isLCP'] ) );
		$this->assertTrue( $element->offsetExists( 'isLCP' ) );

		$this->assertSame( $element_data['isLCPCandidate'], $element->is_lcp_candidate() );
		$this->assertSame( $element_data['isLCPCandidate'], $element['isLCPCandidate'] );
		$this->assertSame( $element_data['isLCPCandidate'], $element->offsetGet( 'isLCPCandidate' ) );
		$this->assertSame( $element_data['isLCPCandidate'], $element->get( 'isLCPCandidate' ) );
		$this->assertTrue( isset( $element['isLCPCandidate'] ) );
		$this->assertTrue( $element->offsetExists( 'isLCPCandidate' ) );

		$this->assertSame( $element_data['intersectionRatio'], $element->get_intersection_ratio() );
		$this->assertSame( $element_data['intersectionRatio'], $element['intersectionRatio'] );
		$this->assertSame( $element_data['intersectionRatio'], $element->offsetGet( 'intersectionRatio' ) );
		$this->assertSame( $element_data['intersectionRatio'], $element->get( 'intersectionRatio' ) );
		$this->assertTrue( isset( $element['intersectionRatio'] ) );
		$this->assertTrue( $element->offsetExists( 'intersectionRatio' ) );

		$this->assertSame( $element_data['intersectionRect'], $element->get_intersection_rect() );
		$this->assertSame( $element_data['intersectionRect'], $element['intersectionRect'] );
		$this->assertSame( $element_data['intersectionRect'], $element->offsetGet( 'intersectionRect' ) );
		$this->assertSame( $element_data['intersectionRect'], $element->get( 'intersectionRect' ) );
		$this->assertTrue( isset( $element['intersectionRect'] ) );
		$this->assertTrue( $element->offsetExists( 'intersectionRect' ) );

		$this->assertSame( $element_data['boundingClientRect'], $element->get_bounding_client_rect() );
		$this->assertSame( $element_data['boundingClientRect'], $element['boundingClientRect'] );
		$this->assertSame( $element_data['boundingClientRect'], $element->offsetGet( 'boundingClientRect' ) );
		$this->assertSame( $element_data['boundingClientRect'], $element->get( 'boundingClientRect' ) );
		$this->assertTrue( isset( $element['boundingClientRect'] ) );
		$this->assertTrue( $element->offsetExists( 'boundingClientRect' ) );

		$this->assertNull( $element['notFound'] );
		$this->assertNull( $element->get( 'notFound' ) );
		$this->assertNull( $element->offsetGet( 'notFound' ) ); // @phpstan-ignore argument.templateType (Likely resolved by <https://github.com/phpstan/phpstan/issues/8438>)
		$this->assertFalse( isset( $element['notFound'] ) );
		$this->assertFalse( $element->offsetExists( 'notFound' ) );

		$this->assertSame( $element_data['customProp'], $element['customProp'] ); // TODO: Why is PHPStan not complaining about the argument.templateType here?
		$this->assertSame( $element_data['customProp'], $element->get( 'customProp' ) );
		$this->assertSame( $element_data['customProp'], $element->offsetGet( 'customProp' ) ); // @phpstan-ignore argument.templateType (Likely resolved by <https://github.com/phpstan/phpstan/issues/8438>)
		$this->assertTrue( isset( $element['customProp'] ) );
		$this->assertTrue( $element->offsetExists( 'customProp' ) );

		$this->assertEquals( $element_data, $element->jsonSerialize() );

		$exception = null;
		try {
			$element['isLCP'] = true;
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( Exception::class, $exception );

		$exception = null;
		try {
			unset( $element['isLCP'] );
		} catch ( Exception $e ) { // @phpstan-ignore catch.neverThrown (It is thrown by offsetUnset actually.)
			$exception = $e;
		}
		$this->assertInstanceOf( Exception::class, $exception ); // @phpstan-ignore method.impossibleType (It is thrown by offsetUnset actually.)
	}
}
