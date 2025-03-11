<?php
/**
 * Optimization Detective: OD_URL_Metric_Group class
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * URL Metrics grouped by viewport according to breakpoints.
 *
 * @implements IteratorAggregate<int, OD_URL_Metric>
 *
 * @since 0.1.0
 */
final class OD_URL_Metric_Group implements IteratorAggregate, Countable, JsonSerializable {

	/**
	 * URL Metrics.
	 *
	 * @since 0.1.0
	 *
	 * @var OD_URL_Metric[]
	 */
	private $url_metrics;

	/**
	 * Minimum possible viewport width for the group (exclusive).
	 *
	 * @since 0.1.0
	 *
	 * @var int<0, max>
	 */
	private $minimum_viewport_width;

	/**
	 * Maximum possible viewport width for the group (inclusive), where null means it is unbounded.
	 *
	 * @since 0.1.0
	 *
	 * @var int<1, max>|null
	 */
	private $maximum_viewport_width;

	/**
	 * Sample size for URL Metrics for a given breakpoint.
	 *
	 * @since 0.1.0
	 *
	 * @var int<1, max>
	 */
	private $sample_size;

	/**
	 * Freshness age (TTL) for a given URL Metric.
	 *
	 * @since 0.1.0
	 *
	 * @var int<0, max>
	 */
	private $freshness_ttl;

	/**
	 * Collection that this instance belongs to.
	 *
	 * @since 0.3.0
	 *
	 * @var OD_URL_Metric_Group_Collection
	 */
	private $collection;

	/**
	 * Result cache.
	 *
	 * @since 0.3.0
	 *
	 * @var array{
	 *          get_lcp_element?: OD_Element|null,
	 *          is_complete?: bool,
	 *          get_xpath_elements_map?: array<string, non-empty-array<int, OD_Element>>,
	 *          get_all_element_max_intersection_ratios?: array<string, float>,
	 *      }
	 */
	private $result_cache = array();

	/**
	 * Constructor.
	 *
	 * This class should never be directly constructed. It should only be constructed by the {@see OD_URL_Metric_Group_Collection::create_groups()}.
	 *
	 * @since 0.1.0
	 *
	 * @access private
	 * @throws InvalidArgumentException If arguments are invalid.
	 *
	 * @phpstan-param int<0, max>      $minimum_viewport_width
	 * @phpstan-param int<1, max>|null $maximum_viewport_width
	 * @phpstan-param int<1, max>      $sample_size
	 * @phpstan-param int<0, max>      $freshness_ttl
	 *
	 * @param OD_URL_Metric[]                $url_metrics            URL Metrics to add to the group.
	 * @param int                            $minimum_viewport_width Minimum possible viewport width (exclusive) for the group. Must be zero or greater.
	 * @param int|null                       $maximum_viewport_width Maximum possible viewport width (inclusive) for the group. Must be greater than zero and the minimum viewport width. Null means unbounded.
	 * @param int                            $sample_size            Sample size for the maximum number of viewports in a group between breakpoints.
	 * @param int                            $freshness_ttl          Freshness age (TTL) for a given URL Metric.
	 * @param OD_URL_Metric_Group_Collection $collection             Collection that this instance belongs to.
	 */
	public function __construct( array $url_metrics, int $minimum_viewport_width, ?int $maximum_viewport_width, int $sample_size, int $freshness_ttl, OD_URL_Metric_Group_Collection $collection ) {
		if ( $minimum_viewport_width < 0 ) {
			throw new InvalidArgumentException(
				esc_html__( 'The minimum viewport width must be at least zero.', 'optimization-detective' )
			);
		}
		if ( isset( $maximum_viewport_width ) ) {
			if ( $maximum_viewport_width < 1 ) {
				throw new InvalidArgumentException(
					esc_html__( 'The maximum viewport width must be greater than zero.', 'optimization-detective' )
				);
			}
			if ( $minimum_viewport_width >= $maximum_viewport_width ) {
				throw new InvalidArgumentException(
					esc_html__( 'The minimum viewport width must be smaller than the maximum viewport width.', 'optimization-detective' )
				);
			}
		}
		$this->minimum_viewport_width = $minimum_viewport_width;
		$this->maximum_viewport_width = $maximum_viewport_width;

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
		$this->collection    = $collection;
		$this->url_metrics   = $url_metrics;
	}

