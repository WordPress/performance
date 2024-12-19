<?php
/**
 * Helper trait for Optimization Detective tests.
 *
 * @package performance-lab
 *
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpUnhandledExceptionInspection
 */

/**
 * @phpstan-type ElementDataSubset array{
 *     xpath: string,
 *     isLCP?: bool,
 *     intersectionRatio?: float
 * }
 */
trait Optimization_Detective_Test_Helpers {

	/**
	 * Populates complete URL metrics for the provided element data.
	 *
	 * @phpstan-param ElementDataSubset[] $elements
	 * @param array[] $elements Element data.
	 * @param bool    $complete Whether to fully populate the groups.
	 * @throws Exception But it won't.
	 */
	public function populate_url_metrics( array $elements, bool $complete = true ): void {
		$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$etag        = od_get_current_url_metrics_etag( new OD_Tag_Visitor_Registry(), null, null ); // Note: Tests rely on the od_current_url_metrics_etag_data filter to set the desired value.
		$sample_size = $complete ? od_get_url_metrics_breakpoint_sample_size() : 1;
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$this->get_sample_url_metric(
						array(
							'etag'           => $etag,
							'viewport_width' => $viewport_width,
							'elements'       => $elements,
						)
					)
				);
			}
		}
	}

	/**
	 * Gets a sample DOM rect for testing.
	 *
	 * @return array<string, float>
	 */
	public function get_sample_dom_rect(): array {
		return array(
			'width'  => 500.1,
			'height' => 500.2,
			'x'      => 100.3,
			'y'      => 100.4,
			'top'    => 0.1,
			'right'  => 0.2,
			'bottom' => 0.3,
			'left'   => 0.4,
		);
	}

	/**
	 * Gets a sample URL metric.
	 *
	 * @phpstan-param array{
	 *                    timestamp?:       float,
	 *                    etag?:            non-empty-string,
	 *                    url?:             string,
	 *                    viewport_width?:  int,
	 *                    viewport_height?: int,
	 *                    element?:         ElementDataSubset,
	 *                    elements?:        array<ElementDataSubset>
	 *                } $params Params.
	 *
	 * @return OD_URL_Metric URL metric.
	 */
	public function get_sample_url_metric( array $params ): OD_URL_Metric {
		$params = array_merge(
			array(
				'etag'           => od_get_current_url_metrics_etag( new OD_Tag_Visitor_Registry(), null, null ), // Note: Tests rely on the od_current_url_metrics_etag_data filter to set the desired value.
				'url'            => home_url( '/' ),
				'viewport_width' => 480,
				'elements'       => array(),
				'timestamp'      => microtime( true ),
				'extended_root'  => array(),
			),
			$params
		);

		if ( array_key_exists( 'element', $params ) ) {
			$params['elements'][] = $params['element'];
		}

		$data = array_merge(
			array(
				'etag'      => $params['etag'],
				'url'       => $params['url'],
				'viewport'  => array(
					'width'  => $params['viewport_width'],
					'height' => $params['viewport_height'] ?? ceil( $params['viewport_width'] / 2 ),
				),
				'timestamp' => $params['timestamp'],
				'elements'  => array_map(
					function ( array $element ): array {
						return array_merge(
							array(
								'isLCP'              => false,
								'isLCPCandidate'     => $element['isLCP'] ?? false,
								'intersectionRatio'  => 1,
								'intersectionRect'   => $this->get_sample_dom_rect(),
								'boundingClientRect' => $this->get_sample_dom_rect(),
							),
							$element
						);
					},
					$params['elements']
				),
			),
			$params['extended_root']
		);
		return new OD_URL_Metric( $data );
	}

	/**
	 * Removes initial tabs from the lines in the input.
	 *
	 * @param string $input Input.
	 * @return string Output.
	 */
	public function remove_initial_tabs( string $input ): string {
		return (string) preg_replace( '/^\t+/m', '', $input );
	}

	/**
	 * Gets JSON-serializable data from an array of JsonSerializable objects.
	 *
	 * @param JsonSerializable[] $items Items.
	 * @return array<string|int, mixed> Data from items.
	 */
	public function get_array_json_data( array $items ): array {
		return array_map(
			static function ( JsonSerializable $item ) {
				return $item->jsonSerialize();
			},
			$items
		);
	}
}
