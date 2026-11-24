/**
 * Link prediction for Speculative Loading.
 *
 * Estimates how likely the visitor is to follow each link on the page and marks the most likely ones, which a
 * speculation rule then loads right away. The estimate is the logistic function of a weighted sum of signals
 * about where and how a link is displayed. The weights adapt to the links the visitor follows, and they stay in
 * the browser.
 */

/**
 * @typedef {Object} LinkPredictionConfig
 * @property {'prefetch'|'prerender'} mode         Speculation mode.
 * @property {Object}                 where        Conditions of the document rule that speculative loading uses.
 * @property {number}                 threshold    Probability a link needs to reach to be speculatively loaded.
 * @property {number}                 maxLinks     Maximum number of predicted links per page view.
 * @property {number}                 learningRate How strongly the weights adapt. 0 disables adapting.
 * @property {Object<string, number>} weights      Weight of each signal.
 */

/**
 * @typedef {Object} Candidate
 * @property {string}                 url         URL without the fragment.
 * @property {HTMLAnchorElement[]}    anchors     Links on the page that go to the URL.
 * @property {Object<string, number>} signals     Signals of the highest scoring link.
 * @property {number}                 probability Probability from 0 to 1 that the visitor follows the link.
 */

const PREDICTED_ATTRIBUTE = 'data-plsr-predicted';
const STORAGE_KEY = 'plsrLinkPredictionWeights';
const MAX_ANCHORS = 500;
const MAX_LEARNED_OFFSET = 3;

const configElement = document.getElementById( 'plsr-link-prediction-config' );

/** @type {LinkPredictionConfig|null} */
const config = configElement
	? JSON.parse( configElement.textContent || 'null' )
	: null;

/**
 * Offsets to the configured weights, learned from the links the visitor followed.
 *
 * @type {Object<string, number>}
 */
const learnedOffsets = {};

/** @type {Set<string>} */
const predictedUrls = new Set();

/**
 * Limits a number to the range from 0 to 1.
 *
 * @param {number} value Number.
 * @return {number} Number from 0 to 1.
 */
const clamp = ( value ) => Math.min( 1, Math.max( 0, value ) );

/**
 * Gets the weight of a signal, including what was learned for the visitor.
 *
 * @param {LinkPredictionConfig} cfg    Configuration.
 * @param {string}               signal Signal name.
 * @return {number} Weight.
 */
const getWeight = ( cfg, signal ) =>
	( cfg.weights[ signal ] || 0 ) + ( learnedOffsets[ signal ] || 0 );

/**
 * Calculates the probability that the visitor follows a link.
 *
 * @param {LinkPredictionConfig}   cfg     Configuration.
 * @param {Object<string, number>} signals Signals of the link.
 * @return {number} Probability from 0 to 1.
 */
const getProbability = ( cfg, signals ) => {
	let sum = 0;
	for ( const [ signal, value ] of Object.entries( signals ) ) {
		sum += getWeight( cfg, signal ) * value;
	}
	return 1 / ( 1 + Math.exp( -sum ) );
};

/**
 * Gets the signals of a link, each one a number from 0 to 1.
 *
 * @param {HTMLAnchorElement} anchor Link.
 * @param {DOMRect}           rect   Bounding box of the link.
 * @param {number}            count  Number of links on the page that go to the same URL.
 * @return {Object<string, number>} Signals.
 */
const getSignals = ( anchor, rect, count ) => {
	const viewportWidth = window.innerWidth;
	const viewportHeight = window.innerHeight;
	const distance = Math.abs(
		rect.top + rect.height / 2 - viewportHeight / 2
	);

	return {
		bias: 1,
		visible:
			rect.bottom > 0 &&
			rect.top < viewportHeight &&
			rect.right > 0 &&
			rect.left < viewportWidth
				? 1
				: 0,
		proximity: 1 - clamp( distance / ( 2 * viewportHeight ) ),
		// The square root keeps a large image link from dwarfing a text link.
		area: clamp(
			3 *
				Math.sqrt(
					( rect.width * rect.height ) /
						( viewportWidth * viewportHeight )
				)
		),
		content: anchor.closest( 'main, article, [role="main"]' ) ? 1 : 0,
		navigation: anchor.closest(
			'nav, header, [role="navigation"], [role="banner"]'
		)
			? 1
			: 0,
		footer: anchor.closest(
			'footer, aside, [role="contentinfo"], [role="complementary"]'
		)
			? 1
			: 0,
		heading: anchor.closest( 'h1, h2, h3' ) ? 1 : 0,
		image: anchor.querySelector( 'img, picture' ) ? 1 : 0,
		next:
			anchor.relList.contains( 'next' ) ||
			anchor.closest(
				'.next, .nav-next, .wp-block-query-pagination-next, .post-navigation-link-next'
			)
				? 1
				: 0,
		repeated: clamp( ( count - 1 ) / 2 ),
	};
};

