<?php
/**
 * Optimization Detective: OD_URL_Metric class
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Representation of the measurements taken from a single client's visit to a specific URL.
 *
 * @phpstan-type ViewportRect array{
 *                                width: int,
 *                                height: int
 *                            }
 * @phpstan-type DOMRect      array{
 *                                width: float,
 *                                height: float,
 *                                x: float,
 *                                y: float,
 *                                top: float,
 *                                right: float,
 *                                bottom: float,
 *                                left: float
 *                            }
 * @phpstan-type ElementData  array{
 *                                isLCP: bool,
 *                                isLCPCandidate: bool,
 *                                xpath: string,
 *                                intersectionRatio: float,
 *                                intersectionRect: DOMRect,
 *                                boundingClientRect: DOMRect,
 *                            }
 * @phpstan-type Data         array{
 *                                uuid: string,
 *                                url: string,
 *                                timestamp: float,
 *                                viewport: ViewportRect,
 *                                elements: ElementData[]
 *                            }
 *
 * @since 0.1.0
 * @access private
 */
class OD_URL_Metric implements JsonSerializable {

	/**
	 * Data.
	 *
	 * @var Data
	 */
	protected $data;

	/**
	 * Constructor.
	 *
	 * @phpstan-param Data|array<string, mixed> $data Valid data or invalid data (in which case an exception is thrown).
	 *
	 * @throws OD_Data_Validation_Exception When the input is invalid.
	 *
	 * @param array<string, mixed> $data URL metric data.
	 */
	public function __construct( array $data ) {
		if ( ! isset( $data['uuid'] ) ) {
			$data['uuid'] = wp_generate_uuid4();
		}
		$this->data = $this->prepare_data( $data );
	}

	/**
	 * Prepares data with validation and sanitization.
	 *
	 * @throws OD_Data_Validation_Exception When the input is invalid.
	 *
	 * @param array<string, mixed> $data Data to validate.
	 * @return Data Validated and sanitized data.
	 */
	private function prepare_data( array $data ): array {
		$schema = static::get_json_schema();
		$valid  = rest_validate_object_value_from_schema( $data, $schema, self::class );
		if ( is_wp_error( $valid ) ) {
			throw new OD_Data_Validation_Exception( esc_html( $valid->get_error_message() ) );
		}
		$aspect_ratio     = $data['viewport']['width'] / $data['viewport']['height'];
		$min_aspect_ratio = od_get_minimum_viewport_aspect_ratio();
		$max_aspect_ratio = od_get_maximum_viewport_aspect_ratio();
		if (
			$aspect_ratio < $min_aspect_ratio ||
			$aspect_ratio > $max_aspect_ratio
		) {
			throw new OD_Data_Validation_Exception(
				esc_html(
					sprintf(
						/* translators: 1: current aspect ratio, 2: minimum aspect ratio, 3: maximum aspect ratio */
						__( 'Viewport aspect ratio (%1$s) is not in the accepted range of %2$s to %3$s.', 'optimization-detective' ),
						$aspect_ratio,
						$min_aspect_ratio,
						$max_aspect_ratio
					)
				)
			);
		}
		return rest_sanitize_value_from_schema( $data, $schema, self::class );
	}

