<?php
/**
 * Optimization Detective: OD_HTML_Tag_Processor class
 *
 * @package optimization-detective
 * @since 0.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extension to WP_HTML_Tag_Processor that supports injecting HTML and obtaining XPath for the current tag.
 *
 * @since 0.1.1
 * @access private
 */
final class OD_HTML_Tag_Processor extends WP_HTML_Tag_Processor {

	/**
	 * HTML void tags (i.e. those which are self-closing).
	 *
	 * @link https://html.spec.whatwg.org/multipage/syntax.html#void-elements
	 * @see WP_HTML_Processor::is_void()
	 * @todo Reuse `WP_HTML_Processor::is_void()` once WordPress 6.5 is the minimum-supported version. See <https://github.com/WordPress/performance/pull/1115>.
	 *
	 * @since n.e.x.t
	 * @var string[]
	 */
	const VOID_TAGS = array(
		'AREA',
		'BASE',
		'BASEFONT', // Obsolete.
		'BGSOUND', // Obsolete.
		'BR',
		'COL',
		'EMBED',
		'FRAME', // Deprecated.
		'HR',
		'IMG',
		'INPUT',
		'KEYGEN', // Obsolete.
		'LINK',
		'META',
		'PARAM', // Deprecated.
		'SOURCE',
		'TRACK',
		'WBR',
	);

	/**
	 * Raw text tags.
	 *
	 * These are treated like void tags for the purposes of walking over the document since we do not process any text
	 * nodes. To cite the docblock for WP_HTML_Tag_Processor:
	 *
	 * > Some HTML elements are handled in a special way; their start and end tags
	 * > act like a void tag. These are special because their contents can't contain
	 * > HTML markup. Everything inside these elements is handled in a special way
	 * > and content that _appears_ like HTML tags inside of them isn't. There can
	 * > be no nesting in these elements.
	 * >
	 * > In the following list, "raw text" means that all of the content in the HTML
	 * > until the matching closing tag is treated verbatim without any replacements
	 * > and without any parsing.
	 *
	 * @link https://github.com/WordPress/wordpress-develop/blob/6dd00b1ffac54c20c1c1c7721aeebbcd82d0e378/src/wp-includes/html-api/class-wp-html-tag-processor.php#L136-L155
	 * @link https://core.trac.wordpress.org/ticket/60392#comment:2
	 *
	 * @since n.e.x.t
	 * @var string[]
	 */
	const RAW_TEXT_TAGS = array(
		'SCRIPT',
		'IFRAME',
		'NOEMBED', // Deprecated.
		'NOFRAMES', // Deprecated.
		'STYLE',
		'TEXTAREA',
		'TITLE',
		'XMP', // Deprecated.
	);

	/**
	 * The set of HTML tags whose presence will implicitly close a <p> element.
	 * For example '<p>foo<h1>bar</h1>' should parse the same as '<p>foo</p><h1>bar</h1>'.
	 *
	 * @link https://html.spec.whatwg.org/multipage/grouping-content.html#the-p-element
	 *
	 * @since n.e.x.t
	 * @var string[]
	 */
	const P_CLOSING_TAGS = array(
		'ADDRESS',
		'ARTICLE',
		'ASIDE',
		'BLOCKQUOTE',
		'DETAILS',
		'DIV',
		'DL',
		'FIELDSET',
		'FIGCAPTION',
		'FIGURE',
		'FOOTER',
		'FORM',
		'H1',
		'H2',
		'H3',
		'H4',
		'H5',
		'H6',
		'HEADER',
		'HGROUP',
		'HR',
		'MAIN',
		'MENU',
		'NAV',
		'OL',
		'P',
		'PRE',
		'SEARCH',
		'SECTION',
		'TABLE',
		'UL',
	);

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
	 * @since n.e.x.t
	 * @var string
	 */
	const END_OF_HEAD_BOOKMARK = 'end_of_head';

	/**
	 * Bookmark for the end of the BODY.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const END_OF_BODY_BOOKMARK = 'end_of_body';

	/**
	 * Whether the old (pre-WP 6.5) signature for WP_HTML_Text_Replacement is needed.
	 *
	 * WordPress 6.5 changed the $end arg in the WP_HTML_Text_Replacement constructor to $length.
	 *
	 * @since n.e.x.t
	 * @var bool
	 */
	private $old_text_replacement_signature_needed;

	/**
	 * Open stack tags.
	 *
	 * @since n.e.x.t
	 * @var string[]
	 */
	private $open_stack_tags = array();

	/**
	 * Open stack indices.
	 *
	 * @since n.e.x.t
	 * @var int[]
	 */
	private $open_stack_indices = array();

