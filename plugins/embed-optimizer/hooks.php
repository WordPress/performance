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
 * Filter the oEmbed HTML.
 *
 * Add loading="lazy" to any iframe tags.
 * Lazy load any script tags.
 *
 * @since 0.1.0
 *
 * @param string $html The oEmbed HTML.
 * @return string
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
					embed_optimizer_trigger_error( __FUNCTION__, esc_html__( 'Embed Optimizer unable to set iframe bookmark.', 'embed-optimizer' ) );
					return $html;
				}
			}
		} elseif ( 'SCRIPT' === $html_processor->get_tag() ) {
			if ( ! $html_processor->get_attribute( 'src' ) ) {
				$has_inline_script = true;
			} else {
				++$script_count;
				if ( ! $html_processor->set_bookmark( 'script' ) ) {
					embed_optimizer_trigger_error( __FUNCTION__, esc_html__( 'Embed Optimizer unable to set script bookmark.', 'embed-optimizer' ) );
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
			embed_optimizer_trigger_error( __FUNCTION__, esc_html__( 'Embed Optimizer unable to seek to script bookmark.', 'embed-optimizer' ) );
		}
	}
	// If there was only one iframe, make it lazy.
	if ( 1 === $iframe_count && $html_processor->has_bookmark( 'iframe' ) ) {
		if ( $html_processor->seek( 'iframe' ) ) {
			$html_processor->set_attribute( 'loading', 'lazy' );
		} else {
			embed_optimizer_trigger_error( __FUNCTION__, esc_html__( 'Embed Optimizer unable to seek to iframe bookmark.', 'embed-optimizer' ) );
		}
	}
	return $html_processor->get_updated_html();
}
add_filter( 'embed_oembed_html', 'embed_optimizer_filter_oembed_html' );

/**
 * Add a script to the footer if there are lazy loaded embeds.
 * Load the embed's scripts when they approach the viewport using an IntersectionObserver.
 *
 * @since 0.1.0
 */
function embed_optimizer_lazy_load_scripts() {
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
 * Generates a user-level error/warning/notice/deprecation message.
 *
 * Generates the message when `WP_DEBUG` is true.
 *
 * @since 0.1.0
 *
 * @param string $function_name The function that triggered the error.
 * @param string $message       The message explaining the error.
 *                              The message can contain allowed HTML 'a' (with href), 'code',
 *                              'br', 'em', and 'strong' tags and http or https protocols.
 *                              If it contains other HTML tags or protocols, the message should be escaped
 *                              before passing to this function to avoid being stripped {@see wp_kses()}.
 * @param int    $error_level   Optional. The designated error type for this error.
 *                              Only works with E_USER family of constants. Default E_USER_NOTICE.
 */
function embed_optimizer_trigger_error( string $function_name, string $message, int $error_level = E_USER_NOTICE ) {
	if ( ! function_exists( 'wp_trigger_error' ) ) {
		return;
	}
	wp_trigger_error( $function_name, $message, $error_level );
}

/**
 * Displays the HTML generator tag for the Embed Optimizer plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function embed_optimizer_render_generator() {
	if (
		defined( 'EMBED_OPTIMIZER_VERSION' )
	) {
		echo '<meta name="generator" content="Embed Optimizer ' . esc_attr( EMBED_OPTIMIZER_VERSION ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'embed_optimizer_render_generator' );
