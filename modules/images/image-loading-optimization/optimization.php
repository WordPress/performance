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
 * Adds template output buffer filter for optimization if eligible.
 *
 * @since n.e.x.t
 * @access private
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
 * @since n.e.x.t
 * @access private
 *
 * @param array $lcp_elements_by_minimum_viewport_widths LCP images keyed by minimum viewport width, amended with attributes key for the IMG attributes.
 * @return string Markup for zero or more preload link tags.
 */
function ilo_construct_preload_links( array $lcp_elements_by_minimum_viewport_widths ): string {
	$preload_links = array();

	// This uses a for loop to be able to access the following element within the iteration, using a numeric index.
	$minimum_viewport_widths = array_keys( $lcp_elements_by_minimum_viewport_widths );
	for ( $i = 0, $len = count( $minimum_viewport_widths ); $i < $len; $i++ ) {
		$lcp_element = $lcp_elements_by_minimum_viewport_widths[ $minimum_viewport_widths[ $i ] ];
		if ( false === $lcp_element ) {
			// No supported LCP element at this breakpoint, so nothing to preload.
			continue;
		}

		// TODO: Add support for background images.
		$img_attributes = $lcp_element['attributes'];

		// Prevent preloading src for browsers that don't support imagesrcset on the link element.
		if ( isset( $img_attributes['src'], $img_attributes['srcset'] ) ) {
			unset( $img_attributes['src'] );
		}

		// Add media query if it's going to be something other than just `min-width: 0px`.
		$minimum_viewport_width = $minimum_viewport_widths[ $i ];
		$maximum_viewport_width = isset( $minimum_viewport_widths[ $i + 1 ] ) ? $minimum_viewport_widths[ $i + 1 ] - 1 : null;
		if ( $minimum_viewport_width > 0 || null !== $maximum_viewport_width ) {
			$media_query = sprintf( '( min-width: %dpx )', $minimum_viewport_width );
			if ( null !== $maximum_viewport_width ) {
				$media_query .= sprintf( ' and ( max-width: %dpx )', $maximum_viewport_width );
			}
			$img_attributes['media'] = $media_query;
		}

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
 * Optimizes template output buffer.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $buffer Template output buffer.
 * @return string Filtered template output buffer.
 */
function ilo_optimize_template_output_buffer( string $buffer ): string {
	$slug = ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() );
	$post = ilo_get_url_metrics_post( $slug );

	$url_metrics = $post ? ilo_parse_stored_url_metrics( $post ) : array();

	$needed_minimum_viewport_widths = ilo_get_needed_minimum_viewport_widths(
		$url_metrics,
		microtime( true ),
		ilo_get_breakpoint_max_widths(),
		ilo_get_url_metrics_breakpoint_sample_size(),
		ilo_get_url_metric_freshness_ttl()
	);

	// Whether we need to add the data-ilo-xpath attribute to elements and whether the detection script should be injected.
	$needs_detection = ilo_needs_url_metric_for_breakpoint( $needed_minimum_viewport_widths );

	$breakpoint_max_widths                   = ilo_get_breakpoint_max_widths();
	$url_metrics_grouped_by_breakpoint       = ilo_group_url_metrics_by_breakpoint( $url_metrics, $breakpoint_max_widths );
	$lcp_elements_by_minimum_viewport_widths = ilo_get_lcp_elements_by_minimum_viewport_widths( $url_metrics_grouped_by_breakpoint );
	$all_breakpoints_have_url_metrics        = count( array_filter( $url_metrics_grouped_by_breakpoint ) ) === count( $breakpoint_max_widths ) + 1;

	// Optimize looking up the LCP element by XPath.
	$lcp_element_minimum_viewport_width_by_xpath = array();
	foreach ( $lcp_elements_by_minimum_viewport_widths as $minimum_viewport_width => $lcp_element ) {
		if ( false !== $lcp_element ) {
			$lcp_element_minimum_viewport_width_by_xpath[ $lcp_element['xpath'] ][] = $minimum_viewport_width;
		}
	}

	// TODO: Handle case when the LCP element is not an image at all, but rather a background-image.
	// Prepare to set fetchpriority attribute on the image when all breakpoints have the same LCP element.
	if (
		// All breakpoints share the same LCP element (or all have none at all).
		1 === count( $lcp_elements_by_minimum_viewport_widths )
		&&
		// The breakpoints don't share a common lack of an LCP image.
		! in_array( false, $lcp_elements_by_minimum_viewport_widths, true )
		&&
		// All breakpoints have URL metrics being reported.
		$all_breakpoints_have_url_metrics
	) {
		$common_lcp_element = current( $lcp_elements_by_minimum_viewport_widths );
	} else {
		$common_lcp_element = null;
	}

	// Walk over all IMG tags in the document and ensure fetchpriority is set/removed, and gather IMG attributes for preloading.
	$processor = new ILO_HTML_Tag_Processor( $buffer );
	foreach ( $processor->open_tags() as $tag_name ) {
		if ( 'IMG' !== $tag_name ) {
			continue;
		}

		$xpath = $processor->get_xpath();

		// Ensure the fetchpriority attribute is set on the element properly.
		if ( $common_lcp_element && $xpath === $common_lcp_element['xpath'] ) {
			if ( 'high' === $processor->get_attribute( 'fetchpriority' ) ) {
				$processor->set_attribute( 'data-ilo-fetchpriority-already-added', true );
			} else {
				$processor->set_attribute( 'fetchpriority', 'high' );
				$processor->set_attribute( 'data-ilo-added-fetchpriority', true );
			}

			// Never include loading=lazy on the LCP image common across all breakpoints.
			if ( 'lazy' === $processor->get_attribute( 'loading' ) ) {
				$processor->set_attribute( 'data-ilo-removed-loading', $processor->get_attribute( 'loading' ) );
				$processor->remove_attribute( 'loading' );
			}
		} elseif ( $all_breakpoints_have_url_metrics && $processor->get_attribute( 'fetchpriority' ) ) {
			// Note: The $all_breakpoints_have_url_metrics condition here allows for server-side heuristics to
			// continue to apply while waiting for all breakpoints to have metrics collected for them.
			$processor->set_attribute( 'data-ilo-removed-fetchpriority', $processor->get_attribute( 'fetchpriority' ) );
			$processor->remove_attribute( 'fetchpriority' );
		}

		// Capture the attributes from the LCP elements to use in preload links.
		if ( isset( $lcp_element_minimum_viewport_width_by_xpath[ $xpath ] ) ) {
			$attributes = array();
			foreach ( array( 'src', 'srcset', 'sizes', 'crossorigin' ) as $attr_name ) {
				$attributes[ $attr_name ] = $processor->get_attribute( $attr_name );
			}
			foreach ( $lcp_element_minimum_viewport_width_by_xpath[ $xpath ] as $minimum_viewport_width ) {
				$lcp_elements_by_minimum_viewport_widths[ $minimum_viewport_width ]['attributes'] = $attributes;
			}
		}

		if ( $needs_detection ) {
			$processor->set_attribute( 'data-ilo-xpath', $xpath );
		}
	}
	$buffer = $processor->get_updated_html();

	// Inject any preload links at the end of the HEAD. In the future, WP_HTML_Processor could be used to do this injection.
	// However, given the simple replacement here this is not essential.
	$head_injection = ilo_construct_preload_links( $lcp_elements_by_minimum_viewport_widths );

	// Inject detection script.
	// TODO: When optimizing above, if we find that there is a stored LCP element but it fails to match, it should perhaps set $needs_detection to true and send the request with an override nonce.
	if ( $needs_detection ) {
		$head_injection .= ilo_get_detection_script( $slug, $needed_minimum_viewport_widths );
	}

	if ( $head_injection ) {
		$buffer = preg_replace(
			'#(?=</HEAD>)#i',
			$head_injection,
			$buffer,
			1
		);
	}

	return $buffer;
}
