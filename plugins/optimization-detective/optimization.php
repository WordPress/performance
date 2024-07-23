<?php
/**
 * Optimizing for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Starts output buffering at the end of the 'template_include' filter.
 *
 * This is to implement #43258 in core.
 *
 * This is a hack which would eventually be replaced with something like this in wp-includes/template-loader.php:
 *
 *          $template = apply_filters( 'template_include', $template );
 *     +    ob_start( 'wp_template_output_buffer_callback' );
 *          if ( $template ) {
 *              include $template;
 *          } elseif ( current_user_can( 'switch_themes' ) ) {
 *
 * @since 0.1.0
 * @access private
 * @link https://core.trac.wordpress.org/ticket/43258
 *
 * @param string $passthrough Value for the template_include filter which is passed through.
 * @return string Unmodified value of $passthrough.
 */
function od_buffer_output( string $passthrough ): string {
	ob_start(
		static function ( string $output ): string {
			/**
			 * Filters the template output buffer prior to sending to the client.
			 *
			 * @since 0.1.0
			 *
			 * @param string $output Output buffer.
			 * @return string Filtered output buffer.
			 */
			return (string) apply_filters( 'od_template_output_buffer', $output );
		}
	);
	return $passthrough;
}

/**
 * Adds template output buffer filter for optimization if eligible.
 *
 * @since 0.1.0
 * @access private
 */
function od_maybe_add_template_output_buffer_filter(): void {
	if ( ! od_can_optimize_response() || isset( $_GET['optimization_detective_disabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$callback = 'od_optimize_template_output_buffer';
	if (
		function_exists( 'perflab_wrap_server_timing' )
		&&
		function_exists( 'perflab_server_timing_use_output_buffer' )
		&&
		perflab_server_timing_use_output_buffer()
	) {
		$callback = perflab_wrap_server_timing( $callback, 'optimization-detective', 'exist' );
	}
	add_filter( 'od_template_output_buffer', $callback );
}

/**
 * Determines whether the current response can be optimized.
 *
 * @since 0.1.0
 * @access private
 *
 * @return bool Whether response can be optimized.
 */
function od_can_optimize_response(): bool {
	$able = ! (
		// Since there is no predictability in whether posts in the loop will have featured images assigned or not. If a
		// theme template for search results doesn't even show featured images, then this wouldn't be an issue.
		is_search() ||
		// Since injection of inline-editing controls interfere with breadcrumbs, while also just not necessary in this context.
		is_customize_preview() ||
		// Since the images detected in the response body of a POST request cannot, by definition, be cached.
		( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) ||
		// The aim is to optimize pages for the majority of site visitors, not those who administer the site. For admin
		// users, additional elements will be present like the script from wp_customize_support_script() which will
		// interfere with the XPath indices. Note that od_get_normalized_query_vars() is varied by is_user_logged_in()
		// so membership sites and e-commerce sites will still be able to be optimized for their normal visitors.
		current_user_can( 'customize' )
	);

	/**
	 * Filters whether the current response can be optimized.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $able Whether response can be optimized.
	 */
	return (bool) apply_filters( 'od_can_optimize_response', $able );
}

/**
 * Determines whether the response has an HTML Content-Type.
 *
 * @since 0.2.0
 * @private
 *
 * @return bool Whether Content-Type is HTML.
 */
function od_is_response_html_content_type(): bool {
	$is_html_content_type = false;

	$headers_list = array_merge(
		array( 'Content-Type: ' . ini_get( 'default_mimetype' ) ),
		headers_list()
	);
	foreach ( $headers_list as $header ) {
		$header_parts = preg_split( '/\s*[:;]\s*/', strtolower( $header ) );
		if ( is_array( $header_parts ) && count( $header_parts ) >= 2 && 'content-type' === $header_parts[0] ) {
			$is_html_content_type = in_array( $header_parts[1], array( 'text/html', 'application/xhtml+xml' ), true );
		}
	}

	return $is_html_content_type;
}

/**
 * Optimizes template output buffer.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string $buffer Template output buffer.
 * @return string Filtered template output buffer.
 *
 * @throws Exception Except it won't really.
 */
function od_optimize_template_output_buffer( string $buffer ): string {
	if ( ! od_is_response_html_content_type() ) {
		return $buffer;
	}

	$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$post = OD_URL_Metrics_Post_Type::get_post( $slug );

	$group_collection = new OD_URL_Metrics_Group_Collection(
		$post instanceof WP_Post ? OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ) : array(),
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);

	// Whether we need to add the data-od-xpath attribute to elements and whether the detection script should be injected.
	$needs_detection = ! $group_collection->is_every_group_complete();

	$tag_visitor_registry = new OD_Tag_Visitor_Registry();

	/**
	 * Fires to register tag visitors before walking over the document to perform optimizations.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_Tag_Visitor_Registry $tag_visitor_registry Tag visitor registry.
	 */
	do_action( 'od_register_tag_visitors', $tag_visitor_registry );

	$link_collection      = new OD_Link_Collection();
	$processor            = new OD_HTML_Tag_Processor( $buffer );
	$tag_visitor_context  = new OD_Tag_Visitor_Context( $processor, $group_collection, $link_collection );
	$current_tag_bookmark = 'optimization_detective_current_tag';
	$visitors             = iterator_to_array( $tag_visitor_registry );
	while ( $processor->next_open_tag() ) {
		$did_visit = false;
		$processor->set_bookmark( $current_tag_bookmark ); // TODO: Should we break if this returns false?

		foreach ( $visitors as $visitor ) {
			$seek_count       = $processor->get_seek_count();
			$next_token_count = $processor->get_next_token_count();
			$did_visit        = $visitor( $tag_visitor_context ) || $did_visit;

			// If the visitor traversed HTML tags, we need to go back to this tag so that in the next iteration any
			// relevant tag visitors may apply, in addition to properly setting the data-od-xpath on this tag below.
			if ( $seek_count !== $processor->get_seek_count() || $next_token_count !== $processor->get_next_token_count() ) {
				$processor->seek( $current_tag_bookmark ); // TODO: Should this break out of the optimization loop if it returns false?
			}
		}
		$processor->release_bookmark( $current_tag_bookmark );

		if ( $did_visit && $needs_detection ) {
			$processor->set_meta_attribute( 'xpath', $processor->get_xpath() );
		}
	}

	// Send any preload links in a Link response header and in a LINK tag injected at the end of the HEAD.
	if ( count( $link_collection ) > 0 ) {
		$response_header_links = $link_collection->get_response_header();
		if ( ! is_null( $response_header_links ) && ! headers_sent() ) {
			header( $response_header_links, false );
		}
		$processor->append_head_html( $link_collection->get_html() );
	}

	// Inject detection script.
	// TODO: When optimizing above, if we find that there is a stored LCP element but it fails to match, it should perhaps set $needs_detection to true and send the request with an override nonce. However, this would require backtracking and adding the data-od-xpath attributes.
	if ( $needs_detection ) {
		$processor->append_body_html( od_get_detection_script( $slug, $group_collection ) );
	}

	return $processor->get_updated_html();
}
