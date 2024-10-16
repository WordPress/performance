<?php
/**
 * Hook callbacks used for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'od_init', 'image_prioritizer_init' );

/**
 * Gets the script to lazy-load videos.
 *
 * Load a video and its poster image when it approaches the viewport using an IntersectionObserver.
 *
 * Handles 'autoplay' and 'preload' attributes accordingly.
 *
 * @since n.e.x.t
 */
function image_prioritizer_get_lazy_load_script(): string {
	return <<<JS
		const lazyVideoObserver = new IntersectionObserver(
			( entries ) => {
				for ( const entry of entries ) {
					if ( entry.isIntersecting ) {
						/** @type {HTMLVideoElement} */
						const video = entry.target;

						if ( video.hasAttribute( 'data-original-poster' ) ) {
							video.setAttribute( 'poster', video.getAttribute( 'data-original-poster' ) );
						}

						if ( video.hasAttribute( 'data-original-autoplay' ) ) {
							video.setAttribute( 'autoplay', 'autoplay' );
						}

						if ( video.hasAttribute( 'data-original-preload' ) ) {
							const preload = video.getAttribute( 'data-original-poster' );
							if ( 'default' === preload ) {
								video.removeAttribute( 'preload' );
							} else {
								video.setAttribute( 'preload', preload );
							}
						}

						lazyVideoObserver.unobserve( video );
					}
				}
			},
			{
				rootMargin: '100% 0% 100% 0%',
				threshold: 0
			}
		);

		const videos = document.querySelectorAll( 'video.wp-lazy-video' );
		for ( const video of videos ) {
			lazyVideoObserver.observe( video );
		}
JS;
}
