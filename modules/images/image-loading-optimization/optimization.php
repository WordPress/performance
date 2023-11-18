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

function ilo_construct_preload_links( array $lcp_images_by_minimum_viewport_widths ): string {
	$minimum_viewport_widths = array_keys( $lcp_images_by_minimum_viewport_widths );
	for ( $i = 0, $len = count( $minimum_viewport_widths ); $i < $len; $i++ ) {
		$lcp_element = $lcp_images_by_minimum_viewport_widths[ $minimum_viewport_widths[ $i ] ];
		if ( false === $lcp_element ) {
			// No LCP element at this breakpoint, so nothing to preload.
			continue;
		}

		$media_query = sprintf( 'screen and ( min-width: %dpx )', $minimum_viewport_widths[ $i ] );
		if ( isset( $minimum_viewport_widths[ $i + 1 ] ) ) {
			$media_query .= sprintf( ' and ( max-width: %dpx )', $minimum_viewport_widths[ $i + 1 ] - 1 );
		}

	}

	return '';
}

function ilo_find_element_by_breadcrumbs( string $html, array $breadcrumbs ): array {
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
			} elseif ( ! ( 'DIV' === $tag_name && $p->get_attribute( 'id' ) === 'wpadminbar' ) ) {
				// Only increment the tag index at this level only if it isn't the admin bar, since the presence of the
				// admin bar can throw off the indices.
				++$open_stack_indices[ $level ];
			}

			// TODO: Now check if $open_stack matches breadcrumbs.

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

		// ...
		$src    = $p->get_attribute( 'src' );
		$srcset = $p->get_attribute( 'srcset' );
	}

	return array();
}

function ilo_remove_fetchpriority_from_all_images( string $html ): string {
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
		if ( $p->get_attribute( 'fetchpriority' ) ) {
			$p->set_attribute( 'data-wp-removed-fetchpriority', $p->get_attribute( 'fetchpriority' ) );
			$p->remove_attribute( 'fetchpriority' );
		}
	}
	return $p->get_updated_html();
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

	// TODO: We need to walk the document to find the breadcrumbs.
	if ( ! empty( $lcp_images_by_minimum_viewport_widths ) ) {
		$breakpoint_count_with_lcp_images = count( array_filter( $lcp_images_by_minimum_viewport_widths ) );

		if ( 1 === count( $lcp_images_by_minimum_viewport_widths ) && 1 === $breakpoint_count_with_lcp_images ) {
			// If there is exactly one LCP image for all breakpoints, ensure fetchpriority is set on that image only.
			$buffer = ilo_remove_fetchpriority_from_all_images( $buffer );

			$lcp_element = current( $lcp_images_by_minimum_viewport_widths );

		} elseif ( 0 === $breakpoint_count_with_lcp_images ) {
			// If there are no LCP images, remove fetchpriority from all images.
			$buffer = ilo_remove_fetchpriority_from_all_images( $buffer );
		} else {
			// Otherwise, there are two or more breakpoints have different LCP images, so we must remove fetchpriority
			// from all images and add breakpoint-specific preload links.
			$buffer = ilo_remove_fetchpriority_from_all_images( $buffer );

			// TODO: We need to locate the elements by their breadcrumbs.
			ilo_construct_preload_links( $lcp_images_by_minimum_viewport_widths );

		}
	}

	return $buffer;
}

