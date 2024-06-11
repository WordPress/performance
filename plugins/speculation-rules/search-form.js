import {
	store,
	getConfig,
	getContext,
	getElement,
} from '@wordpress/interactivity';

const { actions } = store( 'speculationRules', {
	callbacks: {
		doSpeculativeLoad: () => {
			/**
			 * @type {Object}
			 * @property {string} [speculativeLoadUrl] Speculative load URL.
			 */
			const context = getContext();
			const scriptId = 'speculation-rules-search-form';
			const existingScript = document.getElementById( scriptId );
			if ( ! context.speculativeLoadUrl ) {
				if ( existingScript ) {
					existingScript.remove();
				}
			} else {
				const script = document.createElement( 'script' );
				script.type = 'speculationrules';
				script.id = scriptId;
				const rules = {
					[ getConfig().mode ]: [
						{
							source: 'list',
							urls: [ context.speculativeLoadUrl ],
						},
					],
				};
				script.textContent = JSON.stringify( rules );

				if ( existingScript ) {
					existingScript.replaceWith( script );
				} else {
					document.body.appendChild( script );
				}
			}
		},
	},
	actions: {
		// TODO: Is this really actually callback?
		updateSpeculativeLoadUrl: () => {
			const context = getContext();
			const { ref } = getElement();
			const form = ref.closest( 'form' );
			const formData = new FormData( form );
			if ( ! formData.get( 's' ) ) {
				context.speculativeLoadUrl = null;
			} else {
				const url = new URL( form.action );
				url.search = new URLSearchParams( formData ).toString();
				context.speculativeLoadUrl = url.href;
			}
		},
		handleInputKeydown: ( event ) => {
			// Eke out a few milliseconds when hitting enter on the input to submit.
			if ( event.key === 'Enter' ) {
				actions.updateSpeculativeLoadUrl();
			}
		},
		handleFormSubmit: ( event ) => {
			event.preventDefault();
			const form = event.target;
			const formData = new FormData( form );
			const url = new URL( form.action );
			url.search = new URLSearchParams( formData ).toString();
			location.href = url.href;
		},
	},
} );