	/**
	 * Gets JSON schema for URL Metric.
	 *
	 * @todo Cache the return value?
	 *
	 * @return array<string, mixed> Schema.
	 */
	public static function get_json_schema(): array {
		/*
		 * The intersectionRect and boundingClientRect are both instances of the DOMRectReadOnly, which
		 * the following schema describes. See <https://developer.mozilla.org/en-US/docs/Web/API/DOMRectReadOnly>.
		 * Note that 'number' is used specifically instead of 'integer' because the values are all specified as
		 * floats/doubles.
		 */
		$dom_rect_properties = array_fill_keys(
			array(
				'width',
				'height',
				'x',
				'y',
				'top',
				'right',
				'bottom',
				'left',
			),
			array(
				'type'     => 'number',
				'required' => true,
			)
		);

		// The spec allows these to be negative but this doesn't make sense in the context of intersectionRect and boundingClientRect.
		$dom_rect_properties['width']['minimum']  = 0.0;
		$dom_rect_properties['height']['minimum'] = 0.0;

		$dom_rect_schema = array(
			'type'                 => 'object',
			'required'             => true,
			'properties'           => $dom_rect_properties,
			'additionalProperties' => false,
		);

		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'od-url-metric',
			'type'                 => 'object',
			'required'             => true,
			'properties'           => array(
				'uuid'      => array(
					'description' => __( 'The UUID for the URL metric.', 'optimization-detective' ),
					'type'        => 'string',
					'format'      => 'uuid',
					'required'    => true,
					'readonly'    => true, // Omit from REST API.
				),
				'url'       => array(
					'description' => __( 'The URL for which the metric was obtained.', 'optimization-detective' ),
					'type'        => 'string',
					'required'    => true,
					'format'      => 'uri',
					'pattern'     => '^https?://',
				),
				'viewport'  => array(
					'description'          => __( 'Viewport dimensions', 'optimization-detective' ),
					'type'                 => 'object',
					'required'             => true,
					'properties'           => array(
						'width'  => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 0,
						),
						'height' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 0,
						),
					),
					'additionalProperties' => false,
				),
				'timestamp' => array(
					'description' => __( 'Timestamp at which the URL metric was captured.', 'optimization-detective' ),
					'type'        => 'number',
					'required'    => true,
					'readonly'    => true, // Omit from REST API.
					'minimum'     => 0,
				),
				'elements'  => array(
					'description' => __( 'Element metrics', 'optimization-detective' ),
					'type'        => 'array',
					'required'    => true,
					'items'       => array(
						// See the ElementMetrics in detect.js.
						'type'                 => 'object',
						'required'             => true,
						'properties'           => array(
							'isLCP'              => array(
								'type'     => 'boolean',
								'required' => true,
							),
							'isLCPCandidate'     => array(
								'type'     => 'boolean',
								'required' => true,
							),
							'xpath'              => array(
								'type'     => 'string',
								'required' => true,
								'pattern'  => OD_HTML_Tag_Processor::XPATH_PATTERN,
							),
							'intersectionRatio'  => array(
								'type'     => 'number',
								'required' => true,
								'minimum'  => 0.0,
								'maximum'  => 1.0,
							),
							'intersectionRect'   => $dom_rect_schema,
							'boundingClientRect' => $dom_rect_schema,
						),

						// Additional properties may be added to the schema for items of elements via the od_url_metric_schema_element_item_additional_properties filter.
						// See further explanation below.
						'additionalProperties' => true,
					),
				),
			),
			// Additional root properties may be added to the schema via the od_url_metric_schema_root_additional_properties filter.
			// Therefore, additionalProperties is set to true so that additional properties defined in the extended schema may persist
			// in a stored URL Metric even when the extension is deactivated. For REST API requests, the OD_Strict_URL_Metric
			// which sets this to false so that newly-submitted URL Metrics only ever include the known properties.
			'additionalProperties' => true,
		);

		/**
		 * Filters additional schema properties which should be allowed at the root of a URL metric.
		 *
		 * @since 0.6.0
		 *
		 * @param array<string, array{type: string}> $additional_properties Additional properties.
		 */
		$additional_properties = (array) apply_filters( 'od_url_metric_schema_root_additional_properties', array() );
		if ( count( $additional_properties ) > 0 ) {
			$schema['properties'] = self::extend_schema_with_optional_properties( $schema['properties'], $additional_properties, 'od_url_metric_schema_root_additional_properties' );
		}

		/**
		 * Filters additional schema properties which should be allowed for an elements item in a URL metric.
		 *
		 * @since 0.6.0
		 *
		 * @param array<string, array{type: string}> $additional_properties Additional properties.
		 */
		$additional_properties = (array) apply_filters( 'od_url_metric_schema_element_item_additional_properties', array() );
		if ( count( $additional_properties ) > 0 ) {
			$schema['properties']['elements']['items']['properties'] = self::extend_schema_with_optional_properties(
				$schema['properties']['elements']['items']['properties'],
				$additional_properties,
				'od_url_metric_schema_root_additional_properties'
			);
		}

		return $schema;
	}

	/**
	 * Extends a schema with additional optional properties.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string, mixed> $properties_schema     Properties schema to extend.
	 * @param array<string, mixed> $additional_properties Additional properties.
	 * @param string               $filter_name           Filter name used to extend.
	 *
	 * @return array<string, mixed> Extended schema.
	 */
	protected static function extend_schema_with_optional_properties( array $properties_schema, array $additional_properties, string $filter_name ): array {
		$doing_it_wrong = static function ( string $message ) use ( $filter_name ): void {
			_doing_it_wrong(
				esc_html( "Filter: '{$filter_name}'" ),
				esc_html( $message ),
				'Optimization Detective 0.6.0'
			);
		};
		foreach ( $additional_properties as $property_key => $property_schema ) {
			if ( ! is_array( $property_schema ) ) {
				continue;
			}
			if ( isset( $properties_schema[ $property_key ] ) ) {
				$doing_it_wrong(
					sprintf(
						/* translators: property name */
						__( 'Disallowed override of existing schema property "%s".', 'optimization-detective' ),
						$property_key
					)
				);
				continue;
			}
			if ( ! isset( $property_schema['type'] ) || ! ( is_string( $property_schema['type'] ) || is_array( $property_schema['type'] ) ) ) {
				$doing_it_wrong(
					sprintf(
						/* translators: 1: property name, 2: 'type' */
						__( 'Supplied schema property "%1$s" with missing "%2$s" key.', 'optimization-detective' ),
						'type',
						$property_key
					)
				);
				continue;
			}
			if ( isset( $property_schema['required'] ) && false !== $property_schema['required'] ) {
				$doing_it_wrong(
					sprintf(
						/* translators: 1: property name, 2: 'required' */
						__( 'Supplied schema property "%1$s" has a truthy value for "%2$s". All extended properties must be optional so that URL Metrics are not all immediately invalidated once an extension is deactivated.', 'optimization-detective' ),
						$property_key,
						'required'
					)
				);
			}
			$property_schema['required'] = false;

			if ( isset( $property_schema['default'] ) ) {
				$doing_it_wrong(
					sprintf(
						/* translators: 1: property name, 2: 'default' */
						__( 'Supplied schema property "%1$s" has disallowed "%2$s" specified.', 'optimization-detective' ),
						$property_key,
						'default'
					)
				);
				unset( $property_schema['default'] );
			}

			$properties_schema[ $property_key ] = $property_schema;
		}
		return $properties_schema;
	}

	/**
	 * Gets property value for an arbitrary key.
	 *
	 * This is particularly useful in conjunction with the `od_url_metric_schema_root_additional_properties` filter.
	 *
	 * @since 0.6.0
	 *
	 * @param string $key Property.
	 * @return mixed|null The property value, or null if not set.
	 */
	public function get( string $key ) {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * Gets UUID.
	 *
	 * @return string UUID.
	 */
	public function get_uuid(): string {
		return $this->data['uuid'];
	}

	/**
	 * Gets URL.
	 *
	 * @return string URL.
	 */
	public function get_url(): string {
		return $this->data['url'];
	}

	/**
	 * Gets viewport data.
	 *
	 * @return ViewportRect Viewport data.
	 */
	public function get_viewport(): array {
		return $this->data['viewport'];
	}

	/**
	 * Gets viewport width.
	 *
	 * @return int Viewport width.
	 */
	public function get_viewport_width(): int {
		return $this->data['viewport']['width'];
	}

	/**
	 * Gets timestamp.
	 *
	 * @return float Timestamp.
	 */
	public function get_timestamp(): float {
		return $this->data['timestamp'];
	}

	/**
	 * Gets elements.
	 *
	 * @return ElementData[] Elements.
	 */
	public function get_elements(): array {
		return $this->data['elements'];
	}

	/**
	 * Specifies data which should be serialized to JSON.
	 *
	 * @return Data Exports to be serialized by json_encode().
	 */
	public function jsonSerialize(): array {
		return $this->data;
	}
}
