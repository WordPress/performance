<?php
/**
 * Helper functions for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Initializes extensions for Optimization Detective.
 *
 * @since 0.7.0
 * @access private
 */
function od_initialize_extensions(): void {
	/**
	 * Fires when extensions to Optimization Detective can be loaded and initialized.
	 *
	 * @since 0.7.0
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Action%3A%20od_init
	 *
	 * @param string $version Optimization Detective version.
	 */
	do_action( 'od_init', OPTIMIZATION_DETECTIVE_VERSION );
}

/**
 * Generates a media query for the provided minimum and maximum viewport widths.
 *
 * This helper function is available for extensions to leverage when manually printing STYLE rules via
 * {@see OD_HTML_Tag_Processor::append_head_html()} or {@see OD_HTML_Tag_Processor::append_body_html()}
 *
 * @since 0.7.0
 *
 * @param int<0, max>|null $minimum_viewport_width Minimum viewport width (exclusive).
 * @param int<1, max>|null $maximum_viewport_width Maximum viewport width (inclusive).
 * @return non-empty-string|null Media query, or null if the min/max were both unspecified or invalid.
 */
function od_generate_media_query( ?int $minimum_viewport_width, ?int $maximum_viewport_width ): ?string {
	if ( is_int( $minimum_viewport_width ) && is_int( $maximum_viewport_width ) && $minimum_viewport_width >= $maximum_viewport_width ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The minimum width cannot be greater than or equal to the maximum width.', 'optimization-detective' ), 'Optimization Detective 0.7.0' );
		return null;
	}
	$has_min_width = ( null !== $minimum_viewport_width && $minimum_viewport_width > 0 );
	$has_max_width = ( null !== $maximum_viewport_width && PHP_INT_MAX !== $maximum_viewport_width ); // Note: The use of PHP_INT_MAX is obsolete.
	if ( $has_min_width && $has_max_width ) {
		return sprintf( '(%dpx < width <= %dpx)', $minimum_viewport_width, $maximum_viewport_width );
	} elseif ( $has_min_width ) {
		return sprintf( '(%dpx < width)', $minimum_viewport_width );
	} elseif ( $has_max_width ) {
		return sprintf( '(width <= %dpx)', $maximum_viewport_width );
	} else {
		return null;
	}
}

/**
 * Gets the reasons why Optimization Detective is disabled for the current response.
 *
 * @since 1.0.0
 * @access private
 *
 * @return array{
 *     is_search?: string,
 *     is_embed?: string,
 *     is_preview?: string,
 *     is_customize_preview?: string,
 *     non_get_request?: string,
 *     no_cache_purge_post_id?: string,
 *     filter_disabled?: string,
 *     rest_api_unavailable?: string,
 *     query_param_disabled?: string
 * } Array of disabled reason codes and their messages.
 */
