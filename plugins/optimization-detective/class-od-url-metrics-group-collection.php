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
 * @phpstan-import-type ElementData from OD_URL_Metric
 *
 * @implements IteratorAggregate<int, OD_URL_Metrics_Group>
 *
 * @since 0.1.0
 * @access private
 */
final class OD_URL_Metrics_Group_Collection implements Countable, IteratorAggregate, JSONSerializable {

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
	 * Result cache.
	 *
	 * @var array{
	 *          get_group_for_viewport_width?: array<int, OD_URL_Metrics_Group>,
	 *          is_every_group_populated?: bool,
	 *          is_every_group_complete?: bool,
	 *          get_groups_by_lcp_element?: array<string, OD_URL_Metrics_Group[]>,
	 *          get_common_lcp_element?: ElementData|null,
	 *          get_all_element_max_intersection_ratios?: array<string, float>
	 *      }
	 */
	private $result_cache = array();

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
	 * Clear result cache.
	 */
	public function clear_cache(): void {
		$this->result_cache = array();
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
			$groups[]  = new OD_URL_Metrics_Group( array(), $min_width, $max_width, $this->sample_size, $this->freshness_ttl, $this );
			$min_width = $max_width + 1;
		}
		$groups[] = new OD_URL_Metrics_Group( array(), $min_width, PHP_INT_MAX, $this->sample_size, $this->freshness_ttl, $this );
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
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) && array_key_exists( $viewport_width, $this->result_cache[ __FUNCTION__ ] ) ) {
			return $this->result_cache[ __FUNCTION__ ][ $viewport_width ];
		}

		$result = ( function () use ( $viewport_width ) {
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
		} )();

		$this->result_cache[ __FUNCTION__ ][ $viewport_width ] = $result;
		return $result;
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
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) ) {
			return $this->result_cache[ __FUNCTION__ ];
		}

		$result = ( function () {
			foreach ( $this->groups as $group ) {
				if ( count( $group ) === 0 ) {
					return false;
				}
			}
			return true;
		} )();

		$this->result_cache[ __FUNCTION__ ] = $result;
		return $result;
	}

	/**
	 * Checks whether every group is complete.
	 *
	 * @see OD_URL_Metrics_Group::is_complete()
	 *
	 * @return bool Whether all groups are complete.
	 */
	public function is_every_group_complete(): bool {
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) ) {
			return $this->result_cache[ __FUNCTION__ ];
		}

		$result = ( function () {
			foreach ( $this->groups as $group ) {
				if ( ! $group->is_complete() ) {
					return false;
				}
			}

			return true;
		} )();

		$this->result_cache[ __FUNCTION__ ] = $result;
		return $result;
	}

	/**
	 * Gets the groups with the provided LCP element XPath.
	 *
	 * @see OD_URL_Metrics_Group::get_lcp_element()
	 *
	 * @param string $xpath XPath for LCP element.
	 * @return OD_URL_Metrics_Group[] Groups which have the LCP element.
	 */
	public function get_groups_by_lcp_element( string $xpath ): array {
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) && array_key_exists( $xpath, $this->result_cache[ __FUNCTION__ ] ) ) {
			return $this->result_cache[ __FUNCTION__ ][ $xpath ];
		}

		$result = ( function () use ( $xpath ) {
			$groups = array();
			foreach ( $this->groups as $group ) {
				$lcp_element = $group->get_lcp_element();
				if ( ! is_null( $lcp_element ) && $xpath === $lcp_element['xpath'] ) {
					$groups[] = $group;
				}
			}

			return $groups;
		} )();

		$this->result_cache[ __FUNCTION__ ][ $xpath ] = $result;
		return $result;
	}

	/**
	 * Gets common LCP element.
	 *
	 * @return ElementData|null
	 */
	public function get_common_lcp_element(): ?array {
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) ) {
			return $this->result_cache[ __FUNCTION__ ];
		}

		$result = ( function () {

			// If every group isn't populated, then we can't say whether there is a common LCP element across every viewport group.
			if ( ! $this->is_every_group_populated() ) {
				return null;
			}

			// Look at the LCP elements across all the viewport groups.
			$groups_by_lcp_element_xpath   = array();
			$lcp_elements_by_xpath         = array();
			$group_has_unknown_lcp_element = false;
			foreach ( $this->groups as $group ) {
				$lcp_element = $group->get_lcp_element();
				if ( ! is_null( $lcp_element ) ) {
					$groups_by_lcp_element_xpath[ $lcp_element['xpath'] ][] = $group;
					$lcp_elements_by_xpath[ $lcp_element['xpath'] ][]       = $lcp_element;
				} else {
					$group_has_unknown_lcp_element = true;
				}
			}

			if (
				// All breakpoints share the same LCP element.
				1 === count( $groups_by_lcp_element_xpath )
				&&
				// The breakpoints don't share a common lack of a detected LCP element.
				! $group_has_unknown_lcp_element
			) {
				$xpath = key( $lcp_elements_by_xpath );

				return $lcp_elements_by_xpath[ $xpath ][0];
			}

			return null;
		} )();

		$this->result_cache[ __FUNCTION__ ] = $result;
		return $result;
	}

	/**
	 * Gets the max intersection ratios of all elements across all groups and their captured URL metrics.
	 *
	 * @return array<string, float> Keys are XPaths and values are the intersection ratios.
	 */
	public function get_all_element_max_intersection_ratios(): array {
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) ) {
			return $this->result_cache[ __FUNCTION__ ];
		}

		$result = ( function () {
			$element_max_intersection_ratios = array();

			/*
			 * O(n^3) my! Yes. This is why the result is cached. This being said, the number of groups should be 4 (one
			 * more than the default number of breakpoints) and the number of URL metrics for each group should be 3
			 * (the default sample size). Therefore, given the number (n) of visited elements on the page this will only
			 * end up running n*4*3 times.
			 */
			foreach ( $this->groups as $group ) {
				foreach ( $group as $url_metric ) {
					foreach ( $url_metric->get_elements() as $element ) {
						$element_max_intersection_ratios[ $element['xpath'] ] = array_key_exists( $element['xpath'], $element_max_intersection_ratios )
							? max( $element_max_intersection_ratios[ $element['xpath'] ], $element['intersectionRatio'] )
							: $element['intersectionRatio'];
					}
				}
			}
			return $element_max_intersection_ratios;
		} )();

		$this->result_cache[ __FUNCTION__ ] = $result;
		return $result;
	}

	/**
	 * Gets the max intersection ratio of an element across all groups and their captured URL metrics.
	 *
	 * @param string $xpath XPath for the element.
	 * @return float|null Max intersection ratio of null if tag is unknown (not captured).
	 */
	public function get_element_max_intersection_ratio( string $xpath ): ?float {
		return $this->get_all_element_max_intersection_ratios()[ $xpath ] ?? null;
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

	/**
	 * Specifies data which should be serialized to JSON.
	 *
	 * @since 0.3.1
	 *
	 * @return array{
	 *             breakpoints: positive-int[],
	 *             freshness_ttl: 0|positive-int,
	 *             sample_size: positive-int,
	 *             all_element_max_intersection_ratios: array<string, float>,
	 *             common_lcp_element: ?ElementData,
	 *             every_group_complete: bool,
	 *             every_group_populated: bool,
	 *             groups: array<int, array{
	 *                 lcp_element: ?ElementData,
	 *                 minimum_viewport_width: 0|positive-int,
	 *                 maximum_viewport_width: positive-int,
	 *                 complete: bool,
	 *                 url_metrics: OD_URL_Metric[]
	 *             }>
	 *         } Data which can be serialized by json_encode().
	 */
	public function jsonSerialize(): array {
		return array(
			'breakpoints'                         => $this->breakpoints,
			'freshness_ttl'                       => $this->freshness_ttl,
			'sample_size'                         => $this->sample_size,
			'all_element_max_intersection_ratios' => $this->get_all_element_max_intersection_ratios(),
			'common_lcp_element'                  => $this->get_common_lcp_element(),
			'every_group_complete'                => $this->is_every_group_complete(),
			'every_group_populated'               => $this->is_every_group_populated(),
			'groups'                              => array_map(
				static function ( OD_URL_Metrics_Group $group ): array {
					$group_data = $group->jsonSerialize();
					// Remove redundant data.
					unset(
						$group_data['freshness_ttl'],
						$group_data['sample_size']
					);
					return $group_data;
				},
				$this->groups
			),
		);
	}
}
