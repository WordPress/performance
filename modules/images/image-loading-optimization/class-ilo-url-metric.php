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
final class ILO_URL_Metric {

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
				'viewport' => array(
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
				'elements' => array(
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
}
