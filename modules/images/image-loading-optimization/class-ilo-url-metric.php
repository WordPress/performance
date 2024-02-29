<?php
/**
 * Image Loading Optimization: ILO_URL_Metric class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Representation of the measurements taken from a single client's visit to a specific URL.
 *
 * @since n.e.x.t
 * @access private
 */
final class ILO_URL_Metric implements JsonSerializable {

	/**
	 * Data.
	 *
	 * @var array{
	 *          timestamp: int,
	 *          viewport: array{ width: int, height: int },
	 *          elements: array<array{
	 *              isLCP: bool,
	 *              isLCPCandidate: bool,
	 *              xpath: string,
	 *              intersectionRatio: float,
	 *              intersectionRect: array{ width: int, height: int },
	 *              boundingClientRect: array{ width: int, height: int },
	 *          }>
	 *      }
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param array $data URL metric data.
	 *
	 * @throws ILO_Data_Validation_Exception When the input is invalid.
	 */
	public function __construct( array $data ) {
		$valid = rest_validate_object_value_from_schema( $data, self::get_json_schema(), self::class );
		if ( is_wp_error( $valid ) ) {
			throw new ILO_Data_Validation_Exception( esc_html( $valid->get_error_message() ) );
		}
		$this->data = $data;
	}

	/**
	 * Gets JSON schema for URL Metric.
	 *
	 * @return array Schema.
	 */
	public static function get_json_schema(): array {
		$dom_rect_schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'width'  => array(
					'type'    => 'number',
					'minimum' => 0,
				),
				'height' => array(
					'type'    => 'number',
					'minimum' => 0,
				),
			),
			// TODO: There are other properties to define if we need them: x, y, top, right, bottom, left.
			'additionalProperties' => true,
		);

		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'ilo-url-metric',
			'type'                 => 'object',
			'properties'           => array(
				'viewport'  => array(
					'description' => __( 'Viewport dimensions', 'performance-lab' ),
					'type'        => 'object',
					'required'    => true,
					'properties'  => array(
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
				),
				'timestamp' => array(
					'description' => __( 'Timestamp at which the URL metric was captured.', 'performance-lab' ),
					'type'        => 'number',
					'required'    => true,
					'readonly'    => true, // Omit from REST API.
					'default'     => microtime( true ), // Value provided when instantiating ILO_URL_Metric in REST API.
					'minimum'     => 0,
				),
				'elements'  => array(
					'description' => __( 'Element metrics', 'performance-lab' ),
					'type'        => 'array',
					'required'    => true,
					'items'       => array(
						// See the ElementMetrics in detect.js.
						'type'                 => 'object',
						'properties'           => array(
							'isLCP'              => array(
								'type'     => 'boolean',
								'required' => true,
							),
							'isLCPCandidate'     => array(
								'type' => 'boolean',
							),
							'xpath'              => array(
								'type'     => 'string',
								'required' => true,
								'pattern'  => ILO_HTML_Tag_Processor::XPATH_PATTERN,
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
						'additionalProperties' => false,
					),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Gets viewport width.
	 *
	 * @return array{ width: int, height: int }
	 */
	public function get_viewport(): array {
		return $this->data['viewport'];
	}

	/**
	 * Gets timestamp.
	 *
	 * @return float
	 */
	public function get_timestamp(): float {
		return $this->data['timestamp'];
	}

	/**
	 * Gets elements.
	 *
	 * @return array<array{
	 *             isLCP: bool,
	 *             isLCPCandidate: bool,
	 *             xpath: string,
	 *             intersectionRatio: float,
	 *             intersectionRect: array{ width: int, height: int },
	 *             boundingClientRect: array{ width: int, height: int },
	 *         }>
	 */
	public function get_elements(): array {
		return $this->data['elements'];
	}

	/**
	 * Specifies data which should be serialized to JSON.
	 *
	 * @return array Data which can be serialized by json_encode().
	 */
	public function jsonSerialize(): array {
		return $this->data;
	}
}
