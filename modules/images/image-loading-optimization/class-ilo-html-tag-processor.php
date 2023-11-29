<?php
/**
 * Image Loading Optimization: ILO_HTML_Tag_Processor class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Subclass of WP_HTML_Tag_Processor that adds support for breadcrumbs and a visiting callback.
 *
 * @since n.e.x.t
 * @access private
 */
class ILO_HTML_Tag_Processor {

	/**
	 * HTML elements that are self-closing.
	 *
	 * @link https://www.w3.org/TR/html5/syntax.html#serializing-html-fragments
	 * @link https://github.com/ampproject/amp-toolbox-php/blob/c79a0fe558a3c042aee4789bbf33376cca7a733d/src/Html/Tag.php#L206-L232
	 *
	 * @var string[]
	 */
	const SELF_CLOSING_TAGS = array(
		'AREA',
		'BASE',
		'BASEFONT',
		'BGSOUND',
		'BR',
		'COL',
		'EMBED',
		'FRAME',
		'HR',
		'IMG',
		'INPUT',
		'KEYGEN',
		'LINK',
		'META',
		'PARAM',
		'SOURCE',
		'TRACK',
		'WBR',
	);

	/**
	 * The set of HTML tags whose presence will implicitly close a <p> element.
	 * For example '<p>foo<h1>bar</h1>' should parse the same as '<p>foo</p><h1>bar</h1>'.
	 *
	 * @link https://www.w3.org/TR/html-markup/p.html
	 * @link https://github.com/ampproject/amp-toolbox-php/blob/c79a0fe558a3c042aee4789bbf33376cca7a733d/src/Html/Tag.php#L262-L293
	 */
	const P_CLOSING_TAGS = array(
		'ADDRESS',
		'ARTICLE',
		'ASIDE',
		'BLOCKQUOTE',
		'DIR',
		'DL',
		'FIELDSET',
		'FOOTER',
		'FORM',
		'H1',
		'H2',
		'H3',
		'H4',
		'H5',
		'H6',
		'HEADER',
		'HR',
		'MENU',
		'NAV',
		'OL',
		'P',
		'PRE',
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
	 * Walk over the document.
	 *
	 * Whenever an open tag is encountered, invoke the supplied $open_tag_callback and pass the tag name and breadcrumbs.
	 *
	 * @param callable $open_tag_callback Open tag callback. The processor instance is passed as the sole argument.
	 */
	public function walk( callable $open_tag_callback ) {
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
		 * The two upon processing the IMG element, the two arrays should be equal to the following:
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

				// TODO: We should consider not collecting metrics when the admin bar is shown and the user is logged-in.
				// Only increment the tag index at this level only if it isn't the admin bar, since the presence of the
				// admin bar can throw off the indices.
				if ( 'DIV' === $tag_name && $p->get_attribute( 'id' ) === 'wpadminbar' ) {
					--$this->open_stack_indices[ $level ];
				}

				// Invoke the callback to do processing.
				$open_tag_callback( $tag_name, $this->get_breadcrumbs() );

				// Immediately pop off self-closing tags.
				if ( in_array( $tag_name, self::SELF_CLOSING_TAGS, true ) ) {
					array_pop( $this->open_stack_tags );
				}
			} else {
				// If the closing tag is for self-closing tag, we ignore it since it was already handled above.
				if ( in_array( $tag_name, self::SELF_CLOSING_TAGS, true ) ) {
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
					if ( $popped_tag_name !== $tag_name ) {
						error_log( "Expected popped tag stack element $popped_tag_name to match the currently visited closing tag $tag_name." ); // phpcs:ignore
					}
				}
				array_splice( $this->open_stack_indices, count( $this->open_stack_tags ) + 1 );
			}
		}
	}

	/**
	 * Returns the uppercase name of the matched tag.
	 *
	 * This is a wrapper around the underlying HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @see WP_HTML_Tag_Processor::get_tag()
	 *
	 * @return string|null Name of currently matched tag in input HTML, or `null` if none found.
	 */
	public function get_tag() {
		return $this->processor->get_tag();
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
	 * Removes the fetchpriority attribute from the current node being walked over.
	 *
	 * Also sets an attribute to indicate that the attribute was removed.
	 *
	 * @return bool Whether an attribute was removed.
	 */
	public function remove_fetchpriority_attribute(): bool {
		$p = $this->processor;
		if ( $p->get_attribute( 'fetchpriority' ) ) {
			$p->set_attribute( 'data-ilo-removed-fetchpriority', $p->get_attribute( 'fetchpriority' ) );
			return $p->remove_attribute( 'fetchpriority' );
		} else {
			return false;
		}
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
