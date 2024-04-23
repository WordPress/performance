<?php
/**
 * Optimization Detective: OD_HTML_Tag_Walker class
 *
 * @package optimization-detective
 * @since 0.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker leveraging WP_HTML_Tag_Processor which gathers breadcrumbs for computing XPaths while iterating the open_tags() generator.
 *
 * Eventually this class should be made largely obsolete once `WP_HTML_Processor` is fully implemented to support all HTML tags.
 *
 * @since 0.1.0
 * @since 0.1.1 Renamed from OD_HTML_Tag_Processor to OD_HTML_Tag_Walker
 * @access private
 */
final class OD_HTML_Tag_Walker {

	/**
	 * HTML void tags (i.e. those which are self-closing).
	 *
	 * @link https://html.spec.whatwg.org/multipage/syntax.html#void-elements
	 * @see WP_HTML_Processor::is_void()
	 * @todo Reuse `WP_HTML_Processor::is_void()` once WordPress 6.4 is the minimum-supported version.
	 *
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
	 * @see self::get_xpath()
	 * @var string
	 */
	const XPATH_PATTERN = '^(/\*\[\d+\]\[self::.+?\])+$';

	/**
	 * Bookmark for the end of the HEAD.
	 *
	 * @var string
	 */
	const END_OF_HEAD_BOOKMARK = 'end_of_head';

	/**
	 * Bookmark for the end of the BODY.
	 *
	 * @var string
	 */
	const END_OF_BODY_BOOKMARK = 'end_of_body';

	/**
	 * Open stack tags.
	 *
	 * @var string[]
	 */
	private $open_stack_tags = array();

	/**
	 * Open stag indices.
	 *
	 * @var int[]
	 */
	private $open_stack_indices = array();

	/**
	 * Processor.
	 *
	 * @var OD_HTML_Tag_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @param string $html HTML to process.
	 */
	public function __construct( string $html ) {
		$this->processor = new OD_HTML_Tag_Processor( $html );
	}

