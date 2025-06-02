/**
 * @typedef {import("./types.ts").ViewTransitionsConfig} ViewTransitionsConfig
 * @typedef {import("./types.ts").InitViewTransitionsFunction} InitViewTransitionsFunction
 * @typedef {import("./types.ts").PageSwapListenerFunction} PageSwapListenerFunction
 * @typedef {import("./types.ts").PageRevealListenerFunction} PageRevealListenerFunction
 */

/**
 * Initializes view transitions for the current URL.
 *
 * @type {InitViewTransitionsFunction}
 * @param {ViewTransitionsConfig} config - The view transitions configuration.
 */
window.plvtInitViewTransitions = ( config ) => {
	if ( ! window.navigation || ! ( 'CSSViewTransitionRule' in window ) ) {
		window.console.warn(
			'View transitions not loaded as the browser is lacking support.'
		);
		return;
	}

	/**
	 * Gets all view transition entries relevant for a view transition.
	 *
	 * @param {string}       transitionType View transition type. Only 'default' is supported so far, but more to be added.
	 * @param {Element}      bodyElement    The body element.
	 * @param {Element|null} articleElement The post element relevant for the view transition, if any.
	 * @return {Array[]} View transition entries with each one containing the element and its view transition name.
	 */
	const getViewTransitionEntries = (
		transitionType,
		bodyElement,
		articleElement
	) => {
		const animations = config.animations || {};

		const globalEntries = animations[ transitionType ]
			.useGlobalTransitionNames
			? Object.entries( config.globalTransitionNames || {} ).map(
					( [ selector, name ] ) => {
						const element = bodyElement.querySelector( selector );
						return [ element, name ];
					}
			  )
			: [];

		const postEntries =
			animations[ transitionType ].usePostTransitionNames &&
			articleElement
				? Object.entries( config.postTransitionNames || {} ).map(
						( [ selector, name ] ) => {
							const element =
								articleElement.querySelector( selector );
							return [ element, name ];
						}
				  )
				: [];

		return [ ...globalEntries, ...postEntries ];
	};

	/**
	 * Temporarily sets view transition names for the given entries until the view transition has been completed.
	 *
	 * @param {Array[]}       entries   View transition entries as received from `getViewTransitionEntries()`.
	 * @param {Promise<void>} vtPromise Promise that resolves after the view transition has been completed.
	 * @return {Promise<void>} Promise that resolves after the view transition names were reset.
	 */
	const setTemporaryViewTransitionNames = async ( entries, vtPromise ) => {
		for ( const [ element, name ] of entries ) {
			if ( ! element ) {
				continue;
			}
			element.style.viewTransitionName = name;
		}

		await vtPromise;

		for ( const [ element ] of entries ) {
			if ( ! element ) {
				continue;
			}
			element.style.viewTransitionName = '';
		}
	};

	/**
	 * Appends a selector to another selector.
	 *
	 * This supports selectors which technically include multiple selectors (separated by comma).
	 *
	 * @param {string} selectors Main selector.
	 * @param {string} append    Selector to append to the main selector.
	 * @return {string} Combined selector.
	 */
	const appendSelectors = ( selectors, append ) => {
		return selectors
			.split( ',' )
			.map( ( subselector ) => subselector.trim() + ' ' + append )
			.join( ',' );
	};

	/**
	 * Gets a post element (the first on the page, in case there are multiple).
	 *
	 * @return {Element|null} Post element, or null if none is found.
	 */
	const getArticle = () => {
		if ( ! config.postSelector ) {
			return null;
		}
		return document.querySelector( config.postSelector );
	};

	/**
	 * Gets the post element for a specific post URL.
	 *
	 * @param {string} url Post URL (permalink) to find post element.
	 * @return {Element|null} Post element, or null if none is found.
	 */
	const getArticleForUrl = ( url ) => {
		if ( ! config.postSelector ) {
			return null;
		}
		const postLinkSelector = appendSelectors(
			config.postSelector,
			'a[href="' + url + '"]'
		);
		const articleLink = document.querySelector( postLinkSelector );
		if ( ! articleLink ) {
			return null;
		}
		return articleLink.closest( config.postSelector );
	};

	/**
	 * Customizes view transition behavior on the URL that is being navigated from.
	 *
	 * @type {PageSwapListenerFunction}
	 * @param {PageSwapEvent} event - Event fired as the previous URL is about to unload.
	 */
	window.addEventListener(
		'pageswap',
		( /** @type {PageSwapEvent} */ event ) => {
			if ( event.viewTransition ) {
				const transitionType = 'default'; // Only 'default' is supported so far, but more to be added.
				event.viewTransition.types.add( transitionType );

				let viewTransitionEntries;
				if ( document.body.classList.contains( 'single' ) ) {
					viewTransitionEntries = getViewTransitionEntries(
						transitionType,
						document.body,
						getArticle()
					);
				} else if (
					document.body.classList.contains( 'home' ) ||
					document.body.classList.contains( 'archive' )
				) {
					viewTransitionEntries = getViewTransitionEntries(
						transitionType,
						document.body,
						getArticleForUrl( event.activation.entry.url )
					);
				}
				if ( viewTransitionEntries ) {
					setTemporaryViewTransitionNames(
						viewTransitionEntries,
						event.viewTransition.finished
					);
				}
			}
		}
	);

	/**
	 * Customizes view transition behavior on the URL that is being navigated to.
	 *
	 * @type {PageRevealListenerFunction}
	 * @param {PageRevealEvent} event - Event fired as the new URL being navigated to is loaded.
	 */
	window.addEventListener(
		'pagereveal',
		( /** @type {PageRevealEvent} */ event ) => {
			if ( event.viewTransition ) {
				const transitionType = 'default'; // Only 'default' is supported so far, but more to be added.
				event.viewTransition.types.add( transitionType );

				let viewTransitionEntries;
				if ( document.body.classList.contains( 'single' ) ) {
					viewTransitionEntries = getViewTransitionEntries(
						transitionType,
						document.body,
						getArticle()
					);
				} else if (
					document.body.classList.contains( 'home' ) ||
					document.body.classList.contains( 'archive' )
				) {
					viewTransitionEntries = getViewTransitionEntries(
						transitionType,
						document.body,
						window.navigation.activation.from
							? getArticleForUrl(
									window.navigation.activation.from.url
							  )
							: null
					);
				}
				if ( viewTransitionEntries ) {
					setTemporaryViewTransitionNames(
						viewTransitionEntries,
						event.viewTransition.ready
					);
				}
			}
		}
	);
};
