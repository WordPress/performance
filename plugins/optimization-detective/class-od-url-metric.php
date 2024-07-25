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
 * @phpstan-type RectData    array{ width: int, height: int }
 * @phpstan-type ElementData array{
 *                               isLCP: bool,
 *                               isLCPCandidate: bool,
 *                               xpath: string,
 *                               intersectionRatio: float,
 *                               intersectionRect: RectData,
 *                               boundingClientRect: RectData,
 *                           }
 * @phpstan-type Data        array{
 *                               url: string,
 *                               timestamp: float,
 *                               viewport: RectData,
 *                               elements: ElementData[]
 *                           }
 *
 * @since 0.1.0
 * @access private
 */
final class OD_URL_Metric implements JsonSerializable {

	/**
	 * Data.
	 *
	 * @var Data
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @phpstan-param Data|array<string, mixed> $data Valid data or invalid data (in which case an exception is thrown).
	 *
	 * @param array<string, mixed> $data URL metric data.
	 *
	 * @throws OD_Data_Validation_Exception When the input is invalid.
	 */
	public function __construct( array $data ) {
		$this->validate_data( $data );
		$this->data = $data;
	}

	/**
	 * Validate data.
	 *
	 * @phpstan-assert Data $data
	 *
	 * @param array<string, mixed> $data Data to validate.
	 * @throws OD_Data_Validation_Exception When the input is invalid.
	 */
	private function validate_data( array $data ): void {
		$valid = rest_validate_object_value_from_schema( $data, self::get_json_schema(), self::class );
		if ( is_wp_error( $valid ) ) {
			throw new OD_Data_Validation_Exception( esc_html( $valid->get_error_message() ) );
		}
	}

	/**
	 * Gets JSON schema for URL Metric.
	 *
	 * @return array<string, mixed> Schema.
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
			'title'                => 'od-url-metric',
			'type'                 => 'object',
			'properties'           => array(
				'url'       => array(
					'type'        => 'string',
					'description' => __( 'The URL for which the metric was obtained.', 'optimization-detective' ),
					'required'    => true,
					'format'      => 'uri',
					'pattern'     => '^https?://',
				),
				'viewport'  => array(
					'description' => __( 'Viewport dimensions', 'optimization-detective' ),
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
					'description' => __( 'Timestamp at which the URL metric was captured.', 'optimization-detective' ),
					'type'        => 'number',
					'required'    => true,
					'readonly'    => true, // Omit from REST API.
					'default'     => microtime( true ), // Value provided when instantiating OD_URL_Metric in REST API.
					'minimum'     => 0,
				),
				'elements'  => array(
					'description' => __( 'Element metrics', 'optimization-detective' ),
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
						'additionalProperties' => false,
					),
				),
			),
			'additionalProperties' => false,
		);
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
	 * @return RectData Viewport data.
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