	/**
	 * Gets the minimum possible viewport width (exclusive).
	 *
	 * @since 0.1.0
	 *
	 * @todo Eliminate in favor of readonly public property.
	 * @return int<0, max> Minimum viewport width (exclusive).
	 */
	public function get_minimum_viewport_width(): int {
		return $this->minimum_viewport_width;
	}

	/**
	 * Gets the maximum possible viewport width (inclusive).
	 *
	 * @since 0.1.0
	 *
	 * @todo Eliminate in favor of readonly public property.
	 * @return int<1, max>|null Minimum viewport width (inclusive). Null means unbounded.
	 */
	public function get_maximum_viewport_width(): ?int {
		return $this->maximum_viewport_width;
	}

	/**
	 * Gets the sample size for URL Metrics for a given breakpoint.
	 *
	 * @since 0.9.0
	 *
	 * @todo Eliminate in favor of readonly public property.
	 * @return int<1, max> Sample size.
	 */
	public function get_sample_size(): int {
		return $this->sample_size;
	}

	/**
	 * Gets the freshness age (TTL) for a given URL Metric.
	 *
	 * @since 0.9.0
	 *
	 * @todo Eliminate in favor of readonly public property.
	 * @return int<0, max> Freshness age.
	 */
	public function get_freshness_ttl(): int {
		return $this->freshness_ttl;
	}

	/**
	 * Gets the collection that this group is a part of.
	 *
	 * @since 1.0.0
	 *
	 * @todo Eliminate in favor of readonly public property.
	 * @return OD_URL_Metric_Group_Collection Collection.
	 */
	public function get_collection(): OD_URL_Metric_Group_Collection {
		return $this->collection;
	}

	/**
	 * Checks whether the provided viewport width is between the minimum (exclusive) and maximum (inclusive).
	 *
	 * @since 0.1.0
	 *
	 * @phpstan-param int<1, max> $viewport_width
	 *
	 * @param int $viewport_width Viewport width.
	 * @return bool Whether the viewport width is in range.
	 */
	public function is_viewport_width_in_range( int $viewport_width ): bool {
		return (
			$viewport_width > $this->minimum_viewport_width &&
			( null === $this->maximum_viewport_width || $viewport_width <= $this->maximum_viewport_width )
		);
	}

	/**
	 * Adds a URL Metric to the group.
	 *
	 * @since 0.1.0
	 * @access private
	 *
	 * @throws InvalidArgumentException If the viewport width of the URL Metric is not within the min/max bounds of the group.
	 *
	 * @param OD_URL_Metric $url_metric URL Metric.
	 */
	public function add_url_metric( OD_URL_Metric $url_metric ): void {
		if ( ! $this->is_viewport_width_in_range( $url_metric->get_viewport_width() ) ) {
			throw new InvalidArgumentException(
				esc_html__( 'URL Metric is not in the viewport range for group.', 'optimization-detective' )
			);
		}

		$this->clear_cache();

		$url_metric->set_group( $this );
		$this->url_metrics[] = $url_metric;

		// If we have too many URL Metrics now, remove the oldest ones up to the sample size.
		if ( count( $this->url_metrics ) > $this->sample_size ) {

			// Sort URL Metrics in descending order by timestamp.
			usort(
				$this->url_metrics,
				static function ( OD_URL_Metric $a, OD_URL_Metric $b ): int {
					return $b->get_timestamp() <=> $a->get_timestamp();
				}
			);

			// Only keep the sample size of the newest URL Metrics.
			$this->url_metrics = array_slice( $this->url_metrics, 0, $this->sample_size );
		}
	}

