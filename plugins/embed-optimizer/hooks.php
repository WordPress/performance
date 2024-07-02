<?php
/**
 * Hook callbacks used for Embed Optimizer.
 *
 * @since 0.1.0
 * @package embed-optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add hooks.
 *
 * @since n.e.x.t
 */
function embed_optimizer_add_hooks(): void {
	add_action( 'wp_head', 'embed_optimizer_render_generator' );

	if ( defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) ) {
		add_action( 'od_register_tag_visitors', 'embed_optimizer_register_tag_visitors', 10, 3 );
	} else {
		add_filter( 'embed_oembed_html', 'embed_optimizer_filter_oembed_html' );
	}
}
add_action( 'init', 'embed_optimizer_add_hooks' );

/**
 * Registers the tag visitor for embeds.
 *
 * @since n.e.x.t
 *
 * @param OD_Tag_Visitor_Registry         $registry                     Tag visitor registry.
 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL Metrics Group Collection.
 * @param OD_Link_Collection              $link_collection              Link Collection.
 */
function embed_optimizer_register_tag_visitors( OD_Tag_Visitor_Registry $registry, OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Link_Collection $link_collection ): void {
	require_once __DIR__ . '/class-embed-optimizer-tag-visitor.php';
	$registry->register( 'embeds', new Embed_Optimizer_Tag_Visitor( $url_metrics_group_collection, $link_collection ) );
}

/**
 * Filter the oEmbed HTML.
 *
 * Add loading="lazy" to any iframe tags.
 * Lazy load any script tags.
 *
 * @since 0.1.0
 *
 * @param string $html The oEmbed HTML.
 * @return string Filtered oEmbed HTML.
 */
function embed_optimizer_filter_oembed_html( string $html ): string {
	$html_processor = new WP_HTML_Tag_Processor( $html );

	/**
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
	$has_inline_script = false;
	// Locate the iframes and scripts.
	while ( $html_processor->next_tag() ) {
		if ( 'IFRAME' === $html_processor->get_tag() ) {
			$loading_value = $html_processor->get_attribute( 'loading' );
			if ( empty( $loading_value ) ) {
				++$iframe_count;
				if ( ! $html_processor->set_bookmark( 'iframe' ) ) {
					wp_trigger_error( __FUNCTION__, esc_html__( 'Embed Optimizer unable to set iframe bookmark.', 'embed-optimizer' ) );
					return $html;
				}
			}
		} elseif ( 'SCRIPT' === $html_processor->get_tag() ) {
			if ( ! $html_processor->get_attribute( 'src' ) ) {
				$has_inline_script = true;
			} else {
				++$script_count;
				if ( ! $html_processor->set_bookmark( 'script' ) ) {
					wp_trigger_error( __FUNCTION__, esc_html__( 'Embed Optimizer unable to set script bookmark.', 'embed-optimizer' ) );
					return $html;
				}
			}
		}
	}
	// If there was only one non-inline script, make it lazy.
	if ( 1 === $script_count && ! $has_inline_script && $html_processor->has_bookmark( 'script' ) ) {
		add_action( 'wp_footer', 'embed_optimizer_lazy_load_scripts' );
		if ( $html_processor->seek( 'script' ) ) {
			if ( $html_processor->get_attribute( 'type' ) ) {
				$html_processor->set_attribute( 'data-original-type', $html_processor->get_attribute( 'type' ) );
			}
			$html_processor->set_attribute( 'type', 'application/vnd.embed-optimizer.javascript' );
		} else {
			wp_trigger_error( __FUNCTION__, esc_html__( 'Embed Optimizer unable to seek to script bookmark.', 'embed-optimizer' ) );
		}
	}
	// If there was only one iframe, make it lazy.
	if ( 1 === $iframe_count && $html_processor->has_bookmark( 'iframe' ) ) {
		if ( $html_processor->seek( 'iframe' ) ) {
			$html_processor->set_attribute( 'loading', 'lazy' );

			// For post embeds, use visibility:hidden instead of clip since browsers will consistently load the
			// lazy-loaded iframe (where Chromium is unreliably with clip) while at the same time improve accessibility
			// by preventing links in the hidden iframe from receiving focus.
			if ( $html_processor->has_class( 'wp-embedded-content' ) ) {
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
			wp_trigger_error( __FUNCTION__, esc_html__( 'Embed Optimizer unable to seek to iframe bookmark.', 'embed-optimizer' ) );
		}
	}
	return $html_processor->get_updated_html();
}

/**
 * Add a script to the footer if there are lazy loaded embeds.
 * Load the embed's scripts when they approach the viewport using an IntersectionObserver.
 *
 * @since 0.1.0
 */
function embed_optimizer_lazy_load_scripts(): void {
	$js = <<<JS
		const lazyEmbedsScripts = document.querySelectorAll( 'script[type="application/vnd.embed-optimizer.javascript"]' );
		const lazyEmbedScriptsByParents = new Map();

		const lazyEmbedObserver = new IntersectionObserver(
			( entries ) => {
				for ( const entry of entries ) {
					if ( entry.isIntersecting ) {
						const lazyEmbedParent = entry.target;
						const lazyEmbedScript = /** @type {HTMLScriptElement} */ lazyEmbedScriptsByParents.get( lazyEmbedParent );
						const embedScript = document.createElement( 'script' );
						for ( const attr of lazyEmbedScript.attributes ) {
							if ( attr.nodeName === 'type' ) {
								// Omit type=application/vnd.embed-optimizer.javascript type.
								continue;
							}
							embedScript.setAttribute(
								attr.nodeName === 'data-original-type' ? 'type' : attr.nodeName,
								attr.nodeValue
							);
						}
						lazyEmbedScript.replaceWith( embedScript );
						lazyEmbedObserver.unobserve( lazyEmbedParent );
					}
				}
			},
			{
				rootMargin: '100% 0% 100% 0%',
				threshold: 0
			}
		);

		for ( const lazyEmbedScript of lazyEmbedsScripts ) {
			const lazyEmbedParent = /** @type {HTMLElement} */ lazyEmbedScript.parentNode;
			lazyEmbedScriptsByParents.set( lazyEmbedParent, lazyEmbedScript );
			lazyEmbedObserver.observe( lazyEmbedParent );
		}
JS;
	wp_print_inline_script_tag( $js, array( 'type' => 'module' ) );
}

/**
 * Prints the Optimization Detective installation notices.
 *
 * @since n.e.x.t
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
