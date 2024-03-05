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
	 * URL metrics groups.
	 *
	 * @var ILO_URL_Metrics_Group[]
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
	 * @throws InvalidArgumentException When an invalid argument is supplied.
	 *
	 * @param ILO_URL_Metric[] $url_metrics   URL metrics.
	 * @param int[]            $breakpoints   Breakpoints in max widths.
	 * @param int              $sample_size   Sample size for the maximum number of viewports in a group between breakpoints.
	 * @param int              $freshness_ttl Freshness age (TTL) for a given URL metric.
	 */
	public function __construct( array $url_metrics, array $breakpoints, int $sample_size, int $freshness_ttl ) {
		// Set breakpoints.
		sort( $breakpoints );
		$breakpoints = array_values( array_unique( $breakpoints, SORT_NUMERIC ) );
		foreach ( $breakpoints as $breakpoint ) {
			if ( $breakpoint <= 0 ) {
				throw new InvalidArgumentException(
					esc_html(
						sprintf(
							/* translators: %d is the invalid breakpoint */
							__(
								'Each of the breakpoints must be greater than zero, but encountered: %d',
								'performance-lab'
							),
							$breakpoint
						)
					)
				);
			}
		}
		$this->breakpoints = $breakpoints;

		// Set sample size.
		if ( $sample_size <= 0 ) {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						/* translators: %d is the invalid sample size */
						__( 'Sample size must be greater than zero, but provided: %d', 'performance-lab' ),
						$sample_size
					)
				)
			);
		}
		$this->sample_size = $sample_size;

		// Set freshness TTL.
		if ( $freshness_ttl < 0 ) {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						/* translators: %d is the invalid sample size */
						__( 'Freshness TTL must be at least zero, but provided: %d', 'performance-lab' ),
						$freshness_ttl
					)
				)
			);
		}
		$this->freshness_ttl = $freshness_ttl;

		// Set groups.
		$this->groups = $this->group_url_metrics_by_breakpoint( $url_metrics );
	}

	/**
	 * Gets grouped keyed by the minimum viewport width.
	 *
	 * @return ILO_URL_Metrics_Group[] Groups.
	 */
	public function get_groups(): array {
		return $this->groups;
	}

	/**
	 * Get group for viewport width.
	 *
	 * @throws InvalidArgumentException When there is no group for the provided viewport width. This would only happen if a negative width is provided.
	 *
	 * @param int $viewport_width Viewport width.
	 * @return ILO_URL_Metrics_Group URL metrics group for the viewport width.
	 */
	public function get_group_for_viewport_width( int $viewport_width ): ILO_URL_Metrics_Group {
		foreach ( $this->groups as $group ) {
			if ( $group->is_viewport_width_in_range( $viewport_width ) ) {
				return $group;
			}
		}
		throw new InvalidArgumentException(
			esc_html(
				sprintf(
					/* translators: %d is viewport width */
					__( 'No URL metrics group found for viewport width: %d', 'performance-lab' ),
					$viewport_width
				)
			)
		);
	}

	/**
	 * Adds a new URL metric to a group.
	 *
	 * Once a group reaches the sample size, the oldest URL metric is pushed out.
	 *
	 * @param ILO_URL_Metric $new_url_metric New URL metric.
	 * @return bool Whether the URL metric was added to a group.
	 */
	public function add_url_metric( ILO_URL_Metric $new_url_metric ): bool {
		foreach ( $this->groups as $group ) {
			if ( $group->add_url_metric( $new_url_metric ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Groups URL metrics by breakpoint.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @param ILO_URL_Metric[] $url_metrics URL metrics.
	 * @return ILO_URL_Metrics_Group[] URL metrics grouped by breakpoint.
	 */
	private function group_url_metrics_by_breakpoint( array $url_metrics ): array {
		$url_metrics_groups     = array();
		$minimum_viewport_width = 0;
		foreach ( $this->breakpoints as $maximum_viewport_width ) {
			$url_metrics_groups[]   = new ILO_URL_Metrics_Group( array(), $minimum_viewport_width, $maximum_viewport_width, $this->sample_size, $this->freshness_ttl );
			$minimum_viewport_width = $maximum_viewport_width + 1;
		}
		$url_metrics_groups[] = new ILO_URL_Metrics_Group( array(), $minimum_viewport_width, PHP_INT_MAX, $this->sample_size, $this->freshness_ttl );

		// Now add the URL metrics to the groups.
		foreach ( $url_metrics as $url_metric ) {
			foreach ( $url_metrics_groups as $url_metrics_group ) {
				if ( $url_metrics_group->add_url_metric( $url_metric ) ) {
					// Skip to the next URL metric once successfully added to a group.
					continue 2;
				}
			}
		}

		return $url_metrics_groups;
	}

	/**
	 * Checks whether every group is populated with at least one URL metric each.
	 *
	 * They aren't necessarily filled to the sample size, however.
	 *
	 * @return bool Whether all groups have URL metrics.
	 */
	public function is_every_group_populated(): bool {
		foreach ( $this->groups as $group ) {
			if ( $group->count() === 0 ) {
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
			...array_map(
				static function ( ILO_URL_Metrics_Group $group ): array {
					return $group->get_url_metrics();
				},
				$this->groups
			)
		);
	}
}
