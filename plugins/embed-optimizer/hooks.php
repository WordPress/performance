<?php
/**
 * Hook callbacks used for Embed Optimizer.
 *
 * @since 0.1.0
 * @package embed-optimizer
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Add hooks.
 *
 * @since 0.2.0
 */
function embed_optimizer_add_hooks(): void {
	add_action( 'wp_head', 'embed_optimizer_render_generator' );

	add_action( 'od_init', 'embed_optimizer_init_optimization_detective' );
	add_action( 'wp_loaded', 'embed_optimizer_add_non_optimization_detective_hooks' );
}
add_action( 'init', 'embed_optimizer_add_hooks' );

/**
 * Adds hooks for when the Optimization Detective logic is not running.
 *
 * @since 0.3.0
 */
function embed_optimizer_add_non_optimization_detective_hooks(): void {
	if ( false === has_action( 'od_register_tag_visitors', 'embed_optimizer_register_tag_visitors' ) ) {
		add_filter( 'embed_oembed_html', 'embed_optimizer_filter_oembed_html_to_lazy_load' );
	}
}

/**
 * Initializes Embed Optimizer when Optimization Detective has loaded.
 *
 * @since 0.3.0
 *
 * @param string $optimization_detective_version Current version of the optimization detective plugin.
 */
function embed_optimizer_init_optimization_detective( string $optimization_detective_version ): void {
	$required_od_version = '0.9.0';
	if ( version_compare( (string) strtok( $optimization_detective_version, '-' ), $required_od_version, '<' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				global $pagenow;
				if ( ! in_array( $pagenow, array( 'index.php', 'plugins.php' ), true ) ) {
					return;
				}
				wp_admin_notice(
					esc_html__( 'The Embed Optimizer plugin requires a newer version of the Optimization Detective plugin. Please update your plugins.', 'embed-optimizer' ),
					array( 'type' => 'warning' )
				);
			}
		);
		return;
	}

	add_action( 'od_register_tag_visitors', 'embed_optimizer_register_tag_visitors' );
	add_filter( 'embed_oembed_html', 'embed_optimizer_filter_oembed_html_to_detect_embed_presence' );
	add_filter( 'od_url_metric_schema_element_item_additional_properties', 'embed_optimizer_add_element_item_schema_properties' );
}

/**
 * Registers the tag visitor for embeds.
 *
 * @since 0.2.0
 *
 * @param OD_Tag_Visitor_Registry $registry Tag visitor registry.
 */
function embed_optimizer_register_tag_visitors( OD_Tag_Visitor_Registry $registry ): void {
	// Note: This class is loaded on the fly since it is only needed here when Optimization Detective is active.
	require_once __DIR__ . '/class-embed-optimizer-tag-visitor.php';
	$registry->register( 'embeds', new Embed_Optimizer_Tag_Visitor() );
}

/**
 * Filters additional properties for the element item schema for Optimization Detective.
 *
 * @since 0.3.0
 *
 * @param array<string, array{type: string}> $additional_properties Additional properties.
 * @return array<string, array{type: string}> Additional properties.
 */
function embed_optimizer_add_element_item_schema_properties( array $additional_properties ): array {
	$additional_properties['resizedBoundingClientRect'] = array(
		'type'       => 'object',
		'properties' => array_fill_keys(
			array(
				'width',
				'height',
				'x',
				'y',
				'top',
				'right',
				'bottom',
				'left',
			),
			array(
				'type'     => 'number',
				'required' => true,
			)
		),
	);
	return $additional_properties;
}

/**
 * Filters the list of Optimization Detective extension module URLs to include the extension for Embed Optimizer.
 *
 * @since 0.3.0
 *
 * @param string[]|mixed $extension_module_urls Extension module URLs.
 * @return string[] Extension module URLs.
 */
function embed_optimizer_filter_extension_module_urls( $extension_module_urls ): array {
	if ( ! is_array( $extension_module_urls ) ) {
		$extension_module_urls = array();
	}
	$extension_module_urls[] = plugins_url( add_query_arg( 'ver', EMBED_OPTIMIZER_VERSION, embed_optimizer_get_asset_path( 'detect.js' ) ), __FILE__ );
	return $extension_module_urls;
}

