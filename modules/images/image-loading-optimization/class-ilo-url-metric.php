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
 * @implements ArrayAccess<string, mixed>
 *
 * @since n.e.x.t
 * @access private
 */
final class ILO_URL_Metric implements ArrayAccess, JsonSerializable {

	/**
	 * Gets JSON schema for URL Metric.
	 *
	 * @return array
	 */
	public static function get_json_schema(): array {
		$dom_rect_schema = array(
			'type'       => 'object',
			'properties' => array(
				'width'  => array(
					'type'    => 'number',
					'minimum' => 0,
				),
				'height' => array(
					'type'    => 'number',
					'minimum' => 0,
				),
				// TODO: There are other properties to define if we need them: x, y, top, right, bottom, left.
			),
		);

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ilo-url-metric',
			'type'       => 'object',
			'properties' => array(
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
					'readonly'    => true, // Use the server-provided timestamp.
					'default'     => microtime( true ),
					'minimum'     => 0,
				),
				'elements'  => array(
					'description' => __( 'Element metrics', 'performance-lab' ),
					'type'        => 'array',
					'required'    => true,
					'items'       => array(
						// See the ElementMetrics in detect.js.
						'type'       => 'object',
						'properties' => array(
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
					),
				),
			),
		);
	}

	/**
	 * Validated data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param array $data      URL metric data.
	 * @param bool  $validated Whether the data was already validated.
	 */
	public function __construct( array $data, bool $validated = false ) {
		if ( ! $validated ) {
			// TODO: Validate.
		}
		$this->data = $data;
	}

	/**
	 * Checks if the offset exists.
	 *
	 * @param string $offset Offset.
	 * @return bool Whether exists.
	 */
	public function offsetExists( $offset ): bool {
		return isset( $this->data[ $offset ] );
	}

	/**
	 * Gets offset.
	 *
	 * @throws Exception If the offset does not exist.
	 *
	 * @param string $offset Offset.
	 * @return mixed Value.
	 */
	public function offsetGet( $offset ) {
		if ( ! $this->offsetExists( $offset ) ) {
			throw new Exception( sprintf( __( 'Unknown property %s on ILO_URL_Metric.', 'performance-lab' ), $offset ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $this->data[ $offset ];
	}

	/**
	 * Sets offset (disabled).
	 *
	 * @param string $offset Offset.
	 * @param mixed  $value  Value.
	 * @throws Exception
	 */
	public function offsetSet( $offset, $value ) {
		throw new Exception( __( 'Cannot set properties on ILO_URL_Metric.', 'performance-lab' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Unsets offset (disabled).
	 *
	 * @param string $offset Offset.
	 * @throws Exception
	 */
	public function offsetUnset( $offset ) {
		throw new Exception( __( 'Cannot unset properties on ILO_URL_Metric.', 'performance-lab' ) );
	}

	/**
	 * Gets the JSON representation of the object.
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return $this->data;
	}
}
