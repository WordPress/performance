<?php
/**
 * Optimization Detective: OD_HTML_Tag_Processor class
 *
 * @package optimization-detective
 * @since 0.1.1
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Extension to WP_HTML_Tag_Processor that supports injecting HTML and obtaining XPath for the current tag.
 *
 * @since 0.1.1
 */
final class OD_HTML_Tag_Processor extends WP_HTML_Tag_Processor {

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
	 * @since 0.4.0
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
	 * @since 0.4.0
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
	 * The pattern for matching a tag /[a-zA-Z0-9:_-]+/ used here is informed by the characters found in tag names in
	 * HTTP Archive as {@link https://docs.google.com/spreadsheets/d/1grkd2_1xSV3jvNK6ucRQ0OL1HmGTsScHuwA8GZuRLHU/edit?gid=2057119066#gid=2057119066 seen}
	 * in Web Almanac 2022, with the only exception being the very malformed tag name `script="async"`. Note that XPaths
	 * begin with `/HTML/BODY` followed by an index-free reference to an element which is a direct child of the BODY but
	 * with a disambiguating attribute predicate added, for example `/HTML/BODY/DIV[@id="page"]`. Below this point, all
	 * tags must then have indices to disambiguate the XPaths among siblings. For example:
	 * `/HTML/BODY/DIV[@id="page"]/*[2][self::MAIN]/*[1][self::FIGURE]/*[2][self::IMG]`.
	 *
	 * The benefit of omitting the node index from direct children of the BODY allows for variation in the content output
	 * at `wp_body_open()` without impacting the computed XPaths for subsequent tags. Omitting the node index at this
	 * level, however, does introduce the risk of duplicate XPaths being computed. For example, if a theme has a
	 * `<div id="header" role="banner">` and a `<div id="footer" role="contentinfo">` which are both direct descendants
	 * of `BODY`, then it is possible for an XPath like `/HTML/BODY/DIV/*[1][self::IMG]` to be duplicated if both of
	 * these `DIV` elements have an `IMG` as the first child. This is also an issue in sites using the Image block
	 * because it outputs a `DIV.wp-lightbox-overlay.zoom` in `wp_footer`, resulting in there being a real possibility
	 * for XPaths to not be unique in the page. This would similarly be an issue for any theme/plugin that prints a
	 * `DIV` at the `wp_footer`, again to add a modal, for example. Therefore, en lieu of node index being added to
	 * children of `BODY`, a disambiguating attribute predicate is added for the element's `id`, `role`, or `class`
	 * attribute. These three attributes are the most stable across page loads, especially at the root of the document
	 * (where there is no Post Loop using `post_class()`).
	 *
	 * @since 0.4.0
	 * @see self::get_xpath()
	 * @var string
	 * @link https://github.com/WordPress/performance/issues/1787
	 */
	const XPATH_PATTERN = '^(/([a-zA-Z0-9:_-]+|\*\[\d+\]\[self::[a-zA-Z0-9:_-]+\])(\[@(id|role|class)=\'[a-zA-Z0-9_.\s:-]*\'\])?)+$';

	/**
	 * Bookmark for the end of the HEAD.
	 *
	 * @todo Consider reserving this.
	 * @since 0.4.0
	 * @var string
	 */
	const END_OF_HEAD_BOOKMARK = 'optimization_detective_end_of_head';

	/**
	 * Bookmark for the end of the BODY.
	 *
	 * @todo Consider reserving this.
	 * @since 0.4.0
	 * @var string
	 */
	const END_OF_BODY_BOOKMARK = 'optimization_detective_end_of_body';

	/**
	 * Open stack tags.
	 *
	 * @since 0.4.0
	 * @var non-empty-string[]
	 */
	private $open_stack_tags = array();

	/**
	 * Stack of the attributes for open tags.
	 *
	 * Note that currently only the third item will currently be populated (index 2), as this corresponds to tags which
	 * are children of the `BODY` tag. This is used in {@see self::get_xpath()}.
	 *
	 * @since 1.0.0
	 * @var array<array<non-empty-string, string>>
	 */
	private $open_stack_attributes = array();

	/**
	 * Open stack indices.
	 *
	 * @since 0.4.0
	 * @var non-negative-int[]
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
	 * @since 0.4.0
	 * @var array<string, array{tags: non-empty-string[], attributes: array<array<non-empty-string, string>>, indices: non-negative-int[]}>
	 */
	private $bookmarked_open_stacks = array();

	/**
	 * (Transitional) XPath for the current tag.
	 *
	 * This is used to store the old XPath format in a transitional period until which new URL Metrics are expected to
	 * have been collected to purge out references to the old format.
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	private $current_xpath = null;

	/**
	 * Whether the previous tag does not expect a closer.
	 *
	 * @since 0.4.0
	 * @var bool
	 */
	private $previous_tag_without_closer = false;

	/**
	 * Mapping of bookmark name to a list of HTML strings which will be inserted at the time get_updated_html() is called.
	 *
	 * @since 0.4.0
	 * @var array<non-empty-string, string[]>
	 */
	private $buffered_text_replacements = array();

	/**
	 * Whether the end of the document was reached.
	 *
	 * @since 0.7.0
	 * @see self::next_token()
	 * @var bool
	 */
	private $reached_end_of_document = false;

	/**
	 * Count for the number of times that the cursor was moved.
	 *
	 * @since 0.6.0
	 * @var non-negative-int
	 * @see self::next_token()
	 * @see self::seek()
	 */
	private $cursor_move_count = 0;

	/**
	 * Finds the next tag.
	 *
	 * Unlike the base class, this subclass currently visits tag closers by default.
	 * However, for the 1.0.0 release this method will behave the same as the method in
	 * the base class, where it skips tag closers by default.
	 *
	 * @inheritDoc
	 * @since 0.4.0
	 * @since 1.0.0 Passing a $query is now allowed. TODO: In the final non-beta 1.0.0 release, also note that this will default to skipping tag closers.
	 *
	 * @param array{tag_name?: string|null, match_offset?: int|null, class_name?: string|null, tag_closers?: string|null}|null $query Query.
	 * @return bool Whether a tag was matched.
	 */
	public function next_tag( $query = null ): bool {
		if ( null === $query ) {
			$query = array( 'tag_closers' => 'visit' );
			$this->warn(
				__METHOD__,
				esc_html__( 'Previously this method always visited tag closers and did not allow a query to be supplied. Now, however, a query can be supplied. To align this method with the behavior of the base class, a future version of this method will default to skipping tag closers.', 'optimization-detective' )
			);
		}
		return parent::next_tag( $query );
	}

	/**
	 * Finds the next open tag.
	 *
	 * This method will soon be equivalent to calling {@see self::next_tag()} without passing any `$query`.
	 *
	 * @since 0.4.0
	 * @deprecated n.e.x.t Use {@see self::next_tag()} instead.
	 *
	 * @return bool Whether a tag was matched.
	 */
	public function next_open_tag(): bool {
		return $this->next_tag( array( 'tag_closers' => 'skip' ) );
	}

	/**
	 * Whether the tag expects a closing tag.
	 *
	 * @see WP_HTML_Processor::expects_closer()
	 * @since 0.4.0
	 *
	 * @param non-empty-string|null $tag_name Tag name, if not provided then the current tag is used. Optional.
	 * @return bool Whether to expect a closer for the tag.
	 */
	public function expects_closer( ?string $tag_name = null ): bool {
		if ( is_null( $tag_name ) ) {
			$tag_name = $this->get_tag();
		}
		if ( is_null( $tag_name ) ) {
			return false;
		}

		return ! (
			WP_HTML_Processor::is_void( $tag_name )
			||
			in_array( $tag_name, self::RAW_TEXT_TAGS, true )
		);
	}

	/**
	 * Finds the next token in the HTML document.
	 *
	 * @inheritDoc
	 * @since 0.4.0
	 *
	 * @return bool Whether a token was parsed.
	 */
	public function next_token(): bool {
		$this->current_xpath = null; // Clear cache.
		++$this->cursor_move_count;
		if ( ! parent::next_token() ) {
			$this->open_stack_tags       = array();
			$this->open_stack_attributes = array();
			$this->open_stack_indices    = array();

			// Mark that the end of the document was reached, meaning that get_modified_html() should now be able to append markup to the HEAD and the BODY.
			$this->reached_end_of_document = true;
			return false;
		}

		$tag_name = $this->get_tag();
		if ( null === $tag_name || $this->get_token_type() !== '#tag' ) {
			return true;
		}
		/**
		 * Tag name.
		 *
		 * @var non-empty-string $tag_name
		 */

		if ( $this->previous_tag_without_closer ) {
			array_pop( $this->open_stack_tags );
			array_pop( $this->open_stack_attributes );
		}

		if ( ! $this->is_tag_closer() ) {

			// Close an open P tag when a P-closing tag is encountered.
			// TODO: There are quite a few more cases of optional closing tags: https://html.spec.whatwg.org/multipage/syntax.html#optional-tags
			// Nevertheless, given WordPress's legacy of XHTML compatibility, the lack of closing tags may not be common enough to warrant worrying about any of them.
			if ( in_array( $tag_name, self::P_CLOSING_TAGS, true ) ) {
				$i = array_search( 'P', $this->open_stack_tags, true );
				if ( false !== $i ) {
					array_splice( $this->open_stack_tags, (int) $i );
					array_splice( $this->open_stack_attributes, (int) $i );
					array_splice( $this->open_stack_indices, count( $this->open_stack_tags ) + 1 );
				}
			}

			$level                   = count( $this->open_stack_tags );
			$this->open_stack_tags[] = $tag_name;

			// For children of the BODY, capture disambiguating comments. See the get_xpath() method for where this data is used.
			$attributes = array();
			if ( isset( $this->open_stack_tags[1] ) && 'BODY' === $this->open_stack_tags[1] && 2 === $level ) {
				$attributes = $this->get_disambiguating_attributes();
			}
			$this->open_stack_attributes[] = $attributes;

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
			array_pop( $this->open_stack_attributes );
			if ( $popped_tag_name !== $tag_name ) {
				$this->warn(
					__METHOD__,
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
	 * Gets the number of times the cursor has moved.
	 *
	 * @since 0.6.0
	 * @see self::next_token()
	 * @see self::seek()
	 *
	 * @return non-negative-int Count of times the cursor has moved.
	 */
	public function get_cursor_move_count(): int {
		return $this->cursor_move_count;
	}

	/**
	 * Updates or creates a new attribute on the currently matched tag with the passed value.
	 *
	 * @inheritDoc
	 * @since 0.4.0
	 *
	 * @param string      $name  The attribute name to target.
	 * @param string|bool $value The new attribute value.
	 * @return bool Whether an attribute value was set.
	 */
	public function set_attribute( $name, $value ): bool { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
		$existing_value = $this->get_attribute( $name );
		$result         = parent::set_attribute( $name, $value );
		if ( $result && $existing_value !== $value ) {
			if ( null !== $existing_value ) {
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
	 * @since 0.4.0
	 *
	 * @param non-empty-string $name  Meta attribute name.
	 * @param string|true      $value Value.
	 * @return bool Whether an attribute was set.
	 */
	public function set_meta_attribute( string $name, $value ): bool {
		return parent::set_attribute( "data-od-{$name}", $value );
	}

	/**
	 * Removes an attribute from the currently-matched tag.
	 *
	 * @inheritDoc
	 * @since 0.4.0
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
	 * @since 0.4.0
	 * @see WP_HTML_Processor::get_current_depth()
	 *
	 * @return non-negative-int Nesting-depth of current location in the document.
	 */
	public function get_current_depth(): int {
		return count( $this->open_stack_tags );
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
			$this->open_stack_tags       = $this->bookmarked_open_stacks[ $bookmark_name ]['tags'];
			$this->open_stack_attributes = $this->bookmarked_open_stacks[ $bookmark_name ]['attributes'];
			$this->open_stack_indices    = $this->bookmarked_open_stacks[ $bookmark_name ]['indices'];
		}
		return $result;
	}

	/**
	 * Sets a bookmark in the HTML document.
	 *
	 * @inheritDoc
	 * @since 0.4.0
	 *
	 * @param string $name Identifies this particular bookmark.
	 * @return bool Whether the bookmark was successfully created.
	 */
	public function set_bookmark( $name ): bool {
		$result = parent::set_bookmark( $name );
		if ( $result ) {
			$this->bookmarked_open_stacks[ $name ] = array(
				'tags'       => $this->open_stack_tags,
				'attributes' => $this->open_stack_attributes,
				'indices'    => $this->open_stack_indices,
			);
		}
		return $result;
	}

	/**
	 * Removes a bookmark that is no longer needed.
	 *
	 * @inheritDoc
	 * @since 0.4.0
	 *
	 * @param string $name Name of the bookmark to remove.
	 * @return bool Whether the bookmark already existed before removal.
	 */
	public function release_bookmark( $name ): bool {
		if ( in_array( $name, array( self::END_OF_HEAD_BOOKMARK, self::END_OF_BODY_BOOKMARK ), true ) ) {
			$this->warn(
				__METHOD__,
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
	 * @since 0.4.0
	 * @since 0.9.0 Renamed from get_breadcrumbs() to get_indexed_breadcrumbs().
	 *
	 * @return Generator<array{non-empty-string, non-negative-int, array<non-empty-string, string>}> Breadcrumb.
	 */
	private function get_indexed_breadcrumbs(): Generator {
		foreach ( $this->open_stack_tags as $i => $breadcrumb_tag_name ) {
			yield array( $breadcrumb_tag_name, $this->open_stack_indices[ $i ], $this->open_stack_attributes[ $i ] );
		}
	}

	/**
	 * Gets disambiguating attributes.
	 *
	 * This returns the most stable attribute which can be used to disambiguate an XPath expression when the node index
	 * is not appropriate. This is used specifically for children of the `BODY`. The `id` and `role` attributes are most
	 * stable followed by the `class` attribute (cf. <https://g.co/gemini/share/032edd9063c1>), although all Block
	 * Themes utilize the 'wp-site-blocks' class name in the root `DIV`. Only one attribute is currently returned,
	 * although potentially more could be returned if additional disambiguation is needed in the future.
	 *
	 * @since 1.0.0
	 *
	 * @return array<non-empty-string, string> Disambiguating attributes.
	 */
	private function get_disambiguating_attributes(): array {
		$attributes = array();
		foreach ( array( 'id', 'role', 'class' ) as $attribute_name ) {
			$attribute_value = $this->get_attribute( $attribute_name );
			if ( null === $attribute_value ) {
				continue;
			}
			if ( true === $attribute_value ) {
				// In XPath, a boolean attribute in HTML like `<video controls>` is the same as `<video controls="">`. Both are matched by `//video[@controls=""]`.
				$attribute_value = '';
			} elseif ( 1 !== preg_match( '/^[a-zA-Z0-9_.\s:-]*$/', $attribute_value ) ) {
				// Skip attribute values which contain uncommon characters, especially single/double quote marks and
				// brackets, which could cause headaches when constructing/deconstructing XPath attribute predicates.
				continue;
			}

			$attributes[ $attribute_name ] = $attribute_value;
			break; // Stop when we've found one.
		}
		return $attributes;
	}

	/**
	 * Computes the HTML breadcrumbs for the currently-matched node, if matched.
	 *
	 * Breadcrumbs start at the outermost parent and descend toward the matched element.
	 * They always include the entire path from the root HTML node to the matched element.
	 *
	 * @since 0.9.0
	 * @see WP_HTML_Processor::get_breadcrumbs()
	 *
	 * @return non-empty-string[] Array of tag names representing path to matched node.
	 */
	public function get_breadcrumbs(): array {
		return $this->open_stack_tags;
	}

	/**
	 * Determines whether currently inside a foreign element (MATH or SVG).
	 *
	 * @since 0.4.0
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
	 * It would be nicer if this were like `.../DIV[1]/DIV[2]` but in XPath the position() here refers to the
	 * index of the preceding node set. So it has to rather be written `.../*[1][self::DIV]/*[2][self::DIV]`.
	 * Note that the first three levels lack any node index whereas the third level includes a disambiguating
	 * attribute predicate (e.g. `/HTML/BODY/DIV[@id="page"]`) for the reasons explained in {@see self::XPATH_PATTERN}.
	 * This predicate will be included once the transitional period is over.
	 *
	 * @since 0.4.0
	 *
	 * @return string XPath.
	 */
	public function get_xpath(): string {
		if ( null === $this->current_xpath ) {
			$this->current_xpath = '';
			foreach ( $this->get_indexed_breadcrumbs() as $i => list( $tag_name, $index, $attributes ) ) {
				if ( $i < 2 ) {
					$this->current_xpath .= "/$tag_name";
				} elseif ( 2 === $i && '/HTML/BODY' === $this->current_xpath ) {
					$segment = "/$tag_name";
					foreach ( $attributes as $attribute_name => $attribute_value ) {
						$segment .= sprintf(
							"[@%s='%s']",
							$attribute_name,
							$attribute_value // Note: $attribute_value has already been validated to only contain safe characters /^[a-zA-Z0-9_.\s:-]*/ which do not need escaping.
						);
					}
					$this->current_xpath .= $segment;
				} else {
					$this->current_xpath .= sprintf( '/*[%d][self::%s]', $index + 1, $tag_name );
				}
			}
		}
		return $this->current_xpath;
	}

	/**
	 * Gets stored XPath for the current open tag.
	 *
	 * This method was temporary for a transition period while new URL Metrics are collected for active installs
	 *
	 * @since 1.0.0
	 * @access private
	 * @deprecated
	 * @codeCoverageIgnore
	 *
	 * @return string XPath.
	 */
	public function get_stored_xpath(): string {
		return $this->get_xpath();
	}

	/**
	 * Returns whether the processor is currently at or inside the admin bar.
	 *
	 * This is only intended to be used internally by Optimization Detective as part of the "optimization loop". Tag
	 * visitors should not rely on this method as it may be deprecated in the future, especially with a migration to
	 * WP_HTML_Processor after {@link https://core.trac.wordpress.org/ticket/63020} is implemented.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @return bool Whether at or inside the admin bar.
	 */
	public function is_admin_bar(): bool {
		return (
			isset( $this->open_stack_tags[2], $this->open_stack_attributes[2]['id'] )
			&&
			'DIV' === $this->open_stack_tags[2]
			&&
			'wpadminbar' === $this->open_stack_attributes[2]['id']
		);
	}

	/**
	 * Appends raw HTML to the HEAD.
	 *
	 *  The provided HTML must be valid for insertion in the HEAD. No validation is currently performed. However, in the
	 *  future the HTML Processor may be used to ensure the validity of the provided HTML. At that time, when invalid
	 *  HTML is provided, this method may emit a `_doing_it_wrong()` warning.
	 *
	 * @since 0.4.0
	 *
	 * @param string $raw_html Raw HTML to inject.
	 */
	public function append_head_html( string $raw_html ): void {
		$this->buffered_text_replacements[ self::END_OF_HEAD_BOOKMARK ][] = $raw_html;
	}

	/**
	 * Appends raw HTML to the BODY.
	 *
	 * The provided HTML must be valid for insertion in the BODY. No validation is currently performed. However, in the
	 * future the HTML Processor may be used to ensure the validity of the provided HTML. At that time, when invalid
	 * HTML is provided, this method may emit a `_doing_it_wrong()` warning.
	 *
	 * @since 0.4.0
	 *
	 * @param string $raw_html Raw HTML to inject.
	 */
	public function append_body_html( string $raw_html ): void {
		$this->buffered_text_replacements[ self::END_OF_BODY_BOOKMARK ][] = $raw_html;
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
		if ( ! $this->reached_end_of_document ) {
			return parent::get_updated_html();
		}

		foreach ( array_keys( $this->buffered_text_replacements ) as $bookmark ) {
			$html_strings = $this->buffered_text_replacements[ $bookmark ];
			if ( count( $html_strings ) === 0 ) {
				continue;
			}
			if ( ! $this->has_bookmark( $bookmark ) ) {
				$this->warn(
					__METHOD__,
					sprintf(
						/* translators: %s is the bookmark name */
						__( 'Unable to append markup to %s since the bookmark no longer exists.', 'optimization-detective' ),
						$bookmark
					)
				);
			} else {
				$start = $this->bookmarks[ $bookmark ]->start;

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
	 * @since 0.4.0
	 *
	 * @phpstan-param callable-string $function_name
	 *
	 * @param string $function_name Function name.
	 * @param string $message       Warning message.
	 *
	 * @noinspection PhpDocMissingThrowsInspection
	 */
	private function warn( string $function_name, string $message ): void {
		/**
		 * No WP_Exception is thrown by wp_trigger_error() since E_USER_ERROR is not passed as the error level.
		 *
		 * @noinspection PhpUnhandledExceptionInspection
		 */
		wp_trigger_error(
			$function_name,
			esc_html( $message )
		);
	}
}
