<?php
/**
 * Optimization Detective: OD_HTML_Processor class
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extension to WP_HTML_Processor that supports injecting HTML and obtaining XPath for the current tag.
 *
 * @since n.e.x.t
 * @access private
 */
final class OD_HTML_Processor extends WP_HTML_Processor {

	/**
	 * Pattern for valid XPath subset for breadcrumb.
	 *
	 * @since n.e.x.t
	 * @see self::get_xpath()
	 * @var string
	 */
	const XPATH_PATTERN = '^(/\*\[\d+\]\[self::.+?\])+$';

	/**
	 * Bookmark for the end of the HEAD.
	 *
	 * @todo Consider reserving this.
	 * @since n.e.x.t
	 * @var string
	 */
	const END_OF_HEAD_BOOKMARK = 'optimization_detective_end_of_head';

	/**
	 * Bookmark for the end of the BODY.
	 *
	 * @todo Consider reserving this.
	 * @since n.e.x.t
	 * @var string
	 */
	const END_OF_BODY_BOOKMARK = 'optimization_detective_end_of_body';

	/**
	 * Open stack indices.
	 *
	 * @since n.e.x.t
	 * @var int[]
	 */
	private $open_stack_indices = array();

	/**
	 * Bookmarked open stack indices.
	 *
	 * This is populated with the contents of `$this->open_stack_indices` whenever calling `self::set_bookmark()`. Then
	 * whenever `self::seek()` is called, the bookmarked open stacks are populated back into `$this->open_stack_indices`.
	 *
	 * @since n.e.x.t
	 * @var array<string, int[]>
	 */
	private $bookmarked_open_stack_indices = array();

	/**
	 * XPath for the current tag.
	 *
	 * This is used so that repeated calls to {@see self::get_xpath()} won't needlessly reconstruct the string. This
	 * gets cleared whenever {@see self::open_tags()} iterates to the next tag.
	 *
	 * @since n.e.x.t
	 * @var string|null
	 */
	private $current_xpath = null;

	/**
	 * Mapping of bookmark name to a list of HTML strings which will be inserted at the time get_updated_html() is called.
	 *
	 * @since n.e.x.t
	 * @var array<string, string[]>
	 */
	private $buffered_text_replacements = array();

	/**
	 * Count for the number of times that the cursor was moved.
	 *
	 * @since n.e.x.t
	 * @var int
	 * @see self::next_token()
	 * @see self::seek()
	 */
	private $cursor_move_count = 0;

	/**
	 * Previous depth.
	 *
	 * @since n.e.x.t
	 * @var int
	 */
	private $previous_depth = -1;