function od_get_disabled_reasons(): array {
	$disabled_flags = array(
		'is_search'              => false,
		'is_embed'               => false,
		'is_preview'             => false,
		'is_customize_preview'   => false,
		'non_get_request'        => false,
		'no_cache_purge_post_id' => false,
	);

	// Disable the search template since there is no predictability in whether posts in the loop will have featured images assigned or not. If a
	// theme template for search results doesn't even show featured images, then this wouldn't be an issue.
	if ( is_search() ) {
		$disabled_flags['is_search'] = true;
	}

	// Avoid optimizing embed responses because the Post Embed iframes include a sandbox attribute with the value of
	// "allow-scripts" but without "allow-same-origin". This can result in an error in the console:
	// > Access to script at '.../detect.js?ver=0.4.1' from origin 'null' has been blocked by CORS policy: No 'Access-Control-Allow-Origin' header is present on the requested resource.
	// So it's better to just avoid attempting to optimize Post Embed responses (which don't need optimization anyway).
	if ( is_embed() ) {
		$disabled_flags['is_embed'] = true;
	}

	// Skip posts that aren't published yet.
	if ( is_preview() ) {
		$disabled_flags['is_preview'] = true;
	}

	// Disable in Customizer preview since injection of inline-editing controls can interfere with XPath. Optimization is also not necessary in this context.
	if ( is_customize_preview() ) {
		$disabled_flags['is_customize_preview'] = true;
	}

	// Disable for POST responses since they cannot, by definition, be cached.
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
		$disabled_flags['non_get_request'] = true;
	}

	// Disable when there is no post ID available for cache purging. Page caching plugins can only reliably be told to invalidate a cached page when a post is available to trigger
	// the relevant actions on.
	if ( null === od_get_cache_purge_post_id() ) {
		$disabled_flags['no_cache_purge_post_id'] = true;
	}

	// Check if any flags are set to true.
	$has_disabled_flags = count( array_filter( $disabled_flags ) ) > 0;

	/**
	 * Filters whether the current response can be optimized.
	 *
	 * @since 0.1.0
	 * @since 1.0.0 Added $disabled_flags parameter
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Filter%3A%20od_can_optimize_response
	 *
	 * @param bool $can_optimize Whether response can be optimized.
	 * @param array{
	 *     is_search: bool,
	 *     is_embed: bool,
	 *     is_preview: bool,
	 *     is_customize_preview: bool,
	 *     non_get_request: bool,
	 *     no_cache_purge_post_id: bool
	 * } $disabled_flags Flags indicating which conditions are disabling optimization.
	 */
	$can_optimize = (bool) apply_filters( 'od_can_optimize_response', ! $has_disabled_flags, $disabled_flags );

	$reasons = array();
	if ( ! $can_optimize ) {
		$reason_messages = array(
			'is_search'              => __( 'Page is not optimized because it is a search results page.', 'optimization-detective' ),
			'is_embed'               => __( 'Page is not optimized because it is an embed.', 'optimization-detective' ),
			'is_preview'             => __( 'Page is not optimized because it is a preview.', 'optimization-detective' ),
			'is_customize_preview'   => __( 'Page is not optimized because it is a customize preview.', 'optimization-detective' ),
			'non_get_request'        => __( 'Page is not optimized because it is not a GET request.', 'optimization-detective' ),
			'no_cache_purge_post_id' => __( 'Page is not optimized because there is no post ID available for cache purging.', 'optimization-detective' ),
		);

		$reasons = wp_array_slice_assoc( $reason_messages, array_keys( array_filter( $disabled_flags ) ) );

		// If no technical reasons but optimization still disabled, it's because of the filter.
		if ( 0 === count( $reasons ) ) {
			$reasons['filter_disabled'] = __( 'Page is not optimized because the od_can_optimize_response filter returned false.', 'optimization-detective' );
		}
	}

	if ( od_is_rest_api_unavailable() && ! ( wp_get_environment_type() === 'local' && ! function_exists( 'tests_add_filter' ) ) ) {
		$reasons['rest_api_unavailable'] = __( 'Page is not optimized because the REST API for storing URL Metrics is not available.', 'optimization-detective' );
	}

	if ( isset( $_GET['optimization_detective_disabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reasons['query_param_disabled'] = __( 'Page is not optimized because the URL has the optimization_detective_disabled query parameter.', 'optimization-detective' );
	}

	return $reasons;
}

/**
 * Displays the HTML generator META tag for the Optimization Detective plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 * @access private
 */
function od_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	$content = 'optimization-detective ' . OPTIMIZATION_DETECTIVE_VERSION;

	// Add any reasons why Optimization Detective is disabled.
	$disabled_reasons = od_get_disabled_reasons();
	if ( count( $disabled_reasons ) > 0 ) {
		$flags    = array_keys( $disabled_reasons );
		$content .= '; ' . implode( '; ', $flags );
	}

	echo '<meta name="generator" content="' . esc_attr( $content ) . '">' . "\n";
}

/**
 * Adds an Extensions link to the plugin row meta for Optimization Detective.
 *
 * This link directs users to the plugin directory to discover extensions that
 * provide optimization functionality using the Optimization Detective plugin.
 *
 * @since 1.0.0
 * @access private
 *
 * @param string[]|mixed $plugin_meta The plugin's metadata.
 * @param string         $plugin_file Plugin file.
 * @return string[] Updated plugin metadata.
 */
