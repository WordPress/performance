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
 */
function ilo_maybe_add_template_output_buffer_filter() {
	if ( ! ilo_can_optimize_response() ) {
		return;
	}
	add_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' );
}
add_action( 'wp', 'ilo_maybe_add_template_output_buffer_filter' );

/**
 * Optimizes template output buffer.
 *
 * @param string $buffer Template output buffer.
 * @return string Filtered template output buffer.
 */
function ilo_optimize_template_output_buffer( string $buffer ): string {
	$slug         = ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() );
	$post         = ilo_get_url_metrics_post( $slug );
	$page_metrics = ilo_parse_stored_url_metrics( $post );

	$lcp_images_by_minimum_viewport_widths = ilo_get_lcp_elements_by_minimum_viewport_widths( $page_metrics, ilo_get_breakpoint_max_widths() );

	if ( ! empty( $lcp_images_by_minimum_viewport_widths ) ) {
		if ( count( $lcp_images_by_minimum_viewport_widths ) !== 1 ) {
			$p = new WP_HTML_Tag_Processor( $buffer );
			while ( $p->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
				if ( $p->get_attribute( 'fetchpriority' ) ) {
					$p->set_attribute( 'data-wp-removed-fetchpriority', $p->get_attribute( 'fetchpriority' ) );
					$p->remove_attribute( 'fetchpriority' );
				}
			}
			$buffer = $p->get_updated_html();
		}
	}

	return $buffer;
}
