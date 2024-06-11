import {
	store,
	getConfig,
	getContext,
	getElement,
} from '@wordpress/interactivity';

store( 'speculationRules', {
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
		updateSpeculativeLoadUrl: () => {
			const context = getContext();
			const { ref } = getElement();
			const form = ref.closest( 'form' );
			const formData = new FormData( form );
			if ( ! formData.get( 's' ) ) {
				context.speculativeLoadUrl = null;
			} else {
				const url = new URL( form.action || location.href );
				url.search = new URLSearchParams( formData ).toString();
				context.speculativeLoadUrl = url.href;
			}
		},
		handleFormSubmit: ( event ) => {
			const context = getContext();
			if ( context.speculativeLoadUrl ) {
				event.preventDefault();
				location.href = context.speculativeLoadUrl;
			}
		},
	},
} );