function od_render_extensions_meta_link( $plugin_meta, string $plugin_file ): array {
	if ( ! is_array( $plugin_meta ) ) {
		$plugin_meta = array();
	}
	if ( 'optimization-detective/load.php' !== $plugin_file || ! current_user_can( 'install_plugins' ) ) {
		return $plugin_meta;
	}

	/* @noinspection HtmlUnknownTarget */
	$extensions_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'plugin-install.php?s=optimization-detective&tab=search&type=tag' ) ),
		esc_html__( 'Extensions', 'optimization-detective' )
	);

	$plugin_meta[] = $extensions_link;
	return $plugin_meta;
}

/**
 * Checks for active extension plugins for Optimization Detective.
 *
 * @since 1.0.0
 * @access private
 *
 * @return string[] List of active extension plugin files.
 */
function od_get_active_extensions(): array {
	$installed_plugins = get_plugins();
	$active_extensions = array();

	foreach ( $installed_plugins as $plugin_slug => $plugin_data ) {
		if ( isset( $plugin_data['RequiresPlugins'] ) && is_string( $plugin_data['RequiresPlugins'] ) ) {
			$required_plugins = array_map( 'trim', explode( ',', $plugin_data['RequiresPlugins'] ) );
			if ( in_array( 'optimization-detective', $required_plugins, true ) && is_plugin_active( $plugin_slug ) ) {
				$active_extensions[] = $plugin_slug;
			}
		}
	}

	// Check for plugins without Requires Plugins header but known to be extensions.
	$suggesting_extensions = array(
		'embed-optimizer/load.php',
	);
	foreach ( $suggesting_extensions as $extension ) {
		if ( isset( $installed_plugins[ $extension ] ) && is_plugin_active( $extension ) ) {
			$active_extensions[] = $extension;
		}
	}

	return array_values( array_unique( $active_extensions ) );
}

/**
 * Renders an inline admin notice prompting the user to install or activate extensions for Optimization Detective.
 *
 * @since 1.0.0
 * @access private
 */
function od_maybe_render_installed_extensions_admin_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$active_extensions = od_get_active_extensions();
	if ( count( $active_extensions ) > 0 ) {
		return;
	}

	$message = sprintf(
		'<summary style="margin: 0.5em 0">%s</summary>',
		esc_html__( 'Optimization Detective is a framework plugin which requires extensions.', 'optimization-detective' )
	);

	$message .= '<p>' . esc_html__( 'This plugin doesn&#8217;t provide standalone functionality; it is a framework that requires extension plugins to implement optimizations. Please install and activate one or more of the following extensions:', 'optimization-detective' ) . '</p>';

	$featured_extensions = array(
		'image-prioritizer' => array(
			'name'        => __( 'Image Prioritizer', 'optimization-detective' ),
			'description' => __( 'Prioritizes the loading of images and videos based on how visible they are to actual visitors; adds fetchpriority and applies lazy-loading.', 'optimization-detective' ),
			'url'         => admin_url( 'plugin-install.php?tab=plugin-information&plugin=image-prioritizer&TB_iframe=true&width=772' ),
		),
		'embed-optimizer'   => array(
			'name'        => __( 'Embed Optimizer', 'optimization-detective' ),
			'description' => __( 'Optimizes the performance of embeds through lazy-loading, adding dns-prefetch links, and reserving space to reduce layout shifts.', 'optimization-detective' ),
			'url'         => admin_url( 'plugin-install.php?tab=plugin-information&plugin=embed-optimizer&TB_iframe=true&width=772' ),
		),
	);

	$message .= '<table class="widefat" style="margin-bottom: 11px;"><tbody>';
	foreach ( $featured_extensions as $featured_extension ) {
		/* @noinspection HtmlUnknownTarget */
		$message .= sprintf(
			'<tr>
				<td><strong>%s</strong></td>
				<td>%s</td>
			</tr>',
			current_user_can( 'install_plugins' ) ?
				sprintf( '<a href="%s" class="thickbox open-plugin-details-modal">%s</a>', esc_url( $featured_extension['url'] ), esc_html( $featured_extension['name'] ) ) :
				esc_html( $featured_extension['name'] ),
			esc_html( $featured_extension['description'] )
		);
	}
	$message .= '</tbody></table>';
	$message  = "<details>$message</details>";

	$notice = wp_get_admin_notice(
		$message,
		array(
			'type'               => 'info',
			'additional_classes' => array( 'inline' ),
			'paragraph_wrap'     => false,
		)
	);

	if ( current_user_can( 'install_plugins' ) ) {
		add_thickbox();
	}
	echo wp_kses( $notice, wp_kses_allowed_html( 'post' ) );
}

