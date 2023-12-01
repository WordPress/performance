<?php
/**
 * Image Loading Optimization: ILO_HTML_Tag_Processor class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Processor leveraging WP_HTML_Tag_Processor which gathers breadcrumbs which can be queried while iterating the open_tags() generator .
 *
 * @since n.e.x.t
 * @access private
 */
final class ILO_HTML_Tag_Processor {

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
	 * @var WP_HTML_Tag_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @param string $html HTML to process.
	 */
	public function __construct( string $html ) {
		$this->processor = new WP_HTML_Tag_Processor( $html );
	}

	/**
	 * Gets all open tags in the document.
	 *
	 * A generator is used so that when iterating at a specific tag, additional information about the tag at that point
	 * can be queried from the class. Similarly, mutations may be performed when iterating at an open tag.
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

				// Only increment the tag index at this level only if it isn't the admin bar, since the presence of the
				// admin bar can throw off the indices.
				if ( 'DIV' === $tag_name && $p->get_attribute( 'id' ) === 'wpadminbar' ) {
					--$this->open_stack_indices[ $level ];
				}

				// Now that the breadcrumbs are constructed, yield the tag name so that they can be queried if desired.
				// Other mutations may be performed to the open tag's attributes by the callee at this point as well.
				yield $tag_name;

				// Immediately pop off self-closing tags.
				if ( in_array( $tag_name, self::VOID_TAGS, true ) ) {
					array_pop( $this->open_stack_tags );
				}
			} else {
				// If the closing tag is for self-closing tag, we ignore it since it was already handled above.
				if ( in_array( $tag_name, self::VOID_TAGS, true ) ) {
					continue;
				}

				// Since SVG and MathML can have a lot more self-closing/empty tags, potentially pop off the stack until getting to the open tag.
				$did_splice = false;
				if ( 'SVG' === $tag_name || 'MATH' === $tag_name ) {
					$i = array_search( $tag_name, $this->open_stack_tags, true );
					if ( false !== $i ) {
						array_splice( $this->open_stack_tags, $i );
						$did_splice = true;
					}
				}

				if ( ! $did_splice ) {
					$popped_tag_name = array_pop( $this->open_stack_tags );
					if ( $popped_tag_name !== $tag_name && function_exists( 'wp_trigger_error' ) ) {
						wp_trigger_error(
							__METHOD__,
							esc_html(
								sprintf(
									/* translators: 1: Popped tag name, 2: Closing tag name */
									__( 'Expected popped tag stack element %1$s to match the currently visited closing tag %2$s.', 'performance-lab' ),
									$popped_tag_name,
									$tag_name
								)
							)
						);
					}
				}
				array_splice( $this->open_stack_indices, count( $this->open_stack_tags ) + 1 );
			}
		}
	}

	/**
	 * Gets breadcrumbs for the current open tag.
	 *
	 * Breadcrumbs are constructed to match the format from detect.js.
	 *
	 * @return array<array{tag: string, index: int}> Breadcrumbs.
	 */
	public function get_breadcrumbs(): array {
		$breadcrumbs = array();
		foreach ( $this->open_stack_tags as $i => $breadcrumb_tag_name ) {
			$breadcrumbs[] = array(
				'tag'   => $breadcrumb_tag_name,
				'index' => $this->open_stack_indices[ $i ],
			);
		}
		return $breadcrumbs;
	}

	/**
	 * Returns the value of a requested attribute from a matched tag opener if that attribute exists.
	 *
	 * This is a wrapper around the underlying HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
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
	 * This is a wrapper around the underlying HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
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
	 * This is a wrapper around the underlying HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
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
	 * @see WP_HTML_Tag_Processor::get_updated_html()
	 *
	 * @return string The processed HTML.
	 */
	public function get_updated_html(): string {
		return $this->processor->get_updated_html();
	}
}
