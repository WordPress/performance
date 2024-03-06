<?php
/**
 * Image Loading Optimization: ILO_URL_Metrics_Group class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * URL metrics grouped by viewport according to breakpoints.
 *
 * @phpstan-type Data array{
 *                        minimumViewportWidth: int,
 *                        isLacking: bool,
 *                    }
 *
 * @since n.e.x.t
 * @access private
 */
final class ILO_URL_Metrics_Group {

	/**
	 * URL metrics.
	 *
	 * @var ILO_URL_Metric[]
	 */
	private $url_metrics;

	/**
	 * Minimum possible viewport width for the group (inclusive).
	 *
	 * @var int
	 * @phpstan-var int<0, max>
	 */
	private $minimum_viewport_width;

	/**
	 * Maximum possible viewport width for the group (inclusive).
	 *
	 * @var int
	 * @phpstan-var int<1, max>
	 */
	private $maximum_viewport_width;

	/**
	 * Sample size for URL metrics for a given breakpoint.
	 *
	 * @var int
	 * @phpstan-var int<1, max>
	 */
	private $sample_size;

	/**
	 * Freshness age (TTL) for a given URL metric.
	 *
	 * @var int
	 * @phpstan-var int<0, max>
	 */
	private $freshness_ttl;

	/**
	 * Constructor.
	 *
	 * @param ILO_URL_Metric[] $url_metrics            URL metrics to add to the group.
	 * @param int              $minimum_viewport_width Minimum possible viewport width for the group.
	 * @param int              $maximum_viewport_width Maximum possible viewport width for the group.
	 * @param int              $sample_size            Sample size for the maximum number of viewports in a group between breakpoints.
	 * @param int              $freshness_ttl          Freshness age (TTL) for a given URL metric.
	 */
	public function __construct( array $url_metrics, int $minimum_viewport_width, int $maximum_viewport_width, int $sample_size, int $freshness_ttl ) {
		$this->url_metrics            = $url_metrics;
		$this->minimum_viewport_width = $minimum_viewport_width;
		$this->maximum_viewport_width = $maximum_viewport_width;
		$this->sample_size            = $sample_size;
		$this->freshness_ttl          = $freshness_ttl;
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
	 * Adds a URL metric to the group if it is in the bounds of the max and min viewport width.
	 *
	 * @param ILO_URL_Metric $url_metric URL metric.
	 * @return bool Whether the URL metric was added.
	 */
	public function add_url_metric( ILO_URL_Metric $url_metric ): bool {
		if ( ! $this->is_viewport_width_in_range( $url_metric->get_viewport()['width'] ) ) {
			return false;
		}

		$this->url_metrics[] = $url_metric;

		// If we have too many URL metrics now, remove the oldest ones up to the sample size.
		if ( count( $this->url_metrics ) > $this->sample_size ) {

			// Sort URL metrics in descending order by timestamp.
			usort(
				$this->url_metrics,
				static function ( ILO_URL_Metric $a, ILO_URL_Metric $b ): int {
					return $b->get_timestamp() <=> $a->get_timestamp();
				}
			);

			// Only keep the sample size of the newest URL metrics.
			$this->url_metrics = array_slice( $this->url_metrics, 0, $this->sample_size );
		}
		return true;
	}

	/**
	 *  Determines whether the URL metrics group is lacking URL metrics.
	 *
	 *  Either the group does not have enough URL metrics for the desired sample size,
	 *  or some of the URL metrics are stale.
	 *
	 * @return bool Whether lacking.
	 */
	public function is_lacking(): bool {
		if ( count( $this->url_metrics ) < $this->sample_size ) {
			return true;
		}
		$current_time = microtime( true );
		foreach ( $this->url_metrics as $url_metric ) {
			if ( $current_time > $url_metric->get_timestamp() + $this->freshness_ttl ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Gets the URL metrics in the group.
	 *
	 * @return ILO_URL_Metric[] URL metrics in group.
	 */
	public function get_url_metrics(): array {
		return $this->url_metrics;
	}

	/**
	 * Counts the URL metrics in the group.
	 *
	 * @return int Count of URL metrics in the group.
	 */
	public function count(): int {
		return count( $this->url_metrics );
	}
}