/**
 * Renders a paragraph of links to the plugin's documentation on GitHub.
 *
 * @since 1.0.0
 * @access private
 */
function od_render_documentation_links(): void {
	echo '<p>';
	/* @noinspection HtmlUnknownTarget */
	echo wp_kses_post(
		sprintf(
			/* translators: 1: project documentation URL, 2: introduction URL, 3: code reference URL, 4: extensions list URL. */
			__( 'The <a href="%1$s" target="_blank">project documentation</a> is available on GitHub, including an <a href="%2$s" target="_blank">introduction</a>, <a href="%3$s" target="_blank">code reference</a>, and a list of <a href="%4$s" target="_blank">extensions</a>.', 'optimization-detective' ),
			esc_url( 'https://github.com/WordPress/performance/tree/trunk/plugins/optimization-detective/docs' ),
			esc_url( 'https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/introduction.md' ),
			esc_url( 'https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md' ),
			esc_url( 'https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/extensions.md' )
		)
	);
	echo '</p>';
}

/**
 * Displays an inline admin notice on the plugin row if no extensions are installed and active.
 *
 * @since 1.0.0
 * @access private
 *
 * @param non-empty-string $plugin_file Plugin file.
 */
function od_render_installed_extensions_admin_notice_in_plugin_row( string $plugin_file ): void {
	if ( 'optimization-detective/load.php' !== $plugin_file ) {
		return;
	}
	od_maybe_render_installed_extensions_admin_notice();
	od_render_documentation_links();
}

/**
 * Gets the path to a script or stylesheet.
 *
 * @since 0.9.0
 * @access private
 *
 * @param string      $src_path Source path, relative to the plugin root.
 * @param string|null $min_path Minified path. If not supplied, then '.min' is injected before the file extension in the source path.
 * @return string URL to script or stylesheet.
 *
 * @noinspection PhpDocMissingThrowsInspection
 */
function od_get_asset_path( string $src_path, ?string $min_path = null ): string {
	if ( null === $min_path ) {
		// Note: wp_scripts_get_suffix() is not used here because we need access to both the source and minified paths.
		$min_path = (string) preg_replace( '/(?=\.\w+$)/', '.min', $src_path );
	}

	$force_src = false;
	if ( WP_DEBUG && ! file_exists( trailingslashit( __DIR__ ) . $min_path ) ) {
		$force_src = true;
		/**
		 * No WP_Exception is thrown by wp_trigger_error() since E_USER_ERROR is not passed as the error level.
		 *
		 * @noinspection PhpUnhandledExceptionInspection
		 */
		wp_trigger_error(
			__FUNCTION__,
			sprintf(
				/* translators: %s is the minified asset path */
				__( 'Minified asset has not been built: %s', 'optimization-detective' ),
				$min_path
			),
			E_USER_WARNING
		);
	}

	if ( SCRIPT_DEBUG || $force_src ) {
		return $src_path;
	}

	return $min_path;
}

/**
 * Enqueues scripts for the URL priming in the admin area.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $hook_suffix Current admin page.
 */