/**
 * Filter the oEmbed HTML to detect when an embed is present so that the Optimization Detective extension module can be enqueued.
 *
 * This ensures that the module for handling embeds is only loaded when there is an embed on the page.
 *
 * @since 0.3.0
 *
 * @param string|mixed $html The oEmbed HTML.
 * @return string Unchanged oEmbed HTML.
 */
function embed_optimizer_filter_oembed_html_to_detect_embed_presence( $html ): string {
	if ( ! is_string( $html ) ) {
		$html = '';
	}
	add_filter( 'od_extension_module_urls', 'embed_optimizer_filter_extension_module_urls' );
	return $html;
}

/**
 * Filter the oEmbed HTML to lazy load the embed.
 *
 * Add loading="lazy" to any iframe tags.
 * Lazy load any script tags.
 *
 * @since 0.1.0
 *
 * @param string|mixed $html The oEmbed HTML.
 * @return string Filtered oEmbed HTML.
 */
function embed_optimizer_filter_oembed_html_to_lazy_load( $html ): string {
	if ( ! is_string( $html ) ) {
		$html = '';
	}
	$html_processor = new WP_HTML_Tag_Processor( $html );
	if ( embed_optimizer_update_markup( $html_processor, true ) ) {
		add_action( 'wp_footer', 'embed_optimizer_lazy_load_scripts' );
	}
	return $html_processor->get_updated_html();
}

/**
 * Applies changes to HTML in the supplied tag processor to lazy-load the embed.
 *
 * @since 0.2.0
 *
 * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- The exception is caught.
 *
 * @param WP_HTML_Tag_Processor|OD_HTML_Tag_Processor $html_processor HTML Processor.
 * @param bool                                        $is_isolated    Whether processing an isolated embed fragment or the entire document.
 * @return bool Whether the lazy-loading script is required.
 */