	/**
	 * Bookmarked open stacks.
	 *
	 * This is populated with the contents of `$this->open_stack_tags` and
	 * `$this->open_stack_indices` whenever calling `self::set_bookmark()`.
	 * Then whenever `self::seek()` is called, the bookmarked open stacks are
	 * populated back into `$this->open_stack_tags` and `$this->open_stack_indices`.
	 *
	 * @since n.e.x.t
	 * @var array<string, array{tags: string[], indices: int[]}>
	 */
	private $bookmarked_open_stacks = array();

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
	 * Whether the previous tag does not expect a closer.
	 *
	 * @since n.e.x.t
	 * @var bool
	 */
	private $previous_tag_without_closer = false;

	/**
	 * Mapping of bookmark name to a list of HTML strings which will be inserted at the time get_updated_html() is called.
	 *
	 * @since n.e.x.t
	 * @var array<string, string[]>
	 */
	private $buffered_text_replacements = array();

	/**
	 * Constructor.
	 *
	 * @param string $html HTML to process.
	 */
	public function __construct( string $html ) {
		$this->old_text_replacement_signature_needed = version_compare( get_bloginfo( 'version' ), '6.5', '<' );
		parent::__construct( $html );
	}

	/**
	 * Finds the next tag.
	 *
	 * Unlike the base class, this subclass disallows querying. This is to ensure the breadcrumbs can be tracked.
	 * It will _always_ visit tag closers.
	 *
	 * @inheritDoc
	 * @since n.e.x.t
	 *
	 * @param null $query Query.
	 * @return bool Whether a tag was matched.
	 *
	 * @throws InvalidArgumentException If attempting to pass a query.
	 */
	public function next_tag( $query = null ): bool {
		if ( null !== $query ) {
			throw new InvalidArgumentException( esc_html__( 'Processor subclass does not support queries.', 'optimization-detective' ) );
		}
		return parent::next_tag( array( 'tag_closers' => 'visit' ) );
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
	 * Whether the tag expects a closing tag.
	 *
	 * @see WP_HTML_Processor::expects_closer()
	 * @since n.e.x.t
	 *
	 * @param string|null $tag_name Tag name, if not provided then the current tag is used. Optional.
	 * @return bool Whether to expect a closer for the tag.
	 */
	public function expects_closer( ?string $tag_name = null ): bool {
		if ( is_null( $tag_name ) ) {
			$tag_name = $this->get_tag();
		}

		return ! (
			in_array( $tag_name, self::VOID_TAGS, true )
			||
			in_array( $tag_name, self::RAW_TEXT_TAGS, true )
		);
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
		$this->current_xpath = null; // Clear cache.
		if ( ! parent::next_token() ) {
			$this->open_stack_tags    = array();
			$this->open_stack_indices = array();
			return false;
		}

		$tag_name = $this->get_tag();
		if ( null === $tag_name || $this->get_token_type() !== '#tag' ) {
			return true;
		}

		if ( $this->previous_tag_without_closer ) {
			array_pop( $this->open_stack_tags );
		}

		if ( ! $this->is_tag_closer() ) {

			// Close an open P tag when a P-closing tag is encountered.
			// TODO: There are quite a few more cases of optional closing tags: https://html.spec.whatwg.org/multipage/syntax.html#optional-tags
			// Nevertheless, given WordPress's legacy of XHTML compatibility, the lack of closing tags may not be common enough to warrant worrying about any of them.
			if ( in_array( $tag_name, self::P_CLOSING_TAGS, true ) ) {
				$i = array_search( 'P', $this->open_stack_tags, true );
				if ( false !== $i ) {
					array_splice( $this->open_stack_tags, (int) $i );
					array_splice( $this->open_stack_indices, count( $this->open_stack_tags ) );
				}
			}

			$level                   = count( $this->open_stack_tags );
			$this->open_stack_tags[] = $tag_name;

			if ( ! isset( $this->open_stack_indices[ $level ] ) ) {
				$this->open_stack_indices[ $level ] = 0;
			} else {
				++$this->open_stack_indices[ $level ];
			}

			// Keep track of whether the next call to next_token() should start by
			// immediately popping off the stack due to this tag being either self-closing
			// or a raw text tag.
			$this->previous_tag_without_closer = (
				! $this->expects_closer()
				||
				( $this->has_self_closing_flag() && $this->is_foreign_element() )
			);
		} else {
			$this->previous_tag_without_closer = false;

			// If the closing tag is for self-closing or raw text tag, we ignore it since it was already handled above.
			if ( ! $this->expects_closer() ) {
				return true;
			}

			$popped_tag_name = array_pop( $this->open_stack_tags );
			if ( $popped_tag_name !== $tag_name ) {
				$this->warn(
					sprintf(
						/* translators: 1: Popped tag name, 2: Closing tag name */
						__( 'Expected popped tag stack element %1$s to match the currently visited closing tag %2$s.', 'optimization-detective' ),
						$popped_tag_name,
						$tag_name
					)
				);
			}

			// Set bookmarks for insertion of preload links and the detection script module.
			if ( 'HEAD' === $popped_tag_name ) {
				$this->set_bookmark( self::END_OF_HEAD_BOOKMARK );
			} elseif ( 'BODY' === $popped_tag_name ) {
				$this->set_bookmark( self::END_OF_BODY_BOOKMARK );
			}

			array_splice( $this->open_stack_indices, $this->get_current_depth() + 1 );
		}

		return true;
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
	 * All meta attributes are prefixed with 'data-od-'.
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
	 * Returns the nesting depth of the current location in the document.
	 *
	 * @since n.e.x.t
	 * @see WP_HTML_Processor::get_current_depth()
	 *
	 * @return int Nesting-depth of current location in the document.
	 */
	public function get_current_depth(): int {
		return count( $this->open_stack_tags );
	}

	/**
	 * Gets the bookmark names.
	 *
	 * @return string[] Bookmark names.
	 */
	public function get_bookmark_names(): array {
		return array_keys( $this->bookmarks );
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
			$this->open_stack_tags    = $this->bookmarked_open_stacks[ $bookmark_name ]['tags'];
			$this->open_stack_indices = $this->bookmarked_open_stacks[ $bookmark_name ]['indices'];
		}
		return $result;
	}

	/**
	 * Sets a bookmark in the HTML document.
	 *
	 * @inheritDoc
	 * @since n.e.x.t
	 *
	 * @param string $name Identifies this particular bookmark.
	 * @return bool Whether the bookmark was successfully created.
	 */
	public function set_bookmark( $name ): bool {
		$result = parent::set_bookmark( $name );
		if ( $result ) {
			$this->bookmarked_open_stacks[ $name ] = array(
				'tags'    => $this->open_stack_tags,
				'indices' => $this->open_stack_indices,
			);
		}
		return $result;
	}

	/**
	 * Removes a bookmark that is no longer needed.
	 *
	 * @inheritDoc
	 * @since n.e.x.t
	 *
	 * @param string $name Name of the bookmark to remove.
	 * @return bool Whether the bookmark already existed before removal.
	 */
	public function release_bookmark( $name ): bool {
		if ( in_array( $name, array( self::END_OF_HEAD_BOOKMARK, self::END_OF_BODY_BOOKMARK ), true ) ) {
			$this->warn(
				/* translators: %s is the bookmark name */
				sprintf( 'The %s bookmark is not allowed to be released.', 'optimization-detective' )
			);
			return false;
		}
		unset( $this->bookmarked_open_stacks[ $name ] );
		return parent::release_bookmark( $name );
	}

	/**
	 * Gets breadcrumbs for the current open tag.
	 *
	 * A breadcrumb consists of a tag name and its sibling index.
	 *
	 * @since n.e.x.t
	 *
	 * @return Generator<array{string, int}> Breadcrumb.
	 */
	private function get_breadcrumbs(): Generator {
		foreach ( $this->open_stack_tags as $i => $breadcrumb_tag_name ) {
			yield array( $breadcrumb_tag_name, $this->open_stack_indices[ $i ] );
		}
	}

	/**
	 * Determines whether currently inside a foreign element (MATH or SVG).
	 *
	 * @since n.e.x.t
	 *
	 * @return bool In foreign element.
	 */
	private function is_foreign_element(): bool {
		foreach ( $this->open_stack_tags as $open_stack_tag ) {
			if ( 'MATH' === $open_stack_tag || 'SVG' === $open_stack_tag ) {
				return true;
			}
		}
		return false;
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
			foreach ( $this->get_breadcrumbs() as list( $tag_name, $index ) ) {
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
	 * Returns the string representation of the HTML Tag Processor.
	 *
	 * @return string The processed HTML.
	 */
	public function get_updated_html(): string {
		foreach ( array_keys( $this->buffered_text_replacements ) as $bookmark ) {
			$html_strings = $this->buffered_text_replacements[ $bookmark ];
			if ( count( $html_strings ) === 0 ) {
				continue;
			}
			if ( ! $this->has_bookmark( $bookmark ) ) {
				$this->warn(
					/* translators: %s is the bookmark name */
					__( 'Unable to append markup to %s since the bookmark no longer exists.', 'optimization-detective' )
				);
			} else {
				$start = $this->bookmarks[ $bookmark ]->start;

				$this->lexical_updates[] = new WP_HTML_Text_Replacement(
					$start,
					$this->old_text_replacement_signature_needed ? $start : 0,
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
	 * @param string $message Warning message.
	 */
	private function warn( string $message ): void {
		wp_trigger_error(
			__CLASS__ . '::open_tags',
			esc_html( $message )
		);
	}
}