function od_enqueue_prime_url_metrics_scripts( string $hook_suffix ): void {
	if ( 'tools_page_od-optimization-detective' === $hook_suffix ) {
		wp_enqueue_script(
			'od-prime-url-metrics',
			plugins_url( od_get_asset_path( 'prime-url-metrics.js' ), __FILE__ ),
			array( 'wp-i18n', 'wp-api-fetch' ),
			OPTIMIZATION_DETECTIVE_VERSION,
			true
		);
	}

	if (
		'post.php' === $hook_suffix &&
		function_exists( 'get_current_screen' ) &&
		isset( $_GET['od_classic_editor_post_update_nonce'] ) &&
		false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['od_classic_editor_post_update_nonce'] ) ), 'od_classic_editor_post_update' ) &&
		isset( $_GET['post'] ) &&
		isset( $_GET['message'] ) &&
		1 === (int) $_GET['message']
	) {
		$screen = get_current_screen();
		if ( $screen instanceof WP_Screen && ! $screen->is_block_editor() ) {
			$permalink = get_permalink( (int) $_GET['post'] );

			if ( false !== $permalink ) {
				wp_enqueue_script(
					'od-prime-url-metrics-classic-editor',
					plugins_url( od_get_asset_path( 'prime-url-metrics-classic-editor.js' ), __FILE__ ),
					array( 'wp-i18n', 'wp-api-fetch' ),
					OPTIMIZATION_DETECTIVE_VERSION,
					true
				);

				wp_localize_script(
					'od-prime-url-metrics-classic-editor',
					'odPrimeURLMetricsClassicEditor',
					array(
						'permalink' => $permalink,
					)
				);
			}
		}
	}
}

/**
 * Enqueues scripts for the URL priming in block editor.
 *
 * @since n.e.x.t
 * @access private
 */
function od_enqueue_block_editor_prime_url_metrics_scripts(): void {
	wp_enqueue_script(
		'od-prime-url-metrics',
		plugins_url( od_get_asset_path( 'prime-url-metrics-block-editor.js' ), __FILE__ ),
		array( 'wp-data', 'wp-api-fetch' ),
		OPTIMIZATION_DETECTIVE_VERSION,
		true
	);
}

/**
 * Adds a nonce to the post update redirect URL for the classic editor.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $location The redirect URL.
 * @return string The updated redirect URL.
 */
function od_add_data_to_post_update_redirect_url_for_classic_editor( string $location ): string {
	return add_query_arg( 'od_classic_editor_post_update_nonce', wp_create_nonce( 'od_classic_editor_post_update' ), $location );
}

/**
 * Gets URLs for priming URL Metrics from sitemap in batches.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array<string, int> $cursor Cursor to resume from.
 * @return array<string, mixed> Batch of URLs to prime metrics for and the updated cursor.
 */