	/**
	 * Gets all open tags in the document.
	 *
	 * A generator is used so that when iterating at a specific tag, additional information about the tag at that point
	 * can be queried from the class. Similarly, mutations may be performed when iterating at an open tag.
	 *
	 * @since 0.1.0
	 *
	 * @return Generator<string> Tag name of current open tag.
	 */
	public function open_tags(): Generator {
		$p = $this->processor;

		/*
		 * The keys for the following two arrays correspond to each other. Given the following document:
		 *
		 * <html>
		 *   <head>
		 *   </head>
		 *   <body>
		 *     <p>Hello!</p>
		 *     <img src="lcp.png">
		 *   </body>
		 * </html>
		 *
		 * Upon processing the IMG element, the two arrays should be equal to the following:
		 *
		 * $open_stack_tags    = array( 'HTML', 'BODY', 'IMG' );
		 * $open_stack_indices = array( 0, 1, 1 );
		 */
		$this->open_stack_tags    = array();
		$this->open_stack_indices = array();
		while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag_name = $p->get_tag();
			if ( ! $p->is_tag_closer() ) {

				// Close an open P tag when a P-closing tag is encountered.
				// TODO: There are quite a few more cases of optional closing tags: https://html.spec.whatwg.org/multipage/syntax.html#optional-tags
				// Nevertheless, given WordPress's legacy of XHTML compatibility, the lack of closing tags may not be common enough to warrant worrying about any of them.
				if ( in_array( $tag_name, self::P_CLOSING_TAGS, true ) ) {
					$i = array_search( 'P', $this->open_stack_tags, true );
					if ( false !== $i ) {
						array_splice( $this->open_stack_tags, $i );
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

				// Now that the breadcrumbs are constructed, yield the tag name so that they can be queried if desired.
				// Other mutations may be performed to the open tag's attributes by the callee at this point as well.
				yield $tag_name;

				// Immediately pop off self-closing and raw text tags.
				if (
					in_array( $tag_name, self::VOID_TAGS, true )
					||
					in_array( $tag_name, self::RAW_TEXT_TAGS, true )
					||
					( $p->has_self_closing_flag() && $this->is_foreign_element() )
				) {
					array_pop( $this->open_stack_tags );
				}
			} else {
				// If the closing tag is for self-closing or raw text tag, we ignore it since it was already handled above.
				if (
					in_array( $tag_name, self::VOID_TAGS, true )
					||
					in_array( $tag_name, self::RAW_TEXT_TAGS, true )
				) {
					continue;
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
					$p->set_bookmark( self::END_OF_HEAD_BOOKMARK );
				} elseif ( 'BODY' === $popped_tag_name ) {
					$p->set_bookmark( self::END_OF_BODY_BOOKMARK );
				}

				array_splice( $this->open_stack_indices, count( $this->open_stack_tags ) + 1 );
			}
		}
	}

	/**
	 * Warns of bad markup.
	 *
	 * @param string $message Warning message.
	 */
	private function warn( string $message ): void {
		wp_trigger_error(
			__CLASS__ . '::open_tags',
			esc_html( $message )
		);
	}

	/**
	 * Gets breadcrumbs for the current open tag.
	 *
	 * A breadcrumb consists of a tag name and its sibling index.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @return string XPath.
	 */
	public function get_xpath(): string {
		$xpath = '';
		foreach ( $this->get_breadcrumbs() as list( $tag_name, $index ) ) {
			$xpath .= sprintf( '/*[%d][self::%s]', $index, $tag_name );
		}
		return $xpath;
	}

	/**
	 * Append HTML to the HEAD.
	 *
	 * Before this can be called, the document must first have been iterated over with
	 * {@see OD_HTML_Tag_Walker::open_tags()} so that the bookmark for the HEAD end tag is set.
	 *
	 * @param string $html HTML to inject.
	 * @return bool Whether successful.
	 */
	public function append_head_html( string $html ): bool {
		$success = $this->processor->append_html( self::END_OF_HEAD_BOOKMARK, $html );
		if ( ! $success ) {
			$this->warn( __( 'Unable to append markup to the HEAD.', 'optimization-detective' ) );
		}
		return $success;
	}

	/**
	 * Append HTML to the BODY.
	 *
	 * Before this can be called, the document must first have been iterated over with
	 * {@see OD_HTML_Tag_Walker::open_tags()} so that the bookmark for the BODY end tag is set.
	 *
	 * @param string $html HTML to inject.
	 * @return bool Whether successful.
	 */
	public function append_body_html( string $html ): bool {
		$success = $this->processor->append_html( self::END_OF_BODY_BOOKMARK, $html );
		if ( ! $success ) {
			$this->warn( __( 'Unable to append markup to the BODY.', 'optimization-detective' ) );
		}
		return $success;
	}

	/**
	 * Returns the value of a requested attribute from a matched tag opener if that attribute exists.
	 *
	 * This is a wrapper around the underlying WP_HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.1.0
	 * @see WP_HTML_Tag_Processor::get_attribute()
	 *
	 * @param string $name Name of attribute whose value is requested.
	 * @return string|true|null Value of attribute or `null` if not available. Boolean attributes return `true`.
	 */
	public function get_attribute( string $name ) {
		return $this->processor->get_attribute( $name );
	}

	/**
	 * Updates or creates a new attribute on the currently matched tag with the passed value.
	 *
	 * This is a wrapper around the underlying WP_HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.1.0
	 * @see WP_HTML_Tag_Processor::set_attribute()
	 *
	 * @param string      $name  The attribute name to target.
	 * @param string|bool $value The new attribute value.
	 * @return bool Whether an attribute value was set.
	 */
	public function set_attribute( string $name, $value ): bool {
		return $this->processor->set_attribute( $name, $value );
	}

	/**
	 * Removes an attribute from the currently-matched tag.
	 *
	 * This is a wrapper around the underlying WP_HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.1.0
	 * @see WP_HTML_Tag_Processor::remove_attribute()
	 *
	 * @param string $name The attribute name to remove.
	 * @return bool Whether an attribute was removed.
	 */
	public function remove_attribute( string $name ): bool {
		return $this->processor->remove_attribute( $name );
	}

	/**
	 * Returns the string representation of the HTML Tag Processor.
	 *
	 * This is a wrapper around the underlying HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.1.0
	 * @see WP_HTML_Tag_Processor::get_updated_html()
	 *
	 * @return string The processed HTML.
	 */
	public function get_updated_html(): string {
		return $this->processor->get_updated_html();
	}
}
