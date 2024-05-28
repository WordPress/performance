/**
 * External dependencies
 */
const { styleText } = require( 'node:util' );

/**
 * Create a function that applies a style to text using `node:util.styleText`.
 *
 * @param {string} style Style to apply to the text
 *
 * @return {Function} Function that applies the style to the text
 */
const createStyledText = ( style ) => ( text ) => styleText( style, text );

const bold = createStyledText( 'bold' );
const error = createStyledText( 'red' );
const warning = createStyledText( 'yellowBright' );
const success = createStyledText( 'green' );

const log = console.log; // eslint-disable-line no-console

module.exports = {
	log,
	formats: {
		title: bold,
		error: ( text ) => bold( error( text ) ),
		warning: ( text ) => bold( warning( text ) ),
		success: ( text ) => bold( success( text ) ),
	},
};
