const lazyVideoObserver = new IntersectionObserver(
	( entries ) => {
		for ( const entry of entries ) {
			if ( entry.isIntersecting ) {
				const video = /** @type {HTMLVideoElement} */ entry.target;

				if ( video.hasAttribute( 'data-original-poster' ) ) {
					video.setAttribute(
						'poster',
						video.getAttribute( 'data-original-poster' )
					);
				}

				if ( video.hasAttribute( 'data-original-autoplay' ) ) {
					video.setAttribute( 'autoplay', 'autoplay' );
				}

				if ( video.hasAttribute( 'data-original-preload' ) ) {
					const preload = video.getAttribute(
						'data-original-preload'
					);
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
		threshold: 0,
	}
);

const videos = document.querySelectorAll( 'video.od-lazy-video' );
for ( const video of videos ) {
	lazyVideoObserver.observe( video );
}
