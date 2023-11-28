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

		} elseif ( 'SCRIPT' === $p->get_tag() ) {
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
	<script>
		(function() {
			var lazyEmbeds = document.querySelectorAll( 'script[data-lazy-embed-src]' );
			parents = [];
			lazyEmbeds.forEach( function( lazyEmbed ) {
				parents.push( lazyEmbed.parentNode );
			} );
			var lazyEmbedObserver = new IntersectionObserver( function( entries ) {
				entries.forEach( function( entry ) {
					if ( entry.isIntersecting ) {
						var lazyDiv = entry.target;
						var lazyEmbed = lazyDiv.querySelector( 'script[data-lazy-embed-src]' );
						lazyEmbed.src = lazyEmbed.dataset.lazyEmbedSrc;
						lazyEmbedObserver.unobserve( lazyDiv );
					}
				} );
			}, {
				rootMargin: '0px 0px 500px 0px',
				threshold: 0
			} );
			parents.forEach( function( lazyEmbed ) {
				lazyEmbedObserver.observe( lazyEmbed );
			} );
		})();
	</script>
	<?php
}
add_action( 'wp_footer', 'perflab_optimize_oembed_lazy_load_scripts', 99 );