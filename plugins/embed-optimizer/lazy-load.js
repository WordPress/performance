/**
 * Lazy load embeds
 *
 * When an embed block is lazy loaded, the script tag is replaced with a script tag that has the original attributes
 */

const lazyEmbedsScripts = document.querySelectorAll(
	'script[type="application/vnd.embed-optimizer.javascript"]'
);
const lazyEmbedScriptsByParents = new Map();

const lazyEmbedObserver = new IntersectionObserver(
	( entries ) => {
		for ( const entry of entries ) {
			if ( entry.isIntersecting ) {
				const lazyEmbedParent = entry.target;
				const lazyEmbedScript =
					/** @type {HTMLScriptElement} */ lazyEmbedScriptsByParents.get(
						lazyEmbedParent
					);
				const embedScript =
					/** @type {HTMLScriptElement} */ document.createElement(
						'script'
					);
				for ( const attr of lazyEmbedScript.attributes ) {
					if ( attr.nodeName === 'type' ) {
						// Omit type=application/vnd.embed-optimizer.javascript type.
						continue;
					}
					embedScript.setAttribute(
						attr.nodeName === 'data-original-type'
							? 'type'
							: attr.nodeName,
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
		threshold: 0,
	}
);

for ( const lazyEmbedScript of lazyEmbedsScripts ) {
	const lazyEmbedParent =
		/** @type {HTMLElement} */ lazyEmbedScript.parentNode;
	if ( lazyEmbedParent instanceof HTMLElement ) {
		lazyEmbedScriptsByParents.set( lazyEmbedParent, lazyEmbedScript );
		lazyEmbedObserver.observe( lazyEmbedParent );
	}
}