function od_get_priming_mode_batch( array $cursor ): array {
	// Get the server & its registry of sitemap providers.
	$server   = wp_sitemaps_get_server();
	$registry = $server->registry;

	// All registered providers.
	$providers = array_values( $registry->get_providers() ); // Ensure zero-based index.

	$all_urls        = array();
	$collected_count = 0;

	// Flag to indicate if we should stop collecting further URLs (i.e., we reached $cursor['batch_size']).
	$done = false;

	// Start iterating from the current provider_index forward.
	$providers_count = count( $providers );
	for ( $provider_index = $cursor['provider_index']; $provider_index < $providers_count && ! $done; ) {
		$provider = $providers[ $provider_index ];

		// WordPress providers return an array of strings from get_object_subtypes().
		$subtypes = array_values( $provider->get_object_subtypes() ); // zero-based index.

		// Start from the current subtype_index if resuming.
		$subtypes_count = count( $subtypes );
		for ( $subtype_index = ( $provider_index === $cursor['provider_index'] ) ? $cursor['subtype_index'] : 0; $subtype_index < $subtypes_count && ! $done; ) {
			// This is a string, e.g. 'post', 'page', etc.
			$subtype = $subtypes[ $subtype_index ];

			// Retrieve the max number of pages for this subtype.
			$max_num_pages = $provider->get_max_num_pages( $subtype->name );

			// Start from the current page_number if resuming.
			for ( $page = ( ( $provider_index === $cursor['provider_index'] ) && ( $subtype_index === $cursor['subtype_index'] ) ) ? $cursor['page_number'] : 1; $page <= $max_num_pages && ! $done; ++$page ) {
				$url_list = $provider->get_url_list( $page, $subtype->name );
				if ( ! is_array( $url_list ) ) {
					continue;
				}

				// Filter out empty URLs.
				$url_chunk = array_filter( array_column( $url_list, 'loc' ) );

				// We might have partially consumed this page, so skip $cursor['offset_within_page'] items first.
				$current_page_urls = array_slice( $url_chunk, $cursor['offset_within_page'] );

				// Count how many URLs we consumed in this page.
				$consumed_in_this_page = 0;

				// Now collect from current_page_urls until we reach $cursor['batch_size'].
				foreach ( $current_page_urls as $url ) {
					$all_urls[] = $url;
					++$collected_count;
					++$consumed_in_this_page;

					if ( $collected_count >= $cursor['batch_size'] ) {
						// We have our full batch; stop collecting further.
						$done = true;
						break;
					}
				}

				if ( ! $done ) {
					// We consumed this entire page, so if we continue, next time we start at offset 0 of the next page.
					$cursor['page_number']        = $page + 1;
					$cursor['offset_within_page'] = 0;
				} else {
					// We reached the limit in the middle of this page.
					// Figure out how many we used from this page to update the offset properly.
					$extra_consumed = $collected_count - $cursor['batch_size']; // If exactly $cursor['batch_size'], this might be 0 or negative.
					if ( $extra_consumed < 0 ) {
						$extra_consumed = 0;
					}

					$cursor['offset_within_page'] = $cursor['offset_within_page'] + ( $consumed_in_this_page - $extra_consumed );

					// We haven't fully finished this page, so keep the same $cursor['page_number'].
					$cursor['page_number'] = $page;
				}
			} // end for pages.

			if ( ! $done ) {
				// If we've finished all pages in this subtype, move to next subtype from the start (page 1, offset 0).
				$cursor['page_number']        = 1;
				$cursor['offset_within_page'] = 0;
			}

			$cursor['subtype_index'] = $subtype_index;
			++$subtype_index;
		} // end for subtypes.

		if ( ! $done ) {
			// If we finished all subtypes in this provider, move to next provider and start at subtype=0, page=1.
			$cursor['subtype_index']      = 0;
			$cursor['page_number']        = 1;
			$cursor['offset_within_page'] = 0;
		}

		$cursor['provider_index'] = $provider_index;
		++$provider_index;
	} // end for providers.

	// Prepare next cursor.
	$new_cursor = array(
		'provider_index'     => $cursor['provider_index'],
		'subtype_index'      => $cursor['subtype_index'],
		'page_number'        => $cursor['page_number'],
		'offset_within_page' => $cursor['offset_within_page'],
		'batch_size'         => $cursor['batch_size'],
	);

	return array(
		'urls'   => $all_urls,
		'cursor' => $new_cursor,
	);
}

/**
 * Filter for WP_Query to allow specifying 'post_title__in' => array( 'title1', 'title2', ... ).
 *
 * This is needed because WP_Query does not support filtering by post_title.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string   $where The WHERE clause of the query.
 * @param WP_Query $query The WP_Query instance.
 */
function od_filter_posts_where_for_titles( string $where, WP_Query $query ): string {
	global $wpdb;

	$titles = (array) $query->get( 'post_title__in', array() );
	$titles = array_filter( $titles );

	if ( 0 === count( $titles ) ) {
		return $where;
	}

	// Safely prepare each title for IN() clause.
	$placeholders = array();
	foreach ( $titles as $title ) {
		$placeholders[] = $wpdb->prepare( '%s', $title );
	}
	$list = implode( ',', $placeholders );

	$where .= " AND {$wpdb->posts}.post_title IN ($list)";
	return $where;
}

