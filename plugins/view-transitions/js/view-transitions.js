/**
 * @typedef {import("./types.ts").ViewTransitionsConfig} ViewTransitionsConfig
 * @typedef {import("./types.ts").InitViewTransitionsFunction} InitViewTransitionsFunction
 * @typedef {import("./types.ts").PageSwapListenerFunction} PageSwapListenerFunction
 * @typedef {import("./types.ts").PageRevealListenerFunction} PageRevealListenerFunction
 * @typedef {import("./types.ts").NavigationHistoryEntry} NavigationHistoryEntry
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
	 * Determines the view transition type to use, given an old and new navigation history entry.
	 *
	 * @param {NavigationHistoryEntry|null} oldEntry Navigation history entry for the URL navigated from.
	 * @param {NavigationHistoryEntry}      newEntry Navigation history entry for the URL navigated to.
	 * @return {string} View transition type (e.g. 'default', 'chronological-forwards', 'chronological-backwards').
	 */
	const determineTransitionType = ( oldEntry, newEntry ) => {
		if ( ! oldEntry || ! newEntry ) {
			return 'default';
		}

		// Use 'default' transition type if all other transition types are disabled.
		if (
			! config.animations[ 'chronological-forwards' ] &&
			! config.animations[ 'chronological-backwards' ] &&
			! config.animations[ 'pagination-forwards' ] &&
			! config.animations[ 'pagination-backwards' ]
		) {
			return 'default';
		}

		const oldURL = new URL( oldEntry.url );
		const newURL = new URL( newEntry.url );

		// TODO: Handle non-pretty permalinks.
		const oldPathname = oldURL.pathname;
		const newPathname = newURL.pathname;

		if ( oldPathname === newPathname ) {
			return 'default';
		}

		let oldPageMatches = null;
		let newPageMatches = null;
		let prefix = '';

		// If enabled, check if the URLs are for a chronologically paginated archive.
		if (
			config.animations[ 'chronological-forwards' ] ||
			config.animations[ 'chronological-backwards' ]
		) {
			const pagedRegEx = new RegExp(
				'/' + config.paginationBase + '/(\\d+)/?$' // TODO: Escape.
			);
			// TODO: Handle non-pretty permalinks.
			oldPageMatches = oldPathname.match( pagedRegEx );
			newPageMatches = newPathname.match( pagedRegEx );
			prefix = 'chronological-';
		}

		// If not, check if the URLs are for a multipage post.
		if (
			! oldPageMatches &&
			! newPageMatches &&
			( config.animations[ 'pagination-forwards' ] ||
				config.animations[ 'pagination-backwards' ] )
		) {
			// TODO: Handle non-pretty permalinks.
			oldPageMatches = oldPathname.match( /\/(\d+)\/?$/ );
			newPageMatches = newPathname.match( /\/(\d+)\/?$/ );
			prefix = 'pagination-';
		}
		// If there is a match on at least one of the URLs, compare whether their roots before the page segment match.
		if ( oldPageMatches || newPageMatches ) {
			const oldPageBase = oldPageMatches
				? oldPathname.substring(
						0,
						oldPathname.length - oldPageMatches[ 0 ].length
				  )
				: oldPathname.replace( /\/$/, '' );
			const newPageBase = newPageMatches
				? newPathname.substring(
						0,
						newPathname.length - newPageMatches[ 0 ].length
				  )
				: newPathname.replace( /\/$/, '' );

			if ( oldPageBase === newPageBase ) {
				// They belong to the same archive or post.
				// Return the appropriate transition type, or 'default' if no particular animation is specified.
				if ( oldPageMatches && newPageMatches ) {
					if (
						Number( oldPageMatches[ 1 ] ) <
						Number( newPageMatches[ 1 ] )
					) {
						return config.animations[ `${ prefix }forwards` ]
							? `${ prefix }forwards`
							: 'default';
					}
					return config.animations[ `${ prefix }backwards` ]
						? `${ prefix }backwards`
						: 'default';
				}
				if ( newPageMatches && Number( newPageMatches[ 1 ] ) > 1 ) {
					return config.animations[ `${ prefix }forwards` ]
						? `${ prefix }forwards`
						: 'default';
				}
				if ( oldPageMatches && Number( oldPageMatches[ 1 ] ) > 1 ) {
					return config.animations[ `${ prefix }backwards` ]
						? `${ prefix }backwards`
						: 'default';
				}
			}
		}

		// If enabled, check if the URLs are for content labeled by date (e.g. navigation to previous/next post).
		if (
			config.animations[ 'chronological-forwards' ] ||
			config.animations[ 'chronological-backwards' ]
		) {
			// TODO: Handle non-pretty permalinks.
			const oldDateMatches = oldPathname.match(
				/\/(\d{4})\/(\d{2})\/(\d{2})\/[^\/]+\/?$/
			);
			const newDateMatches = newPathname.match(
				/\/(\d{4})\/(\d{2})\/(\d{2})\/[^\/]+\/?$/
			);
			if ( oldDateMatches && newDateMatches ) {
				const oldPageBase = oldPathname.substring(
					0,
					oldPathname.length - oldDateMatches[ 0 ].length
				);
				const newPageBase = newPathname.substring(
					0,
					newPathname.length - newDateMatches[ 0 ].length
				);
				if ( oldPageBase === newPageBase ) {
					// They belong to the same hierarchy.
					const oldDate = new Date(
						parseInt( oldDateMatches[ 1 ] ),
						parseInt( oldDateMatches[ 2 ] ) - 1,
						parseInt( oldDateMatches[ 3 ] )
					);
					const newDate = new Date(
						parseInt( newDateMatches[ 1 ] ),
						parseInt( newDateMatches[ 2 ] ) - 1,
						parseInt( newDateMatches[ 3 ] )
					);
					if ( oldDate < newDate ) {
						return config.animations[ 'chronological-forwards' ]
							? 'chronological-forwards'
							: 'default';
					}
					if ( oldDate > newDate ) {
						return config.animations[ 'chronological-backwards' ]
							? 'chronological-backwards'
							: 'default';
					}
				}
			}
		}

		return 'default';
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
				const transitionType = determineTransitionType(
					event.activation.from,
					event.activation.entry
				);
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
					document.body.classList.contains( 'blog' ) ||
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
				const transitionType = determineTransitionType(
					window.navigation.activation.from,
					window.navigation.activation.entry
				);
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
