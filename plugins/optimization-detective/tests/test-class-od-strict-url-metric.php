<?php
/**
 * Tests for optimization-detective class OD_Strict_URL_Metric.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_Strict_URL_Metric
 */
class Test_OD_Strict_URL_Metric extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * Tests get_json_schema().
	 *
	 * @covers ::get_json_schema
	 */
	public function test_get_json_schema(): void {
		add_filter(
			'od_url_metric_schema_root_additional_properties',
			static function ( array $properties ) {
				$properties['colors'] = array(
					'type'                 => 'object',
					'properties'           => array(
						'hex' => array(
							'type' => 'string',
						),
					),
					'additionalProperties' => true,
				);
				return $properties;
			}
		);
		add_filter(
			'od_url_metric_schema_element_item_additional_properties',
			static function ( array $properties ) {
				$properties['region'] = array(
					'type'                 => 'object',
					'properties'           => array(
						'continent' => array(
							'type' => 'string',
						),
					),
					'additionalProperties' => true,
				);
				return $properties;
			}
		);
		$loose_schema = OD_URL_Metric::get_json_schema();
		$this->assertTrue( $loose_schema['additionalProperties'] );
		$this->assertFalse( $loose_schema['properties']['viewport']['additionalProperties'] ); // The viewport is never extensible. Only the root and the elements are.
		$this->assertTrue( $loose_schema['properties']['elements']['items']['additionalProperties'] );
		$this->assertTrue( $loose_schema['properties']['elements']['items']['properties']['region']['additionalProperties'] );
		$this->assertTrue( $loose_schema['properties']['colors']['additionalProperties'] );

		$strict_schema = OD_Strict_URL_Metric::get_json_schema();
		$this->assertFalse( $strict_schema['additionalProperties'] );
		$this->assertFalse( $strict_schema['properties']['viewport']['additionalProperties'] );
		$this->assertFalse( $strict_schema['properties']['elements']['items']['additionalProperties'] );
		$this->assertFalse( $strict_schema['properties']['elements']['items']['properties']['region']['additionalProperties'] );
		$this->assertFalse( $strict_schema['properties']['colors']['additionalProperties'] );
	}
}