/**
 * Fetches od_url_metrics posts of URLs in a single WP_Query.
 *
 * This function is used to reduce the number of database queries done by querying all URLs in a
 * single query instead of one per URL.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string[] $urls Array of exact URLs, as stored in post_title of od_url_metrics.
 * @return array<string, OD_URL_Metric_Group_Collection> Map of URL to its OD_URL_Metric_Group_Collection.
 */
function od_get_metrics_by_post_title( array $urls ): array {
	$urls = array_unique( array_filter( $urls ) );
	if ( 0 === count( $urls ) ) {
		return array();
	}

	$results_map = array();

	add_filter( 'posts_where', 'od_filter_posts_where_for_titles', 10, 2 );

	$query = new WP_Query(
		array(
			'post_type'              => OD_URL_Metrics_Post_Type::SLUG,
			'post_status'            => 'publish',
			'post_title__in'         => $urls,
			// Currently the count of urls is 10 or less for each batch so we can use -1 for now.
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'all',
		)
	);

	remove_filter( 'posts_where', 'od_filter_posts_where_for_titles', 10 );

	foreach ( $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$results_map[ $post->post_title ] = new OD_URL_Metric_Group_Collection(
			OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ),
			md5( '' ), // This is a dummy hash.
			od_get_breakpoint_max_widths(),
			od_get_url_metrics_breakpoint_sample_size(),
			od_get_url_metric_freshness_ttl()
		);
	}
	return $results_map;
}

/**
 * Gets the standard array of viewport based on real world device.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return array<int, array{width: int, height: int}> Array of viewports.
 */
function od_get_standard_viewports(): array {
	$device_viewports = array(
		array( // Small smartphones.
			'width'  => 360,
			'height' => 780,
		),
		array( // Large smartphones.
			'width'  => 414,
			'height' => 896,
		),
		array( // Tablets.
			'width'  => 768,
			'height' => 1024,
		),
		array( // Desktop/laptop screens.
			'width'  => 1920,
			'height' => 1080,
		),
	);

	/**
	 * Filters the standard device viewports used for priming mode.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<int, array{width: int, height: int}> $device_viewports Array of viewport dimensions.
	 */
	return apply_filters( 'od_standard_viewports', $device_viewports );
}

/**
 * Filters the batch of URLs to only include those that need additional metrics.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array<string> $urls Array of URLs to filter.
 * @return array<int, array{url: string, viewports: array<int, array{width: int, height: int}>}> Filtered batch of URL groups.
 */
function od_filter_priming_mode_batch_urls( array $urls ): array {
	$filtered_url_groups = array();
	$standard_viewports  = od_get_standard_viewports();
	$group_collections   = od_get_metrics_by_post_title( $urls );

	foreach ( $urls as $url ) {
		$group_collection = $group_collections[ $url ] ?? null;
		if ( ! $group_collection instanceof OD_URL_Metric_Group_Collection ) {
			$filtered_url_groups[] = array(
				'url'       => $url,
				'viewports' => $standard_viewports,
			);
			continue;
		}

		if ( $group_collection->is_every_group_populated() ) {
			continue;
		}

		$existing_widths = array();
		foreach ( $group_collection as $group ) {
			if ( ! $group->is_complete() ) {
				foreach ( $group as $url_metric ) {
					$existing_widths[] = $url_metric->get_viewport_width();
				}
			}
		}

		$missing_viewports = array();
		foreach ( $standard_viewports as $viewport ) {
			if ( ! in_array( $viewport['width'], $existing_widths, true ) ) {
				$missing_viewports[] = $viewport;
			}
		}

		if ( count( $missing_viewports ) > 0 ) {
			$filtered_url_groups[] = array(
				'url'       => $url,
				'viewports' => $missing_viewports,
			);
		}
	}

	return $filtered_url_groups;
}

/**
 * Determine whether to show the priming mode settings page.
 *
 * @since n.e.x.t
 *
 * @return bool True to display the settings page; false to hide it.
 */