/**
 * Gets the URL that a link goes to, if the link is one that could be speculatively loaded.
 *
 * The speculation rule applies the exclusions for speculative loading. This only leaves out what would
 * otherwise use up one of the predicted links without ever being loaded.
 *
 * @param {HTMLAnchorElement} anchor Link.
 * @return {string|null} URL without the fragment, or null.
 */
const getCandidateUrl = ( anchor ) => {
	if (
		anchor.hasAttribute( 'download' ) ||
		anchor.relList.contains( 'nofollow' ) ||
		( anchor.target && '_self' !== anchor.target )
	) {
		return null;
	}

	let url;
	try {
		url = new URL( anchor.href );
	} catch {
		return null;
	}

	if (
		url.origin !== window.location.origin ||
		( url.pathname === window.location.pathname &&
			url.search === window.location.search )
	) {
		return null;
	}

	return url.origin + url.pathname + url.search;
};

/**
 * Calculates the probability of each link on the page being followed.
 *
 * @param {LinkPredictionConfig} cfg Configuration.
 * @return {Candidate[]} Candidates, with the most likely one first.
 */
const getCandidates = ( cfg ) => {
	/** @type {Map<string, HTMLAnchorElement[]>} */
	const anchorsByUrl = new Map();
	const anchors = Array.from( document.links ).slice( 0, MAX_ANCHORS );
	for ( const anchor of anchors ) {
		if ( ! ( anchor instanceof HTMLAnchorElement ) ) {
			continue;
		}
		const url = getCandidateUrl( anchor );
		if ( null === url ) {
			continue;
		}
		const group = anchorsByUrl.get( url );
		if ( group ) {
			group.push( anchor );
		} else {
			anchorsByUrl.set( url, [ anchor ] );
		}
	}

	/** @type {Candidate[]} */
	const candidates = [];
	for ( const [ url, group ] of anchorsByUrl ) {
		/** @type {Candidate|null} */
		let candidate = null;
		for ( const anchor of group ) {
			const rect = anchor.getBoundingClientRect();

			// Skip links that are not displayed.
			if ( 0 === rect.width || 0 === rect.height ) {
				continue;
			}

			const signals = getSignals( anchor, rect, group.length );
			const probability = getProbability( cfg, signals );
			if ( ! candidate || probability > candidate.probability ) {
				candidate = { url, anchors: group, signals, probability };
			}
		}
		if ( candidate ) {
			candidates.push( candidate );
		}
	}

	return candidates.sort( ( a, b ) => b.probability - a.probability );
};

/**
 * Marks the most likely links so that the speculation rule loads them.
 *
 * @param {LinkPredictionConfig} cfg Configuration.
 */
const predict = ( cfg ) => {
	for ( const candidate of getCandidates( cfg ) ) {
		if (
			predictedUrls.size >= cfg.maxLinks ||
			candidate.probability < cfg.threshold
		) {
			break;
		}
		if ( predictedUrls.has( candidate.url ) ) {
			continue;
		}
		predictedUrls.add( candidate.url );
		for ( const anchor of candidate.anchors ) {
			anchor.setAttribute(
				PREDICTED_ATTRIBUTE,
				candidate.probability.toFixed( 2 )
			);
		}
	}
};

/**
 * Adapts the weights to a link that the visitor followed.
 *
 * This is one step of logistic regression: the followed link is the positive example and the other links on the
 * page are the negative examples, which together count as much as the positive one.
 *
 * @param {LinkPredictionConfig} cfg        Configuration.
 * @param {Candidate[]}          candidates Candidates.
 * @param {string}               url        URL of the followed link.
 */
