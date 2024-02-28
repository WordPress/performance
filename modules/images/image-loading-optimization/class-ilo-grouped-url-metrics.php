<?php
/**
 * Image Loading Optimization: ILO_Grouped_URL_Metric_Collection class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * URL metrics grouped by minimum viewport width for the provided breakpoints.
 *
 * @since n.e.x.t
 * @access private
 */
final class ILO_Grouped_URL_Metrics {

	/**
	 * URL metrics grouped by minimum viewport width for the provided breakpoints.
	 *
	 * @var array<int, ILO_URL_Metric[]>
	 */
	private $groups;

	/**
	 * Breakpoints in max widths.
	 *
	 * @var int[]
	 */
	private $breakpoints;

	/**
	 * Sample size for URL metrics for a given breakpoint.
	 *
	 * @var int
	 */
	private $sample_size;

	/**
	 * Freshness age (TTL) for a given URL metric.
	 *
	 * @var int
	 */
	private $freshness_ttl;

	/**
	 * Constructor.
	 *
	 * @param ILO_URL_Metric[] $url_metrics   URL metrics.
	 * @param int[]            $breakpoints   Breakpoints in max widths.
	 * @param int              $sample_size   Sample size for the maximum number of viewports in a group between breakpoints.
	 * @param int              $freshness_ttl Freshness age (TTL) for a given URL metric.
	 */
	public function __construct( array $url_metrics, array $breakpoints, int $sample_size, int $freshness_ttl ) {
		$this->breakpoints   = $breakpoints;
		$this->sample_size   = $sample_size;
		$this->freshness_ttl = $freshness_ttl;
		$this->groups        = $this->ilo_group_url_metrics_by_breakpoint( $url_metrics );
	}

	/**
	 * Gets grouped keyed by the minimum viewport width.
	 *
	 * @return array<int, ILO_URL_Metric[]> Groups.
	 */
	public function get_groups(): array {
		return $this->groups;
	}

	/**
	 * Gets minimum viewport widths for the groups of URL metrics divided by the breakpoints.
	 *
	 * @return int[]
	 */
	public function get_minimum_viewport_widths(): array {
		return array_keys( $this->groups );
	}

	/**
	 * Unshifts a new URL metric, potentially pushing out older URL metrics when exceeding the sample size.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @param ILO_URL_Metric $new_url_metric New URL metric.
	 */
	public function ilo_unshift_url_metrics( ILO_URL_Metric $new_url_metric ) {
		$url_metrics = $this->flatten();
		array_unshift( $url_metrics, $new_url_metric );

		$grouped_url_metrics = $this->ilo_group_url_metrics_by_breakpoint( $url_metrics );

		// Make sure there is at most $sample_size number of URL metrics for each breakpoint.
		$grouped_url_metrics = array_map(
			function ( $breakpoint_url_metrics ) {
				if ( count( $breakpoint_url_metrics ) > $this->sample_size ) {

					// Sort URL metrics in descending order by timestamp.
					usort(
						$breakpoint_url_metrics,
						static function ( ILO_URL_Metric $a, ILO_URL_Metric $b ): int {
							return $b->get_timestamp() <=> $a->get_timestamp();
						}
					);

					// Only keep the sample size of the newest URL metrics.
					$breakpoint_url_metrics = array_slice( $breakpoint_url_metrics, 0, $this->sample_size );
				}
				return $breakpoint_url_metrics;
			},
			$grouped_url_metrics
		);

		$this->groups = $grouped_url_metrics;
	}

	/**
	 * Groups URL metrics by breakpoint.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @param ILO_URL_Metric[] $url_metrics URL metrics.
	 * @return array<int, ILO_URL_Metric[]> URL metrics grouped by breakpoint. The array keys are the minimum widths for a viewport to lie within
	 *                                      the breakpoint. The returned array is always one larger than the provided array of breakpoints, since
	 *                                      the breakpoints reflect the max inclusive boundaries whereas the return value is the groups of page
	 *                                      metrics with viewports on either side of the breakpoint boundaries.
	 */
	private function ilo_group_url_metrics_by_breakpoint( array $url_metrics ): array {

		// Convert breakpoint max widths into viewport minimum widths.
		$minimum_viewport_widths = array_map(
			static function ( $breakpoint ) {
				return $breakpoint + 1;
			},
			$this->breakpoints
		);

		$grouped = array_fill_keys( array_merge( array( 0 ), $minimum_viewport_widths ), array() );

		foreach ( $url_metrics as $url_metric ) {
			$current_minimum_viewport = 0;
			foreach ( $minimum_viewport_widths as $viewport_minimum_width ) {
				if ( $url_metric->get_viewport()['width'] > $viewport_minimum_width ) {
					$current_minimum_viewport = $viewport_minimum_width;
				} else {
					break;
				}
			}

			$grouped[ $current_minimum_viewport ][] = $url_metric;
		}
		return $grouped;
	}

