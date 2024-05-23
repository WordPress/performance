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
		$post ? OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ) : array(),
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);

	// Whether we need to add the data-od-xpath attribute to elements and whether the detection script should be injected.
	$needs_detection = ! $group_collection->is_every_group_complete();

	// Walk over all tags in the document and ensure fetchpriority is set/removed, and construct preload links for image LCP elements.
	$preload_links = new OD_Preload_Link_Collection();
	$walker        = new OD_HTML_Tag_Walker( $buffer );

	/**
	 * Filters whether the current tag is optimized (or could be) while the HTML document is being walked over.
	 *
	 * This is the key filter allowing Optimization Detective to be extended. All optimizations should be performed
	 * via this filter. Document mutations can be performed via the supplied `$walker` argument. Information about
	 * what optimizations should be performed can be determined by inspecting the `$group_collection` argument.
	 * Preload links can be added via the `$preload_links` argument.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string|int, callable( OD_HTML_Tag_Walker, OD_URL_Metrics_Group_Collection, OD_Preload_Link_Collection ): bool> $visitors         Visitors which are invoked for each tag in the document.
	 * @param OD_HTML_Tag_Walker                                                                                                   $walker           HTML tag walker.
	 * @param OD_URL_Metrics_Group_Collection                                                                                      $group_collection URL metrics group collection.
	 * @param OD_Preload_Link_Collection                                                                                           $preload_links    Preload link collection.
	 */
	$visitors = (array) apply_filters( 'od_html_tag_walker_visitors', array(), $walker, $group_collection, $preload_links );
	$visitors = array_filter(
		$visitors,
		static function ( $visitor ) {
			// @phpstan-ignore-next-line function.alreadyNarrowedType (Defensive WP coding.)
			return is_callable( $visitor );
		}
	);

	$generator = $walker->open_tags();
	while ( $generator->valid() ) {
		$did_visit = false;
		foreach ( $visitors as $visitor ) {
			$did_visit = $visitor( $walker, $group_collection, $preload_links ) || $did_visit;
		}

		if ( $did_visit && $needs_detection ) {
			$walker->set_attribute( 'data-od-xpath', $walker->get_xpath() );
		}
		$generator->next();
	}

	// Inject any preload links at the end of the HEAD.
	if ( count( $preload_links ) > 0 ) {
		$walker->append_head_html( $preload_links->get_html() );
	}

	// Inject detection script.
	// TODO: When optimizing above, if we find that there is a stored LCP element but it fails to match, it should perhaps set $needs_detection to true and send the request with an override nonce. However, this would require backtracking and adding the data-od-xpath attributes.
	if ( $needs_detection ) {
		$walker->append_body_html( od_get_detection_script( $slug, $group_collection ) );
	}

	return $walker->get_updated_html();
}