function embed_optimizer_update_markup( WP_HTML_Tag_Processor $html_processor, bool $is_isolated ): bool {
	$bookmark_names = array(
		'script' => 'embed_optimizer_script',
		'iframe' => 'embed_optimizer_iframe',
	);
	$function_name  = __FUNCTION__;
	$trigger_error  = static function ( string $message ) use ( $function_name ): void {
		wp_trigger_error( $function_name, esc_html( $message ) );
	};

	// As of 1.0.0-beta3, next_tag() allows $query and is beginning to migrate to skip tag closers by default.
	// In versions prior to this, the method always visited closers and passing a $query actually threw an exception.
	$tag_query = ! defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) || version_compare( OPTIMIZATION_DETECTIVE_VERSION, '1.0.0-beta3', '>=' )
		? array( 'tag_closers' => 'visit' ) : null;
	try {
		/*
		 * Determine how to lazy load the embed.
		 *
		 * - If there is only one iframe, set loading="lazy".
		 * - Prevent making scripts lazy if there is an inline script.
		 * - Only make script lazy if there is a single external script (since if there are
		 *   multiple they may not get loaded in the right order).
		 * - Ensure that both the iframe and the script are made lazy if both occur in the same embed.
		 */
		$iframe_count      = 0;
		$script_count      = 0;
		$needs_lazy_script = false;
		$has_inline_script = false;
		$figure_depth      = 0;
		// Locate the iframes and scripts.
		do {
			// This condition ensures that when iterating over an embed inside a larger document that we stop once we reach
			// closing </figure> tag. The $processor is an OD_HTML_Tag_Processor when Optimization Detective is iterating
			// over all tags in the document, and this embed_optimizer_update_markup() is used as part of the tag visitor
			// from Embed Optimizer. On the other hand, if $html_processor is not an OD_HTML_Tag_Processor then this is
			// iterating over the tags of the embed markup alone as is passed into the embed_oembed_html filter.
			if ( ! $is_isolated ) {
				if ( 'FIGURE' === $html_processor->get_tag() ) {
					if ( $html_processor->is_tag_closer() ) {
						--$figure_depth;
						if ( $figure_depth <= 0 ) {
							// We reached the end of the embed.
							break;
						}
					} else {
						++$figure_depth;
						// Move to next element to start looking for IFRAME or SCRIPT tag.
						continue;
					}
				}
				if ( 0 === $figure_depth ) {
					continue;
				}
			}

			if ( 'IFRAME' === $html_processor->get_tag() ) {
				$loading_value = $html_processor->get_attribute( 'loading' );
				// Per the HTML spec: "The attribute's missing value default and invalid value default are both the Eager state".
				if ( 'lazy' !== $loading_value ) {
					++$iframe_count;
					if ( ! $html_processor->set_bookmark( $bookmark_names['iframe'] ) ) {
						throw new Exception(
							/* translators: %s is bookmark name */
							sprintf( __( 'Embed Optimizer unable to set %s bookmark.', 'embed-optimizer' ), $bookmark_names['iframe'] )
						);
					}
				}
			} elseif ( 'SCRIPT' === $html_processor->get_tag() ) {
				if ( null === $html_processor->get_attribute( 'src' ) ) {
					$has_inline_script = true;
				} else {
					++$script_count;
					if ( ! $html_processor->set_bookmark( $bookmark_names['script'] ) ) {
						throw new Exception(
							/* translators: %s is bookmark name */
							sprintf( __( 'Embed Optimizer unable to set %s bookmark.', 'embed-optimizer' ), $bookmark_names['script'] )
						);
					}
				}
			}
		} while ( $html_processor->next_tag( $tag_query ) );
		// If there was only one non-inline script, make it lazy.
		if ( 1 === $script_count && ! $has_inline_script && $html_processor->has_bookmark( $bookmark_names['script'] ) ) {
			$needs_lazy_script = true;
			if ( $html_processor->seek( $bookmark_names['script'] ) ) {
				if ( is_string( $html_processor->get_attribute( 'type' ) ) ) {
					$html_processor->set_attribute( 'data-original-type', $html_processor->get_attribute( 'type' ) );
				}
				$html_processor->set_attribute( 'type', 'application/vnd.embed-optimizer.javascript' );
			} else {
				$trigger_error(
					/* translators: %s is bookmark name */
					sprintf( __( 'Embed Optimizer unable to seek to %s bookmark.', 'embed-optimizer' ), $bookmark_names['script'] )
				);
			}
		}
		// If there was only one iframe, make it lazy.
		if ( 1 === $iframe_count && $html_processor->has_bookmark( $bookmark_names['iframe'] ) ) {
			if ( $html_processor->seek( $bookmark_names['iframe'] ) ) {
				$html_processor->set_attribute( 'loading', 'lazy' );

				// For post embeds, use visibility:hidden instead of clip since browsers will consistently load the
				// lazy-loaded iframe (where Chromium is unreliably with clip) while at the same time improve accessibility
				// by preventing links in the hidden iframe from receiving focus.
				if ( true === $html_processor->has_class( 'wp-embedded-content' ) ) {
					$style = $html_processor->get_attribute( 'style' );
					if ( is_string( $style ) ) {
						// WordPress core injects this clip CSS property:
						// <https://github.com/WordPress/wordpress-develop/blob/6974b994de5/src/wp-includes/embed.php#L968>.
						$style = str_replace( 'clip: rect(1px, 1px, 1px, 1px);', 'visibility: hidden;', $style );

						// Note: wp-embed.js removes the style attribute entirely when the iframe is loaded:
						// <https://github.com/WordPress/wordpress-develop/blob/6974b994d/src/js/_enqueues/wp/embed.js#L60>.
						$html_processor->set_attribute( 'style', $style );
					}
				}
			} else {
				$trigger_error(
					/* translators: %s is bookmark name */
					sprintf( __( 'Embed Optimizer unable to seek to %s bookmark.', 'embed-optimizer' ), $bookmark_names['iframe'] )
				);
			}
		}
	} catch ( Exception $exception ) {
		$trigger_error( $exception->getMessage() );
		$needs_lazy_script = false;
	}

	// Since there is a limit to the number of bookmarks we can add, make sure any new ones we add get removed.
	foreach ( $bookmark_names as $bookmark_name ) {
		$html_processor->release_bookmark( $bookmark_name );
	}

	return $needs_lazy_script;
}

/**
 * Prints the script to lazy-load embeds.
 *
 * Load an embed's scripts when it approaches the viewport using an IntersectionObserver.
 *
 * @since 0.1.0
 */
function embed_optimizer_lazy_load_scripts(): void {
	wp_print_inline_script_tag( embed_optimizer_get_lazy_load_script(), array( 'type' => 'module' ) );
}

