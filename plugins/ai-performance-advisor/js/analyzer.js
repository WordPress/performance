/**
 * AI Performance Advisor - Site Health tab behavior.
 *
 * Runs the analysis via the REST API and renders the recommendations.
 *
 * @since 1.0.0
 */

/**
 * A machine-actionable recommendation action. `settings_url` links to a settings
 * screen for a manual fix; `ability` names a registered ability that could apply
 * the change (future use).
 *
 * @typedef {{settings_url?: string, ability?: {name: string, args: Object}}} AipaAction
 */

/**
 * A sanitized recommendation returned by the analysis endpoint, with an optional
 * severity, category, supporting evidence, and forward-looking action payload.
 *
 * @typedef {{id?: string, title?: string, severity?: string, category?: string, summary?: string, details?: string, evidence?: Array<string>, action?: AipaAction|null}} AipaRecommendation
 */

/**
 * Identity fallback used when wp.i18n is unavailable.
 *
 * @param {string} text Text to return unchanged.
 * @return {string} The same text.
 */
const aipaIdentity = ( text ) => text;

( function () {
	'use strict';

	const apiFetch = window.wp && window.wp.apiFetch;

	// Reference the translation function directly so all calls below pass string
	// literals (required by the @wordpress/i18n lint rules). Fall back to an
	// identity function when wp.i18n is not available.
	const __ =
		window.wp && window.wp.i18n && window.wp.i18n.__
			? window.wp.i18n.__
			: aipaIdentity;

	const buttonElement = document.getElementById( 'aipa-analyze' );
	const resultsElement = document.getElementById( 'aipa-results' );

	if (
		! ( buttonElement instanceof HTMLButtonElement ) ||
		! ( resultsElement instanceof HTMLElement ) ||
		! apiFetch
	) {
		return;
	}

	const button = buttonElement;
	const results = resultsElement;
	const spinner = document.getElementById( 'aipa-spinner' );

	/**
	 * Sets the busy state of the analyze control.
	 *
	 * @param {boolean} busy Whether a request is in flight.
	 */
	function setBusy( busy ) {
		button.disabled = busy;
		if ( spinner ) {
			spinner.classList.toggle( 'is-active', busy );
		}
	}

	/**
	 * Renders a notice in the results area.
	 *
	 * @param {string} message The message text.
	 * @param {string} type    Notice type (error, info).
	 */
	function renderNotice( message, type ) {
		const notice = document.createElement( 'div' );
		notice.className = 'notice notice-' + ( type || 'info' ) + ' inline';
		const p = document.createElement( 'p' );
		p.textContent = message;
		notice.appendChild( p );
		results.replaceChildren( notice );
	}

	/**
	 * Builds a single recommendation card element.
	 *
	 * @param {AipaRecommendation} rec A sanitized recommendation object.
	 * @return {HTMLElement} The card element.
	 */
	function buildCard( rec ) {
		const card = document.createElement( 'div' );
		card.className =
			'aipa-card aipa-severity-' + ( rec.severity || 'info' );

		const heading = document.createElement( 'h3' );
		heading.className = 'aipa-card-title';

		const badge = document.createElement( 'span' );
		badge.className = 'aipa-badge aipa-badge-' + ( rec.severity || 'info' );
		badge.textContent = rec.severity || 'info';
		heading.appendChild( badge );

		heading.appendChild(
			document.createTextNode( ' ' + ( rec.title || '' ) )
		);
		card.appendChild( heading );

		const summary = document.createElement( 'p' );
		summary.className = 'aipa-card-summary';
		summary.textContent = rec.summary || '';
		card.appendChild( summary );

		if ( rec.details ) {
			const details = document.createElement( 'div' );
			details.className = 'aipa-card-details';
			details.textContent = rec.details;
			card.appendChild( details );
		}

		if ( Array.isArray( rec.evidence ) && rec.evidence.length ) {
			const evidenceWrap = document.createElement( 'details' );
			evidenceWrap.className = 'aipa-card-evidence';
			const sum = document.createElement( 'summary' );
			sum.textContent = __( 'Evidence', 'ai-performance-advisor' );
			evidenceWrap.appendChild( sum );
			const list = document.createElement( 'ul' );
			rec.evidence.forEach( ( /** @type {string} */ line ) => {
				const li = document.createElement( 'li' );
				li.textContent = line;
				list.appendChild( li );
			} );
			evidenceWrap.appendChild( list );
			card.appendChild( evidenceWrap );
		}

		// Forward-looking: render a "Configure" link now, and an "Apply" affordance
		// once the AI can map a recommendation to a registered ability.
		if ( rec.action ) {
			const actions = document.createElement( 'p' );
			actions.className = 'aipa-card-actions';
			if ( rec.action.settings_url ) {
				const link = document.createElement( 'a' );
				link.className = 'button';
				link.href = rec.action.settings_url;
				link.textContent = __( 'Configure', 'ai-performance-advisor' );
				actions.appendChild( link );
			}
			if ( rec.action.ability && rec.action.ability.name ) {
				const apply = document.createElement( 'button' );
				apply.type = 'button';
				apply.className = 'button button-secondary aipa-apply';
				apply.disabled = true;
				apply.textContent = __(
					'Apply (coming soon)',
					'ai-performance-advisor'
				);
				actions.appendChild( apply );
			}
			if ( actions.childNodes.length ) {
				card.appendChild( actions );
			}
		}

		return card;
	}

	/**
	 * Renders the list of recommendations.
	 *
	 * @param {Array<AipaRecommendation>|undefined} recommendations The recommendations array.
	 */
	function renderRecommendations( recommendations ) {
		if ( ! Array.isArray( recommendations ) || ! recommendations.length ) {
			renderNotice(
				__(
					'No recommendations were returned. Your site may already be well optimized.',
					'ai-performance-advisor'
				),
				'info'
			);
			return;
		}

		const fragment = document.createDocumentFragment();
		recommendations.forEach( ( /** @type {AipaRecommendation} */ rec ) => {
			fragment.appendChild( buildCard( rec ) );
		} );
		results.replaceChildren( fragment );
	}

	button.addEventListener( 'click', function () {
		setBusy( true );
		renderNotice(
			__(
				'Analyzing your site, this may take a moment…',
				'ai-performance-advisor'
			),
			'info'
		);

		apiFetch( {
			path: '/ai-performance-advisor/v1/analyze',
			method: 'POST',
			data: { refresh: true },
		} )
			.then(
				(
					/** @type {{recommendations?: Array<AipaRecommendation>}} */ response
				) => {
					renderRecommendations(
						response && response.recommendations
					);
				}
			)
			.catch( ( /** @type {{message?: string}} */ error ) => {
				renderNotice(
					( error && error.message ) ||
						__(
							'The analysis could not be completed.',
							'ai-performance-advisor'
						),
					'error'
				);
			} )
			.finally( () => {
				setBusy( false );
			} );
	} );
} )();