	/**
	 * Determines whether the URL Metric group is complete.
	 *
	 * A group is complete if it has the full sample size of URL Metrics
	 * and all of these URL Metrics are fresh (with a current ETag and a
	 * timestamp that is not older than the freshness TTL).
	 *
	 * @since 0.1.0
	 * @since 0.9.0 If the current environment's generated ETag does not match the URL Metric's ETag, the URL Metric is considered stale.
	 *
	 * @return bool Whether complete.
	 */
	public function is_complete(): bool {
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) ) {
			return $this->result_cache[ __FUNCTION__ ];
		}

		$result = ( function () {
			if ( count( $this->url_metrics ) < $this->sample_size ) {
				return false;
			}
			$current_time = microtime( true );
			foreach ( $this->url_metrics as $url_metric ) {
				// The URL Metric is too old to be fresh.
				if ( $current_time > $url_metric->get_timestamp() + $this->freshness_ttl ) {
					return false;
				}

				// The ETag of the URL Metric does not match the current ETag for the collection, so it is stale.
				if ( ! hash_equals( $url_metric->get_etag(), $this->collection->get_current_etag() ) ) {
					return false;
				}
			}

			return true;
		} )();

		$this->result_cache[ __FUNCTION__ ] = $result;
		return $result;
	}

	/**
	 * Gets the LCP element in the viewport group.
	 *
	 * @since 0.3.0
	 *
	 * @return OD_Element|null LCP element data or null if not available, either because there are no URL Metrics or
	 *                          the LCP element type is not supported.
	 */
	public function get_lcp_element(): ?OD_Element {
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) ) {
			return $this->result_cache[ __FUNCTION__ ];
		}

		$result = ( function () {

			// No metrics have been gathered for this group so there is no LCP element.
			if ( count( $this->url_metrics ) === 0 ) {
				return null;
			}

			// The following arrays all share array indices.

			/**
			 * Seen breadcrumbs counts.
			 *
			 * @var array<int, non-empty-string> $seen_breadcrumbs
			 */
			$seen_breadcrumbs = array();

			/**
			 * Breadcrumb counts.
			 *
			 * @var array<int, non-negative-int> $breadcrumb_counts
			 */
			$breadcrumb_counts = array();

			/**
			 * Breadcrumb element.
			 *
			 * @var array<int, OD_Element> $breadcrumb_element
			 */
			$breadcrumb_element = array();

			// Prefer to use URL Metrics which have a current ETag.
			$url_metrics = array_filter(
				$this->url_metrics,
				function ( OD_URL_Metric $url_metric ): bool {
					return $url_metric->get_etag() === $this->get_collection()->get_current_etag();
				}
			);

			// Otherwise, if no URL Metrics have a current ETag, fall back to using all the stale ones.
			if ( count( $url_metrics ) === 0 ) {
				$url_metrics = $this->url_metrics;
			}

			foreach ( $url_metrics as $url_metric ) {
				foreach ( $url_metric->get_elements() as $element ) {
					if ( ! $element->is_lcp() ) {
						continue;
					}

					$i = array_search( $element->get_xpath(), $seen_breadcrumbs, true );
					if ( false === $i ) {
						$i                       = count( $seen_breadcrumbs );
						$seen_breadcrumbs[ $i ]  = $element->get_xpath();
						$breadcrumb_counts[ $i ] = 0;
					}

					$breadcrumb_counts[ $i ] += 1;
					$breadcrumb_element[ $i ] = $element;
					break; // We found the LCP element for the URL Metric, go to the next URL Metric.
				}
			}

			// Now sort by the breadcrumb counts in descending order, so the remaining first key is the most common breadcrumb.
			if ( count( $seen_breadcrumbs ) > 0 ) {
				arsort( $breadcrumb_counts );
				$most_common_breadcrumb_index = key( $breadcrumb_counts );

				$lcp_element = $breadcrumb_element[ $most_common_breadcrumb_index ];
			} else {
				$lcp_element = null;
			}

			return $lcp_element;
		} )();

		$this->result_cache[ __FUNCTION__ ] = $result;
		return $result;
	}

	/**
	 * Gets all elements from all URL Metrics in the viewport group keyed by the elements' XPaths.
	 *
	 * @since 0.9.0
	 *
	 * @return array<string, non-empty-array<int, OD_Element>> Keys are XPaths and values are the element instances.
	 */
	public function get_xpath_elements_map(): array {
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) ) {
			return $this->result_cache[ __FUNCTION__ ];
		}

		$result = ( function () {
			$all_elements = array();
			foreach ( $this->url_metrics as $url_metric ) {
				foreach ( $url_metric->get_elements() as $element ) {
					$all_elements[ $element->get_xpath() ][] = $element;
				}
			}
			return $all_elements;
		} )();

		$this->result_cache[ __FUNCTION__ ] = $result;
		return $result;
	}

	/**
	 * Gets the max intersection ratios of all elements in the viewport group and its captured URL Metrics.
	 *
	 * @since 0.9.0
	 *
	 * @return array<string, float> Keys are XPaths and values are the intersection ratios.
	 */
	public function get_all_element_max_intersection_ratios(): array {
		if ( array_key_exists( __FUNCTION__, $this->result_cache ) ) {
			return $this->result_cache[ __FUNCTION__ ];
		}

		$result = ( function () {
			$elements_max_intersection_ratios = array();
			foreach ( $this->get_xpath_elements_map() as $xpath => $elements ) {
				$element_intersection_ratios = array();
				foreach ( $elements as $element ) {
					$element_intersection_ratios[] = $element->get_intersection_ratio();
				}
				$elements_max_intersection_ratios[ $xpath ] = (float) max( $element_intersection_ratios );
			}
			return $elements_max_intersection_ratios;
		} )();

		$this->result_cache[ __FUNCTION__ ] = $result;
		return $result;
	}

	/**
	 * Gets the max intersection ratio of an element in the viewport group and its captured URL Metrics.
	 *
	 * @since 0.9.0
	 *
	 * @param string $xpath XPath for the element.
	 * @return float|null Max intersection ratio of null if tag is unknown (not captured).
	 */
	public function get_element_max_intersection_ratio( string $xpath ): ?float {
		return $this->get_all_element_max_intersection_ratios()[ $xpath ] ?? null;
	}

	/**
	 * Returns an iterator for the URL Metrics in the group.
	 *
	 * @since 0.1.0
	 *
	 * @return ArrayIterator<int, OD_URL_Metric> ArrayIterator for OD_URL_Metric instances.
	 */
	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->url_metrics );
	}

	/**
	 * Counts the URL Metrics in the group.
	 *
	 * @since 0.1.0
	 *
	 * @return int<0, max> URL Metric count.
	 */
	public function count(): int {
		return count( $this->url_metrics );
	}

	/**
	 * Clears result cache.
	 *
	 * @since 0.9.0
	 * @access private
	 */
	public function clear_cache(): void {
		$this->result_cache = array();
		$this->collection->clear_cache();
	}

	/**
	 * Specifies data which should be serialized to JSON.
	 *
	 * @since 0.3.1
	 *
	 * @return array{
	 *             freshness_ttl: 0|positive-int,
	 *             sample_size: positive-int,
	 *             minimum_viewport_width: int<0, max>,
	 *             maximum_viewport_width: int<1, max>|null,
	 *             lcp_element: ?OD_Element,
	 *             complete: bool,
	 *             url_metrics: OD_URL_Metric[]
	 *         } Data which can be serialized by json_encode().
	 */
	public function jsonSerialize(): array {
		return array(
			'freshness_ttl'          => $this->freshness_ttl,
			'sample_size'            => $this->sample_size,
			'minimum_viewport_width' => $this->minimum_viewport_width,
			'maximum_viewport_width' => $this->maximum_viewport_width,
			'lcp_element'            => $this->get_lcp_element(),
			'complete'               => $this->is_complete(),
			'url_metrics'            => $this->url_metrics,
		);
	}
}
