/**
 * CSS Selector Validation for View Transitions Settings
 *
 * This script provides real-time validation for CSS selector input fields
 * in the View Transitions settings panel.
 *
 * @since n.e.x.t
 */

/**
 * @typedef  {Object}   WP
 * @property {Object}   i18n    - WordPress internationalization object
 * @property {Function} i18n.__ - Translation function
 */

/**
 * Global `wp` object.
 *
 * @type {WP}
 */

(
	() => {
		/**
		 * Validates a CSS selector by attempting to use it with document.querySelector.
		 *
		 * @param {string} selector The CSS selector to validate.
		 * @return {boolean} Whether the selector is valid.
		 */
		function validateSelector( selector ) {
			// Empty selectors are allowed (they reset to default)
			if ( '' === selector.trim() ) {
				return true;
			}

			return CSS.supports( `selector(${ selector })` );
		}

		/**
		 * Sets custom validity for a selector input field.
		 *
		 * @param {HTMLInputElement} input The input element to validate.
		 */
		function updateValidation( input ) {
			const isValid = validateSelector( input.value );

			if ( isValid ) {
				input.setCustomValidity( '' );
				input.classList.remove( 'plvt-selector-invalid' );
				input.classList.add( 'plvt-selector-valid' );

				const existingError = input.parentNode.querySelector(
					'.plvt-selector-error'
				);
				if ( existingError ) {
					existingError.remove();
				}
			} else {
				const errorMessage = wp.i18n.__(
					'Invalid CSS selector',
					'view-transitions'
				);
				input.setCustomValidity( errorMessage );
				input.classList.remove( 'plvt-selector-valid' );
				input.classList.add( 'plvt-selector-invalid' );

				let errorElement = input.parentNode.querySelector(
					'.plvt-selector-error'
				);
				if ( ! errorElement ) {
					errorElement = document.createElement( 'p' );
					errorElement.className = 'plvt-selector-error description';
					input.parentNode.insertBefore(
						errorElement,
						input.nextSibling
					);
				}
				errorElement.textContent = errorMessage;
			}
		}

		/**
		 * Initializes validation for all selector input fields.
		 */
		function initValidation() {
			// Target all text inputs for selectors
			const selectorInputs = document.querySelectorAll(
				'input[data-plvt-validate-selector]'
			);

			selectorInputs.forEach( ( element ) => {
				const input = /** @type {HTMLInputElement} */ ( element );

				// Validate on blur
				input.addEventListener( 'blur', () => {
					updateValidation( input );
				} );

				// Validate on input for real-time feedback
				input.addEventListener( 'input', () => {
					updateValidation( input );
				} );

				// Validate on page load if field has a value
				if ( '' !== input.value.trim() ) {
					updateValidation( input );
				}
			} );
		}

		// Initialize when DOM is ready
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', initValidation );
		} else {
			initValidation();
		}
	}
)();
