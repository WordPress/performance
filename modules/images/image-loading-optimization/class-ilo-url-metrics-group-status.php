<?php
/**
 * Image Loading Optimization: ILO_URL_Metrics_Group_Status class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Status of a given URL metric group.
 *
 * @phpstan-type Data array{
 *                        minimumViewportWidth: int,
 *                        isLacking: bool,
 *                    }
 *
 * @since n.e.x.t
 * @access private
 */
final class ILO_URL_Metrics_Group_Status implements JsonSerializable {

	/**
	 * Data.
	 *
	 * @var Data
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param int  $minimum_viewport_width Minimum viewport width for the group.
	 * @param bool $is_lacking             Whether the group lacks URL metrics.
	 */
	public function __construct( int $minimum_viewport_width, bool $is_lacking ) {
		$this->data = array(
			'minimumViewportWidth' => $minimum_viewport_width,
			'isLacking'            => $is_lacking,
		);
	}

	/**
	 * Gets the minimum viewport width for the group,
	 *
	 * @return int Minimum viewport width.
	 */
	public function get_minimum_viewport_width(): int {
		return $this->data['minimumViewportWidth'];
	}

	/**
	 * Returns whether the group is lacking URL metrics.
	 *
	 * @return bool Whether lacking.
	 */
	public function is_lacking(): bool {
		return $this->data['isLacking'];
	}

	/**
	 * Specifies data which should be serialized to JSON.
	 *
	 * @return Data Exports to JSON.
	 */
	public function jsonSerialize(): array {
		return $this->data;
	}
}