/**
 * Gets the script to lazy-load embeds.
 *
 * Load an embed's scripts when it approaches the viewport using an IntersectionObserver.
 *
 * @since 0.2.0
 */
function embed_optimizer_get_lazy_load_script(): string {
	$script = file_get_contents( __DIR__ . '/' . embed_optimizer_get_asset_path( 'lazy-load.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.

	if ( false === $script ) {
		return '';
	}

	return $script;
}

/**
 * Prints the Optimization Detective installation notices.
 *
 * @since 0.2.0
 *
 * @param string $plugin_file Plugin file.
 */
function embed_optimizer_print_row_meta_install_notice( string $plugin_file ): void {
	$od_plugin_slug = 'optimization-detective';
	$od_plugin_file = "{$od_plugin_slug}/load.php";
	$od_plugin_name = 'Optimization Detective';
	if ( 'embed-optimizer/load.php' === $plugin_file && ! is_plugin_active( $od_plugin_file ) ) {
		if ( current_user_can( 'install_plugins' ) ) {
			$details_url = esc_url_raw(
				add_query_arg(
					array(
						'tab'       => 'plugin-information',
						'plugin'    => $od_plugin_slug,
						'TB_iframe' => 'true',
						'width'     => 600,
						'height'    => 550,
					),
					admin_url( 'plugin-install.php' )
				)
			);

			$link_start_tag = sprintf(
				'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">',
				esc_url( $details_url ),
				/* translators: %s: Plugin name and version. */
				esc_attr( sprintf( __( 'More information about %s', 'default' ), $od_plugin_name ) )
			);
		} else {
			/* translators: %s: Plugin name. */
			$aria_label  = sprintf( __( 'Visit plugin site for %s', 'default' ), $od_plugin_name );
			$details_url = __( 'https://wordpress.org/plugins/', 'default' ) . $od_plugin_slug . '/';

			$link_start_tag = sprintf(
				'<a href="%s" aria-label="%s" target="_blank">',
				esc_url( $details_url ),
				esc_attr( $aria_label )
			);
		}

		$message = str_replace(
			'<a>',
			$link_start_tag,
			__( 'This plugin performs best when <a>Optimization Detective</a> is also installed and active.', 'embed-optimizer' )
		);

		wp_admin_notice(
			'<p>' . $message . '</p>',
			array(
				'type'               => 'warning',
				'additional_classes' => array( 'inline', 'notice' ),
			)
		);
	} elseif ( $od_plugin_file === $plugin_file ) {
		if ( is_plugin_active( $od_plugin_file ) ) {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Recommended by:', 'embed-optimizer' ),
				'Embed Optimizer'
			);
		} else {
			$message = __( 'This plugin is strongly recommended to be active by <strong>Embed Optimizer</strong>.', 'embed-optimizer' );
			wp_admin_notice(
				'<p>' . wp_kses( $message, array( 'strong' => array() ) ) . '</p>',
				array(
					'type'               => 'warning',
					'additional_classes' => array( 'inline', 'notice-alt' ),
				)
			);
		}
	}
}
add_action( 'after_plugin_row_meta', 'embed_optimizer_print_row_meta_install_notice', 20 );

/**
 * Displays the HTML generator tag for the Embed Optimizer plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function embed_optimizer_render_generator(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="embed-optimizer ' . esc_attr( EMBED_OPTIMIZER_VERSION ) . '">' . "\n";
}

/**
 * Gets the path to a script or stylesheet.
 *
 * @since 0.4.0
 *
 * @param string      $src_path Source path, relative to plugin root.
 * @param string|null $min_path Minified path. If not supplied, then '.min' is injected before the file extension in the source path.
 * @return string URL to script or stylesheet.
 */
function embed_optimizer_get_asset_path( string $src_path, ?string $min_path = null ): string {
	if ( null === $min_path ) {
		// Note: wp_scripts_get_suffix() is not used here because we need access to both the source and minified paths.
		$min_path = (string) preg_replace( '/(?=\.\w+$)/', '.min', $src_path );
	}

	$force_src = false;
	if ( WP_DEBUG && ! file_exists( trailingslashit( __DIR__ ) . $min_path ) ) {
		$force_src = true;
		wp_trigger_error(
			__FUNCTION__,
			sprintf(
				/* translators: %s is the minified asset path */
				__( 'Minified asset has not been built: %s', 'embed-optimizer' ),
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
