const lazyBgImageObserver = new IntersectionObserver(
	( entries ) => {
		for ( const entry of entries ) {
			if ( entry.isIntersecting ) {
				const bgImageElement = /** @type {HTMLElement} */ entry.target;

				bgImageElement.classList.remove( 'od-lazy-bg-image' );

				lazyBgImageObserver.unobserve( bgImageElement );
			}
		}
	},
	{
		rootMargin: '100% 0% 100% 0%',
		threshold: 0,
	}
);

const bgImageElements = document.querySelectorAll( '.od-lazy-bg-image' );
for ( const bgImageElement of bgImageElements ) {
	lazyBgImageObserver.observe( bgImageElement );
}
