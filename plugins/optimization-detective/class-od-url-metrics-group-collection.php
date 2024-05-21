<?php
/**
 * Optimization Detective: OD_URL_Metrics_Group_Collection class
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collection of URL groups according to the breakpoints.
 *
 * @implements IteratorAggregate<int, OD_URL_Metrics_Group>
 *
 * @since 0.1.0
 * @access private
 */
final class OD_URL_Metrics_Group_Collection implements Countable, IteratorAggregate {

	/**
	 * URL metrics groups.
	 *
	 * The number of groups corresponds to one greater than the number of
	 * breakpoints. This is because breakpoints are the dividing line between
	 * the groups of URL metrics with specific viewport widths. This extends
	 * even to when there are zero breakpoints: there will still be one group
	 * in this case, in which every single URL metric is added.
	 *
	 * @var OD_URL_Metrics_Group[]
	 * @phpstan-var non-empty-array<OD_URL_Metrics_Group>
	 */
	private $groups;

	/**
	 * Breakpoints in max widths.
	 *
	 * Valid values are from 1 to PHP_INT_MAX - 1. This is because:
	 *
	 * 1. It doesn't make sense for there to be a viewport width of zero, so the first breakpoint (max width) must be at least 1.
	 * 2. After the last breakpoint, the final breakpoint group is set to be spanning one plus the last breakpoint max width up
	 *    until PHP_INT_MAX. So a breakpoint cannot be PHP_INT_MAX because then the minimum viewport width for the final group
	 *    would end up being larger than PHP_INT_MAX.
	 *
	 * @var int[]
	 * @phpstan-var positive-int[]
	 */
	private $breakpoints;

	/**
	 * Sample size for URL metrics for a given breakpoint.
	 *
	 * @var int
	 * @phpstan-var positive-int
	 */
	private $sample_size;

	/**
	 * Freshness age (TTL) for a given URL metric.
	 *
	 * A freshness age of zero means a URL metric will always be considered stale.
	 *
	 * @var int
	 * @phpstan-var 0|positive-int
	 */
	private $freshness_ttl;

