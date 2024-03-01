<?php
/**
 * Image Loading Optimization: ILO_Grouped_URL_Metrics class
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
		$this->groups        = $this->group_url_metrics_by_breakpoint( $url_metrics );
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
	 * Adds a new URL metric to a group.
	 *
	 * Once a group reaches the sample size, the oldest URL metric is pushed out.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @param ILO_URL_Metric $new_url_metric New URL metric.
	 */
	public function add( ILO_URL_Metric $new_url_metric ) {
		$url_metrics = $this->flatten();
		array_unshift( $url_metrics, $new_url_metric );

		$grouped_url_metrics = $this->group_url_metrics_by_breakpoint( $url_metrics );

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
	private function group_url_metrics_by_breakpoint( array $url_metrics ): array {

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
				if ( $url_metric->get_viewport()['width'] >= $viewport_minimum_width ) {
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
	 * Determines whether the group for a given viewport has been filled to the sample size.
	 *
	 * @param int $viewport_width Viewport width.
	 * @return bool Whether group is filled.
	 */
	public function is_group_filled( int $viewport_width ): bool {
		$last_was_needed = false;
		foreach ( $this->get_needed_minimum_viewport_widths() as list( $minimum_viewport_width, $is_needed ) ) {
			if ( $viewport_width >= $minimum_viewport_width ) {
				$last_was_needed = $is_needed;
			} else {
				break;
			}
		}
		return $last_was_needed;
	}

	/**
	 * Gets needed minimum viewport widths.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @return array<int, array{int, bool}> Array of tuples mapping minimum viewport width to whether URL metric(s) are needed.
	 */
	public function get_needed_minimum_viewport_widths(): array {
		$current_time = microtime( true );

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
	 * Checks whether all groups have URL metrics.
	 *
	 * @return bool Whether all groups have URL metrics.
	 */
	public function are_all_groups_populated(): bool {
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
	 * @return ILO_URL_Metric[] Ungrouped URL metrics.
	 */
	public function flatten(): array {
		return array_merge(
			...array_values( $this->groups )
		);
	}
}
