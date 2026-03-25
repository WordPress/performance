<?php
/**
 * Optimizing for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Starts output buffering at the end of the 'template_include' filter.
 *
 * This is to implement #43258 in core.
 *
 * This is a hack that would eventually be replaced with something like this in wp-includes/template-loader.php:
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
 * @param string|mixed $passthrough Value for the template_include filter which is passed through.
 * @return string|mixed Unmodified value of $passthrough.
 */
function od_buffer_output( $passthrough ) {
	/*
	 * Instead of the default PHP_OUTPUT_HANDLER_STDFLAGS (cleanable, flushable, and removable) being used for flags,
	 * we need to omit PHP_OUTPUT_HANDLER_FLUSHABLE. If the buffer were flushable, then each time that ob_flush() is
	 * called, it would send a fragment of the output into the output buffer callback. When buffering the entire
	 * response as an HTML document, this would result in broken HTML processing.
	 *
	 * If this ends up being problematic, then PHP_OUTPUT_HANDLER_FLUSHABLE could be added to the $flags and the
	 * output buffer callback could check if the phase is PHP_OUTPUT_HANDLER_FLUSH and abort any later
	 * processing while also emitting a _doing_it_wrong().
	 *
	 * The output buffer needs to be removable because WordPress calls wp_ob_end_flush_all() and then calls
	 * wp_cache_close(). If the buffers are not all flushed before wp_cache_close() is closed, then some output buffer
	 * handlers (e.g. for caching plugins) may fail to be able to store the page output in the object cache.
	 * See <https://github.com/WordPress/performance/pull/1317#issuecomment-2271955356>.
	 */
	$flags = PHP_OUTPUT_HANDLER_STDFLAGS ^ PHP_OUTPUT_HANDLER_FLUSHABLE;

	ob_start(
		static function ( string $output, ?int $phase ): string {
			// When the output is being cleaned (e.g. the pending template is replaced with an error page), do not send it through the filter.
			if ( ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) !== 0 ) {
				return $output;
			}

			/**
			 * Filters the template output buffer before sending it to the client.
			 *
			 * @since 0.1.0
			 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Filter%3A%20od_template_output_buffer
			 *
			 * @param string $output Output buffer.
			 * @return string Filtered output buffer.
			 */
			return (string) apply_filters( 'od_template_output_buffer', $output );
		},
		0, // Unlimited buffer size.
		$flags
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
	$disabled_reasons = od_get_disabled_reasons();
	if ( count( $disabled_reasons ) > 0 ) {
		if ( WP_DEBUG ) {
			add_action(
				'wp_print_footer_scripts',
				static function () use ( $disabled_reasons ): void {
					od_print_disabled_reasons( array_values( $disabled_reasons ) );
				}
			);
		}
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
 * Prints the reasons why Optimization Detective is not optimizing the current page.
 *
 * This is only used when WP_DEBUG is enabled.
 *
 * @since 1.0.0
 * @access private
 *
 * @param string[] $reasons Reason messages.
 */
function od_print_disabled_reasons( array $reasons ): void {
	foreach ( $reasons as $i => $reason ) {
		wp_print_inline_script_tag(
			sprintf(
				"console.info( %s );\n//# sourceURL=od-print-disabled-reasons-%d",
				wp_json_encode( '[Optimization Detective] ' . $reason, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
				$i + 1
			),
			array( 'type' => 'module' )
		);
	}
}

/**
 * Determines whether the current response can be optimized.
 *
 * @since 0.1.0
 * @since 0.9.0 Response is optimized for admin users as well when in 'plugin' development mode.
 *
 * @access private
 *
 * @return bool Whether response can be optimized.
 */
function od_can_optimize_response(): bool {
	return count( od_get_disabled_reasons() ) === 0;
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
 * @global WP_Query $wp_the_query WP_Query object.
 *
 * @param string $buffer Template output buffer.
 * @return string Filtered template output buffer.
 */
function od_optimize_template_output_buffer( string $buffer ): string {
	global $wp_the_query;

	// If the content-type is not HTML or the output does not start with '<', then abort since the buffer is definitely not HTML.
	if (
		! od_is_response_html_content_type() ||
		! str_starts_with( ltrim( $buffer ), '<' )
	) {
		return $buffer;
	}

	// If the initial tag is not an open HTML tag, then abort since the buffer is not a complete HTML document.
	$processor = new OD_HTML_Tag_Processor( $buffer );
	if ( ! (
		$processor->next_tag( array( 'tag_closers' => 'visit' ) ) &&
		! $processor->is_tag_closer() &&
		'HTML' === $processor->get_tag()
	) ) {
		return $buffer;
	}

	$query_vars = od_get_normalized_query_vars();
	$slug       = od_get_url_metrics_slug( $query_vars );
	$post       = OD_URL_Metrics_Post_Type::get_post( $slug );

	/**
	 * Post ID.
	 *
	 * @var positive-int|null $post_id
	 */
	$post_id = $post instanceof WP_Post ? $post->ID : null;

	$tag_visitor_registry = new OD_Tag_Visitor_Registry();

	/**
	 * Fires to register tag visitors before walking over the document to perform optimizations.
	 *
	 * @since 0.3.0
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Action%3A%20od_register_tag_visitors
	 *
	 * @param OD_Tag_Visitor_Registry $tag_visitor_registry Tag visitor registry.
	 */
	do_action( 'od_register_tag_visitors', $tag_visitor_registry );

	$current_etag     = od_get_current_url_metrics_etag( $tag_visitor_registry, $wp_the_query, od_get_current_theme_template() );
	$group_collection = new OD_URL_Metric_Group_Collection(
		$post instanceof WP_Post ? OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ) : array(),
		$current_etag,
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);
	$link_collection  = new OD_Link_Collection();

	$template_optimization_context = new OD_Template_Optimization_Context(
		$group_collection,
		$link_collection,
		$query_vars,
		$slug,
		$post_id
	);

	/**
	 * Fires before Optimization Detective starts iterating over the document in the output buffer.
	 *
	 * This is before any of the registered tag visitors have been invoked.
	 *
	 * @since 1.0.0
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Action%3A%20od_start_template_optimization
	 *
	 * @param OD_Template_Optimization_Context $template_optimization_context Template optimization context.
	 */
	do_action( 'od_start_template_optimization', $template_optimization_context );

	$visited_tag_state    = new OD_Visited_Tag_State();
	$tag_visitor_context  = new OD_Tag_Visitor_Context(
		$processor,
		$group_collection,
		$link_collection,
		$visited_tag_state,
		$post_id
	);
	$current_tag_bookmark = 'optimization_detective_current_tag';
	$visitors             = iterator_to_array( $tag_visitor_registry );

	// Whether we need to add the data-od-xpath attribute to elements and whether the detection script should be injected.
	$needs_detection          = ! $group_collection->is_every_group_complete();
	$did_amend_meta_generator = false;
	do {
		// Never process anything inside NOSCRIPT since it will never show up in the DOM when scripting is enabled, and thus it can never be detected nor measured.
		// Similarly, elements in the Admin Bar are not relevant for optimization, so this loop ensures that no tags in the Admin Bar are visited.
		if (
			in_array( 'NOSCRIPT', $processor->get_breadcrumbs(), true )
			||
			$processor->is_admin_bar()
		) {
			continue;
		}

		// Amend the META generator tag if it's the right one and hasn't been amended already.
		if (
			! $did_amend_meta_generator && // @phpstan-ignore booleanNot.alwaysTrue, booleanAnd.alwaysFalse, booleanAnd.alwaysFalse, booleanAnd.alwaysFalse (False positives in PHPStan due to the following line.)
			'META' === $processor->get_tag() && // @phpstan-ignore identical.alwaysFalse (False positive in PHPStan since it isn't aware of the do/while loop apparently.)
			'generator' === $processor->get_attribute( 'name' ) &&
			str_starts_with( (string) $processor->get_attribute( 'content' ), 'optimization-detective ' )
		) {
			$content               = (string) $processor->get_attribute( 'content' );
			$viewport_group_status = array();
			foreach ( $group_collection as $group ) {
				$min_width = $group->get_minimum_viewport_width();

				$status = 'empty';
				if ( $group->is_complete() ) {
					$status = 'complete';
				} elseif ( $group->count() > 0 ) {
					$status = 'populated';
				}

				$viewport_group_status[] = sprintf( '%s:%s', $min_width, $status );
			}
			$content .= '; url_metric_groups={' . implode( ', ', $viewport_group_status ) . '}';
			$processor->set_attribute( 'content', $content );
			$did_amend_meta_generator = true;
		}

		$tracked_in_url_metrics = false;
		$processor->set_bookmark( $current_tag_bookmark ); // TODO: Should we break if this returns false?

		foreach ( $visitors as $visitor ) {
			$cursor_move_count    = $processor->get_cursor_move_count();
			$visitor_return_value = $visitor( $tag_visitor_context );
			if ( true === $visitor_return_value ) {
				$tracked_in_url_metrics = true;
			}

			// If the visitor traversed HTML tags, we need to go back to this tag so that in the next iteration any
			// relevant tag visitors may apply, in addition to properly setting the data-od-xpath on this tag below.
			if ( $cursor_move_count !== $processor->get_cursor_move_count() ) {
				$processor->seek( $current_tag_bookmark ); // TODO: Should this break out of the optimization loop if it returns false?
			}
		}
		$processor->release_bookmark( $current_tag_bookmark );

		if ( $visited_tag_state->is_tag_tracked() ) {
			$tracked_in_url_metrics = true;
		}

		if ( $tracked_in_url_metrics && $needs_detection ) {
			$processor->set_meta_attribute( 'xpath', $processor->get_xpath() );
		}

		$visited_tag_state->reset();
	} while ( $processor->next_tag( array( 'tag_closers' => 'skip' ) ) );

	// Inject detection script.
	// TODO: When optimizing above, if we find that there is a stored LCP element but it fails to match, it should perhaps set $needs_detection to true and send the request with an override nonce. However, this would require backtracking and adding the data-od-xpath attributes.
	if ( $needs_detection ) {
		$processor->append_body_html( od_get_detection_scripts( $slug, $group_collection ) );
	}

	/**
	 * Fires after Optimization Detective has finished iterating over the document in the output buffer.
	 *
	 * This is after all the registered tag visitors have been invoked.
	 *
	 * @since 1.0.0
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Action%3A-,od_finish_template_optimization
	 *
	 * @param OD_Template_Optimization_Context $template_optimization_context Template optimization context.
	 */
	do_action( 'od_finish_template_optimization', $template_optimization_context );

	// Send any preload links in a Link response header and in a LINK tag injected at the end of the HEAD.
	// Additional links may have been added at the od_finish_template_optimization action, so this must come after.
	if ( count( $link_collection ) > 0 ) {
		$response_header_links = $link_collection->get_response_header();
		if ( ! is_null( $response_header_links ) && ! headers_sent() ) {
			header( $response_header_links, false );
		}
		$processor->append_head_html( $link_collection->get_html() );
	}

	return $processor->get_updated_html();
}