	/**
	 * Gets needed minimum viewport widths.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @param float $current_time Current time, defaults to `microtime(true)`.
	 * @return array<int, array{int, bool}> Array of tuples mapping minimum viewport width to whether URL metric(s) are needed.
	 */
	public function ilo_get_needed_minimum_viewport_widths( float $current_time = null ): array {
		if ( null === $current_time ) {
			$current_time = microtime( true );
		}

		$needed_minimum_viewport_widths = array();
		foreach ( $this->groups as $minimum_viewport_width => $viewport_url_metrics ) {
			$needs_url_metrics = false;
			if ( count( $viewport_url_metrics ) < $this->sample_size ) {
				$needs_url_metrics = true;
			} else {
				foreach ( $viewport_url_metrics as $url_metric ) {
					if ( $url_metric->get_timestamp() + $this->freshness_ttl < $current_time ) {
						$needs_url_metrics = true;
						break;
					}
				}
			}
			$needed_minimum_viewport_widths[] = array(
				$minimum_viewport_width,
				$needs_url_metrics,
			);
		}

		return $needed_minimum_viewport_widths;
	}

	/**
	 * Gets the LCP element for each breakpoint.
	 *
	 * The array keys are the minimum viewport width required for the element to be LCP. If there are URL metrics for a
	 * given breakpoint and yet there is no supported LCP element, then the array value is `false`. (Currently only IMG is
	 * a supported LCP element.) If there is a supported LCP element at the breakpoint, then the array value is an array
	 * representing that element, including its breadcrumbs. If two adjoining breakpoints have the same value, then the
	 * latter is dropped.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @return array LCP elements keyed by its minimum viewport width. If there is no supported LCP element at a breakpoint, then `false` is used.
	 */
	public function ilo_get_lcp_elements_by_minimum_viewport_widths(): array {
		$lcp_element_by_viewport_minimum_width = array();
		foreach ( $this->groups as $viewport_minimum_width => $breakpoint_url_metrics ) {

			// The following arrays all share array indices.
			$seen_breadcrumbs   = array();
			$breadcrumb_counts  = array();
			$breadcrumb_element = array();

			foreach ( $breakpoint_url_metrics as $breakpoint_url_metric ) {
				foreach ( $breakpoint_url_metric->get_elements() as $element ) {
					if ( ! $element['isLCP'] ) {
						continue;
					}

					$i = array_search( $element['xpath'], $seen_breadcrumbs, true );
					if ( false === $i ) {
						$i                       = count( $seen_breadcrumbs );
						$seen_breadcrumbs[ $i ]  = $element['xpath'];
						$breadcrumb_counts[ $i ] = 0;
					}

					$breadcrumb_counts[ $i ] += 1;
					$breadcrumb_element[ $i ] = $element;
					break; // We found the LCP element for the URL metric, go to the next URL metric.
				}
			}

			// Now sort by the breadcrumb counts in descending order, so the remaining first key is the most common breadcrumb.
			if ( $seen_breadcrumbs ) {
				arsort( $breadcrumb_counts );
				$most_common_breadcrumb_index = key( $breadcrumb_counts );

				$lcp_element_by_viewport_minimum_width[ $viewport_minimum_width ] = $breadcrumb_element[ $most_common_breadcrumb_index ];
			} elseif ( ! empty( $breakpoint_url_metrics ) ) {
				$lcp_element_by_viewport_minimum_width[ $viewport_minimum_width ] = false; // No LCP image at this breakpoint.
			}
		}

		// Now merge the breakpoints when there is an LCP element common between them.
		$prev_lcp_element = null;
		return array_filter(
			$lcp_element_by_viewport_minimum_width,
			static function ( $lcp_element ) use ( &$prev_lcp_element ) {
				$include = (
					// First element in list.
					null === $prev_lcp_element
					||
					( is_array( $prev_lcp_element ) && is_array( $lcp_element )
						?
						// This breakpoint and previous breakpoint had LCP element, and they were not the same element.
						$prev_lcp_element['xpath'] !== $lcp_element['xpath']
						:
						// This LCP element and the last LCP element were not the same. In this case, either variable may be
						// false or an array, but both cannot be an array. If both are false, we don't want to include since
						// it is the same. If one is an array and the other is false, then do want to include because this
						// indicates a difference at this breakpoint.
						$prev_lcp_element !== $lcp_element
					)
				);
				$prev_lcp_element = $lcp_element;
				return $include;
			}
		);
	}

	/**
	 * Checks whether all groups have URL metrics.
	 *
	 * @return bool
	 */
	public function all_breakpoints_have_url_metrics(): bool {
		foreach ( $this->groups as $group ) {
			if ( empty( $group ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Flatten groups of URL metrics into an array of URL metrics.
	 *
	 * @return ILO_URL_Metric[] URL metrics.
	 */
	public function flatten(): array {
		return array_merge(
			...array_values( $this->groups )
		);
	}
}
