<?php
/**
 * Optimization Detective: OD_Visited_Tag_State class
 *
 * @package optimization-detective
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * State for a tag visitation when visited by tag visitors while walking over a document.
 *
 * @since 1.0.0
 * @access private
 */
final class OD_Visited_Tag_State {

	/**
	 * Whether the tag should be tracked among the elements in URL Metrics.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $should_track_tag;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->reset();
	}

	/**
	 * Marks the tag for being tracked in URL Metrics.
	 *
	 * @since 1.0.0
	 */
	public function track_tag(): void {
		$this->should_track_tag = true;
	}

	/**
	 * Whether the tag should be tracked among the elements in URL Metrics.
	 *
	 * @since 1.0.0
	 * @return bool Whether tracked.
	 */
	public function is_tag_tracked(): bool {
		return $this->should_track_tag;
	}

	/**
	 * Resets state.
	 *
	 * This should be called after tag visitors have been invoked on a tag.
	 *
	 * @since 1.0.0
	 */
	public function reset(): void {
		$this->should_track_tag = false;
	}
}
