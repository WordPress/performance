/* eslint camelcase: "off", no-alert: "off" */
/* global jQuery:false, perflabAutoloadSettings:false */

( function ( $, document ) {
	$( document ).on( 'click', '.update-autoload', function ( e ) {
		e.preventDefault();
		const button = $( this );
		const optionName = button.data( 'option-name' );
		const autoload = button.data( 'autoload' );
		const value = button.data( 'value' );
		const data = {
			option_name: optionName,
			autoload,
			value,
		};

		$.ajax( {
			url:
				perflabAutoloadSettings.root +
				'perflab-aao/v1/update-autoload/' +
				optionName,
			method: 'POST',
			data,
			success( response ) {
				if ( response.success ) {
					button.attr( 'disabled', true );
				} else {
					alert( response.message );
				}
			},
			error( error ) {
				alert( error.errorMessage );
			},
		} );
	} );
} )( jQuery, document );
