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
	 * @var array<int, array{tag_name: string, index: int}>
	 */
	private $open_stack_indices = array();

	/**
	 * Bookmarked open stack indices.
	 *
	 * This is populated with the contents of `$this->open_stack_indices` whenever calling `self::set_bookmark()`. Then
	 * whenever `self::seek()` is called, the bookmarked open stacks are populated back into `$this->open_stack_indices`.
	 *
	 * @since n.e.x.t
	 * @var array<string, array<int, array{tag_name: string, index: int}>>
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
	 * Constructor.
	 *
	 * Do not use this method. Use the static creator methods instead.
	 *
	 * @access private
	 *
	 * @since  n.e.x.t
	 *
	 * @see    WP_HTML_Processor::create_fragment()
	 *
	 * @param string      $html                                  HTML to process.
	 * @param string|null $use_the_static_create_methods_instead This constructor should not be called manually.
	 *
	 * @throws ReflectionException When unable to access private properties.
	 */
	public function __construct( string $html, ?string $use_the_static_create_methods_instead = null ) {
		parent::__construct( $html, $use_the_static_create_methods_instead );

		$html_processor_reflection = new ReflectionClass( WP_HTML_Processor::class );
		$state_property_reflection = $html_processor_reflection->getProperty( 'state' );
		$state_property_reflection->setAccessible( true );

		/**
		 * State.
		 *
		 * @var WP_HTML_Processor_State $state
		 */
		$state = $state_property_reflection->getValue( $this );

		$stack_of_open_elements_reflection = new ReflectionObject( $state->stack_of_open_elements );

		$push_handler_reflection = $stack_of_open_elements_reflection->getProperty( 'push_handler' );
		$push_handler_reflection->setAccessible( true );
		$existing_push_handler = $push_handler_reflection->getValue( $state->stack_of_open_elements );

		$pop_handler_reflection = $stack_of_open_elements_reflection->getProperty( 'pop_handler' );
		$pop_handler_reflection->setAccessible( true );
		$existing_pop_handler = $pop_handler_reflection->getValue( $state->stack_of_open_elements );

		$state->stack_of_open_elements->set_push_handler( // @phpstan-ignore method.notFound (Not yet part of szepeviktor/phpstan-wordpress.)
			function ( WP_HTML_Token $token ) use ( $existing_push_handler, $state ): void {
				if ( $existing_push_handler instanceof Closure ) {
					$existing_push_handler( $token );
				}

				if ( '#' !== $token->node_name[0] && 'html' !== $token->node_name ) {
					$this->current_xpath = null; // Clear cache.

					$depth = $state->stack_of_open_elements->count();
					if ( ! isset( $this->open_stack_indices[ $depth ] ) ) {
						$this->open_stack_indices[ $depth ] = array(
							'tag_name' => $token->node_name,
							'index'    => 0,
						);
					} else {
						$this->open_stack_indices[ $depth ]['tag_name'] = $token->node_name;
						++$this->open_stack_indices[ $depth ]['index'];
					}
				}
			}
		);
		$state->stack_of_open_elements->set_pop_handler( // @phpstan-ignore method.notFound (Not yet part of szepeviktor/phpstan-wordpress.)
			function ( WP_HTML_Token $token ) use ( $existing_pop_handler, $state ): void {
				if ( $existing_pop_handler instanceof Closure ) {
					$existing_pop_handler( $token );
				}

				if ( '#' !== $token->node_name[0] && 'html' !== $token->node_name ) {
					$this->current_xpath = null;

					if ( count( $this->open_stack_indices ) > $state->stack_of_open_elements->count() + 1 ) {
						array_pop( $this->open_stack_indices );
					}
				}

				if ( 'HEAD' === $token->node_name ) {
					$this->set_bookmark( self::END_OF_HEAD_BOOKMARK );
				} elseif ( 'BODY' === $token->node_name ) {
					// TODO: This currently always fails because self::STATE_COMPLETE === $this->parser_state, so the below hack is required.
					$this->set_bookmark( self::END_OF_BODY_BOOKMARK );
				}
			}
		);

		// TODO: This is a hack! It's only needed because of a failure to set a bookmark when the BODY tag is popped above.
		$body_end_position = strripos( $html, '</body>' );
		if ( false === $body_end_position ) {
			$body_end_position = strlen( $html );
		}
		$this->bookmarks[ '_' . self::END_OF_BODY_BOOKMARK ] = new WP_HTML_Span( $body_end_position, 0 );
	}

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
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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
			foreach ( $this->open_stack_indices as $level ) {
				$this->current_xpath .= sprintf( '/*[%d][self::%s]', $level['index'] + 1, $level['tag_name'] );
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
	 * Returns the string representation of the HTML Tag Processor.
	 *
	 * Once the end of the document has been reached this is responsible for adding the pending markup to append to the
	 * HEAD and the BODY. It waits to do this injection until the end of the document has been reached because every
	 * time that seek() is called it the HTML Processor will flush any pending updates to the document. This means that
	 * if there is any pending markup to append to the end of the BODY then the insertion will fail because the closing
	 * tag for the BODY has not been encountered yet. Additionally, by not prematurely processing the buffered text
	 * replacements in get_updated_html() then we avoid trying to insert them every time that seek() is called which is
	 * wasteful as they are only needed once finishing iterating over the document.
	 *
	 * @since 0.4.0
	 * @see WP_HTML_Tag_Processor::get_updated_html()
	 * @see WP_HTML_Tag_Processor::seek()
	 *
	 * @return string The processed HTML.
	 */
	public function get_updated_html(): string {
		if ( WP_HTML_Processor::STATE_COMPLETE !== $this->parser_state ) {
			return parent::get_updated_html();
		}

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