	/**
	 * Finds the next open tag.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool Whether a tag was matched.
	 */
	public function next_open_tag(): bool {
		while ( $this->next_tag() ) {
			if ( ! $this->is_tag_closer() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Finds the next token in the HTML document.
	 *
	 * @inheritDoc
	 * @since n.e.x.t
	 *
	 * @return bool Whether a token was parsed.
	 */
	public function next_token(): bool {
		$previous_depth       = $this->previous_depth;
		$current_depth        = $this->get_current_depth();
		$this->previous_depth = $current_depth;

		$this->current_xpath = null; // Clear cache.
		++$this->cursor_move_count;
		if ( ! parent::next_token() ) {
			$this->open_stack_indices = array();
			return false;
		}

		if ( $this->get_token_type() === '#tag' ) {
			if ( $current_depth < $previous_depth ) {
				array_splice( $this->open_stack_indices, $current_depth );
			} elseif ( ! isset( $this->open_stack_indices[ $current_depth ] ) ) {
				$this->open_stack_indices[ $current_depth ] = 0;
			} else {
				++$this->open_stack_indices[ $current_depth ];
			}

//			if ( $current_depth === 0 ) {
//				echo  '=========>' . $this->get_tag() . PHP_EOL;
//			}

//			if ( $current_depth > $previous_depth ) {
//				$this->open_stack_tags[] = $this->get_tag();
//			} elseif ( $current_depth < $previous_depth ) {
//				array_splice( $this->open_stack_indices, $current_depth );
//			} else {
//
//			}

			if ( $current_depth < $previous_depth ) {
				$tag_name = $this->get_tag();

				// Set bookmarks for insertion of preload links and the detection script module.
				if ( 'HEAD' === $tag_name ) {
					$this->set_bookmark( self::END_OF_HEAD_BOOKMARK );
				} elseif ( 'BODY' === $tag_name ) {
					$this->set_bookmark( self::END_OF_BODY_BOOKMARK );
				}
			}
		}
		return true;
	}

	/**
	 * Gets the number of times the cursor has moved.
	 *
	 * @todo Not needed once core short-circuits seek() when current cursor is the same as the sought-bookmark.
	 *
	 * @since n.e.x.t
	 * @see self::next_token()
	 * @see self::seek()
	 *
	 * @return int Count of times the cursor has moved.
	 */
	public function get_cursor_move_count(): int {
		return $this->cursor_move_count;
	}

	/**
	 * Updates or creates a new attribute on the currently matched tag with the passed value.
	 *
	 * @inheritDoc
	 * @since n.e.x.t
	 *
	 * @param string      $name  The attribute name to target.
	 * @param string|bool $value The new attribute value.
	 * @return bool Whether an attribute value was set.
	 */
	public function set_attribute( $name, $value ): bool { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
		$existing_value = $this->get_attribute( $name );
		$result         = parent::set_attribute( $name, $value );
		if ( $result ) {
			if ( is_string( $existing_value ) ) {
				$this->set_meta_attribute( "replaced-{$name}", $existing_value );
			} else {
				$this->set_meta_attribute( "added-{$name}", true );
			}
		}
		return $result;
	}

	/**
	 * Sets a meta attribute.
	 *
	 * All meta attributes are prefixed with data-od-.
	 *
	 * @since n.e.x.t
	 *
	 * @param string      $name  Meta attribute name.
	 * @param string|true $value Value.
	 * @return bool Whether an attribute was set.
	 */
	public function set_meta_attribute( string $name, $value ): bool {
		return parent::set_attribute( "data-od-{$name}", $value );
	}

	/**
	 * Removes an attribute from the currently-matched tag.
	 *
	 * @inheritDoc
	 * @since n.e.x.t
	 *
	 * @param string $name The attribute name to remove.
	 */
	public function remove_attribute( $name ): bool { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
		$old_value = $this->get_attribute( $name );
		$result    = parent::remove_attribute( $name );
		if ( $result ) {
			$this->set_meta_attribute( "removed-{$name}", is_string( $old_value ) ? $old_value : true );
		}
		return $result;
	}

	/**
	 * Move the internal cursor in the Tag Processor to a given bookmark's location.
	 *
	 * @inheritDoc
	 * @since 0.4.0
	 *
	 * @param string $bookmark_name Jump to the place in the document identified by this bookmark name.
	 * @return bool Whether the internal cursor was successfully moved to the bookmark's location.
	 */
	public function seek( $bookmark_name ): bool {
		$result = parent::seek( $bookmark_name );
		if ( $result ) {
			$this->open_stack_indices = $this->bookmarked_open_stack_indices[ $bookmark_name ];
		}
		return $result;
	}

	/**
	 * Sets a bookmark in the HTML document.
	 *
	 * @inheritDoc
	 * @since 0.4.0
	 *
	 * @param string $bookmark_name Identifies this particular bookmark.
	 * @return bool Whether the bookmark was successfully created.
	 */
	public function set_bookmark( $bookmark_name ): bool {
		$result = parent::set_bookmark( $bookmark_name );
		if ( $result ) {
			$this->bookmarked_open_stack_indices[ $bookmark_name ] = $this->open_stack_indices;
		}
		return $result;
	}

	/**
	 * Removes a bookmark that is no longer needed.
	 *
	 * @inheritDoc
	 * @since n.e.x.t
	 *
	 * @param string $bookmark_name Name of the bookmark to remove.
	 * @return bool Whether the bookmark already existed before removal.
	 */
	public function release_bookmark( $bookmark_name ): bool {
		if ( in_array( $bookmark_name, array( self::END_OF_HEAD_BOOKMARK, self::END_OF_BODY_BOOKMARK ), true ) ) {
			$this->warn(
				__METHOD__,
				/* translators: %s is the bookmark name */
				sprintf( 'The %s bookmark is not allowed to be released.', 'optimization-detective' )
			);
			return false;
		}
		unset( $this->bookmarked_open_stack_indices[ $bookmark_name ] );
		return parent::release_bookmark( $bookmark_name );
	}

	/**
	 * Gets XPath for the current open tag.
	 *
	 * It would be nicer if this were like `/html[1]/body[2]` but in XPath the position() here refers to the
	 * index of the preceding node set. So it has to rather be written `/*[1][self::html]/*[2][self::body]`.
	 *
	 * @since n.e.x.t
	 *
	 * @return string XPath.
	 */
	public function get_xpath(): string {
		if ( null === $this->current_xpath ) {
			$this->current_xpath = '';
			foreach ( $this->get_breadcrumbs() ?? array() as $i => $tag_name ) {
				$index                = $this->open_stack_indices[ $i ] ?? 0;
				$this->current_xpath .= sprintf( '/*[%d][self::%s]', $index + 1, $tag_name );
			}
		}
		return $this->current_xpath;
	}

	/**
	 * Append HTML to the HEAD.
	 *
	 * The provided HTML must be valid! No validation is performed.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $html HTML to inject.
	 */
	public function append_head_html( string $html ): void {
		$this->buffered_text_replacements[ self::END_OF_HEAD_BOOKMARK ][] = $html;
	}

	/**
	 * Append HTML to the BODY.
	 *
	 * The provided HTML must be valid! No validation is performed.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $html HTML to inject.
	 */
	public function append_body_html( string $html ): void {
		$this->buffered_text_replacements[ self::END_OF_BODY_BOOKMARK ][] = $html;
	}

	/**
	 * Gets the final updated HTML.
	 *
	 * This should only be called after the closing HTML tag has been reached and just before
	 * calling {@see WP_HTML_Processor::get_updated_html()} to send the document back in the response.
	 *
	 * @since n.e.x.t
	 *
	 * @return string Final updated HTML.
	 */
	public function get_final_updated_html(): string {
		foreach ( array_keys( $this->buffered_text_replacements ) as $bookmark ) {
			$html_strings = $this->buffered_text_replacements[ $bookmark ];
			if ( count( $html_strings ) === 0 ) {
				continue;
			}

			$actual_bookmark_name = "_{$bookmark}";

			if ( ! isset( $this->bookmarks[ $actual_bookmark_name ] ) ) {
				$this->warn(
					__METHOD__,
					sprintf(
						/* translators: %s is the bookmark name */
						__( 'Unable to append markup to %s since the bookmark no longer exists.', 'optimization-detective' ),
						$bookmark
					)
				);
			} else {
				$start = $this->bookmarks[ $actual_bookmark_name ]->start;

				$this->lexical_updates[] = new WP_HTML_Text_Replacement(
					$start,
					0,
					implode( '', $html_strings )
				);

				unset( $this->buffered_text_replacements[ $bookmark ] );
			}
		}

		return parent::get_updated_html();
	}

	/**
	 * Warns of bad markup.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $function_name Function name.
	 * @param string $message       Warning message.
	 */
	private function warn( string $function_name, string $message ): void {
		wp_trigger_error(
			$function_name,
			esc_html( $message )
		);
	}
}
