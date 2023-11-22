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
 * @param string $html The cached HTML result, stored in post meta.
 * @return string
 */
function plab_optimize_oembed_html( $html ) {
	global $oembed_lazy_load_scripts;

	// Find script tags in the oembed using regex.
	preg_match_all( '/<script\b[^>]*>([\s\S]*?)<\/script>/mi', $html, $matches );

	if ( ! empty( $matches[0][0] ) ) {
		$found_tag                = $matches[0][0];
		$oembed_lazy_load_scripts = true;

		// Remove the src attribute from the first script tag, transforming it into data-lazy-src.
		$script_tag = str_replace( 'src=', 'data-lazy-embed-src=', $matches[0][0] );

		// Replace the script tag with the new one.
		$html = str_replace( $found_tag, $script_tag, $html );
	}

	// Find iframes in the oEmbed using a regex.
	preg_match_all( '/<iframe\b[^>]*>([\s\S]*?)<\/iframe>/mi', $html, $matches );

	// Add loading="lazy" to iframes that don't have a loading attribute.
	if ( ! empty( $matches[0][0] ) ) {
		foreach ( $matches[0] as $match ) {
			if ( false === strpos( $match, 'loading=' ) ) {
				$iframe_tag = str_replace( '<iframe', '<iframe loading="lazy"', $match );
				$html       = str_replace( $match, $iframe_tag, $html );
			}
		}
	}
	return $html;
}
add_filter( 'embed_oembed_html', 'plab_optimize_oembed_html', 10 );

/**
 * Add a script to the footer if there are lazy loaded embeds.
 * Load the embed's scripts when they approach the viewport using an IntersectionObserver.
 *
 * @since n.e.x.t
 */
function plab_optimize_oembed_lazy_load_scripts() {
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
			var lazyEmbedObserver = new IntersectionObserver( function( entries, observer ) {
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
add_action( 'wp_footer', 'plab_optimize_oembed_lazy_load_scripts', 99 );