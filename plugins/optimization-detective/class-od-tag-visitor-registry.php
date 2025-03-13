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
	 * Whether finalized.
	 *
	 * @since n.e.x.t
	 * @var bool
	 */
	private $is_finalized = false;

	/**
	 * Finalizes the registry to prevent further modifications.
	 *
	 * @since n.e.x.t
	 * @access private
	 */
	public function finalize(): void {
		$this->is_finalized = true;
	}

	/**
	 * Registers a tag visitor.
	 *
	 * @since 0.3.0
	 * @since n.e.x.t Returns boolean for whether registration is successful. Returns false if registry is finalized.
	 *
	 * @phpstan-param TagVisitorCallback $tag_visitor_callback
	 *
	 * @param non-empty-string $id                   Identifier for the tag visitor.
	 * @param callable         $tag_visitor_callback Tag visitor callback.
	 * @return bool Whether a tag visitor was registered.
	 */
	public function register( string $id, callable $tag_visitor_callback ): bool {
		if ( $this->is_finalized ) {
			_doing_it_wrong( __METHOD__, esc_html( $this->get_finalized_message() ), 'optimization-detective 1.0.0' );
			return false;
		}
		$this->visitors[ $id ] = $tag_visitor_callback;
		return true;
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
	 * @since n.e.x.t Returns false if the registry is finalized.
	 *
	 * @param non-empty-string $id Identifier for the tag visitor.
	 * @return bool Whether a tag visitor was unregistered.
	 */
	public function unregister( string $id ): bool {
		if ( $this->is_finalized ) {
			_doing_it_wrong( __METHOD__, esc_html( $this->get_finalized_message() ), 'optimization-detective 1.0.0' );
			return false;
		}
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

	/**
	 * Gets the finalized message when attempting to mutate the registry after the od_register_tag_visitors action.
	 *
	 * @since n.e.x.t
	 *
	 * @return string Message.
	 */
	private function get_finalized_message(): string {
		return sprintf(
			/* translators: %s is the od_register_tag_visitors action */
			__( 'The tag visitor registry has already been finalized. This method must be called during the %s action.', 'optimization-detective' ),
			'od_register_tag_visitors'
		);
	}
}