function od_show_priming_mode_settings(): bool {
	/**
	 * Filters whether the priming mode settings page should be shown in the admin dashboard, regardless of the number of URLs.
	 *
	 * @since n.e.x.t
	 *
	 * @param bool $show_feature True if the feature should be shown, false otherwise.
	 */
	$force_show = apply_filters( 'od_show_priming_mode_settings', false );
	if ( true === $force_show ) {
		return true;
	}

	/**
	 * Filters maximum number of URLs allowed before hiding the settings page.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $threshold The threshold count of frontend-visible URLs.
	 */
	$threshold = apply_filters( 'od_show_priming_mode_settings_max_urls', 1000 );

	$count = (int) get_transient( 'od_priming_mode_frontend_visible_url_count' );
	if ( 0 !== $count ) {
		return $count <= $threshold;
	}

	// Get the sitemap server and its registry of providers.
	$server    = wp_sitemaps_get_server();
	$registry  = $server->registry;
	$providers = array_values( $registry->get_providers() );
	$show      = true;

	foreach ( $providers as $provider ) {
		// Each provider returns its object subtypes (e.g. 'post', 'page', etc.).
		$subtypes = array_values( $provider->get_object_subtypes() );
		foreach ( $subtypes as $subtype ) {
			$max_pages = $provider->get_max_num_pages( $subtype->name );
			for ( $page = 1; $page <= $max_pages; $page++ ) {
				$url_list = $provider->get_url_list( $page, $subtype->name );
				if ( ! is_array( $url_list ) ) {
					continue;
				}

				$url_chunk = array_filter( array_column( $url_list, 'loc' ) );
				$count    += count( $url_chunk );

				if ( $count >= $threshold ) {
					$show = false;
					break 3;
				}
			}
		}
	}

	set_transient( 'od_priming_mode_frontend_visible_url_count', $count, DAY_IN_SECONDS );

	return $show;
}

/**
 * Generates the batch of URLs for priming URL Metrics.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array<string, int>|null $cursor Cursor to resume from.
 * @return array<string, mixed> Final batch of URLs to prime metrics for and the updated cursor.
 */
function od_generate_priming_mode_batch( ?array $cursor ): array {
	$default_cursor = array(
		'provider_index'     => 0,
		'subtype_index'      => 0,
		'page_number'        => 1,
		'offset_within_page' => 0,
		'batch_size'         => 10,
	);

	// Validate the cursor.
	$cursor = array_map( 'intval', array_intersect_key( wp_parse_args( (array) $cursor, $default_cursor ), $default_cursor ) );

	if ( $default_cursor === $cursor ) {
		$last_cursor = get_option( 'od_priming_mode_batch_cursor' );
		if ( false !== $last_cursor ) {
			$cursor = array_map( 'intval', array_intersect_key( wp_parse_args( $cursor, $last_cursor ), $last_cursor ) );
		}
	} else {
		update_option( 'od_priming_mode_batch_cursor', $cursor );
	}

	$batch                 = array();
	$filtered_url_groups   = array();
	$prevent_infinite_loop = 0;
	while ( $prevent_infinite_loop < 100 ) {
		if ( count( $filtered_url_groups ) > 0 ) {
			break;
		}

		$batch               = od_get_priming_mode_batch( $cursor );
		$filtered_url_groups = od_filter_priming_mode_batch_urls( $batch['urls'] );

		if ( $cursor === $batch['cursor'] ) {
			delete_option( 'od_priming_mode_batch_cursor' );
			break;
		}
		$cursor = $batch['cursor'];

		++$prevent_infinite_loop;
	}

	return array(
		'urlGroups'         => $filtered_url_groups,
		'cursor'            => $batch['cursor'],
		'verificationToken' => od_get_priming_mode_verification_token(),
		'isDebug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
	);
}

/**
 * Gets the verification token for priming mode.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return string Verification token.
 */
function od_get_priming_mode_verification_token(): string {
	$verification_token = get_transient( 'od_priming_mode_verification_token' );
	if ( false === $verification_token ) {
		$verification_token = wp_generate_uuid4();
		set_transient( 'od_priming_mode_verification_token', $verification_token, 30 * MINUTE_IN_SECONDS );
	}
	return $verification_token;
}
