<?php
/**
 * Hook callbacks used for oEmbed Optimizer.
 *
 * @since n.e.x.t
 * @package performance-lab
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
 * @since n.e.x.t
 *
 * @param string $html The oEmbed HTML.
 * @return string
 */
function perflab_optimize_oembed_html( $html ) {
	$p = new WP_HTML_Tag_Processor( $html );

	/**
	 * Determine how to lazy load the embed.
	 *
	 * - If there is only one iframe, set loading="lazy".
	 * - Prevent making scripts lazy if there is an inline script.
	 *  - Only make script lazy if there is a single external script (since if there are
	 *    multiple they may not get loaded in the right order).
	 *  - Ensure that both the iframe and the script are made lazy if both occur in the same embed.
	 */
	$iframe_count      = 0;
	$script_count      = 0;
	$has_inline_script = false;
	// Locate the iframes and scripts.
	while ( $p->next_tag() ) {
		if ( 'IFRAME' === $p->get_tag() ) {
			$loading_value = $p->get_attribute( 'loading' );
			if ( empty( $loading_value ) ) {
				++$iframe_count;
				$p->set_bookmark( 'iframe' );
			}
		} elseif ( 'SCRIPT' === $p->get_tag() ) {
			if ( ! $p->get_attribute( 'src' ) ) {
				$has_inline_script = true;
			} else {
				++$script_count;
				$p->set_bookmark( 'script' );
			}
		}
	}
	// If there was only one non-inline script, make it lazy.
	if ( 1 === $script_count && ! $has_inline_script ) {
		add_action( 'wp_footer', 'perflab_optimize_oembed_lazy_load_scripts' );
		$p->seek( 'script' );
		$p->set_attribute( 'data-lazy-embed-src', $p->get_attribute( 'src' ) );
		$p->remove_attribute( 'src' );
	}
	// If there was only one iframe, make it lazy.
	if ( 1 === $iframe_count ) {
		$p->seek( 'iframe' );
		$p->set_attribute( 'loading', 'lazy' );
	}
	return $p->get_updated_html();
}
add_filter( 'embed_oembed_html', 'perflab_optimize_oembed_html', 10 );

/**
 * Add a script to the footer if there are lazy loaded embeds.
 * Load the embed's scripts when they approach the viewport using an IntersectionObserver.
 *
 * @since n.e.x.t
 */
function perflab_optimize_oembed_lazy_load_scripts() {
	global $oembed_lazy_load_scripts;

	if ( ! $oembed_lazy_load_scripts ) {
		return;
	}
	?>
	<script type="module">
		const lazyEmbedsScripts = document.querySelectorAll( 'script[data-lazy-embed-src]' );
		const lazyEmbedScriptsByParents = new Map();

		const lazyEmbedObserver = new IntersectionObserver(
			( entries ) => {
				for ( const entry of entries ) {
					if ( entry.isIntersecting ) {
						const lazyEmbedParent = entry.target;
						const lazyEmbedScript = lazyEmbedScriptsByParents.get( lazyEmbedParent );
						const embedScript = document.createElement( 'script' );
						for ( const attr of lazyEmbedScript.attributes ) {
							if ( attr.nodeName === 'src' ) {
								// Even though the src attribute is absent, the browser seems to presume it is present.
								continue;
							}

							embedScript.setAttribute(
								attr.nodeName === 'data-lazy-embed-src' ? 'src' : attr.nodeName,
								attr.nodeValue
							);
						}
						lazyEmbedScript.replaceWith( embedScript );
						lazyEmbedObserver.unobserve( lazyEmbedParent );
					}
				}
			},
			{
				rootMargin: '0px 0px 500px 0px',
				threshold: 0
			}
		);

		for ( const lazyEmbedScript of lazyEmbedsScripts ) {
			const lazyEmbedParent = lazyEmbedScript.parentNode;
			lazyEmbedScriptsByParents.set( lazyEmbedParent, lazyEmbedScript );
			lazyEmbedObserver.observe( lazyEmbedParent );
		}
	</script>
	<?php
}
add_action( 'wp_footer', 'perflab_optimize_oembed_lazy_load_scripts', 99 );