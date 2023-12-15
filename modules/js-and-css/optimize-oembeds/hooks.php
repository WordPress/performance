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

$oembed_lazy_load_scripts = false;

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
	global $oembed_lazy_load_scripts;

	$p = new WP_HTML_Tag_Processor( $html );

	// Find the first iframe or script tag to act on.
	while ( $p->next_tag() ) {
		if ( 'IFRAME' === $p->get_tag() ) {
			$loading_value = $p->get_attribute( 'loading' );
			if ( empty( $loading_value ) ) {
				$p->set_attribute( 'loading', 'lazy' );
			}
			return $p->get_updated_html();

		} elseif ( 'SCRIPT' === $p->get_tag() && $p->get_attribute( 'src' ) ) {
			$oembed_lazy_load_scripts = true;
			$p->set_attribute( 'data-lazy-embed-src', $p->get_attribute( 'src' ) );
			$p->set_attribute( 'src', '' );
			return $p->get_updated_html();
		}
	}

	return $html;
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
                        const embedScript = lazyEmbedScript.cloneNode();
                        embedScript.src = lazyEmbedScript.dataset.lazyEmbedSrc;
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