const learn = ( cfg, candidates, url ) => {
	const negativeCount = candidates.length - 1;
	if (
		negativeCount < 1 ||
		! candidates.some( ( candidate ) => candidate.url === url )
	) {
		return;
	}

	/** @type {Object<string, number>} */
	const gradient = {};
	for ( const candidate of candidates ) {
		const followed = candidate.url === url;
		const error =
			( ( followed ? 1 : 0 ) - candidate.probability ) *
			( followed ? 1 : 1 / negativeCount );
		for ( const [ signal, value ] of Object.entries( candidate.signals ) ) {
			gradient[ signal ] = ( gradient[ signal ] || 0 ) + error * value;
		}
	}

	for ( const [ signal, value ] of Object.entries( gradient ) ) {
		learnedOffsets[ signal ] = Math.min(
			MAX_LEARNED_OFFSET,
			Math.max(
				-MAX_LEARNED_OFFSET,
				( learnedOffsets[ signal ] || 0 ) + cfg.learningRate * value
			)
		);
	}

	try {
		window.localStorage.setItem(
			STORAGE_KEY,
			JSON.stringify( learnedOffsets )
		);
	} catch {
		// Storage can be unavailable or full, in which case nothing is learned.
	}
};

/**
 * Loads the weights that were learned on earlier page views.
 */
const loadLearnedOffsets = () => {
	try {
		const stored = JSON.parse(
			window.localStorage.getItem( STORAGE_KEY ) || '{}'
		);
		for ( const [ signal, value ] of Object.entries( stored || {} ) ) {
			if ( 'number' === typeof value && Number.isFinite( value ) ) {
				learnedOffsets[ signal ] = Math.min(
					MAX_LEARNED_OFFSET,
					Math.max( -MAX_LEARNED_OFFSET, value )
				);
			}
		}
	} catch {
		// Storage can be unavailable or hold invalid data, in which case the configured weights are used as is.
	}
};

/**
 * Starts predicting links.
 *
 * @param {LinkPredictionConfig} cfg Configuration.
 */
const init = ( cfg ) => {
	if ( cfg.learningRate > 0 ) {
		loadLearnedOffsets();

		document.addEventListener(
			'click',
			( event ) => {
				if (
					0 !== event.button ||
					event.metaKey ||
					event.ctrlKey ||
					event.shiftKey ||
					event.altKey ||
					! ( event.target instanceof Element )
				) {
					return;
				}
				const anchor = event.target.closest( 'a[href]' );
				const url =
					anchor instanceof HTMLAnchorElement
						? getCandidateUrl( anchor )
						: null;
				if ( null !== url ) {
					learn( cfg, getCandidates( cfg ), url );
				}
			},
			{ capture: true, passive: true }
		);
	}

	const speculationRules = document.createElement( 'script' );
	speculationRules.type = 'speculationrules';
	speculationRules.textContent = JSON.stringify( {
		[ cfg.mode ]: [
			{
				source: 'document',
				where: {
					and: [
						{ selector_matches: `a[${ PREDICTED_ATTRIBUTE }]` },
						cfg.where,
					],
				},
				eagerness: 'immediate',
			},
		],
	} );
	document.head.append( speculationRules );

	const schedule = () => {
		window.requestIdleCallback( () => predict( cfg ) );
	};
	schedule();

	// Links that were scrolled into view have become more likely.
	const onScrollEnd = () => {
		if ( predictedUrls.size >= cfg.maxLinks ) {
			document.removeEventListener( 'scrollend', onScrollEnd );
			return;
		}
		schedule();
	};
	document.addEventListener( 'scrollend', onScrollEnd, { passive: true } );
};

if (
	config &&
	HTMLScriptElement.supports?.( 'speculationrules' ) &&
	'requestIdleCallback' in window &&
	// @ts-ignore -- The Network Information API is not in the DOM types.
	! navigator.connection?.saveData
) {
	if ( document.prerendering ) {
		document.addEventListener( 'prerenderingchange', () => init( config ), {
			once: true,
		} );
	} else {
		init( config );
	}
}
