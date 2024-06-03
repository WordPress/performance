<?php
/**
 * Optimization Detective: OD_Tag_Visitor_Registry class
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry for tag visitors invoked for each tag while walking over a document.
 *
 * @phpstan-type TagVisitorCallback callable( OD_HTML_Tag_Walker, OD_URL_Metrics_Group_Collection, OD_Preload_Link_Collection ): bool
 *
 * @implements IteratorAggregate<string, TagVisitorCallback>
 *
 * @since n.e.x.t
 * @access private
 */
final class OD_Tag_Visitor_Registry implements Countable, IteratorAggregate {

	/**
	 * Visitors.
	 *
	 * @var array<string, TagVisitorCallback>
	 */
	private $visitors = array();

	/**
	 * Registers a tag visitor.
	 *
	 * @phpstan-param TagVisitorCallback $tag_visitor_callback
	 *
	 * @param string   $id                   Identifier for the tag visitor.
	 * @param callable $tag_visitor_callback Tag visitor callback.
	 */
	public function register( string $id, callable $tag_visitor_callback ): void {
		$this->visitors[ $id ] = $tag_visitor_callback;
	}

	/**
	 * Determines if a visitor has been registered.
	 *
	 * @param string $id Identifier for the tag visitor.
	 * @return bool Whether registered.
	 */
	public function is_registered( string $id ): bool {
		return array_key_exists( $id, $this->visitors );
	}

	/**
	 * Gets a registered visitor.
	 *
	 * @param string $id Identifier for the tag visitor.
	 * @return TagVisitorCallback|null Whether registered.
	 */
	public function get_registered( string $id ): ?callable {
		if ( $this->is_registered( $id ) ) {
			return $this->visitors[ $id ];
		}
		return null;
	}

	/**
	 * Unregisters a tag visitor.
	 *
	 * @param string $id Identifier for the tag visitor.
	 * @return bool Whether a tag visitor was unregistered.
	 */
	public function unregister( string $id ): bool {
		if ( ! $this->is_registered( $id ) ) {
			return false;
		}
		unset( $this->visitors[ $id ] );
		return true;
	}

	/**
	 * Returns an iterator for the URL metrics in the group.
	 *
	 * @return ArrayIterator<string, TagVisitorCallback> ArrayIterator for tag visitors.
	 */
	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->visitors );
	}

	/**
	 * Counts the URL metrics in the group.
	 *
	 * @return int<0, max> URL metric count.
	 */
	public function count(): int {
		return count( $this->visitors );
	}
}
