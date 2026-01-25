/**
 * CSS Selector Validation for View Transitions Settings
 *
 * This script provides real-time validation for CSS selector input fields
 * in the View Transitions settings panel.
 *
 * @since n.e.x.t
 */

( () => {
	/**
	 * Validates a CSS selector by attempting to use it with document.querySelector.
	 *
	 * @param {string} selector The CSS selector to validate.
	 * @return {Object} Object with 'valid' boolean and optional 'message' string.
	 */
	function validateSelector( selector ) {
		// Empty selectors are allowed (they reset to default)
		if ( '' === selector.trim() ) {
			return { valid: true };
		}

		try {
			document.querySelector( selector );
			return { valid: true };
		} catch ( error ) {
			return {
				valid: false,
				message: 'Invalid CSS selector: ' + error.message,
			};
		}
	}

	/**
	 * Sets custom validity for a selector input field.
	 *
	 * @param {HTMLInputElement} input The input element to validate.
	 */
	function updateValidation( input ) {
		const result = validateSelector( input.value );

		if ( result.valid ) {
			input.setCustomValidity( '' );
			input.classList.remove( 'plvt-selector-invalid' );
			input.classList.add( 'plvt-selector-valid' );

			// Remove any existing error message
			const existingError = input.parentNode.querySelector(
				'.plvt-selector-error'
			);
			if ( existingError ) {
				existingError.remove();
			}
		} else {
			input.setCustomValidity( result.message );
			input.classList.remove( 'plvt-selector-valid' );
			input.classList.add( 'plvt-selector-invalid' );

			// Show error message
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
			errorElement.textContent = result.message;
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

		selectorInputs.forEach( ( input ) => {
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
} )();
