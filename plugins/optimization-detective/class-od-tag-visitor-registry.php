<?php
/**
 * Optimization Detective: OD_Tag_Visitor_Registry class
 *
 * @package optimization-detective
 * @since 0.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry for tag visitors invoked for each tag while walking over a document.
 *
 * @phpstan-type TagVisitorCallback callable( OD_Tag_Visitor_Context ): bool
 *
 * @implements IteratorAggregate<string, TagVisitorCallback>
 *
 * @since 0.3.0
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
	 * sorted_Visitors.
	 *
	 * @var array<string, TagVisitorCallback>
	 */
	private $sorted_visitors = array();

	/**
	 * Registers a tag visitor.
	 *
	 * @phpstan-param TagVisitorCallback $tag_visitor_callback
	 *
	 * @param string   $id Identifier for the tag visitor.
	 * @param callable $tag_visitor_callback Tag visitor callback.
	 * @param string[] $dependencies Tag visitors that must run before this tag visitor.
	 */
	public function register( string $id, callable $tag_visitor_callback, array $dependencies = array() ): void {
		$this->visitors[ $id ] = array(
			'callback'     => $tag_visitor_callback,
			'dependencies' => $dependencies,
		);
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
			return $this->visitors[ $id ]['callback'];
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
		// sort the visitors so dependents load first
		return new ArrayIterator( $this->visitors );
		if( array() !== $this->sorted_visitors ){
			return new ArrayIterator( $this->sorted_visitors );
		}

		foreach( $this->visitors as $key => $visitor ){
			if( $visitor['dependencies'] && array() !== $visitor['dependencies'] ) {
				foreach( $visitor['dependencies'] as $dependent ) {
					if ( array_key_exists( $dependent, $this->sorted_visitors  ) ) {
						$this->sorted_visitors[ $key ] = $visitor;
					} else {
						// remove current location in array
						unset( $this->visitors[ $key ] );
						// add to the end
						$this->visitors[ $key ] = $visitor;
					}
				}
			} else {
				$this->sorted_visitors[ $key ] = $visitor;
			}

		}

		return new ArrayIterator( $this->sorted_visitors );
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
