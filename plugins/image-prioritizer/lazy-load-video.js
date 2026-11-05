const restoreVideo = ( /** @type {HTMLVideoElement} */ video ) => {
	const poster = video.getAttribute( 'data-original-poster' );
	if ( poster ) {
		video.setAttribute( 'poster', poster );
	}

	if ( video.hasAttribute( 'data-original-autoplay' ) ) {
		video.setAttribute( 'autoplay', 'autoplay' );
	}

	const preload = video.getAttribute( 'data-original-preload' );
	if ( preload ) {
		if ( 'default' === preload ) {
			video.removeAttribute( 'preload' );
		} else {
			video.setAttribute( 'preload', preload );
		}
	}
};

const videos = document.querySelectorAll( 'video.od-lazy-video' );

// When the browser natively supports lazy-loading on video, restore the original
// attributes immediately and rely on loading="lazy" to defer the video load.
if ( 'loading' in HTMLMediaElement.prototype ) {
	for ( const video of videos ) {
		restoreVideo( /** @type {HTMLVideoElement} */ ( video ) );
	}
} else {
	const lazyVideoObserver = new IntersectionObserver(
		( entries ) => {
			for ( const entry of entries ) {
				if ( entry.isIntersecting ) {
					restoreVideo(
						/** @type {HTMLVideoElement} */ ( entry.target )
					);
					lazyVideoObserver.unobserve( entry.target );
				}
			}
		},
		{
			rootMargin: '100% 0% 100% 0%',
			threshold: 0,
		}
	);

	for ( const video of videos ) {
		lazyVideoObserver.observe( video );
	}
}
