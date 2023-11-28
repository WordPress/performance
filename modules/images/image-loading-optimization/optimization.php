<?php
/**
 * Optimizing for image loading optimization.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * HTML elements that are self-closing.
 *
 * @link https://www.w3.org/TR/html5/syntax.html#serializing-html-fragments
 * @link https://github.com/ampproject/amp-toolbox-php/blob/c79a0fe558a3c042aee4789bbf33376cca7a733d/src/Html/Tag.php#L206-L232
 *
 * @var string[]
 */
const ILO_SELF_CLOSING_TAGS = array(
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
const ILO_P_CLOSING_TAGS = array(
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
 * Adds template output buffer filter for optimization if eligible.
 */
function ilo_maybe_add_template_output_buffer_filter() {
	if ( ! ilo_can_optimize_response() ) {
		return;
	}
	add_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' );
}
add_action( 'wp', 'ilo_maybe_add_template_output_buffer_filter' );

/**
 * Constructs preload links.
 *
 * @param array $lcp_images_by_minimum_viewport_widths LCP images keyed by minimum viewport width, amended with attributes key for the IMG attributes.
 * @return string Markup for one or more preload link tags.
 */
function ilo_construct_preload_links( array $lcp_images_by_minimum_viewport_widths ): string {
	$preload_links = array();

	$minimum_viewport_widths = array_keys( $lcp_images_by_minimum_viewport_widths );
	for ( $i = 0, $len = count( $minimum_viewport_widths ); $i < $len; $i++ ) {
		$lcp_element = $lcp_images_by_minimum_viewport_widths[ $minimum_viewport_widths[ $i ] ];
		if ( false === $lcp_element || empty( $lcp_element['attributes'] ) ) {
			// No LCP element at this breakpoint, so nothing to preload.
			continue;
		}

		$img_attributes = $lcp_element['attributes'];

		// Prevent preloading src for browsers that don't support imagesrcset on the link element.
		if ( isset( $img_attributes['src'], $img_attributes['srcset'] ) ) {
			unset( $img_attributes['src'] );
		}

		// Add media query.
		$media_query = sprintf( 'screen and ( min-width: %dpx )', $minimum_viewport_widths[ $i ] );
		if ( isset( $minimum_viewport_widths[ $i + 1 ] ) ) {
			$media_query .= sprintf( ' and ( max-width: %dpx )', $minimum_viewport_widths[ $i + 1 ] - 1 );
		}
		$img_attributes['media'] = $media_query;

		// Construct preload link.
		$link_tag = '<link data-ilo-added-tag rel="preload" fetchpriority="high" as="image"';
		foreach ( array_filter( $img_attributes ) as $name => $value ) {
			// Map img attribute name to link attribute name.
			if ( 'srcset' === $name || 'sizes' === $name ) {
				$name = 'image' . $name;
			} elseif ( 'src' === $name ) {
				$name = 'href';
			}

			$link_tag .= sprintf( ' %s="%s"', $name, esc_attr( $value ) );
		}
		$link_tag .= ">\n";

		$preload_links[] = $link_tag;
	}

	return implode( '', $preload_links );
}

/**
 * Walks the provided HTML document, invoking the callback at each open tag.
 *
 * @param string   $html              Complete HTML document.
 * @param callable $open_tag_callback Callback to invoke at each open tag. Callback is passed instance of
 *                                    WP_HTML_Tag_Processor as well as the breadcrumbs for the current element.
 * @return string Updated HTML if modified by callback.
 */
function ilo_walk_document( string $html, callable $open_tag_callback ): string {
	$p = new WP_HTML_Tag_Processor( $html );

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
	$open_stack_tags    = array();
	$open_stack_indices = array();
	while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag_name = $p->get_tag();
		if ( ! $p->is_tag_closer() ) {

			// Close an open P tag when a P-closing tag is encountered.
			if ( in_array( $tag_name, ILO_P_CLOSING_TAGS, true ) ) {
				$i = array_search( 'P', $open_stack_tags, true );
				if ( false !== $i ) {
					array_splice( $open_stack_tags, $i );
					array_splice( $open_stack_indices, count( $open_stack_tags ) );
				}
			}

			$level             = count( $open_stack_tags );
			$open_stack_tags[] = $tag_name;

			if ( ! isset( $open_stack_indices[ $level ] ) ) {
				$open_stack_indices[ $level ] = 0;
			} else {
				++$open_stack_indices[ $level ];
			}

			// TODO: We should consider not collecting metrics when the admin bar is shown and the user is logged-in.
			// Only increment the tag index at this level only if it isn't the admin bar, since the presence of the
			// admin bar can throw off the indices.
			if ( 'DIV' === $tag_name && $p->get_attribute( 'id' ) === 'wpadminbar' ) {
				--$open_stack_indices[ $level ];
			}

			// Construct the breadcrumbs to match the format from detect.js.
			$breadcrumbs = array();
			foreach ( $open_stack_tags as $i => $breadcrumb_tag_name ) {
				$breadcrumbs[] = array(
					'tagName' => $breadcrumb_tag_name,
					'index'   => $open_stack_indices[ $i ],
				);
			}

			// Invoke the callback to do processing.
			$open_tag_callback( $p, $breadcrumbs );

			// Immediately pop off self-closing tags.
			if ( in_array( $tag_name, ILO_SELF_CLOSING_TAGS, true ) ) {
				array_pop( $open_stack_tags );
			}
		} else {
			// If the closing tag is for self-closing tag, we ignore it since it was already handled above.
			if ( in_array( $tag_name, ILO_SELF_CLOSING_TAGS, true ) ) {
				continue;
			}

			// Since SVG and MathML can have a lot more self-closing/empty tags, potentially pop off the stack until getting to the open tag.
			$did_splice = false;
			if ( 'SVG' === $tag_name || 'MATH' === $tag_name ) {
				$i = array_search( $tag_name, $open_stack_tags, true );
				if ( false !== $i ) {
					array_splice( $open_stack_tags, $i );
					$did_splice = true;
				}
			}

			if ( ! $did_splice ) {
				$popped_tag_name = array_pop( $open_stack_tags );
				if ( $popped_tag_name !== $tag_name ) {
					error_log( "Expected popped tag stack element {$popped_tag_name} to match the currently visited closing tag $tag_name." ); // phpcs:ignore
				}
			}
			array_splice( $open_stack_indices, count( $open_stack_tags ) + 1 );
		}
	}

	return $p->get_updated_html();
}

/**
 * Removes fetchpriority from the current tag if present.
 *
 * @param WP_HTML_Tag_Processor $p Processor instance.
 */
function ilo_remove_fetchpriority_from_current_tag_processor_node( WP_HTML_Tag_Processor $p ) {
	if ( $p->get_attribute( 'fetchpriority' ) ) {
		$p->set_attribute( 'data-ilo-removed-fetchpriority', $p->get_attribute( 'fetchpriority' ) );
		$p->remove_attribute( 'fetchpriority' );
	}
}

/**
 * Optimizes template output buffer.
 *
 * @param string $buffer Template output buffer.
 * @return string Filtered template output buffer.
 */
function ilo_optimize_template_output_buffer( string $buffer ): string {
	$slug        = ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() );
	$post        = ilo_get_url_metrics_post( $slug );
	$url_metrics = ilo_parse_stored_url_metrics( $post );

	$lcp_images_by_minimum_viewport_widths = ilo_get_lcp_elements_by_minimum_viewport_widths( $url_metrics, ilo_get_breakpoint_max_widths() );

	if ( ! empty( $lcp_images_by_minimum_viewport_widths ) ) {
		$breakpoint_lcp_images = array_filter( $lcp_images_by_minimum_viewport_widths );

		// If there is exactly one LCP image for all breakpoints, ensure fetchpriority is set on that image only.
		if ( 1 === count( $lcp_images_by_minimum_viewport_widths ) && 1 === count( $breakpoint_lcp_images ) ) {
			$lcp_element = current( $lcp_images_by_minimum_viewport_widths );

			$buffer = ilo_walk_document(
				$buffer,
				static function ( WP_HTML_Tag_Processor $p, array $breadcrumbs ) use ( $lcp_element ) {
					if ( 'IMG' !== $p->get_tag() ) {
						return;
					}
					if ( $breadcrumbs === $lcp_element['breadcrumbs'] ) {
						$p->set_attribute( 'fetchpriority', 'high' );
						$p->set_attribute( 'data-ilo-added-fetchpriority', true );
					} else {
						ilo_remove_fetchpriority_from_current_tag_processor_node( $p );
					}
				}
			);
			// TODO: We could also add the preload links here.
		} else {
			// If there is not exactly one LCP element, we need to remove fetchpriority from all images while also
			// capturing the attributes from the LCP element which we can then use for preload links.
			$buffer = ilo_walk_document(
				$buffer,
				static function ( WP_HTML_Tag_Processor $p, array $breadcrumbs ) use ( &$lcp_images_by_minimum_viewport_widths ) {
					if ( 'IMG' !== $p->get_tag() ) {
						return;
					}
					ilo_remove_fetchpriority_from_current_tag_processor_node( $p );

					// Capture the attributes from the LCP element to use in preload links.
					if ( count( $lcp_images_by_minimum_viewport_widths ) > 1 ) {
						foreach ( $lcp_images_by_minimum_viewport_widths as &$lcp_element ) {
							if ( $lcp_element && $lcp_element['breadcrumbs'] === $breadcrumbs ) {
								$lcp_element['attributes'] = array();
								foreach ( array( 'src', 'srcset', 'sizes', 'crossorigin', 'integrity' ) as $attr_name ) {
									$lcp_element['attributes'][ $attr_name ] = $p->get_attribute( $attr_name );
								}
							}
						}
					}
				}
			);

			$preload_links = ilo_construct_preload_links( $lcp_images_by_minimum_viewport_widths );

			// TODO: In the future, WP_HTML_Processor could be used to do this injection. However, given the simple replacement here this is not essential.
			$buffer = preg_replace( '#(?=</head>)#', $preload_links, $buffer, 1 );
		}
	}

	return $buffer;
}
