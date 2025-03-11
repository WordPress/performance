<?php
/**
 * Optimization Detective: OD_Tag_Visitor_Registry class
 *
 * @package optimization-detective
 * @since 0.3.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Registry for tag visitors invoked for each tag while walking over a document.
 *
 * @phpstan-type TagVisitorCallback callable( OD_Tag_Visitor_Context ): ( bool | void )
 *
 * @implements IteratorAggregate<string, TagVisitorCallback>
 *
 * @since 0.3.0
 */
final class OD_Tag_Visitor_Registry implements Countable, IteratorAggregate {

	/**
	 * Visitors.
	 *
	 * @since 0.3.0
	 *
	 * @var array<non-empty-string, TagVisitorCallback>
	 */
	private $visitors = array();

	/**
	 * Registers a tag visitor.
	 *
	 * @since 0.3.0
	 *
	 * @phpstan-param TagVisitorCallback $tag_visitor_callback
	 *
	 * @param non-empty-string $id                   Identifier for the tag visitor.
	 * @param callable         $tag_visitor_callback Tag visitor callback.
	 */
	public function register( string $id, callable $tag_visitor_callback ): void {
		$this->visitors[ $id ] = $tag_visitor_callback;
	}

	/**
	 * Determines if a visitor has been registered.
	 *
	 * @since 0.3.0
	 *
	 * @param non-empty-string $id Identifier for the tag visitor.
	 * @return bool Whether registered.
	 */
	public function is_registered( string $id ): bool {
		return array_key_exists( $id, $this->visitors );
	}

	/**
	 * Gets a registered visitor.
	 *
	 * @since 0.3.0
	 *
	 * @param non-empty-string $id Identifier for the tag visitor.
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
	 * @since 0.3.0
	 *
	 * @param non-empty-string $id Identifier for the tag visitor.
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
	 * Returns an iterator for the URL Metrics in the group.
	 *
	 * @since 0.3.0
	 *
	 * @return ArrayIterator<string, TagVisitorCallback> ArrayIterator for tag visitors.
	 */
	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->visitors );
	}

	/**
	 * Counts the URL Metrics in the group.
	 *
	 * @since 0.3.0
	 *
	 * @return int<0, max> URL Metric count.
	 */
	public function count(): int {
		return count( $this->visitors );
	}
}