	/**
	 * Constructor.
	 *
	 * @throws InvalidArgumentException When an invalid argument is supplied.
	 *
	 * @param OD_URL_Metric[] $url_metrics   URL metrics.
	 * @param int[]           $breakpoints   Breakpoints in max widths.
	 * @param int             $sample_size   Sample size for the maximum number of viewports in a group between breakpoints.
	 * @param int             $freshness_ttl Freshness age (TTL) for a given URL metric.
	 */
	public function __construct( array $url_metrics, array $breakpoints, int $sample_size, int $freshness_ttl ) {
		// Set breakpoints.
		sort( $breakpoints );
		$breakpoints = array_values( array_unique( $breakpoints, SORT_NUMERIC ) );
		foreach ( $breakpoints as $breakpoint ) {
			if ( ! is_int( $breakpoint ) || $breakpoint < 1 || PHP_INT_MAX === $breakpoint ) {
				throw new InvalidArgumentException(
					esc_html(
						sprintf(
							/* translators: %d is the invalid breakpoint */
							__(
								'Each of the breakpoints must be greater than zero and less than PHP_INT_MAX, but encountered: %d',
								'optimization-detective'
							),
							$breakpoint
						)
					)
				);
			}
		}
		/**
		 * Validated breakpoints.
		 *
		 * @var positive-int[] $breakpoints
		 */
		$this->breakpoints = $breakpoints;

		// Set sample size.
		if ( $sample_size <= 0 ) {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						/* translators: %d is the invalid sample size */
						__( 'Sample size must be greater than zero, but provided: %d', 'optimization-detective' ),
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
						__( 'Freshness TTL must be at least zero, but provided: %d', 'optimization-detective' ),
						$freshness_ttl
					)
				)
			);
		}
		$this->freshness_ttl = $freshness_ttl;

		// Create groups and the URL metrics to them.
		$this->groups = $this->create_groups();
		foreach ( $url_metrics as $url_metric ) {
			$this->add_url_metric( $url_metric );
		}
	}

	/**
	 * Create groups.
	 *
	 * @phpstan-return non-empty-array<OD_URL_Metrics_Group>
	 *
	 * @return OD_URL_Metrics_Group[] Groups.
	 */
	private function create_groups(): array {
		$groups    = array();
		$min_width = 0;
		foreach ( $this->breakpoints as $max_width ) {
			$groups[]  = new OD_URL_Metrics_Group( array(), $min_width, $max_width, $this->sample_size, $this->freshness_ttl );
			$min_width = $max_width + 1;
		}
		$groups[] = new OD_URL_Metrics_Group( array(), $min_width, PHP_INT_MAX, $this->sample_size, $this->freshness_ttl );
		return $groups;
	}

	/**
	 * Adds a new URL metric to a group.
	 *
	 * Once a group reaches the sample size, the oldest URL metric is pushed out.
	 *
	 * @throws InvalidArgumentException If there is no group available to add a URL metric to.
	 *
	 * @param OD_URL_Metric $new_url_metric New URL metric.
	 */
	public function add_url_metric( OD_URL_Metric $new_url_metric ): void {
		foreach ( $this->groups as $group ) {
			if ( $group->is_viewport_width_in_range( $new_url_metric->get_viewport_width() ) ) {
				$group->add_url_metric( $new_url_metric );
				return;
			}
		}
		throw new InvalidArgumentException(
			esc_html__( 'No group available to add URL metric to.', 'optimization-detective' )
		);
	}

	/**
	 * Gets group for viewport width.
	 *
	 * @throws InvalidArgumentException When there is no group for the provided viewport width. This would only happen if a negative width is provided.
	 *
	 * @param int $viewport_width Viewport width.
	 * @return OD_URL_Metrics_Group URL metrics group for the viewport width.
	 */
	public function get_group_for_viewport_width( int $viewport_width ): OD_URL_Metrics_Group {
		foreach ( $this->groups as $group ) {
			if ( $group->is_viewport_width_in_range( $viewport_width ) ) {
				return $group;
			}
		}
		throw new InvalidArgumentException(
			esc_html(
				sprintf(
					/* translators: %d is viewport width */
					__( 'No URL metrics group found for viewport width: %d', 'optimization-detective' ),
					$viewport_width
				)
			)
		);
	}

	/**
	 * Checks whether every group is populated with at least one URL metric each.
	 *
	 * They aren't necessarily filled to the sample size, however.
	 * The URL metrics may also be stale (non-fresh). This method
	 * should be contrasted with the `is_every_group_complete()`
	 * method below.
	 *
	 * @see OD_URL_Metrics_Group_Collection::is_every_group_complete()
	 *
	 * @return bool Whether all groups have some URL metrics.
	 */
	public function is_every_group_populated(): bool {
		foreach ( $this->groups as $group ) {
			if ( count( $group ) === 0 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks whether every group is complete.
	 *
	 * @see OD_URL_Metrics_Group::is_complete()
	 *
	 * @return bool Whether all groups are complete.
	 */
	public function is_every_group_complete(): bool {
		foreach ( $this->groups as $group ) {
			if ( ! $group->is_complete() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Gets URL metrics from all groups flattened into one list.
	 *
	 * @return OD_URL_Metric[] All URL metrics.
	 */
	public function get_flattened_url_metrics(): array {
		// The duplication of iterator_to_array is not a mistake. This collection is an
		// iterator and the collection contains iterator instances. So to flatten the
		// two levels of iterators we need to nest calls to iterator_to_array().
		return array_merge(
			...array_map(
				'iterator_to_array',
				iterator_to_array( $this )
			)
		);
	}

	/**
	 * Returns an iterator for the groups of URL metrics.
	 *
	 * @return ArrayIterator<int, OD_URL_Metrics_Group> Array iterator for OD_URL_Metric_Group instances.
	 */
	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->groups );
	}

	/**
	 * Counts the URL metrics groups in the collection.
	 *
	 * @return int Group count.
	 */
	public function count(): int {
		return count( $this->groups );
	}
}
