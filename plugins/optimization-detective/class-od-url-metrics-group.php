<?php
/**
 * Optimization Detective: OD_URL_Metrics_Group class
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL metrics grouped by viewport according to breakpoints.
 *
 * @implements IteratorAggregate<int, OD_URL_Metric>
 *
 * @since 0.1.0
 * @access private
 */
final class OD_URL_Metrics_Group implements IteratorAggregate, Countable {

	/**
	 * URL metrics.
	 *
	 * @var OD_URL_Metric[]
	 */
	private $url_metrics;

	/**
	 * Minimum possible viewport width for the group (inclusive).
	 *
	 * @var int
	 * @phpstan-var 0|positive-int
	 */
	private $minimum_viewport_width;

	/**
	 * Maximum possible viewport width for the group (inclusive).
	 *
	 * @var int
	 * @phpstan-var positive-int
	 */
	private $maximum_viewport_width;

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
	 * @var int
	 * @phpstan-var 0|positive-int
	 */
	private $freshness_ttl;

	/**
	 * Constructor.
	 *
	 * @throws InvalidArgumentException If arguments are valid.
	 *
	 * @param OD_URL_Metric[] $url_metrics            URL metrics to add to the group.
	 * @param int             $minimum_viewport_width Minimum possible viewport width for the group. Must be zero or greater.
	 * @param int             $maximum_viewport_width Maximum possible viewport width for the group. Must be greater than zero and the minimum viewport width.
	 * @param int             $sample_size            Sample size for the maximum number of viewports in a group between breakpoints.
	 * @param int             $freshness_ttl          Freshness age (TTL) for a given URL metric.
	 */
	public function __construct( array $url_metrics, int $minimum_viewport_width, int $maximum_viewport_width, int $sample_size, int $freshness_ttl ) {
		if ( $minimum_viewport_width < 0 ) {
			throw new InvalidArgumentException(
				esc_html__( 'The minimum viewport width must be at least zero.', 'optimization-detective' )
			);
		}
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

		$this->url_metrics = $url_metrics;
	}

	/**
	 * Gets the minimum possible viewport width (inclusive).
	 *
	 * @return int Minimum viewport width.
	 */
	public function get_minimum_viewport_width(): int {
		return $this->minimum_viewport_width;
	}

	/**
	 * Gets the maximum possible viewport width (inclusive).
	 *
	 * @return int Minimum viewport width.
	 */
	public function get_maximum_viewport_width(): int {
		return $this->maximum_viewport_width;
	}

	/**
	 * Checks whether the provided viewport width is within the minimum/maximum range for
	 *
	 * @param int $viewport_width Viewport width.
	 * @return bool Whether the viewport width is in range.
	 */
	public function is_viewport_width_in_range( int $viewport_width ): bool {
		return (
			$viewport_width >= $this->minimum_viewport_width &&
			$viewport_width <= $this->maximum_viewport_width
		);
	}

	/**
	 * Adds a URL metric to the group.
	 *
	 * @throws InvalidArgumentException If the viewport width of the URL metric is not within the min/max bounds of the group.
	 *
	 * @param OD_URL_Metric $url_metric URL metric.
	 */
	public function add_url_metric( OD_URL_Metric $url_metric ): void {
		if ( ! $this->is_viewport_width_in_range( $url_metric->get_viewport_width() ) ) {
			throw new InvalidArgumentException(
				esc_html__( 'URL metric is not in the viewport range for group.', 'optimization-detective' )
			);
		}

		$this->url_metrics[] = $url_metric;

		// If we have too many URL metrics now, remove the oldest ones up to the sample size.
		if ( count( $this->url_metrics ) > $this->sample_size ) {

			// Sort URL metrics in descending order by timestamp.
			usort(
				$this->url_metrics,
				static function ( OD_URL_Metric $a, OD_URL_Metric $b ): int {
					return $b->get_timestamp() <=> $a->get_timestamp();
				}
			);

			// Only keep the sample size of the newest URL metrics.
			$this->url_metrics = array_slice( $this->url_metrics, 0, $this->sample_size );
		}
	}

	/**
	 * Determines whether the URL metrics group is complete.
	 *
	 * A group is complete if it has the full sample size of URL metrics
	 * and all of these URL metrics are fresh.
	 *
	 * @return bool Whether complete.
	 */
	public function is_complete(): bool {
		if ( count( $this->url_metrics ) < $this->sample_size ) {
			return false;
		}
		$current_time = microtime( true );
		foreach ( $this->url_metrics as $url_metric ) {
			if ( $current_time > $url_metric->get_timestamp() + $this->freshness_ttl ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns an iterator for the URL metrics in the group.
	 *
	 * @return ArrayIterator<int, OD_URL_Metric> ArrayIterator for OD_URL_Metric instances.
	 */
	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->url_metrics );
	}

	/**
	 * Counts the URL metrics in the group.
	 *
	 * @return int URL metric count.
	 */
	public function count(): int {
		return count( $this->url_metrics );
	}
}
