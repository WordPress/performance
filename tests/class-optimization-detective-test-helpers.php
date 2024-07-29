<?php
/**
 * Helper trait for Optimization Detective tests.
 *
 * @package performance-lab
 */

/**
 * @phpstan-type ElementDataSubset array{xpath: string, isLCP?: bool, intersectionRatio: float}
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
		$sample_size = $complete ? od_get_url_metrics_breakpoint_sample_size() : 1;
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$this->get_validated_url_metric(
						$viewport_width,
						$elements
					)
				);
			}
		}
	}

	/**
	 * Gets a sample DOM rect for testing.
	 *
	 * @return int[]
	 */
	public function get_sample_dom_rect(): array {
		return array(
			'width'  => 100,
			'height' => 100,
			'x'      => 100,
			'y'      => 100,
			'top'    => 0,
			'right'  => 0,
			'bottom' => 0,
			'left'   => 0,
		);
	}

	/**
	 * Gets a validated URL metric.
	 *
	 * @param int                      $viewport_width Viewport width for the URL metric.
	 * @param array<ElementDataSubset> $elements       Elements.
	 * @return OD_URL_Metric URL metric.
	 * @throws OD_Data_Validation_Exception From OD_URL_Metric if there is a parse error, but there won't be.
	 */
	public function get_validated_url_metric( int $viewport_width, array $elements = array() ): OD_URL_Metric {
		return new OD_URL_Metric(
			array(
				'url'       => home_url( '/' ),
				'viewport'  => array(
					'width'  => $viewport_width,
					'height' => 800,
				),
				'timestamp' => microtime( true ),
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
					$elements
				),
			)
		);
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
}
